<?php
if (defined('BACKEND_HELPERS_EXPORT_PHP_LOADED')) { return; }
define('BACKEND_HELPERS_EXPORT_PHP_LOADED', true);

require_once __DIR__ . '/../config/app.php';
if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__, 2));
}
if (file_exists(APP_ROOT . '/vendor/autoload.php')) {
    require_once APP_ROOT . '/vendor/autoload.php';
}

function export_supports_xlsx(): bool {
    return class_exists('\\PhpOffice\\PhpSpreadsheet\\Spreadsheet')
        && class_exists('\\PhpOffice\\PhpSpreadsheet\\Writer\\Xlsx');
}

function export_as_csv(string $filename, array $columns, array $rows): void {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, array_values($columns));
    foreach ($rows as $row) {
        $line = [];
        foreach (array_keys($columns) as $key) {
            $line[] = (string) ($row[$key] ?? '');
        }
        fputcsv($out, $line);
    }
    fclose($out);
    exit;
}

function export_as_xlsx(string $filename, string $sheetName, string $title, string $filterSummary, array $columns, array $rows): void {
    if (!export_supports_xlsx()) {
        export_as_csv($filename . '_excel_compatible', $columns, $rows);
    }
    $spreadsheetClass = '\\PhpOffice\\PhpSpreadsheet\\Spreadsheet';
    $writerClass = '\\PhpOffice\\PhpSpreadsheet\\Writer\\Xlsx';
    $spreadsheet = new $spreadsheetClass();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle(substr($sheetName, 0, 31));

    $sheet->setCellValue('A1', $title);
    $sheet->setCellValue('A2', 'Generated: ' . date('Y-m-d H:i:s'));
    $sheet->setCellValue('A3', 'Filters: ' . ($filterSummary !== '' ? $filterSummary : 'None'));

    $columnKeys = array_keys($columns);
    $columnLabels = array_values($columns);
    $headerRow = 5;
    $colIndex = 1;
    foreach ($columnLabels as $label) {
        $sheet->setCellValueByColumnAndRow($colIndex, $headerRow, $label);
        $colIndex++;
    }

    $dataRow = $headerRow + 1;
    foreach ($rows as $row) {
        $colIndex = 1;
        foreach ($columnKeys as $key) {
            $sheet->setCellValueByColumnAndRow($colIndex, $dataRow, (string) ($row[$key] ?? ''));
            $colIndex++;
        }
        $dataRow++;
    }

    $lastCol = export_column_letter_from_index(count($columnKeys));
    $lastDataRow = max($headerRow, $dataRow - 1);

    $sheet->mergeCells("A1:{$lastCol}1");
    $sheet->mergeCells("A2:{$lastCol}2");
    $sheet->mergeCells("A3:{$lastCol}3");
    $sheet->getStyle("A1:{$lastCol}1")->getFont()->setBold(true)->setSize(15);
    $sheet->getStyle("A5:{$lastCol}5")->getFont()->setBold(true);
    $sheet->getStyle("A5:{$lastCol}5")->getFill()->setFillType('solid')->getStartColor()->setARGB('FFE8EEF9');
    $sheet->freezePane('A6');
    $sheet->setAutoFilter("A5:{$lastCol}{$lastDataRow}");
    for ($i = 1; $i <= count($columnKeys); $i++) {
        $sheet->getColumnDimensionByColumn($i)->setAutoSize(true);
    }
    if ($lastDataRow > 5) {
        $sheet->getStyle("A6:{$lastCol}{$lastDataRow}")
            ->getBorders()
            ->getAllBorders()
            ->setBorderStyle('thin')
            ->getColor()
            ->setARGB('FFE5EAF2');
        for ($row = 6; $row <= $lastDataRow; $row++) {
            if ($row % 2 === 0) {
                $sheet->getStyle("A{$row}:{$lastCol}{$row}")
                    ->getFill()
                    ->setFillType('solid')
                    ->getStartColor()
                    ->setARGB('FFF8FBFF');
            }
        }
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '.xlsx"');
    $writer = new $writerClass($spreadsheet);
    $writer->save('php://output');
    exit;
}

function export_column_letter_from_index(int $index): string {
    $letters = '';
    while ($index > 0) {
        $index--;
        $letters = chr(($index % 26) + 65) . $letters;
        $index = intdiv($index, 26);
    }
    return $letters === '' ? 'A' : $letters;
}

function export_table(string $filenameBase, string $sheetName, string $title, string $filterSummary, array $columns, array $rows, string $format = 'xlsx'): void {
    $requested = strtolower(trim($format));
    if ($requested === 'xlsx' && export_supports_xlsx()) {
        export_as_xlsx($filenameBase, $sheetName, $title, $filterSummary, $columns, $rows);
    }

    $csvName = $requested === 'xlsx' ? ($filenameBase . '_excel_compatible') : $filenameBase;
    export_as_csv($csvName, $columns, $rows);
}
