<?php

set_time_limit(0);
ignore_user_abort(true);

// LEPTON config.php einbinden
require_once('../../config.php');


require_once WB_PATH.'/modules/manufaktur_special/class.documentation.php';

global $database;

$doc = new phpManufakturDocumentation();

$result = file_get_contents(WB_PATH.'/modules/manufaktur_special/config.json');
$config = json_decode($result, true);

// in the first step we update all article informations
if (!$doc->updateArticles()) {
  exit($doc->getError());
}

// now we check if there are topics articles to remove!
$SQL = "SELECT `id`, `topic_id` FROM `addons_tpl_manufaktur_articles`";
if (null == ($query = $database->query($SQL)))
  exit(sprintf('[%s] %s', __LINE__, $database->get_error()));
$i=0;
while (false !== ($id = $query->fetchRow(MYSQL_ASSOC))) {
  $SQL = "SELECT `section_id` FROM `blog_mod_topics` WHERE `topic_id`='{$id['topic_id']}'";
  $section_id = $database->get_one($SQL, MYSQL_ASSOC);
  if ($database->is_error())
    exit(sprintf('[%s] %s', __LINE__, $database->get_error()));
  if ($section_id < 0) {
    // ok - delete this article!
    $SQL = "DELETE FROM `addons_tpl_manufaktur_articles` WHERE `id`='{$id['id']}'";
    if (!$database->query($SQL))
      exit(sprintf('[%s] %s', __LINE__, $database->get_error()));
    $i++;
  }
}
if ($i > 0)
  echo "Es wurden $i Artikel gelöscht, die nicht mehr in TOPICS existieren.<br>";

// get all active repositories
$SQL = "SELECT `repository_name` FROM `addons_mod_github_downloads` WHERE `download_active`='1'";

if (null == ($rp = $database->query($SQL)))
  exit(sprintf('[%s] %s', __LINE__, $database->get_error()));
// loop through the repositories
$i=0;

while (false !== ($repos = $rp->fetchRow(MYSQL_ASSOC))) {
  $repository = strtolower($repos['repository_name']);
  /*
  $SQL = "SELECT MAX(`timestamp`) FROM `addons_tpl_manufaktur_article_cache` WHERE `repository`='$repository'";
  $ts = $database->get_one($SQL, MYSQL_ASSOC);
  if ($database->is_error())
    exit(sprintf('[%s] %s', __LINE__, $database->get_error()));

  $SQL = "SELECT MAX(`posted_modified`) FROM `addons_tpl_manufaktur_articles` WHERE `repository`='$repository'";
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
  // check if there exists multiple pages for this repository
  $SQL = "SELECT DISTINCT `page` FROM `addons_tpl_manufaktur_articles` WHERE `repository`='".$repository."'";
  if (null == ($query = $database->query($SQL))) {
    exit(sprintf('[%s] %s', __LINE__, $database->get_error()));
  }
  $pages = array();
  if ($query->numRows() > 0)
    while (false !== ($page = $query->fetchRow(MYSQL_ASSOC))) $pages[] = $page['page'];
  else
    $pages[] = 1;
  // now we loop through the pages
  foreach ($pages as $page) {
    $params = array(
        'repository' => $repository,
        'page' => $page,
        'page_id' => -1
        );
    $doc->setParams($params);
    if (!$doc->showArticles(false, $config['access_token_documentation']))
      exit(sprintf('[%s] %s', __LINE__, $doc->getError()));
    $i++;
  }
}

exit("OK - $i Artikelseiten aktualisiert.");