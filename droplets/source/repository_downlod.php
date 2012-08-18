<?php

global $database;

function bytes2Str($byte) {
  if ($byte < 1024)
    $result = round($byte, 2) . ' Byte';
  elseif ($byte >= 1024 and $byte < pow(1024, 2))
    $result = round($byte / 1024, 2) . ' KB';
  elseif ($byte >= pow(1024, 2) and $byte < pow(1024, 3))
    $result = round($byte / pow(1024, 2), 2) . ' MB';
  else
    $result = round($byte / pow(1024, 3), 2) . ' GB';
  return $result;
} // bytes2Str()

$config_file = WB_PATH.'/modules/manufaktur_git_downloads/config.json';
if (!file_exists($config_file))
  return sprintf('The configuration file %s does not exists!', $config_file);

if (false === ($cfg = file_get_contents($config_file)))
  return sprintf('Error reading the configuration file %s.', $config_file);

$config = json_decode($cfg, true);

$SQL = "SELECT * FROM `addons_mod_github_downloads` WHERE `download_active`='1' ORDER BY `repository_name` ASC";
if (null == ($query = $database->query($SQL)))
  return $database->get_error();

$rows = '';
$last_update = 0;
$downloads_total = 0;
$downloads_netto = 0;
$flipper = 'dl_flop';

while (false !== ($addon = $query->fetchRow(MYSQL_ASSOC))) {
  $project_link = 'https://addons.phpmanufaktur.de/de/name/'.strtolower($addon['repository_name']).'.php';
  $download_link = 'https://addons.phpmanufaktur.de/download.php?file='.$addon['repository_name'];
  $count = $addon['download_total'];
  $downloads_total += $count;
  if (isset($config['history'][$addon['repository_name']]))
    $count += $config['history'][$addon['repository_name']];
  $downloads_netto += $count;
  $download = number_format($count, 0, ',', '.');
  $size = bytes2Str($addon['download_file_size']);
  $date = date('d.m.y', strtotime($addon['download_file_date']));
  if (strtotime($addon['timestamp']) > $last_update)
    $last_update = strtotime($addon['timestamp']);
  $flipper = ($flipper == 'dl_flop') ? 'dl_flip' : 'dl_flop';
$rows .= <<<EOD
<tr class="$flipper">
  <td class="dl_project_link"><a href="$project_link">{$addon['repository_name']}</a></td>
  <td class="dl_addon_count">$download</td>
  <td class="dl_file_name"><a href="$download_link">{$addon['download_file_name']}</a></td>
  <td class="dl_file_date">$date</td>
</tr>
EOD;
} // while

$downloads_netto = number_format($downloads_netto, 0, ',', '.');

$downloads_total += array_sum($config['history']);
$downloads_total = number_format($downloads_total, 0, ',', '.');

$last_update = date('d.m.Y - H:i:s', $last_update);

$result = <<<EOD
<h1>Downloads</h1>
<p>Die aktuell verfügbaren ||Add-ons|| wurden bis jetzt <strong>$downloads_netto</strong> mal heruntergeladen <span class="dl_download_total">(seit 2009 insgesamt: $downloads_total Downloads)</span>.</p>
<p class="smaller">Die letzte Aktualisierung dieser Übersicht erfolgte am $last_update</p>
<p>&nbsp;</p>
<table id="dl_table">
  <tr>
    <th class="dl_project_link">Add-on</th>
    <th class="dl_addon_count">Downloads</th>
    <th class="dl_file_name">Installationsarchiv</th>
    <th class="dl_file_date">Stand</th>
  </tr>
  $rows
</table>
<div class="smaller">
  <p>&nbsp;</p>
  <p><strong>Hinweis für Webmaster:</strong><br />Wenn Sie auf Ihrer Seite einen Downloadlink für ein Addon der phpManufaktur setzen, verwenden Sie bitte ausschliesslich die auf dieser Seite verwendeten Downloadlinks. Dadurch stellen Sie sicher, dass Sie immer die aktuelleste Version des jeweiligen Add-on zum Download zur Verfügung stellen.</p>
</div>
EOD;

return $result;