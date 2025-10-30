<?php

class Dashboard
{
    protected \PDO $db;
    protected ?\PDO $firebird = null;

    public function __construct()
    {
        $this->db = Database::pgsql();
        try {
            $this->firebird = Database::firebird();
        } catch (\Throwable $exception) {
            $this->firebird = null;
        }
    }

    public function getSummary(int $providerId, array $filters = []): array
    {
        // TODO: Reemplazar con consultas reales una vez que existan vistas consolidadas.
        $period = $filters['period'] ?? 'mtm';
        $factor = $this->resolveFactor($period);

        return [
            'sellin' => round(1250000 * $factor, 2),
            'sellin_growth' => 6.4,
            'sellout' => round(980000 * $factor, 2),
            'sellout_growth' => -3.1,
            'orders_pending' => (int)round(120 * $factor),
            'orders_fulfilled' => (int)round(340 * $factor),
            'returns' => (int)round(24 * $factor),
            'alerts' => (int)round(9 * $factor),
        ];
    }

    public function getSalesTrend(int $providerId, array $filters = []): array
    {
        $months = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
        $factor = $this->resolveFactor($filters['period'] ?? 'mtm');
        $sellIn = [];
        $sellOut = [];

        $baseIn = [320000, 360000, 410000, 380000, 420000, 460000, 480000, 470000, 510000, 540000, 560000, 590000];
        $baseOut = [280000, 300000, 330000, 310000, 340000, 360000, 390000, 380000, 400000, 430000, 450000, 470000];

        foreach ($baseIn as $index => $value) {
            $sellIn[] = round($value * $factor, 2);
            $sellOut[] = round($baseOut[$index] * $factor, 2);
        }

        return [
            'categories' => $months,
            'series' => [
                [
                    'name' => 'Sell In',
                    'data' => $sellIn,
                ],
                [
                    'name' => 'Sell Out',
                    'data' => $sellOut,
                ],
            ],
        ];
    }

    public function getOrdersDistribution(int $providerId, array $filters = []): array
    {
        $factor = $this->resolveFactor($filters['period'] ?? 'mtm');
        return [
            ['status' => 'Nuevas', 'value' => (int)round(120 * $factor)],
            ['status' => 'Preparacion', 'value' => (int)round(85 * $factor)],
            ['status' => 'Transito', 'value' => (int)round(63 * $factor)],
            ['status' => 'Entregadas', 'value' => (int)round(340 * $factor)],
            ['status' => 'Atrasadas', 'value' => (int)round(18 * $factor)],
        ];
    }

    public function getTopProducts(int $providerId, array $filters = []): array
    {
        $top = [
            ['sku' => 'SP-0012', 'description' => 'Cuaderno profesional cuadriculado', 'sellout' => 125000, 'growth' => 12.4],
            ['sku' => 'SP-0345', 'description' => 'Boligrafo tinta gel pack 12', 'sellout' => 98000, 'growth' => 8.1],
            ['sku' => 'SP-0768', 'description' => 'Resaltador colores neon', 'sellout' => 87000, 'growth' => 6.5],
            ['sku' => 'SP-0234', 'description' => 'Plumon pizarra blanca', 'sellout' => 69000, 'growth' => 4.3],
            ['sku' => 'SP-0199', 'description' => 'Papel bond carta 500 hojas', 'sellout' => 62000, 'growth' => -2.6],
        ];

        return $top;
    }

    protected function resolveFactor(string $period): float
    {
        switch ($period) {
            case 'ytd':
                return 1.75;
            case 'qtd':
                return 1.2;
            case 'mtd':
                return 0.45;
            default:
                return 1.0;
        }
    }
}
