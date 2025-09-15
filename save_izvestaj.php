<?php
// save_izvestaj.php — robustan upis u /data/proizvodnja.csv sa proverama i logovanjem
mb_internal_encoding('UTF-8');

header('Content-Type: text/plain; charset=utf-8');

$dir = __DIR__ . '/data';
$csv = $dir . '/proizvodnja.csv';
$log = $dir . '/proizvodnja.log';

function elog($msg){
  global $log;
  @file_put_contents($log, "[".date('c')."] ".$msg."\n", FILE_APPEND);
}

if (!is_dir($dir)) {
  if (!@mkdir($dir, 0775, true)) {
    $err = error_get_last();
    $m = isset($err['message']) ? $err['message'] : 'mkdir failed';
    elog("MKDIR_FAIL: $m");
    http_response_code(500);
    echo "ERROR: ne mogu da kreiram /data (dozvole?).";
    exit;
  }
}

if (!is_writable($dir)) {
  elog("DIR_NOT_WRITABLE: $dir");
  http_response_code(500);
  echo "ERROR: /data nije upisiv (dozvole?).";
  exit;
}

// Ulazi
$action     = isset($_POST['action']) ? trim((string)$_POST['action']) : '';
$datum      = isset($_POST['datum']) ? trim((string)$_POST['datum']) : '';
$broj       = isset($_POST['brojZahteva']) ? trim((string)$_POST['brojZahteva']) : '';
$artikal    = isset($_POST['artikal']) ? trim((string)$_POST['artikal']) : '';
$m1         = isset($_POST['m1']) ? trim((string)$_POST['m1']) : '';
$m2         = isset($_POST['m2']) ? trim((string)$_POST['m2']) : '';
$m3         = isset($_POST['m3']) ? trim((string)$_POST['m3']) : '';
$kom        = isset($_POST['kom']) ? trim((string)$_POST['kom']) : '';
$napomena   = isset($_POST['napomena']) ? trim((string)$_POST['napomena']) : '';
$vremeUnosa = date('c');

// Originalni ključevi (za update)
$odatum   = isset($_POST['o_datum']) ? trim((string)$_POST['o_datum']) : '';
$obroj    = isset($_POST['o_brojZahteva']) ? trim((string)$_POST['o_brojZahteva']) : '';
$oartikal = isset($_POST['o_artikal']) ? trim((string)$_POST['o_artikal']) : '';

if ($datum==='' || $broj==='' || $artikal==='') {
  elog("VALIDATION_FAIL datum=$datum broj=$broj artikal=$artikal");
  http_response_code(400);
  echo "ERROR: nedostaju obavezna polja (datum, brojZahteva, artikal).";
  exit;
}

$m1 = str_replace(',', '.', $m1);
$m2 = str_replace(',', '.', $m2);
$m3 = str_replace(',', '.', $m3);
$kom = str_replace(',', '.', $kom);
// === UPDATE MODE ===
if ($action === 'update') {
  if($odatum==='' || $obroj==='' || $oartikal===''){
    elog("UPDATE_KEYS_MISSING odatum=$odatum obroj=$obroj oartikal=$oartikal");
    http_response_code(400);
    echo "ERROR: nedostaju originalni ključevi.";
    exit;
  }
  if (!file_exists($csv)){
    http_response_code(404);
    echo "ERROR: CSV ne postoji.";
    exit;
  }
  $h = @fopen($csv, 'c+');
  if(!$h){
    $err = error_get_last();
    $m = isset($err['message']) ? $err['message'] : 'fopen failed';
    elog("OPEN_FAIL: $m");
    http_response_code(500);
    echo "ERROR: ne mogu da otvorim CSV.";
    exit;
  }
  if (!@flock($h, LOCK_EX)) { elog("LOCK_FAIL"); }
  $lines = [];
  while(($line = fgets($h)) !== false){ $lines[] = rtrim($line, "\r\n"); }
  if(empty($lines)){ @flock($h, LOCK_UN); @fclose($h); http_response_code(404); echo "ERROR: CSV prazan."; exit; }
  $header = str_getcsv(array_shift($lines), ';');
  $rows = [];
  foreach($lines as $ln){ if($ln==='') continue; $rows[] = str_getcsv($ln, ';'); }
  $updated = false;
  foreach($rows as &$cols){
    if(($cols[0]??'')===$odatum && ($cols[1]??'')===$obroj && ($cols[2]??'')===$oartikal){
      $cols = [$datum, $broj, $artikal, $m1, $m2, $m3, $kom, $napomena, $vremeUnosa];
      $updated = true;
      break;
    }
  }
  if(!$updated){
    @flock($h, LOCK_UN);
    @fclose($h);
    http_response_code(404);
    echo "ERROR: red nije pronađen.";
    exit;
  }
  ftruncate($h, 0);
  rewind($h);
  fputcsv($h, $header, ';');
  foreach($rows as $r){ fputcsv($h, $r, ';'); }
  @flock($h, LOCK_UN);
  @fclose($h);
  @chmod($csv, 0664);
  elog("UPDATED $odatum|$obroj|$oartikal -> $datum|$broj|$artikal");
  echo "OK izmenjeno";
  exit;
}

// === INSERT MODE (default) ===
$needHeader = !file_exists($csv) || filesize($csv)===0;

$h = @fopen($csv, 'a');
if(!$h){
  $err = error_get_last();
  $m = isset($err['message']) ? $err['message'] : 'fopen failed';
  elog("OPEN_FAIL: $m");
  http_response_code(500);
  echo "ERROR: ne mogu da otvorim CSV za upis.";
  exit;
}

if (!@flock($h, LOCK_EX)) {
  elog("LOCK_FAIL");
  // nastavljamo bez hard fail-a, ali logujemo
}

if ($needHeader){
  if (@fputcsv($h, ['datum','brojZahteva','artikal','m1','m2','m3','kom','napomena','vremeUnosa'], ';')===false){
    elog("HEADER_WRITE_FAIL");
  }
}

$row = [$datum, $broj, $artikal, $m1, $m2, $m3, $kom, $napomena, $vremeUnosa];
if (@fputcsv($h, $row, ';')===false){
  elog("ROW_WRITE_FAIL datum=$datum broj=$broj");
  http_response_code(500);
  echo "ERROR: upis reda nije uspeo.";
  @flock($h, LOCK_UN);
  @fclose($h);
  exit;
}

@flock($h, LOCK_UN);
@fclose($h);

@chmod($csv, 0664);

echo "OK upisano";
