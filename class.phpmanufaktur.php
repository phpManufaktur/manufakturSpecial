<?php

// include class.secure.php to protect this file and the whole CMS!
if (defined('WB_PATH')) {
  include(WB_PATH.'/framework/class.secure.php');
} else {
  $oneback = "../";
  $root = $oneback;
  $level = 1;
  while (($level < 10) && (!file_exists($root.'/framework/class.secure.php'))) {
    $root .= $oneback;
    $level += 1;
  }
  if (file_exists($root.'/framework/class.secure.php')) {
    include($root.'/framework/class.secure.php');
  } else {
    trigger_error(sprintf("[ <b>%s</b> ] Can't include class.secure.php!", $_SERVER['SCRIPT_NAME']), E_USER_ERROR);
  }
}
// end include class.secure.php

if (!class_exists('Dwoo'))
  require_once WB_PATH.'/modules/dwoo/include.php';

// initialize the template engine
global $parser;

if (!is_object($parser)) {
  $cache_path = WB_PATH.'/temp/cache';
  if (!file_exists($cache_path)) mkdir($cache_path, 0755, true);
  $compiled_path = WB_PATH.'/temp/compiled';
  if (!file_exists($compiled_path)) mkdir($compiled_path, 0755, true);
  $parser = new Dwoo($compiled_path, $cache_path);
}

// need libSimplePie for RSS feeds
require_once WB_PATH.'/modules/lib_simplepie/SimplePie/SimplePie.compiled.php';


class phpManufakturService {

  protected static $template_path = null;
  protected static $rss_feed_support_group = null;
  protected static $repository = null;
  protected static $user = null;
  protected static $max_issues = 3;
  protected static $avatar_size = 50;
  protected static $avatar_small_size = 30;

  private static $error = '';

  public function __construct($repository) {
    self::$template_path = WB_PATH.'/modules/manufaktur_special/templates/frontend/';
    self::$rss_feed_support_group = 'http://groups.google.com/group/phpmanufaktur-support/feed/rss_v2_0_msgs.xml?num=3';
    self::$user = 'phpManufaktur';
    self::$repository = $repository;
    $this->checkTable();
  } // __construct()

  /**
   * Set the active repository
   *
   * @param string $repository
   */
  public function setRepository($repository) {
    self::$repository = $repository;
  } // setRepository()

  /**
   * Set self::$error to $error
   *
   * @param string $error
   */
  protected function setError($error) {
    self::$error = $error;
  } // setError()

  /**
   * Get Error from self::$error;
   *
   * @return string $this->error
   */
  public function getError() {
    return self::$error;
  } // getError()


  /**
   * Check if self::$error is empty
   *
   * @return boolean
   */
  public function isError() {
    return (bool) !empty(self::$error);
  } // isError

  /**
   * Get the template, set the data and return the compiled result
   *
   * @param string $template the name of the template
   * @param array $template_data
   * @param boolean $trigger_error raise a trigger error on problems
   * @return boolean|Ambigous <string, mixed>
   */
  protected function getTemplate($template, $template_data, $trigger_error=false) {
    global $parser;

    // check if a custom template exists ...
    $load_template = (file_exists(self::$template_path.'custom.'.$template)) ? self::$template_path.'custom.'.$template : self::$template_path.$template;
    try {
      $result = $parser->get($load_template, $template_data);
    }
    catch (Exception $e) {
      $this->setError(sprintf('[%s - %s] %s', __METHOD__, __LINE__,
          sprintf('Error executing the template <b>%s</b>: %s', basename($load_template), $e->getMessage())));
      if ($trigger_error)
        trigger_error($this->getError(), E_USER_ERROR);
      return false;
    }
    return $result;
  } // getTemplate()

