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

require_once WB_PATH.'/modules/manufaktur_special/class.phpmanufaktur.php';
require_once WB_PATH.'/modules/droplets_extension/interface.php';


class phpManufakturDocumentation {

  protected static $template_path = null;
  protected static $repository = '';
  protected static $page = 1;
  protected static $image_URL = '';
  protected static $page_id = -1;

  private static $error = '';

  public function __construct() {
    self::$template_path = WB_PATH.'/modules/manufaktur_special/templates/frontend/';
    $this->checkTable();
    self::$image_URL = WB_URL.'/modules/manufaktur_special/images/';
  } // __construct()

  public function getParams() {
    return array(
        'repository' => self::$repository,
        'page' => self::$page,
        'page_id' => self::$page_id
        );
  } // getParams()

  public function setParams($params) {
    if (isset($params['repository'])) self::$repository = $params['repository'];
    if (isset($params['page'])) self::$page = $params['page'];
    if (isset($params['page_id'])) self::$page_id = $params['page_id'];
  } // setParams()

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
   * Check if the table exists, otherwise create it
   *
   * @return boolean
   */
  protected function checkTable() {
    global $database;

    // check if table exists
    $SQL = "SHOW TABLES LIKE 'addons_tpl_manufaktur_articles'";
    if (null == ($query = $database->query($SQL))) {
      $this->setError(sprintf('[%s - %s] %s', __METHOD__, __LINE__, $database->get_error()));
      return false;
    }
    if ($query->fetchRow(MYSQL_ASSOC) < 1) {
      $SQL = "CREATE TABLE IF NOT EXISTS `addons_tpl_manufaktur_articles` ( ".
          "`id` INT(11) NOT NULL AUTO_INCREMENT, ".
          "`repository` VARCHAR(255) NOT NULL DEFAULT '', ".
          "`page` INT(11) NOT NULL DEFAULT '1', ".
          "`topic_id` INT(11) NOT NULL DEFAULT '-1', ".
          "`published_when` INT(11) NOT NULL DEFAULT '-1', ".
          "`type` ENUM ('INTRO','ARTICLE','TIPP','VIDEO') DEFAULT 'ARTICLE', ".
          "`timestamp` TIMESTAMP, ".
          "PRIMARY KEY (`id`), ".
          "KEY (`repository`,`topic_id`) ".
          ") ENGINE=MyIsam AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci";
      if (null == $database->query($SQL)) {
        $this->setError(sprintf('[%s - %s] %s', __METHOD__, __LINE__, $database->get_error()));
        return false;
      }
    }

    // check if table exists
    $SQL = "SHOW TABLES LIKE 'addons_tpl_manufaktur_article_cache'";
    if (null == ($query = $database->query($SQL))) {
      $this->setError(sprintf('[%s - %s] %s', __METHOD__, __LINE__, $database->get_error()));
      return false;
    }
    if ($query->fetchRow(MYSQL_ASSOC) < 1) {
      $SQL = "CREATE TABLE IF NOT EXISTS `addons_tpl_manufaktur_article_cache` ( ".
          "`id` INT(11) NOT NULL AUTO_INCREMENT, ".
          "`repository` VARCHAR(255) NOT NULL DEFAULT '', ".
          "`page` INT(11) NOT NULL DEFAULT '1', ".
          "`page_id` INT(11) NOT NULL DEFAULT '-1', ".
          "`content` LONGTEXT NOT NULL, ".
          "`timestamp` TIMESTAMP, ".
          "PRIMARY KEY (`id`), ".
          "KEY (`repository`,`page`,`page_id`) ".
          ") ENGINE=MyIsam AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci";
      if (null == $database->query($SQL)) {
        $this->setError(sprintf('[%s - %s] %s', __METHOD__, __LINE__, $database->get_error()));
        return false;
      }
    }

    // check if table exists
    $SQL = "SHOW TABLES LIKE 'addons_tpl_manufaktur_intro_cache'";
    if (null == ($query = $database->query($SQL))) {
      $this->setError(sprintf('[%s - %s] %s', __METHOD__, __LINE__, $database->get_error()));
      return false;
    }
    if ($query->fetchRow(MYSQL_ASSOC) < 1) {
      $SQL = "CREATE TABLE IF NOT EXISTS `addons_tpl_manufaktur_intro_cache` ( ".
          "`id` INT(11) NOT NULL AUTO_INCREMENT, ".
          "`repository` VARCHAR(255) NOT NULL DEFAULT '', ".
          "`page_id` INT(11) NOT NULL DEFAULT '-1', ".
          "`content` LONGTEXT NOT NULL, ".
          "`timestamp` TIMESTAMP, ".
          "PRIMARY KEY (`id`), ".
          "KEY (`repository`,`page_id`) ".
          ") ENGINE=MyIsam AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci";
      if (null == $database->query($SQL)) {
        $this->setError(sprintf('[%s - %s] %s', __METHOD__, __LINE__, $database->get_error()));
        return false;
      }
    }
    return true;
  } // checkTable()

