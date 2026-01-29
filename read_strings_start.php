<?php
$zip = new ZipArchive;
if ($zip->open('public/Aksen Master Data Import.xlsx') === TRUE) {
    $content = $zip->getFromName('xl/sharedStrings.xml');
    echo substr($content, 0, 5000);
    $zip->close();
}
