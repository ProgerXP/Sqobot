<?php namespace Sqobot;

use Exception;

class Error extends Exception {
  public $object;

  static function re(Exception $previous, $append = '') {
    $msg = $previous->getMessage();
    $append and $msg = "$append\n$msg";
    $object = isset($previous->object) ? $previous->object : null;
    throw new static($object, $msg, $previous);
  }

  function __construct($object, $msg, Exception $previous = null) {
    $this->object = $object;
    parent::__construct($msg, 0, $previous);
  }
}

class ENoTask extends Error { }
class ETaskError extends Error { }

class Core {
  //= hash of mixed
  static $config = array();
  //= null, PDO
  static $pdo;
  //= null for web, array of array 'values', 'index', 'flags'
  static $cl;
  //= array of callable
  static $onFatal;

  static function loadConfig($file) {
    if (is_file($file) and $data = file_get_contents($file)) {
      static::$config = static::parseExtConf($data) + static::$config;
    }
  }

  // From http://proger.i-forge.net/Various_format_parsing_functions_for_PHP/ein.
  //* $str string to parse
  //* $prefix string to prepend for every key in the returned array
  //* $unescape bool - if set \XX sequences will be converted to characters in value
  //= array of 'key' => 'value' pairs
  static function parseExtConf($str, $prefix = '', $unescape = false) {
    $result = array();

    $block = null;
    $value = '';

    foreach (explode("\n", $str) as $line) {
      if ($block === null) {
        $line = trim($line);

        if ($line !== '' and strpbrk($line[0], '#;') === false) {
          @list($key, $value) = explode('=', $line, 2);

          $key = rtrim($key);
          $value = ltrim($value);

          if ($value === '{') {
            $block = $key;
            $value = '';
          } elseif (isset($value)) {
            $result[$prefix.$key] = $unescape ? stripcslashes($value) : $value;
          }
        }
      } elseif ($line === '}') {
        $result[$prefix.$block] = (string) substr($value, 0, -1);
        $block = null;
      } else {
        $value .= rtrim($line)."\n";
      }
    }

    return $result;
  }
}

function dd() {
  func_num_args() or print str_repeat('-', 79).PHP_EOL;
  foreach (func_get_args() as $arg) { var_dump($arg); }
  echo PHP_EOL, PHP_EOL;
  debug_print_backtrace();
  exit(1);
}

function cfg($name, $wrap = null) {
  $value = S::pickFlat(Core::$config, $name);
  if ($value === '' or !isset($wrap)) {
    return $value;
  } else {
    return str_replace('$', $value, $wrap);
  }
}

function opt($name, $default = null) {
  $group = is_int($name) ? 'index' : 'options';

  if (isset(Core::$cl[$group][$name])) {
    return Core::$cl[$group][$name];
  } else {
    return S::unclosure($default);
  }
}

function log($msg, $level = 'info') {
  if (strpos(cfg('log', ' $ '), " $level ") !== false and
      $log = strftime( opt('log', cfg('logFile')) )) {
    $msg = sprintf('$ %s [%s] [%s] %s', strtoupper($level), strftime('%H-%M %Y-%m-%d'),
                   Core::$cl ? 'cli' : S::pickFlat($_REQUEST, 'REMOTE_ADDR'), $msg);

    S::mkdirOf($log);
    touch($log);
    file_put_contents($log, "$msg\n\n", FILE_APPEND);
  }
}

function warn($msg) {
  log($msg, 'warn');
}

function error($msg) {
  log($msg, 'error');
}

function exLine(Exception $e) {
  return sprintf('%s (in %s:%d)', $e->getMessage(), $e->getFile(), $e->getLine());
}

function db() {
  if (!Core::$pdo) {
    $pdo = Core::$pdo = new \PDO(cfg('dbDSN'), cfg('dbUser'), cfg('dbPassword'));

    $charset = cfg('dbConCharset') and $pdo->exec('SET NAMES '.$charset);
    $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(\PDO::ATTR_AUTOCOMMIT, true);
    $pdo->setAttribute(\PDO::ATTR_CASE, \PDO::CASE_NATURAL);
    $pdo->setAttribute(\PDO::ATTR_ORACLE_NULLS, \PDO::NULL_NATURAL);
    $pdo->setAttribute(\PDO::ATTR_STRINGIFY_FETCHES, false);
  }

  return Core::$pdo;
}

function dbImport($sqls) {
  is_array($sqls) or $sqls = explode(';', $sqls);

  $sum = 0;
  foreach ($sqls as $sql) { $sql and $sum += db()->exec($sql); }
  return $sum;
}

function atomic($func) {
  db()->beginTransaction();
  return rescue(
    function () use ($func) {
      $result = call_user_func($func);
      db()->commit();
      return $result;
    },
    function () {
      db()->rollBack();
    }
  );
}

function prep($sql, $options = array()) {
  return db()->prepare($sql, $options);
}

function onFatal($func, $name = null) {
  if (!$name) {
    $name = count(Core::$onFatal);
    while (isset(Core::$onFatal[$name])) { ++$name; }
  }

  Core::$onFatal[$name] = $func;
  return $name;
}

function offFatal($func) {
  if (is_scalar($func)) {
    unset(Core::$onFatal[$func]);
  } else {
    foreach (Core::$onFatal as $key => $item) {
      if ($item === $func) { unset(Core::$onFatal[$key]); }
    }
  }
}

function rescue($body, $error, $finally = null) {
  $id = onFatal($catch);

  $catch = function ($e) use ($id, $error, $finally) {
    offFatal($id);
    $finally and call_user_func($finally, $e);
    $error and call_user_func($error, $e);
  };

  try {
    $result = call_user_func($body);
    offFatal($id);
    $finally and call_user_func($finally);
    return $result;
  } catch (Exception $e) {
    $catch($e);
    throw $e;
  }
}
