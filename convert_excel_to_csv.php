<?php

function getZipContent($zipFile, $fileName) {
    $zip = new ZipArchive;
    if ($zip->open($zipFile) === TRUE) {
        $content = $zip->getFromName($fileName);
        $zip->close();
        return $content;
    }
    return null;
}

$excelFile = 'public/Aksen Master Data Import.xlsx';
$outputFile = 'public/Aksen_Master_Data.csv';

// 1. Load Shared Strings
$sharedStringsXml = getZipContent($excelFile, 'xl/sharedStrings.xml');
if (!$sharedStringsXml) die("Could not read shared strings\n");

$strings = [];
$xml = new SimpleXMLElement($sharedStringsXml);
foreach ($xml->si as $si) {
    $strings[] = (string)($si->t ?? $si->r->t ?? "");
}
echo "Total shared strings: " . count($strings) . "\n";
echo "First 100 strings: " . implode(' | ', array_slice($strings, 0, 100)) . "\n";

// 2. Load Sheet1
$sheetXml = getZipContent($excelFile, 'xl/worksheets/sheet1.xml');
if (!$sheetXml) die("Could not read sheet1\n");

$sheet = new SimpleXMLElement($sheetXml);
$rows = [];

// Get column letters to index mapping (A=0, B=1, ...)
function colToIdx($col) {
    $idx = 0;
    for ($i = 0; $i < strlen($col); $i++) {
        $idx = $idx * 26 + (ord($col[$i]) - ord('A') + 1);
    }
    return $idx - 1;
}

foreach ($sheet->sheetData->row as $row) {
    $rowData = [];
    foreach ($row->c as $cell) {
        $attr = $cell->attributes();
        $ref = (string)$attr['r'];
        preg_match('/^[A-Z]+/', $ref, $matches);
        $colIdx = colToIdx($matches[0]);
        
        $type = (string)$attr['t'];
        $val = (string)$cell->v;
        
        if ($type === 's') {
            $rowData[$colIdx] = $strings[(int)$val];
        } else {
            $rowData[$colIdx] = $val;
        }
    }
    $rows[] = $rowData;
}

echo "Total rows found: " . count($rows) . "\n";
if (!empty($rows)) {
    for ($i = 0; $i < min(5, count($rows)); $i++) {
        echo "Row $i: ";
        print_r($rows[$i]);
    }
}

$headerRowIndex = -1;
foreach ($rows as $idx => $row) {
    if (in_array('ProductName', $row)) {
        $headerRowIndex = $idx;
        break;
    }
}

if ($headerRowIndex === -1) {
    echo "Could not find header row with 'ProductName'. Printing all rows to debug:\n";
    foreach ($rows as $idx => $row) {
        if (!empty($row)) {
            echo "Row $idx: " . implode(' | ', $row) . "\n";
            if ($idx > 50) break; // Limit output
        }
    }
    die("\nHeader row not found\n");
}

echo "Found header at row: $headerRowIndex\n";
$headerRow = $rows[$headerRowIndex];
$mapping = [];
foreach ($headerRow as $idx => $name) {
    if ($name) $mapping[$name] = $idx;
}
print_r($mapping);

// Target CSV header: name,code,category_name,unit,barcode,purchase_price,selling_price,min_stock,has_expiry,is_vatable
$fp = fopen($outputFile, 'w');
fputcsv($fp, ['name', 'code', 'category_name', 'unit', 'barcode', 'purchase_price', 'selling_price', 'min_stock', 'has_expiry', 'is_vatable']);

for ($i = $headerRowIndex + 1; $i < count($rows); $i++) {
    $row = $rows[$i];
    
    $name = $row[$mapping['ProductName'] ?? -1] ?? '';
    $code = $row[$mapping['ProductNumber'] ?? -1] ?? '';
    $category = $row[$mapping['PrimaryProductCategory'] ?? -1] ?? '';
    $unit = $row[$mapping['QuantityUom'] ?? -1] ?? '';
    $barcode = $code; // Using code as barcode if not available
    $p_price = $row[$mapping['LastPurchasePrice'] ?? -1] ?? '0';
    $s_price = $row[$mapping['DefaultSalesPrice'] ?? -1] ?? '0';
    $min_stock = $row[$mapping['MininumStockQuantity'] ?? -1] ?? '0';
    $is_taxable = ($row[$mapping['IsTaxable'] ?? -1] ?? '') === 'Y' ? 1 : 0;
    $has_expiry = 0; // Default

    // Skip empty names
    if (empty($name)) continue;

    fputcsv($fp, [
        $name,
        $code,
        $category,
        $unit,
        $barcode,
        $p_price,
        $s_price,
        $min_stock,
        $has_expiry,
        $is_taxable
    ]);
}

fclose($fp);
echo "Conversion complete: $outputFile\n";
