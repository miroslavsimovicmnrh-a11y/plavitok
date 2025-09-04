<?php
// list_nalozi.php — pregled CSV naloga
$csv = __DIR__ . '/data/zahtevi.csv';
if (!file_exists($csv)) { echo "<p>Nema podataka.</p>"; exit; }
$h = fopen($csv, 'r');
echo "<table border='1' cellpadding='5'>";
$hdr = fgetcsv($h);
echo "<tr>"; foreach ($hdr as $c) echo "<th>".htmlspecialchars($c)."</th>"; echo "</tr>";
while(($row=fgetcsv($h))!==false){
  echo "<tr>"; foreach ($row as $c) echo "<td>".htmlspecialchars($c)."</td>"; echo "</tr>";
}
fclose($h);
echo "</table>";
