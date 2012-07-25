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

$module_directory = 'manufaktur_special';
$module_name = 'manufakturSpecial';
$module_function = 'library';
$module_version = '0.10';
$module_status = 'Beta';
$module_platform = '2.8';
$module_author = 'Ralf Hertsch, Berlin (Germany)';
$module_license = 'ONLY FOR USE AT PHPMANUFAKTUR.DE - ANY OTHER USE IS FORBIDDEN!';
$module_description = 'extends the template functions for phpmanufaktur.de';
$module_home = 'https://addons.phpmanufaktur.de';
$module_guid = 'D7AF261D-6275-4A35-BA7C-509154F74681';
