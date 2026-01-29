<?php
$url = 'http://127.0.0.1:8000/api/v1/products/import';
$file = __DIR__ . '/../public/test_import.csv';

if (!file_exists($file)) {
    die("File not found: $file\n");
}

$cfile = new CURLFile($file, 'text/csv', 'test_import.csv');
$data = ['file' => $cfile];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if ($response === false) {
    echo "Error: " . curl_error($ch) . "\n";
} else {
    echo "HTTP Status: $httpCode\n";
    echo "Response: $response\n";
}

curl_close($ch);
