<?php
$zip = new ZipArchive;
if ($zip->open('public/Aksen Master Data Import.xlsx') === TRUE) {
    for ($i = 0; $i < $zip->numFiles; $i++) {
        echo $zip->getNameIndex($i) . "\n";
    }
    $zip->close();
} else {
    echo 'failed';
}
