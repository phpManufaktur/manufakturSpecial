<?php

/**
 * manufakturSpecial
 *
 * @author Ralf Hertsch <ralf.hertsch@phpmanufaktur.de>
 * @link https://phpmanufaktur.de
 * @copyright 2012
 * @license ONLY FOR USE AT PHPMANUFAKTUR.DE - ANY OTHER USE IS FORBIDDEN!
 */

// include class.secure.php to protect this file and the whole CMS!
if (defined('WB_PATH')) {
  if (defined('LEPTON_VERSION'))
    include(WB_PATH.'/framework/class.secure.php');
}
else {
  $oneback = "../";
  $root = $oneback;
  $level = 1;
  while (($level < 10) && (!file_exists($root.'/framework/class.secure.php'))) {
    $root .= $oneback;
    $level += 1;
  }
  if (file_exists($root.'/framework/class.secure.php')) {
    include($root.'/framework/class.secure.php');
  }
  else {
    trigger_error(sprintf("[ <b>%s</b> ] Can't include class.secure.php!", $_SERVER['SCRIPT_NAME']), E_USER_ERROR);
  }
}
// end include class.secure.php

if (!defined('LEPTON_PATH'))
  require_once WB_PATH.'/modules/'.basename(dirname(__FILE__)).'/wb2lepton.php';

require_once LEPTON_PATH.'/modules/droplets_extension/interface.php';
require_once LEPTON_PATH.'/modules/'.basename(dirname(__FILE__)).'/class.documentation.php';
require_once LEPTON_PATH.'/modules/'.basename(dirname(__FILE__)).'/class.phpmanufaktur.php';

/**
 * Unsanitize a text variable and prepare it for output
 *
 * @param string $text
 * @return string
 */
if (!function_exists('unsanitizeText')) {
  function unsanitizeText($text) {
    $text = stripcslashes($text);
    $text = str_replace(array("&lt;","&gt;","&quot;","&#039;"), array("<",">","\"","'"), $text);
    return $text;
  } // unsanitizeText()
}

if (! function_exists('manufaktur_special_droplet_search')) {
  function manufaktur_special_droplet_search($page_id, $page_url) {
    global $database;
    if (droplet_exists('addons_intro', $page_id)) {
      $table = 'addons_tpl_manufaktur_intro_cache';
    }
    elseif (droplet_exists('addons_articles', $page_id)) {
      $table = 'addons_tpl_manufaktur_article_cache';
    }
    elseif (droplet_exists('addons_service_page', $page_id)) {
      $table = 'addons_tpl_manufaktur_service';
    }
    else {
      // no droplet found!
      return false;
    }

    // get the page informations
    $SQL = "SELECT `page_title`, `description` FROM `addons_pages` WHERE `page_id`='$page_id'";
    if (null == ($query = $database->query($SQL)))
      trigger_error(sprintf('[%s - %s] %s', __FUNCTION__, __LINE__, $database->get_error()), E_USER_ERROR);
    $page = $query->fetchRow(MYSQL_ASSOC);

    $SQL = "SELECT `content`,`timestamp` FROM `$table` WHERE `page_id`='$page_id'";
    if (null == ($query = $database->query($SQL)))
      trigger_error(sprintf('[%s - %s] %s', __FUNCTION__, __LINE__, $database->get_error()), E_USER_ERROR);
    // no result - return false!
    if ($query->numRows() < 1) return false;
    $intro = $query->fetchRow(MYSQL_ASSOC);
    if ($table == 'addons_tpl_manufaktur_service') {
      $text = strip_tags(unsanitizeText($intro['content']));
    }
    else {
      $text = strip_tags(stripcslashes($intro['content']));
    }
    $text = str_replace('||', '', $text);
    // create the result array
    $result = array();
    $result[] = array(
        'url'           => $page_url,
        'params'        => '',
        'title'         => $page['page_title'],
        'description'   => $page['description'],
        'text'          => $text,
        'modified_when' => strtotime($intro['timestamp']),
        'modified_by'   => 1 // return always the admin account
    );
    return $result;
  } // manufaktur_special_droplet_search()
}