  /**
   * Save Article
   *
   * @param string $content
   * @return boolean
   */
  protected function saveArticle($repository, $topic_id, $type, $published_when, $page) {
    global $database;

    $SQL = sprintf("SELECT `id` FROM `addons_tpl_manufaktur_articles` WHERE `repository`='%s' AND `topic_id`='%d'",
        $repository, $topic_id);
    $id = $database->get_one($SQL, MYSQL_ASSOC);
    if ($database->is_error()) {
      $this->setError(sprintf('[%s - %s] %s', __METHOD__, __LINE__, $database->get_error()));
      return false;
    }
    if (intval($id) > 0) {
      // update existing record
      $SQL = sprintf("UPDATE `addons_tpl_manufaktur_articles` SET `type`='%s', `published_when`='%d', `page`='%d' WHERE `id`='%d'",
          $type, $published_when, $page, $id);
      if (!$database->query($SQL)) {
        $this->setError(sprintf('[%s - %s] %s', __METHOD__, __LINE__, $database->get_error()));
        return false;
      }
    }
    else {
      $SQL = sprintf("INSERT INTO `addons_tpl_manufaktur_articles` (`topic_id`,`repository`,`type`,`published_when`,`page`) VALUES ('%d','%s','%s','%d','%d')",
          $topic_id, $repository, $type, $published_when, $page);
      if (!$database->query($SQL)) {
        $this->setError(sprintf('[%s - %s] %s', __METHOD__, __LINE__, $database->get_error()));
        return false;
      }
    }
    return true;
  } // saveArticle()

  public function updateArticles() {
    global $database;

    // get the repositories
    $SQL = "SELECT `repository` FROM `addons_tpl_manufaktur_service`";
    if (null == ($query = $database->query($SQL))) {
      $this->setError(sprintf('[%s - %s] %s', __METHOD__, __LINE__, $database->get_error()));
      return false;
    }
    $repositories = array();
    while (false !== ($repository = $query->fetchRow(MYSQL_ASSOC)))
      $repositories[] = strtolower($repository['repository']);

    // get the topics
    $SQL = "SELECT `topic_id`,`keywords`,`published_when` FROM `blog_mod_topics` WHERE `active`='4' AND `section_id`>'0'";
    if (null == ($query = $database->query($SQL))) {
      $this->setError(sprintf('[%s - %s] %s', __METHOD__, __LINE__, $database->get_error()));
      return false;
    }
    $topics = array();
    // loop through the topics
    while (false !== ($topic = $query->fetchRow(MYSQL_ASSOC))) {
      // extract the keywords
      $kws = explode(',', $topic['keywords']);
      $keywords = array();
      // strtolower and trim
      foreach ($kws as $keyword)
        $keywords[] = strtolower(trim($keyword));
      // loop through the keywords
      foreach ($keywords as $keyword) {
        if (in_array($keyword, $repositories)) {
          // hit: save the topic for this repository
          $type = 'ARTICLE';
          // check for the type
          if (in_array('intro', $keywords))
            $type = 'INTRO';
          elseif (in_array('tipp', $keywords))
            $type = 'TIPP';
          elseif (in_array('video', $keywords))
            $type = 'VIDEO';
          $page = 1;
          for ($i=1; $i < 11; $i++) {
            if (in_array("page_$i", $keywords)) {
              $page = $i;
              break;
            }
          }
          if (!$this->saveArticle($keyword, $topic['topic_id'], $type, $topic['published_when'], $page)) {
            $this->setError(sprintf('[%s - %s] %s', __METHOD__, __LINE__, $database->get_error()));
            return false;
          }
        }
      }
    }
    return true;
  } // updateArticles()

