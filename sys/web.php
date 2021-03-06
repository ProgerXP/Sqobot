<?php namespace Sqobot;

// used for chaining static method calls.
Web::$instance = new Web;

class Web {
  static $instance;   //= Web

  static $statuses =  array(
    200 => 'OK',  201 => 'Created', 202 => 'Accepted',

    301 => 'Moved Permanently', 302 => 'Found', 303 => 'See Other', 304 => 'Not Modified',
    307 => 'Temporary Redirect', 308 => 'Permanent Redirect',

    400 => 'Bad Request', 401 => 'Unauthorized', 403 => 'Forbidden',
    404 => 'Not Found', 405 => 'Method Not Allowed', 409 => 'Conflict',
    410 => 'Gone', 429 => 'Too Many Requests',

    500 => 'Internal Server Error', 501 => 'Not Implemented', 503 => 'Service Unavailable',
  );

  static $http11 = array(203, 303, 305, 307, 308);

  static $statusAliases = array(
    'ok' => 200, 'moved' => 301, 'temp' => 303, 'actual' => 304, 'malformed' => 400,
    'auth' => 401, 'deny' => 403, 'none' => 404, 'error' => 500,
  );

  //* $tasks array, str space-separated
  //= true if all given tasks are available
  static function canRun($tasks) {
    is_array($tasks) or $tasks = explode(' ', $tasks);
    $tasks = S($tasks, '"web".strtok(?, "-")');
    return static::can($tasks);
  }

  //* $perms array, str space-separated
  //= true if all given permissions are available
  static function can($perms) {
    is_array($perms) or $perms = explode(' ', $perms);

    if (($self = static::perms()) !== '*') {
      foreach ($perms as $perm) {
        if (strpos(" $self ", ' '.trim($perm).' ') === false) {
          return false;
        }
      }
    }

    return true;
  }

  static function isSuper() {
    return static::perms() === '*';
  }

  // Returns both default user perms and this user's if he's authorized.
  //= str 't-patch perm-1 ...'
  static function perms() {
    $perms = cfg('user '.static::user());

    if (!$perms or ($perms[0] !== '*' and !S::unprefix($perms, '='))) {
      $perms .= ' '.cfg('user');
    }

    return trim($perms);
  }

  //= null no HTTP authorization found, str 'usname'
  static function user() {
    return static::info('PHP_AUTH_USER');
  }

  static function info($name, $default = null) {
    return S::pickFlat($_SERVER, $name, $default);
  }

  static function sendStatus($code) {
    $code = static::codeBy($code);

    if ($code) {
      $version = in_array($code, static::$http11) ? '1.1' : '1.0';
      $status = "$code ".static::$statuses[$code];

      header("HTTP/$version $status");
      header("Status: $status");
    }

    return static::$instance;
  }

  //= int, null if couldn't find or not present in static::$statuses
  static function codeBy($code) {
    if (!is_numeric($code)) {
      $code = strtolower($code);
      $code = S::pickFlat(static::$statusAliases, $code);
    }

    if (!$code) {
      foreach (static::$statuses as $thisCode => $text) {
        if (strtolower($text) === $code) {
          $code = $thisCode;
          break;
        }
      }
    }

    return isset(static::$statuses[$code]) ? (int) $code : null;
  }

  static function sendType($mime, $charset = 'utf-8') {
    if (strtok($mime, '/') === 'text' and $charset) {
      $mime .= "; charset=$charset";
    }

    header("Content-Type: $mime");
    return static::$instance;
  }

  static function deny($log = '') {
    $log and warn("Denied access to web interface $log");
    $code = static::codeBy(cfg('webDenyAs')) ?: 403;

    if ($code == 403 and $log) {
      $log = 'More information has been written to Sqobot\'s log file.';
    } else {
      $log = null;
    }

    static::quitAs($code, $log);
  }

