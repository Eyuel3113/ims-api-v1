<?php

function parseBoolean($value) {
    return in_array(strtolower(trim($value ?? '')), ['yes', '1', 'true', 'on']);
}

$tests = [
    'yes' => true,
    'Yes' => true,
    'YES' => true,
    'no' => false,
    'No' => false,
    '1' => true,
    '0' => false,
    'true' => true,
    'false' => false,
    'random' => false,
    '' => false,
    null => false,
    'on' => true
];

$failed = 0;
foreach ($tests as $input => $expected) {
    $result = parseBoolean($input);
    if ($result !== $expected) {
        echo "FAIL: Input '$input' expected " . ($expected ? 'true' : 'false') . " but got " . ($result ? 'true' : 'false') . "\n";
        $failed++;
    } else {
        echo "PASS: Input '$input' -> " . ($result ? 'true' : 'false') . "\n";
    }
}

if ($failed === 0) {
    echo "\nAll tests passed!\n";
} else {
    echo "\n$failed tests failed.\n";
}
