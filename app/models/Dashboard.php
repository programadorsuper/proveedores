<?php

require_once __DIR__ . '/Analytics.php';

class Dashboard
{
    protected \PDO $db;
    protected ?Analytics $analytics = null;

    public function __construct()
    {
        $this->db = Database::pgsql();
    }

    public function getSummary(int $providerId, array $filters = []): array
    {
        [$start, $end] = $this->resolveDateRange($filters);
        [$previousStart, $previousEnd] = $this->previousRange($start, $end);

        $analytics = $this->analytics();
        $storeId = $filters['store_id'] ?? null;

        $selloutCurrent = $analytics->aggregateSellout($providerId, $start, $end, $storeId, $filters);
        $selloutPrevious = $analytics->aggregateSellout($providerId, $previousStart, $previousEnd, $storeId, $filters);

        $sellinCurrent = $analytics->aggregateSellin($providerId, $start, $end, $storeId, $filters);
        $sellinPrevious = $analytics->aggregateSellin($providerId, $previousStart, $previousEnd, $storeId, $filters);

        return [
            'sellout' => round($selloutCurrent['value'], 2),
            'sellout_growth' => $this->calculateGrowthFromValues($selloutCurrent['value'], $selloutPrevious['value']),
            'sellin' => round($sellinCurrent['value'], 2),
            'sellin_growth' => $this->calculateGrowthFromValues($sellinCurrent['value'], $sellinPrevious['value']),
            'orders_pending' => $sellinCurrent['rows'],
            'orders_fulfilled' => $selloutCurrent['rows'],
            'returns' => round($selloutCurrent['returns_value'], 2),
            'alerts' => $selloutCurrent['returns_count'],
        ];
    }

    public function getSalesTrend(int $providerId, array $filters = []): array
    {
        [$start, $end] = $this->resolveTrendRange($filters);
        $analytics = $this->analytics();

        $selloutData = $analytics->selloutTimeseries($providerId, [
            'start_date' => $start,
            'end_date' => $end,
            'group_by' => $filters['group_by'] ?? 'month',
            'store_id' => $filters['store_id'] ?? null,
            'query' => $filters['query'] ?? null,
            'include_inactive' => $filters['include_inactive'] ?? false,
        ]);

        $sellinData = $analytics->sellinTimeseries($providerId, [
            'start_date' => $start,
            'end_date' => $end,
            'group_by' => $filters['group_by'] ?? 'month',
            'store_id' => $filters['store_id'] ?? null,
            'query' => $filters['query'] ?? null,
            'include_inactive' => $filters['include_inactive'] ?? false,
        ]);

        $points = [];
        foreach ($selloutData['points'] ?? [] as $point) {
            $bucket = $point['bucket'];
            $points[$bucket]['label'] = $point['label'];
            $points[$bucket]['sellout'] = $point['value'];
        }
        foreach ($sellinData['points'] ?? [] as $point) {
            $bucket = $point['bucket'];
            $points[$bucket]['label'] = $point['label'];
            $points[$bucket]['sellin'] = $point['value'];
        }

        if (empty($points)) {
            return [
                'categories' => [],
                'series' => [
                    ['name' => 'Sell-out', 'data' => []],
                    ['name' => 'Sell-in', 'data' => []],
                ],
            ];
        }

        uasort($points, static function (array $a, array $b): int {
            $dateA = new \DateTimeImmutable($a['bucket'] ?? '1970-01-01');
            $dateB = new \DateTimeImmutable($b['bucket'] ?? '1970-01-01');
            return $dateA <=> $dateB;
        });

        $categories = [];
        $selloutSeries = [];
        $sellinSeries = [];
        foreach ($points as $point) {
            $categories[] = $point['label'];
            $selloutSeries[] = round((float)($point['sellout'] ?? 0), 2);
            $sellinSeries[] = round((float)($point['sellin'] ?? 0), 2);
        }

        return [
            'categories' => $categories,
            'series' => [
                ['name' => 'Sell-out', 'data' => $selloutSeries],
                ['name' => 'Sell-in', 'data' => $sellinSeries],
            ],
        ];
    }

