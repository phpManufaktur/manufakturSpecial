<?php

set_time_limit(0);
ignore_user_abort(true);

// LEPTON config.php einbinden
require_once('../../config.php');


require_once WB_PATH.'/modules/manufaktur_special/class.phpmanufaktur.php';

global $database;

$SQL = "SELECT `repository_name` FROM `".TABLE_PREFIX."mod_github_downloads` WHERE `download_active`='1'";

if (null == ($query = $database->query($SQL)))
  exit(sprintf('[%s] %s', __LINE__, $database->get_error()));

$service = new phpManufakturService('');

if (isset($service::$config['X-RateLimit-Remaining']) && ($service::$config['X-RateLimit-Remaining'] < $service::$x_ratelimit_min)) {
  $le = strtotime($service::$config['last_execution']);
  if (mktime(date('H', $le), date('i', $le)+61, date('s', $le), date('m', $le), date('d', $le), date('Y', $le)) > time())
    exit('Not executed, waiting for fresh X-RateLimit');
}

// set the authentication for the DEFAULT
$service::$access_token = $service::$config['access_token_default'];

$i=0;
$first_addon = '';
$step = (isset($service::$config['last_addon'])) ? $service::$config['last_addon'] : '';


while (false !== ($repository = $query->fetchRow(MYSQL_ASSOC))) {
  if (empty($first_addon)) $first_addon = $repository['repository_name'];
  if (!empty($step) && ($step != $first_addon) && ($step != $repository['repository_name']))
    continue;
  $step = '';
  $service->setRepository($repository['repository_name']);
  if (!$service->exec(true, true))
    exit(sprintf('[%s] %s', $repository['repository_name'], $service->getError()));
  $i++;
}
// if we walk through the complete loop set the first addon for the next call!
$service::$config['last_addon'] = $first_addon;
$service->writeConfiguration($service::$config);

$SQL = "SELECT `id`,`page_id` FROM `addons_tpl_manufaktur_service`";
if (null == ($pages = $database->query($SQL)))
  exit(sprintf('[%s] %s', __LINE__, $database->get_error()));

while (false !== ($page = $pages->fetchRow(MYSQL_ASSOC))) {
  $SQL = "SELECT `link` FROM `addons_pages` WHERE `page_id`='{$page['page_id']}'";
  if (null == ($query = $database->query($SQL)))
    exit(sprintf('[%s] %s', __LINE__, $database->get_error()));
  if ($query->numRows() < 1) {
    // delete this entry from the service cache!
    $SQL = "DELETE FROM `addons_tpl_manufaktur_service` WHERE `id`='{$page['id']}'";
    if (!$database->query($SQL))
      exit(sprintf('[%s] %s', __LINE__, $database->get_error()));
  }
}

exit(sprintf('OK - %d repositories updated', $i));
