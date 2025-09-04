<?php
// convert_csv.php — jednom pokreni da pretvoriš zarez CSV u tačka-zarez i doda BOM
$src = __DIR__ . '/data/zahtevi.csv';
if (!file_exists($src)) { echo "nema data/zahtevi.csv"; exit; }

$rows = [];
$h = fopen($src, 'r');
// pokušaj prvo da čitaš sa ; — ako ima samo 1 kolonu, čitaj sa ,
$line = fgets($h);
fseek($h, 0);
$test = str_getcsv(trim($line), ';');
$delim = (count($test) > 1) ? ';' : ',';

while(($r = fgetcsv($h, 0, $delim)) !== false){
  $rows[] = $r;
}
fclose($h);

// upiši nazad sa ; i BOM
$f = fopen($src, 'w');
fwrite($f, chr(0xEF).chr(0xBB).chr(0xBF));
foreach($rows as $r){
  fputcsv($f, $r, ';');
}
fclose($f);
echo "OK pretvoreno";
