<?php

use League\Csv\Writer;
use Mpdf\Mpdf;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class OrdersExporter
{
    protected array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    public function asPdf(array $order, array $details): string
    {
        $items = $this->mapDetails($details);
        $summary = $this->buildSummary($order);
        $company = $this->config['contact']['company'] ?? 'Super Papelera';
        $basePath = rtrim($this->config['base_path'] ?? $this->config['base_url'] ?? '', '/');
        $logoPath = $basePath . '/assets/img/logoPDF.jpeg';

        $mpdf = new Mpdf([
            'format' => 'Letter',
            'margin_top' => 20,
            'margin_left' => 12,
            'margin_right' => 12,
            'margin_bottom' => 20,
        ]);

        $mpdf->SetTitle('Orden ' . ($order['FOLIO'] ?? ''));
        $html = $this->buildPdfHtml($company, $order, $items, $summary, $logoPath);
        $mpdf->WriteHTML($html);

        return $mpdf->Output('', 'S');
    }

    public function asCsv(array $order, array $details): string
    {
        $items = $this->mapDetails($details);

        $writer = Writer::createFromString('');
        $writer->insertOne(['Codigo', 'SKU', 'Codigo barras', 'Descripcion', 'Cantidad', 'Unidad', 'Costo', 'Total']);
        foreach ($items as $item) {
            $writer->insertOne([
                $item['codigo'],
                $item['sku'],
                $item['codigo_barras'],
                $item['descripcion'],
                number_format($item['cantidad'], 2, '.', ''),
                $item['unidad'],
                number_format($item['costo'], 2, '.', ''),
                number_format($item['total'], 2, '.', ''),
            ]);
        }

        return $writer->toString();
    }

    public function asXml(array $order, array $details): string
    {
        $items = $this->mapDetails($details);
        $xml = new \SimpleXMLElement('<orden></orden>');
        $header = $xml->addChild('encabezado');
        foreach ($order as $key => $value) {
            if (is_array($value)) {
                continue;
            }
            $header->addChild(strtolower($key), htmlspecialchars((string)$value, ENT_XML1 | ENT_QUOTES, 'UTF-8'));
        }
        $itemsRoot = $xml->addChild('partidas');
        foreach ($items as $item) {
            $node = $itemsRoot->addChild('item');
            foreach ($item as $key => $value) {
                $node->addChild($key, htmlspecialchars((string)$value, ENT_XML1 | ENT_QUOTES, 'UTF-8'));
            }
        }
        return $xml->asXML() ?: '';
    }

    public function asXlsx(array $order, array $details): string
    {
        $items = $this->mapDetails($details);
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Orden');

        $headers = ['Codigo', 'SKU', 'Codigo barras', 'Descripcion', 'Cantidad', 'Unidad', 'Costo', 'Total'];
        $sheet->fromArray($headers, null, 'A1');

        $row = 2;
        foreach ($items as $item) {
            $sheet->fromArray([
                $item['codigo'],
                $item['sku'],
                $item['codigo_barras'],
                $item['descripcion'],
                $item['cantidad'],
                $item['unidad'],
                $item['costo'],
                $item['total'],
            ], null, 'A' . $row);
            $row++;
        }

        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        $content = ob_get_clean() ?: '';
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return $content;
    }

    protected function buildPdfHtml(string $company, array $order, array $items, array $summary, string $logoPath): string
    {
        $rowsHtml = '';
        foreach ($items as $item) {
            $rowsHtml .= sprintf(
                '<tr>
                    <td>%s</td>
                    <td>%s</td>
                    <td>%s</td>
                    <td>%s</td>
                    <td class="text-end">%s</td>
                    <td class="text-center">%s</td>
                    <td class="text-end">%s</td>
                    <td class="text-end">%s</td>
                </tr>',
                htmlspecialchars($item['codigo']),
                htmlspecialchars($item['sku']),
                htmlspecialchars($item['codigo_barras']),
                htmlspecialchars($item['descripcion']),
                number_format($item['cantidad'], 2),
                htmlspecialchars($item['unidad']),
                $this->formatMoney($item['costo']),
                $this->formatMoney($item['total'])
            );
        }

        $observaciones = nl2br(htmlspecialchars(trim((string)($order['COMENTARIO'] ?? ''))));

        $logoHtml = '';
        if ($logoPath !== '') {
            $logoHtml = '<img src="' . htmlspecialchars($logoPath, ENT_QUOTES, 'UTF-8') . '" alt="Logo" style="height:40px">';
        }

        $html = '
        <style>
            body { font-family: DejaVu Sans, sans-serif; font-size: 9pt; color: #111; }
            .header-title { font-size: 14pt; font-weight: bold; text-align: center; margin-bottom: 4px; }
            .sub-title { text-align: center; margin-bottom: 12px; }
            table { width: 100%; border-collapse: collapse; }
            .meta td { padding: 4px; font-size: 8.5pt; }
            .items th { background: #f3f3f3; font-weight: bold; font-size: 8pt; border: 1px solid #ddd; padding: 4px; }
            .items td { border: 1px solid #eee; padding: 4px; font-size: 8pt; }
            .totals td { padding: 3px; font-size: 9pt; }
            .text-end { text-align: right; }
            .text-center { text-align: center; }
            .mt-2 { margin-top: 12px; }
        </style>
        <table style="width:100%; margin-bottom:10px;">
            <tr>
                <td style="width:30%;">' . $logoHtml . '</td>
                <td style="text-align:center;">
                    <div class="header-title">Requisicion de Compra</div>
                    <div class="sub-title">' . htmlspecialchars($company) . '</div>
                </td>
                <td style="width:30%;"></td>
            </tr>
        </table>
        <table class="meta">
            <tr>
                <td><strong>Proveedor:</strong> ' . htmlspecialchars($order['RAZON_SOCIAL'] ?? '') . '</td>
                <td><strong>Orden:</strong> ' . htmlspecialchars(($order['SERIE'] ?? '') . ' - ' . ($order['FOLIO'] ?? '')) . '</td>
            </tr>
            <tr>
                <td><strong>Numero proveedor:</strong> ' . htmlspecialchars($order['NUMERO_PROVEEDOR'] ?? '') . '</td>
                <td><strong>Fecha:</strong> ' . htmlspecialchars($order['FECHA'] ?? '') . '</td>
            </tr>
            <tr>
                <td><strong>Tienda:</strong> ' . htmlspecialchars($order['NOMBRE_CORTO'] ?? '') . '</td>
                <td><strong>Credito:</strong> ' . (int)($order['DIAS_CREDITO'] ?? 0) . ' dias</td>
            </tr>
            <tr>
                <td><strong>Entrega:</strong> ' . htmlspecialchars($order['LUGAR_ENTREGA'] ?? '') . '</td>
                <td><strong>Autorizo:</strong> ' . htmlspecialchars($order['AUTORIZA'] ?? '') . '</td>
            </tr>
        </table>
        <table class="items mt-2">
            <thead>
                <tr>
                    <th>Codigo</th>
                    <th>SKU</th>
                    <th>Codigo barras</th>
                    <th>Descripcion</th>
                    <th class="text-end">Cantidad</th>
                    <th class="text-center">Unidad</th>
                    <th class="text-end">Costo</th>
                    <th class="text-end">Total</th>
                </tr>
            </thead>
            <tbody>' . $rowsHtml . '</tbody>
        </table>
        <table class="totals mt-2">
            <tr><td><strong>Observaciones:</strong> ' . $observaciones . '</td></tr>
            <tr><td class="text-end"><strong>Subtotal:</strong> ' . $this->formatMoney($summary['importe']) . '</td></tr>
            <tr><td class="text-end"><strong>Descuento:</strong> ' . $this->formatMoney($summary['descuento']) . '</td></tr>
            <tr><td class="text-end"><strong>Impuestos:</strong> ' . $this->formatMoney($summary['impuestos']) . '</td></tr>
            <tr><td class="text-end"><strong>Total:</strong> ' . $this->formatMoney($summary['total']) . '</td></tr>
        </table>';

        return $html;
    }

    protected function mapDetails(array $details): array
    {
        return array_map(static function (array $row): array {
            $cantidad = (float)($row['CANTIDAD_SOLICITADA'] ?? 0);
            $costo = (float)($row['COSTO'] ?? 0);
            return [
                'codigo' => trim((string)($row['CODIGO'] ?? '')),
                'sku' => trim((string)($row['SKU'] ?? '')),
                'codigo_barras' => trim((string)($row['CODIGO_BARRAS'] ?? '')),
                'descripcion' => trim((string)($row['DESCRIPCION'] ?? '')),
                'cantidad' => $cantidad,
                'unidad' => trim((string)($row['UNIDAD_CORTA'] ?? '')),
                'costo' => $costo,
                'total' => $cantidad * $costo,
            ];
        }, $details);
    }

    protected function buildSummary(array $order): array
    {
        $importe = (float)($order['IMPORTE'] ?? 0);
        $descuento = (float)($order['DESCUENTO_1'] ?? 0)
            + (float)($order['DESCUENTO_2'] ?? 0)
            + (float)($order['DESCUENTO_3'] ?? 0);
        $impuestos = (float)($order['IMPUESTOS_TRASLADADOS'] ?? 0)
            + (float)($order['IMPUESTOS_TRASLADADOS_2'] ?? 0);
        $total = (float)($order['TOTAL'] ?? $importe - $descuento + $impuestos);

        return [
            'importe' => $importe,
            'descuento' => $descuento,
            'impuestos' => $impuestos,
            'total' => $total,
        ];
    }

    protected function formatMoney(float $value): string
    {
        return '$ ' . number_format($value, 2, '.', ',');
    }
}
