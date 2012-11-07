<?php

set_time_limit(0);
ignore_user_abort(true);

// LEPTON config.php einbinden
require_once('../../config.php');


require_once WB_PATH.'/modules/manufaktur_special/class.documentation.php';

global $database;

$SQL = "SELECT `repository_name` FROM `".TABLE_PREFIX."mod_github_downloads` WHERE `download_active`='1'";

if (null == ($query = $database->query($SQL)))
  exit(sprintf('[%s] %s', __LINE__, $database->get_error()));

$intro = new phpManufakturDocumentation();

$result = file_get_contents(WB_PATH.'/modules/manufaktur_special/config.json');
$config = json_decode($result, true);

$i=0;
while (false !== ($repository = $query->fetchRow(MYSQL_ASSOC))) {
  /*
  $repos = strtolower($repository['repository_name']);

  $SQL = "SELECT MAX(`timestamp`) FROM `addons_tpl_manufaktur_article_cache` WHERE `repository`='$repos'";
  $ts = $database->get_one($SQL, MYSQL_ASSOC);
  if ($database->is_error())
    exit(sprintf('[%s] %s', __LINE__, $database->get_error()));

  $SQL = "SELECT MAX(`posted_modified`) FROM `addons_tpl_manufaktur_articles` WHERE `repository`='$repos'";
  $pm = $database->get_one($SQL, MYSQL_ASSOC);
  if ($database->is_error())
    exit(sprintf('[%s] %s', __LINE__, $database->get_error()));

  if (is_null($pm) && !is_null($ts)) {
    // it exists no article but the default cache with README.md is generated ...
    continue;
  }
  // check if there was something changed ...
  if (strtotime($ts) > $pm) {
    continue;
  }
*/
  $params = array(
      'repository' => strtolower($repository['repository_name']),
      'page' => 1,
      'page_id' => -1
      );
  $intro->setParams($params);
  if (!$intro->showIntro(false, $config['access_token_intro']))
    exit(sprintf('[%s] %s', __LINE__, $intro->getError()));
  $i++;
}

$SQL = "SELECT `id`,`page_id` FROM `addons_tpl_manufaktur_intro_cache`";
if (null == ($pages = $database->query($SQL)))
  exit(sprintf('[%s] %s', __LINE__, $database->get_error()));

while (false !== ($page = $pages->fetchRow(MYSQL_ASSOC))) {
  $SQL = "SELECT `link` FROM `addons_pages` WHERE `page_id`='{$page['page_id']}'";
  if (null == ($query = $database->query($SQL)))
    exit(sprintf('[%s] %s', __LINE__, $database->get_error()));
  if ($query->numRows() < 1) {
    // delete this entry from the intro cache!
    $SQL = "DELETE FROM `addons_tpl_manufaktur_intro_cache` WHERE `id`='{$page['id']}'";
    if (!$database->query($SQL))
      exit(sprintf('[%s] %s', __LINE__, $database->get_error()));
  }
}

exit(sprintf('OK - %d intros updated', $i));
