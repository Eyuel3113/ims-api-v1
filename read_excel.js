import * as XLSX from 'xlsx/xlsx.mjs';
import * as fs from 'fs';

/* load 'fs' for readFile and writeFile support */
XLSX.set_fs(fs);

const workbook = XLSX.readFile('public/Aksen Master Data Import.xlsx');
const sheetName = workbook.SheetNames[0];
const worksheet = workbook.Sheets[sheetName];
const jsonData = XLSX.utils.sheet_to_json(worksheet, { header: 1 });

if (jsonData.length > 0) {
    console.log(JSON.stringify(jsonData[0]));
} else {
    console.log("Empty sheet");
}