    public function getOrdersDistribution(int $providerId, array $filters = []): array
    {
        [$start, $end] = $this->resolveDateRange($filters);
        $analytics = $this->analytics();
        $storeId = $filters['store_id'] ?? null;

        $sellout = $analytics->aggregateSellout($providerId, $start, $end, $storeId, $filters);
        $sellin = $analytics->aggregateSellin($providerId, $start, $end, $storeId, $filters);

        return [
            ['status' => 'Sell-out', 'value' => round($sellout['value'], 2)],
            ['status' => 'Sell-in', 'value' => round($sellin['value'], 2)],
            ['status' => 'Devoluciones', 'value' => round($sellout['returns_value'], 2)],
        ];
    }

    public function getTopProducts(int $providerId, array $filters = []): array
    {
        [$start, $end] = $this->resolveDateRange($filters);
        $filters = $this->ensureComparativeFilters($filters, $start, $end);

        return $this->analytics()->selloutProducts($providerId, $filters, 50);
    }

    protected function analytics(): Analytics
    {
        if ($this->analytics === null) {
            $this->analytics = new Analytics();
        }
        return $this->analytics;
    }

    protected function calculateGrowthFromValues(float $current, float $previous): float
    {
        if ($previous == 0.0) {
            return 0.0;
        }
        return (($current - $previous) / $previous) * 100;
    }

    protected function mergeBuckets(array $selloutBuckets, array $sellinBuckets): array
    {
        $all = array_values(array_unique(array_merge($selloutBuckets, $sellinBuckets)));
        sort($all);
        return $all;
    }

    protected function formatMonthLabel(string $bucket): string
    {
        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $bucket);
        if ($date === false) {
            return $bucket;
        }
        return $date->format('M Y');
    }

    protected function resolveDateRange($filters): array
    {
        $now = new \DateTimeImmutable('today');

        if (is_array($filters)) {
            if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
                return [
                    $this->toDate($filters['start_date'], '00:00:00'),
                    $this->toDate($filters['end_date'], '23:59:59'),
                ];
            }
            $period = $filters['period'] ?? 'mtm';
        } else {
            $period = (string)$filters;
        }

        switch ($period) {
            case 'mtd':
                $start = $now->modify('first day of this month');
                break;
            case 'qtd':
                $month = (int)$now->format('n');
                $quarterStartMonth = (int)(floor(($month - 1) / 3) * 3) + 1;
                $start = $now->setDate((int)$now->format('Y'), $quarterStartMonth, 1);
                break;
            case 'ytd':
                $start = $now->setDate((int)$now->format('Y'), 1, 1);
                break;
            default:
                $start = $now->modify('-11 months')->modify('first day of this month');
                break;
        }

        $limit = $now->modify('-5 years');
        if ($start < $limit) {
            $start = $limit;
        }

        return [$start, $now];
    }

    protected function resolveTrendRange($filters): array
    {
        [$start, $end] = $this->resolveDateRange($filters);

        $period = is_array($filters) ? ($filters['period'] ?? 'mtm') : (string)$filters;

        if ($period === 'mtm' && empty($filters['start_date']) && empty($filters['end_date'])) {
            $start = $end->modify('-11 months')->modify('first day of this month');
        }
        return [$start, $end];
    }

    protected function previousRange(\DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        $days = max(0, $start->diff($end)->days);
        $previousEnd = $start->modify('-1 day');
        $previousStart = $previousEnd->modify("-{$days} days");

        return [$previousStart, $previousEnd];
    }

    protected function toDate($value, string $timeSuffix = '00:00:00'): \DateTimeImmutable
    {
        if ($value instanceof \DateTimeImmutable) {
            return $value;
        }
        if ($value instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromInterface($value);
        }

        $string = trim((string)$value);
        if ($string === '') {
            return new \DateTimeImmutable('today ' . $timeSuffix);
        }

        if (strlen($string) <= 10) {
            $string .= ' ' . $timeSuffix;
        }

        return new \DateTimeImmutable($string);
    }

    protected function ensureComparativeFilters(array $filters, \DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        if (empty($filters['start_date'])) {
            $filters['start_date'] = $start;
        }
        if (empty($filters['end_date'])) {
            $filters['end_date'] = $end;
        }
        if (empty($filters['compare_start']) || empty($filters['compare_end'])) {
            $filters['compare_start'] = $start->modify('-1 year');
            $filters['compare_end'] = $end->modify('-1 year');
        }
        if (empty($filters['year_start'])) {
            $filters['year_start'] = new \DateTimeImmutable($end->format('Y') . '-01-01 00:00:00');
        }
        if (empty($filters['year_compare_start'])) {
            $filters['year_compare_start'] = $filters['year_start']->modify('-1 year');
        }
        return $filters;
    }
}