  /**
   * GET command to Github
   *
   * @param string $get
   * @return mixed
   */
  protected function gitGet($get, $params=array()) {
    if (strpos($get, 'https://api.github.com') === 0)
      $command = $get;
    else
      $command = "https://api.github.com$get?callback=return";
    if (!empty($params)) {
      $command .= '&'.http_build_query($params);
    }
    $ch = curl_init($command);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    curl_close($ch);
    $matches = array();
    preg_match('/{(.*)}/', $result, $matches);
    return json_decode($matches[0], true);
  } // gitGet()

  /**
   * Get the last messages of the phpManufaktur Support Group
   *
   * @return string formatted result
   */
  protected function getSupportGroupLastMessages() {

    $feed = new SimplePie();
    $feed->set_feed_url(self::$rss_feed_support_group);
    $feed->set_cache_location(WB_PATH.'/temp');
    $feed->init();
    $feed->handle_content_type();

    $messages = array();

    if ($feed->data) {
      $items = $feed->get_items();
      foreach ($items as $item) {
        $messages[] = array(
            'subject' => $item->get_title(),
            'link' => $item->get_permalink(),
            'date' => $item->get_date('d.m.Y - H:i'),
            'message' => $item->get_content()
            );
      }
    }
    $data = array(
        'messages' => $messages
        );
    return $this->getTemplate('rss.support_group.lte', $data);
  } // getSupportGroupLastMessages()

  /**
   * Get the content of the CHANGELOG for the desired repository
   *
   * @return boolean|string
   */
  protected function getCHANGELOG() {
    $command = '/repos/'.self::$user.'/'.self::$repository.'/contents/CHANGELOG';
    $worker = $this->gitGet($command);
    if (!isset($worker['meta']['status'])) {
      $this->setError(sprintf('[%s - %s] %s', __METHOD__, __LINE__, 'Error connecting to GitHub!'));
      return false;
    }
    elseif ($worker['meta']['status'] != 200) {
      $this->setError(sprintf('[%s - %s] %s', __METHOD__, __LINE__, $worker['data']['message']));
      return false;
    }
    $changelog = base64_decode($worker['data']['content']);
    $use_markdown = false;
    if (file_exists(WB_PATH.'/modules/lib_markdown/standard/markdown.php')) {
      require_once(WB_PATH.'/modules/lib_markdown/standard/markdown.php');
      $use_markdown = true;
      $changelog = Markdown($changelog);
    }
    $data = array(
        'use_markdown' => $use_markdown,
        'changelog' => $changelog
        );
    return $this->getTemplate('changelog.lte', $data);
  } // getCHANGELOG

  /**
   * Get the content of the README.md file
   *
   * @return boolean|string
   */
  public function getREADME() {
    $command = '/repos/'.self::$user.'/'.self::$repository.'/contents/README.md';
    $worker = $this->gitGet($command);
    if (!isset($worker['meta']['status'])) {
      $this->setError(sprintf('[%s - %s] %s', __METHOD__, __LINE__, 'Error connecting to GitHub!'));
      return false;
    }
    elseif ($worker['meta']['status'] != 200) {
      // we dont want an error if missing the README.md
      return '<p>- no README.md for the repository '.self::$repository.' available -';
      /**
      $this->setError(sprintf('[%s - %s] %s', __METHOD__, __LINE__, $worker['data']['message']));
      return false;
      **/
    }
    $readme = base64_decode($worker['data']['content']);
    $use_markdown = false;
    if (file_exists(WB_PATH.'/modules/lib_markdown/standard/markdown.php')) {
      require_once(WB_PATH.'/modules/lib_markdown/standard/markdown.php');
      $use_markdown = true;
      $readme = Markdown($readme);
    }
    return $readme;
  } // getREADME()