  static function quit($code, $messages = array()) {
    static::sendStatus($code)->quitAs($code, $messages);
  }

  static function quitAs($code, $messages = array()) {
    // might be called from within a task handler - clear its output.
    while (ob_get_level()) { ob_end_clean(); }

    $messages = S((array) $messages, function ($msg) {
      return ("$msg" !== '' and $msg[0] !== '<') ? HLEx::p_q($msg) : $msg;
    });

    static::sendType('text/html');

    echo '<center>',
         '<h1>', $code = (static::codeBy($code) ?: 500), '</h1>',
         '<h2>', static::$statuses[$code], '</h2>',
         join($messages),
         '</center>';

    exit($code);
  }

  //= bool
  static function https() {
    return strcasecmp(static::info('HTTPS'), 'on') === 0;
  }

  // Read input variable.
  static function get($var, $default = null) {
    return S::pickFlat($_POST, $var, function () use ($var, $default) {
      return S::pickFlat($_GET, $var, $default);
    });
  }

  //= bool
  static function is($var, $default = false) {
    return !!static::get($var, $default);
  }

  //= null no such upload, array, scalar if $var is like 'reqname.tmp_name'
  static function upload($var) {
    $file = &$_FILES[strtok($var, '.')];

    if ($file and !$file['error'] and is_uploaded_file($file['tmp_name'])) {
      $info = strtok(null);
      return $info ? $file[$info] : $file;
    }
  }

  static function cookie($name, $value = null) {
    if (func_num_args() < 2) {
      return S::pickFlat($_COOKIE, cfg('cookiePrefix').$name);
    } else {
      $expire = isset($value) ? time() + S::expire(cfg('cookieExpire')) : 1;

      $secure = cfg('cookieSecure');
      $secure === '' and $secure = static::https();

      setcookie(cfg('cookiePrefix').$name, $value, $expire, cfg('cookiePath'),
                cfg('cookieDomain'), !!$secure);

      return static::$instance;
    }
  }

  static function tasks() {
    return Task::all(true);
  }

  static function runNaked($task, &$title) {
    list($task, $method) = explode('-', strtolower("$task-"));
    $obj = Task::make("web$task");
    $output = $obj->capture($method, $_POST + $_GET);
    $title = $obj->title;
    return $output;
  }

  static function run($task, &$title, $prependTitle = false) {
    $output = static::runNaked($task, $title);

    if ($prependTitle and is_scalar($title)) {
      $output = HLEx::h2_q($title).$output;
    }

    return HLEx::div($output, "web-$task");
  }

  static function runTitled($task, &$title) {
    return static::run($task, $title, true);
  }

  static function taskFile($task) {
    $task = strtolower($task);

    if (ltrim($task, 'a..zA..Z0..9-_') !== '') {
      throw new ENoTask("Unsafe web task name: [$task].");
    }

    $file = USER."user/web-$task.php";
    is_file($file) or $file = USER."web/$task.php";
    return $file;
  }

  static function hasTask($task) {
    return !!Task::factory($task, false);
  }

  static function wrap($content, $title = null) {
    $mediaURL = S::lastPart(static::info('REQUEST_URI'), '/').'/';
    $title or $title = 'Sqobot Web - '.static::info('HTTP_HOST');

    $styles = S(cfgGroup('webStyle'), array(NS.'S.arrize', "href"));
    $scripts = S(cfgGroup('webScript'), array(NS.'S.arrize', "src"));

    $vars = compact('content', 'title', 'mediaURL', 'styles', 'scripts');
    return static::template('page', $vars);
  }

  static function template($_name, array $_vars) {
    extract($_vars, EXTR_SKIP);

    ob_start();
    include USER."web/$_name.html";
    return ob_get_clean();
  }

  static function redirect($url, $code = 'temp') {
    header('Location: '.$url);
    $link = HLEx::a_q('this page', $url);
    static::quit($code, HLEx::p("Please navigate to $link."));
  }
}