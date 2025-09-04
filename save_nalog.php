<?php
// save_nalog.php — upsert u CSV (update ako postoji isti 'broj', inace append).
// CSV je u /data/zahtevi.csv, delimiter ';', sa headerom i UTF-8 BOM (radi Excela).

declare(strict_types=1);

$dir = __DIR__ . '/data';
$csv = $dir . '/zahtevi.csv';

if (!is_dir($dir)) { http_response_code(500); echo "ERR: folder data ne postoji"; exit; }

// Ulaz
$broj   = isset($_POST['broj'])   ? trim((string)$_POST['broj'])   : '';
$firma  = isset($_POST['firma'])  ? trim((string)$_POST['firma'])  : '';
$adresa = isset($_POST['adresa']) ? trim((string)$_POST['adresa']) : '';
$vreme  = isset($_POST['vreme'])  ? trim((string)$_POST['vreme'])  : '';
$stavke = isset($_POST['stavke']) ? (string)$_POST['stavke']       : '[]';

if ($broj==='' || $firma==='' || $adresa==='' || $vreme==='') {
  http_response_code(400);
  echo "ERR: nedostaju polja";
  exit;
}

$DELIM = ';';
$HEADER = ['broj','firma','adresa','vreme','stavke_json'];

// Procitaj sve postojece redove (ako fajl postoji)
$rows = [];
$hadHeader = false;
if (is_file($csv)) {
  $fh = fopen($csv, 'r');
  if ($fh === false) { http_response_code(500); echo "ERR: ne mogu da otvorim CSV za citanje"; exit; }

  // Ukloni BOM sa prve linije ako postoji
  $firstLine = fgets($fh);
  if ($firstLine !== false) {
    $firstLine = preg_replace('/^\xEF\xBB\xBF/', '', $firstLine);
    $tmp = fopen('php://temp', 'r+');
    fwrite($tmp, $firstLine);
    stream_copy_to_stream($fh, $tmp);
    rewind($tmp);
    // Procitaj CSV linije iz temp-a
    $i = 0;
    while (($data = fgetcsv($tmp, 0, $DELIM)) !== false) {
      if ($i === 0 && $data === $HEADER) {
        $hadHeader = true;
        $i++;
        continue;
      }
      $rows[] = $data;
      $i++;
    }
    fclose($tmp);
  }
  fclose($fh);
}

// Pripremi novi red
$newRow = [$broj, $firma, $adresa, $vreme, $stavke];

// Nadji postojeci red po 'broj' (kolona 0). Ako nadjes — zameni. Ako ima vise duplikata — sacuvaj samo JEDAN (prvi), ostale ukloni.
$replaced = false;
$kept = [];
foreach ($rows as $r) {
  if (isset($r[0]) && (string)$r[0] === $broj) {
    if (!$replaced) {
      $kept[] = $newRow;
      $replaced = true;
    }
    // duplikate istog broja preskaci
  } else {
    $kept[] = $r;
  }
}
if (!$replaced) {
  $kept[] = $newRow; // ako ne postoji, dodaj kao novi
}

// Upisi u temp pa atomically zameni
$tmpPath = $csv . '.tmp';
$fw = fopen($tmpPath, 'w');
if ($fw === false) { http_response_code(500); echo "ERR: ne mogu da otvorim temp CSV za pisanje"; exit; }

// BOM + header
fwrite($fw, chr(0xEF) . chr(0xBB) . chr(0xBF));
fputcsv($fw, $HEADER, $DELIM);

// Redovi
foreach ($kept as $row) {
  // osiguraj 5 kolona
  $row = array_pad($row, 5, '');
  fputcsv($fw, $row, $DELIM);
}
fclose($fw);

// Atomic rename
if (!rename($tmpPath, $csv)) {
  @unlink($tmpPath);
  http_response_code(500);
  echo "ERR: ne mogu da zamenim CSV";
  exit;
}

echo "OK";
?>