  /**
   * This is very special: we need the TOPIC URL...
   *
   * we assume the following (sub)directory structure:
   * /www/phpmanufaktur.de = phpmanufaktur.de
   * /www/addons.phpmanufaktur.de = addons.phpmanufaktur.de
   * /www/blog.phpmanufaktur.de = blog.phpmanufaktur.de
   *
   * @param string $topic_link
   */
  protected function getTopicURL($topic_link) {
    $path = substr(WB_PATH, 0, strpos(WB_PATH, '/www/'));
    global $topics_directory;
    // we need the blog path!
    include $path.'/www/blog.phpmanufaktur.de/modules/topics/module_settings.php';
    // ... and the blog URL!
    $url = 'https://blog.phpmanufaktur.de' . $topics_directory . $topic_link . PAGE_EXTENSION;
    return $url;
  } // getTopicURL

  protected function getTopicImageURL($image) {
    return 'https://blog.phpmanufaktur.de/media/content/blog/topics/'.$image;
  } // getTopicImageURL()

  protected function getTopicArticle($id, &$topic) {
    global $database;

    $topic = array();
    $SQL = sprintf("SELECT `link`,`title`,`content_short`,`content_long`,`picture` FROM `blog_mod_topics` WHERE `topic_id`='%d' AND `active`='4'", $id);
    if (null == ($query = $database->query($SQL))) {
      $this->setError(sprintf('[%s - %s] %s', __METHOD__, __LINE__, $database->get_error()));
      return false;
    }
    if ($query->numRows() < 1) return false;
    $topic = $query->fetchRow(MYSQL_ASSOC);
    $topic['url'] = $this->getTopicURL($topic['link']);
    $topic['image'] = $this->getTopicImageURL($topic['picture']);
    return true;
  } // getTopicArticle()