  protected function getIssues() {
    $command = '/repos/'.self::$user.'/'.self::$repository.'/issues';
    $params = array(
        'state' => 'open',
        'sort' => 'created',
        'direction' => 'desc'
        );
    $worker = $this->gitGet($command, $params);
    if (!isset($worker['meta']['status'])) {
      $this->setError(sprintf('[%s - %s] %s', __METHOD__, __LINE__, 'Error connecting to GitHub!'));
      return false;
    }
    elseif ($worker['meta']['status'] != 200) {
      $this->setError(sprintf('[%s - %s] %s', __METHOD__, __LINE__, $worker['data']['message']));
      return false;
    }
    // check for markdown
    $use_markdown = false;
    if (file_exists(WB_PATH.'/modules/lib_markdown/standard/markdown.php')) {
      require_once(WB_PATH.'/modules/lib_markdown/standard/markdown.php');
      $use_markdown = true;
    }

    $issues = array();
    $i = 0;
    foreach ($worker['data'] as $issue) {
      $i++; if ($i > self::$max_issues) break;
      $comments = array();
      if ($issue['comments'] > 0) {
        // there exists comments, so we have to request them
        $command = '/repos/'.self::$user.'/'.self::$repository.'/issues/'.$issue['number'].'/comments';
        $comm = $this->gitGet($command);
        if (isset($comm['data'])) {
          foreach ($comm['data'] as $comment) {
            $comments[] = array(
                'id' => $comment['id'],
                'url' => $comment['url'],
                'body' => $use_markdown ? Markdown($comment['body']) : $comment['body'],
                'user' => array(
                    'login' => $comment['user']['login'],
                    'id' => $comment['user']['id'],
                    'url' => $comment['user']['url'],
                    'avatar' => array(
                        'id' => $comment['user']['gravatar_id'],
                        'url' => $comment['user']['avatar_url'],
                        'size' => self::$avatar_small_size
                        )
                    ),
                'created_at' => date('d.m.Y - H:i', strtotime($comment['created_at'])),
                'updated_at' => date('d.m.Y - H:i', strtotime($comment['updated_at'])),
                );
          }
        }
      }
      $issues[] = array(
          'use_markdown' => $use_markdown,
          'user' => array(
              'login' => $issue['user']['login'],
              'id' => $issue['user']['id'],
              'url' => $issue['user']['url'],
              'avatar' => array(
                  'id' => $issue['user']['gravatar_id'],
                  'url' => $issue['user']['avatar_url'],
                  'size' => self::$avatar_size
                  ),
              ),
          'url' => $issue['html_url'],
          'number' => $issue['number'],
          'title' => $issue['title'],
          'body' => $use_markdown ? Markdown($issue['body']) : $issue['body'],
          'state' => $issue['state'],
          'created_at' => date('d.m.Y - H:i', strtotime($issue['created_at'])),
          'updated_at' => date('d.m.Y - H:i', strtotime($issue['updated_at'])),
          'comments' => array(
              'count' => $issue['comments'],
              'comments' => $comments
              )
          );
    }
    $data = array(
        'issues_count' => count($worker['data']),
        'issues' => $issues
        );
    return $this->getTemplate('issues.lte', $data);
  } // getIssues()

  /**
   * Sanitize variables and prepare them for saving in a MySQL record
   *
   * @param mixed $item
   * @return mixed
   */
  protected static function sanitizeVariable($item) {
    if (!is_array($item)) {
      // undoing 'magic_quotes_gpc = On' directive
      if (get_magic_quotes_gpc())
        $item = stripcslashes($item);
      $item = self::sanitizeText($item);
    }
    return $item;
  } // sanitizeVariable()

  /**
   * Sanitize a text variable and prepare ist for saving in a MySQL record
   *
   * @param string $text
   * @return string
   */
  protected static function sanitizeText($text) {
    $text = str_replace(array("<",">","\"","'"), array("&lt;","&gt;","&quot;","&#039;"), $text);
    $text = mysql_real_escape_string($text);
    return $text;
  } // sanitizeText()

  /**
   * Unsanitize a text variable and prepare it for output
   *
   * @param string $text
   * @return string
   */
  protected static function unsanitizeText($text) {
    $text = stripcslashes($text);
    $text = str_replace(array("&lt;","&gt;","&quot;","&#039;"), array("<",">","\"","'"), $text);
    return $text;
  } // unsanitizeText()

