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

$i=0;
while (false !== ($repository = $query->fetchRow(MYSQL_ASSOC))) {
  $service->setRepository($repository['repository_name']);
  if (!$service->exec(true, true))
    exit(sprintf('[%s] %s', $repository['repository_name'], $service->getError()));
  $i++;
}

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
