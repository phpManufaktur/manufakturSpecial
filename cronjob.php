<?php

// LEPTON config.php einbinden
require_once('../../../config.php');


require_once WB_PATH.'/templates/phpmanufaktur_2012/scripts/class.phpmanufaktur.php';

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
exit(sprintf('OK - %d repositories updated', $i));