  /**
   * Execute all requests (CHANGELOG, Issues, Mailinglist) and return the
   * compiled result template
   *
   * @return boolean|string
   */
  public function exec($overwrite=false, $quiet=false) {
    global $database;

    // check if there is an error
    if ($this->isError()) return false;

    if ($overwrite === false) {
      // read content from database
      $SQL = sprintf("SELECT `content` FROM `addons_tpl_manufaktur_service` WHERE `repository`='%s'",
          self::$repository);
      $content = $database->get_one($SQL, MYSQL_ASSOC);
      if ($database->is_error()) {
        $this->setError(sprintf('[%s - %s] %s', __METHOD__, __LINE__, $database->get_error()));
        return false;
      }
      if (!empty($content))
        return self::unsanitizeText($content);
    }

    // create content
    if (false === ($changelog = $this->getCHANGELOG())) {
      $this->setError(sprintf('[%s - %s] %s', __METHOD__, __LINE__, $this->getError()));
      return false;
    }
    if (false === ($issues = $this->getIssues())) {
      $this->setError(sprintf('[%s - %s] %s', __METHOD__, __LINE__, $this->getError()));
      return false;
    }
    if (false === ($messages = $this->getSupportGroupLastMessages())) {
      $this->setError(sprintf('[%s - %s] %s', __METHOD__, __LINE__, $this->getError()));
      return false;
    }

    $SQL = "SELECT `page_title` FROM `addons_pages` WHERE `link`='/de/name/".strtolower(self::$repository)."/support'";
    $page_title = $database->get_one($SQL, MYSQL_ASSOC);

    $data = array(
        'page_title' => $page_title,
        'repository' => self::$repository,
        'changelog' => $changelog,
        'issues' => $issues,
        'messages' => $messages,
        );
    $content = $this->getTemplate('service.lte', $data);
    if ($quiet) {
      if (!$this->saveContent($content)) {
        $this->setError(sprintf('[%s - %s] %s', __METHOD__, __LINE__, $database->get_error()));
        return false;
      }
      return true;
    }
    else
      return $content;
  } // exec()

  /**
   * Check if the table exists, otherwise create it
   *
   * @return boolean
   */
  protected function checkTable() {
    global $database;

    // check if table exists
    $SQL = "SHOW TABLES LIKE 'addons_tpl_manufaktur_service'";
    if (null == ($query = $database->query($SQL))) {
      $this->setError(sprintf('[%s - %s] %s', __METHOD__, __LINE__, $database->get_error()));
      return false;
    }
    if ($query->fetchRow(MYSQL_ASSOC) < 1) {
      $SQL = "CREATE TABLE IF NOT EXISTS `addons_tpl_manufaktur_service` ( ".
          "`id` INT(11) NOT NULL AUTO_INCREMENT, ".
          "`repository` VARCHAR(255) NOT NULL DEFAULT '', ".
          "`content` LONGTEXT NOT NULL, ".
          "`timestamp` TIMESTAMP, ".
          "PRIMARY KEY (`id`), ".
          "UNIQUE (`repository`) ".
          ") ENGINE=MyIsam AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci";
      if (null == $database->query($SQL)) {
        $this->setError(sprintf('[%s - %s] %s', __METHOD__, __LINE__, $database->get_error()));
        return false;
      }
    }
    return true;
  } // checkTable()

  /**
   * Save the content in the data table
   *
   * @param string $content
   * @return boolean
   */
  protected function saveContent($content) {
    global $database;

    $SQL = sprintf("INSERT INTO `addons_tpl_manufaktur_service` (`repository`,`content`) VALUES ('%s','%s') ON DUPLICATE KEY UPDATE `content`='%s'",
      self::$repository, self::sanitizeVariable($content), self::sanitizeVariable($content));

    if (null == $database->query($SQL)) {
      $this->setError(sprintf('[%s - %s] %s', __METHOD__, __LINE__, $database->get_error()));
      return false;
    }
    return true;
  } // saveContent()

} // class phpManufakturService
