<?php
declare(strict_types=1);
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
@ini_set('display_errors','1'); @error_reporting(E_ALL);

$broj   = $_POST['broj']   ?? '';
$firma  = $_POST['firma']  ?? '';
$adresa = $_POST['adresa'] ?? '';
$vreme  = $_POST['vreme']  ?? date('c');
$stavke = $_POST['stavke'] ?? '[]';
if ($broj===''){ http_response_code(400); echo 'ERR missing broj'; exit; }

$dir = __DIR__ . '/data';
$csv = $dir . '/zahtevi.csv';
$tmp = $dir . '/zahtevi.csv.tmp';
if (!is_dir($dir)) { @mkdir($dir,0755,true); }
if (!file_exists($csv)) {
  $f = fopen($csv,'w'); if(!$f){ http_response_code(500); echo 'ERR create'; exit; }
  // UTF-8 BOM + header za ; delimiter
  fwrite($f, chr(0xEF).chr(0xBB).chr(0xBF));
  fputcsv($f, ['broj','firma','adresa','vreme','stavke_json'], ';'); fclose($f);
}

$in = fopen($csv,'r'); if(!$in){ http_response_code(500); echo 'ERR open'; exit; }
$out= fopen($tmp,'w'); if(!$out){ fclose($in); http_response_code(500); echo 'ERR tmp'; exit; }

// Pročitaj header (preskoči BOM ako ga ima)
$first = fgets($in);
if ($first===false){ fclose($in); fclose($out); @unlink($tmp); echo 'ERR empty'; exit; }
if (substr($first,0,3) === "\xEF\xBB\xBF") { $first = substr($first,3); }
$headers = str_getcsv(trim($first), ';');
if (!$headers){ $headers = ['broj','firma','adresa','vreme','stavke_json']; }

// Upis headera u temp fajl sa BOM-om
fwrite($out, chr(0xEF).chr(0xBB).chr(0xBF"));
fputcsv($out, $headers, ';');

// Mapiraj indexe
$map = [];
foreach ($headers as $i=>$k){ $map[strtolower(trim((string)$k))] = $i; }

$replaced = false;
while (($row = fgetcsv($in, 0, ';')) !== false){
  if (!isset($row[0])) continue;
  $rowBroj = $row[$map['broj']] ?? $row[0] ?? '';
  if ((string)$rowBroj === (string)$broj){
    $new = $row;
    if (isset($map['firma']))  $new[$map['firma']]  = $firma;
    if (isset($map['adresa'])) $new[$map['adresa']] = $adresa;
    if (isset($map['vreme']))  $new[$map['vreme']]  = $vreme;
    if (isset($map['stavke_json'])) $new[$map['stavke_json']] = $stavke;
    fputcsv($out, $new, ';');
    $replaced = true;
  } else {
    fputcsv($out, $row, ';');
  }
}
fclose($in); fclose($out);

if (!$replaced){ @unlink($tmp); http_response_code(404); echo 'ERR not found'; exit; }
if (!@rename($tmp, $csv)){ @unlink($tmp); http_response_code(500); echo 'ERR replace'; exit; }

echo 'OK replaced';