  public function showArticles($use_cache=true) {
    global $database;

    if ($use_cache) {
      // get the complete content from the database if possible
      $SQL = sprintf("SELECT `content` FROM `addons_tpl_manufaktur_article_cache` WHERE `repository`='%s' AND `page`='%d'",
          strtolower(self::$repository), self::$page);
      $content = $database->get_one($SQL, MYSQL_ASSOC);
      if ($database->is_error()) {
        $this->setError(sprintf('[%s - %s] %s', __METHOD__, __LINE__, $database->get_error()));
        return false;
      }
      if (!empty($content)) {
        // don't use unsanitize here - this will disturb the formatted code examples in the articles!
        $content = stripcslashes($content);
        // return the complete content
        return $content;
      }
    }

    $content = array();
    if (self::$page == 1) {
      // first we select the INTRO
      $SQL = sprintf("SELECT `topic_id` FROM `addons_tpl_manufaktur_articles` WHERE `repository`='%s' AND `type`='INTRO'",
          strtolower(self::$repository));
      $id = $database->get_one($SQL, MYSQL_ASSOC);
      if ($database->is_error()) {
        $this->setError(sprintf('[%s - %s] %s', __METHOD__, __LINE__, $database->get_error()));
        return false;
      }

      if ($id > 0) {
        $topic = array();
        if (!$this->getTopicArticle($id, $topic)) {
          $this->setError(sprintf('[%s - %s] %s', __METHOD__, __LINE__, $this->getError()));
          return false;
        }
        $content[] = array(
            'id' => $id,
            'title' => $topic['title'],
            'teaser' => $topic['content_short'],
            'content' => $topic['content_long'],
            'url' => $topic['url'],
            'type' => 'INTRO',
            'image' => $topic['image']
            );
      }
      else {
        // missing intro - load the README.md from GitHub...
        $service = new phpManufakturService(self::$repository);
        if (false === ($readme = $service->getREADME())) {
          $readme = "<p>- no README.md available -</p>";
        }
        // important: we dont want to execute any droplet from README, so sanitize them !!!
        $readme = str_replace(array('[[',']]','||'), array('&#x005b;&#x005b;','&#x005d;&#x005d;','&#x007c;&#x007c;'), $readme);
        $content[] = array(
            'id' => -1,
            'title' => 'README (from GitHub)',
            'teaser' => $readme,
            'content' => $readme,
            'url' => 'https://github.com/phpmanufaktur/'.self::$repository.'#readme',
            'type' => 'INTRO',
            'image' => ''
        );
      }
    } // show_intro

    // now we select all other ARTICLES and TIPPS
    $SQL = sprintf("SELECT `topic_id`, `type`, `page` FROM `addons_tpl_manufaktur_articles` WHERE `repository`='%s' AND `type`!='INTRO' AND `page`='%d' ORDER BY `published_when` DESC",
        self::$repository, self::$page);
    if (null == ($query = $database->query($SQL))) {
      $this->setError(sprintf('[%s - %s] %s', __METHOD__, __LINE__, $this->getError()));
      return false;
    }
    while (false !== ($item = $query->fetchRow(MYSQL_ASSOC))) {
      $topic = array();
      if (!$this->getTopicArticle($item['topic_id'], $topic)) {
        $this->setError(sprintf('[%s - %s] %s', __METHOD__, __LINE__, $this->getError()));
        return false;
      }

      $content[] = array(
          'id' => $item['topic_id'],
          'title' => $topic['title'],
          'teaser' => $topic['content_short'],
          'content' => $topic['content_long'],
          'url' => $topic['url'],
          'type' => $item['type'],
          'image' => $topic['image']
      );
    }
    $SQL = "SELECT `page_title` FROM `addons_pages` WHERE `link`='/de/name/".strtolower(self::$repository)."/documentation'";
    $page_title = $database->get_one($SQL, MYSQL_ASSOC);
    $data = array(
        'repository' => self::$repository,
        'page_title' => $page_title,
        'image_url' => self::$image_URL,
        'articles' => array(
            'count' => count($content),
            'content' => $content
            )
        );
    $content = $this->getTemplate('articles.lte', $data);
    if ($use_cache) {
      // return the content
      return $content;
    }
    else {
      // save the content
      $SQL = sprintf("SELECT `id` FROM `addons_tpl_manufaktur_article_cache` WHERE `repository`='%s' AND `page`='%d'",
          strtolower(self::$repository), self::$page);
      $id = $database->get_one($SQL, MYSQL_ASSOC);
      if ($database->is_error()) {
        $this->setError(sprintf('[%s - %s] %s', __METHOD__, __LINE__, $database->get_error()));
        return false;
      }
      if (self::$page_id < 1) {
        $SQL = "SELECT `page_id` FROM `addons_pages` WHERE `link`='/de/name/".strtolower(self::$repository)."/documentation'";
        self::$page_id = $database->get_one($SQL, MYSQL_ASSOC);
      }
      if ($id > 0) {
        // don't use sanitize here - this will disturb the formatted code examples! Use mysql_real_escape_string()!
        $SQL = sprintf("UPDATE `addons_tpl_manufaktur_article_cache` SET `content`='%s',`page_id`='%d' WHERE `id`='%d'",
            mysql_real_escape_string($content), self::$page_id, $id);
        // update the article cache
        if (!$database->query($SQL)) {
          $this->setError(sprintf('[%s - %s] %s', __METHOD__, __LINE__, $database->get_error()));
          return false;
        }
      }
      else {
        // don't use sanitize here - this will disturb the formatted code examples! Use mysql_real_escape_string()!
        $SQL = sprintf("INSERT INTO `addons_tpl_manufaktur_article_cache` (`repository`,`page`,`content`,`page_id`) VALUES ('%s','%d','%s','%d')",
            strtolower(self::$repository), self::$page, mysql_real_escape_string($content), self::$page_id);
        // add a new article cache
        if (!$database->query($SQL)) {
          $this->setError(sprintf('[%s - %s] %s', __METHOD__, __LINE__, $database->get_error()));
          return false;
        }
      }
      // register the droplet for the search!
      if (!is_registered_droplet_search('addons_articles', self::$page_id)) {
        register_droplet_search('addons_articles', self::$page_id, 'manufaktur_special');
      }
    }
    return true;
  } // showArticles

