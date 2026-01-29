<?php
$zip = new ZipArchive;
if ($zip->open('public/Aksen Master Data Import.xlsx') === TRUE) {
    echo $zip->getFromName('xl/sharedStrings.xml');
    $zip->close();
}
