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
$datum      = isset($_POST['datum']) ? trim((string)$_POST['datum']) : '';
$broj       = isset($_POST['brojZahteva']) ? trim((string)$_POST['brojZahteva']) : '';
$artikal    = isset($_POST['artikal']) ? trim((string)$_POST['artikal']) : '';
$m1         = isset($_POST['m1']) ? trim((string)$_POST['m1']) : '';
$m2         = isset($_POST['m2']) ? trim((string)$_POST['m2']) : '';
$napomena   = isset($_POST['napomena']) ? trim((string)$_POST['napomena']) : '';
$vremeUnosa = date('c');

if ($datum==='' || $broj==='' || $artikal==='') {
  elog("VALIDATION_FAIL datum=$datum broj=$broj artikal=$artikal");
  http_response_code(400);
  echo "ERROR: nedostaju obavezna polja (datum, brojZahteva, artikal).";
  exit;
}

$m1 = str_replace(',', '.', $m1);
$m2 = str_replace(',', '.', $m2);

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
  if (@fputcsv($h, ['datum','brojZahteva','artikal','m1','m2','napomena','vremeUnosa'], ';')===false){
    elog("HEADER_WRITE_FAIL");
  }
}

$row = [$datum, $broj, $artikal, $m1, $m2, $napomena, $vremeUnosa];
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
