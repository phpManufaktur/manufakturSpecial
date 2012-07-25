<?php

// LEPTON config.php einbinden
require_once('../../../config.php');


require_once WB_PATH.'/templates/phpmanufaktur_2012/scripts/class.documentation.php';

global $database;

$doc = new phpManufakturDocumentation();

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
        'page' => $page
        );
    $doc->setParams($params);
    if (!$doc->showArticles(false))
      exit(sprintf('[%s] %s', __LINE__, $doc->getError()));
    $i++;
  }
}

exit("OK - $i Artikelseiten aktualisiert.");