  public function showIntro($use_cache=true) {
    global $database;

    if ($use_cache) {
      // get the complete content from the database if possible
      $SQL = sprintf("SELECT `content` FROM `addons_tpl_manufaktur_intro_cache` WHERE `repository`='%s'",
          strtolower(self::$repository));
      $content = $database->get_one($SQL, MYSQL_ASSOC);
      if ($database->is_error()) {
        $this->setError(sprintf('[%s - %s] %s', __METHOD__, __LINE__, $database->get_error()));
        return false;
      }
      if (!empty($content)) {
        // don't use unsanitize here - this will disturb the formatted code examples in the articles!
        $content = stripcslashes($content);
        // return the complete content
        return $content;
      }
    }

    $SQL = sprintf("SELECT `topic_id` FROM `addons_tpl_manufaktur_articles` WHERE `repository`='%s' AND `type`='INTRO'",
        strtolower(self::$repository));
    $id = $database->get_one($SQL, MYSQL_ASSOC);
    if ($database->is_error()) {
      $this->setError(sprintf('[%s - %s] %s', __METHOD__, __LINE__, $database->get_error()));
      return false;
    }

    if ($id > 0) {
      $topic = array();
      if (!$this->getTopicArticle($id, $topic)) {
        $this->setError(sprintf('[%s - %s] %s', __METHOD__, __LINE__, $this->getError()));
        return false;
      }
      $content = array(
          'id' => $id,
          'title' => $topic['title'],
          'content' => $topic['content_short'],
          'url' => $topic['url'],
          'type' => 'INTRO',
          'image' => $topic['image']
      );
    }
    else {
      // missing intro - load the README.md from GitHub...
      $service = new phpManufakturService(self::$repository);
      if (false === ($readme = $service->getREADME())) {
        $readme = "<p>- no README.md available -</p>";
      }
      // important: we dont want to execute any droplet or glossary item from README, so sanitize them !!!
      $readme = str_replace(array('[[',']]','||'), array('&#x005b;&#x005b;','&#x005d;&#x005d;','&#x007c;&#x007c;'), $readme);
      $content = array(
          'id' => -1,
          'title' => 'README (from GitHub)',
          'content' => $readme,
          'url' => 'https://github.com/phpmanufaktur/'.self::$repository.'#readme',
          'type' => 'INTRO',
          'image' => ''
      );
    }
    // get the page title
    $SQL = "SELECT `page_title` FROM `addons_pages` WHERE `link`='/de/name/".strtolower(self::$repository)."/about'";
    $page_title = $database->get_one($SQL, MYSQL_ASSOC);

    $data = array(
        'repository' => self::$repository,
        'page_title' => $page_title,
        'image_url' => self::$image_URL,
        'article' => $content
        );
    $content = $this->getTemplate('intro.lte', $data);
    if ($use_cache) {
      // return the content
      return $content;
    }
    else {
      // save the content
      $SQL = sprintf("SELECT `id` FROM `addons_tpl_manufaktur_intro_cache` WHERE `repository`='%s'",
          strtolower(self::$repository));
      $id = $database->get_one($SQL, MYSQL_ASSOC);
      if ($database->is_error()) {
        $this->setError(sprintf('[%s - %s] %s', __METHOD__, __LINE__, $database->get_error()));
        return false;
      }
      if (self::$page_id < 1) {
        $SQL = "SELECT `page_id` FROM `addons_pages` WHERE `link`='/de/name/".strtolower(self::$repository)."/about'";
        self::$page_id = $database->get_one($SQL, MYSQL_ASSOC);
      }
      if ($id > 0) {
        // don't use sanitize here - this will disturb the formatted code examples! Use mysql_real_escape_string()!
        $SQL = sprintf("UPDATE `addons_tpl_manufaktur_intro_cache` SET `content`='%s',`page_id`='%d' WHERE `id`='%d'",
            mysql_real_escape_string($content), self::$page_id, $id);
        // update the article cache
        if (!$database->query($SQL)) {
          $this->setError(sprintf('[%s - %s] %s', __METHOD__, __LINE__, $database->get_error()));
          return false;
        }
      }
      else {
        // don't use sanitize here - this will disturb the formatted code examples! Use mysql_real_escape_string()!
        $SQL = sprintf("INSERT INTO `addons_tpl_manufaktur_intro_cache` (`repository`,`page_id`,`content`) VALUES ('%s','%d','%s')",
            strtolower(self::$repository), self::$page_id, mysql_real_escape_string($content));
        // add a new article cache
        if (!$database->query($SQL)) {
          $this->setError(sprintf('[%s - %s] %s', __METHOD__, __LINE__, $database->get_error()));
          return false;
        }
      }
      // register the droplet for the search!
      if (!is_registered_droplet_search('addons_intro', self::$page_id)) {
        register_droplet_search('addons_intro', self::$page_id, 'manufaktur_special');
      }
    }
    return true;
  } // showIntro()

} // class phpManufakturService
