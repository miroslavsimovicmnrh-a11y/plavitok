<?php
header('Content-Type: text/plain; charset=utf-8');
$dir = __DIR__ . '/data';
$csv = $dir . '/proba_write.txt';

echo "DIR: $dir\n";
echo "is_dir: " . (is_dir($dir) ? "YES" : "NO") . "\n";
echo "is_writable(dir): " . (is_writable($dir) ? "YES" : "NO") . "\n";

if (!is_dir($dir)) {
  echo "Trying mkdir...\n";
  if (@mkdir($dir, 0775, true)) echo "mkdir OK\n"; else { echo "mkdir FAIL\n"; exit; }
}

$ok = @file_put_contents($csv, "test ".date('c')."\n", FILE_APPEND);
echo "file_put_contents: " . ($ok!==false ? "OK ($ok bytes)\n" : "FAIL\n");
echo "Done.\n";
