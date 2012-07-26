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
require_once WB_PATH.'/modules/'.basename(dirname(__FILE__)).'/class.documentation.php';
require_once WB_PATH.'/modules/'.basename(dirname(__FILE__)).'/class.phpmanufaktur.php';

/**
 * LEPTON 2.x search function for registered DropLEPs
 * This function is called by the LEPTON Search library - please consult the
 * LEPTON documentation for further informations!
 *
 * @param array $func_vars - parameters for the search
 * @return boolean true on success
 */
function manufaktur_special_search($search) {
  global $database;
  // we have to check for 3 droplets:
echo "hier<br>";
  if (droplet_exists('addons_intro', $search['page_id'])) {
    $SQL = "SELECT `content` FROM `addons_tpl_manufaktur_intro_cache` WHERE `page_id`='{$search['page_id']}'";
    $content = $database->get_one($SQL, MYSQL_ASSOC);
echo "$content<br>";
    if (!empty($content)) {
      $result = array(
          'page_link' => $search['page_link'],
          'page_link_target' => SEC_ANCHOR.$search['section_id'],
          'page_title' => $search['page_title'],
          'page_description' => $search['page_description'],
          'page_modified_when' => $search['page_modified_when'],
          'page_modified_by' => $search['page_modified_by'],
          'text' => stripcslashes($content).'.',
          'max_exerpt_num' => $search['default_max_excerpt']
      );
      if (print_excerpt2($result, $search))
        return true;
    }
  }
  // no search result - return false!
  return false;
} // manufaktur_special_search()
