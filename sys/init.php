<?php namespace Sqobot;

define(__NAMESPACE__.'\\NS', __NAMESPACE__.'\\');
defined(NS.'ROOT') or define(NS.'ROOT', dirname(__DIR__).'/');
defined(NS.'USER') or define(NS.'USER', ROOT);
define(NS.'VERSION', '1.0a');
define(NS.'HOMEPAGE', 'https://github.com/ProgerXP/Sqobot');

error_reporting(-1);
ini_set('display_errors', true);
ini_set('html_errors', false);    // we escape messages on our own when necessary.
function_exists('mb_internal_encoding') and mb_internal_encoding('utf-8');
ignore_user_abort(true);
set_time_limit(24 * 3600);

require_once ROOT.'sys/core.php';
require_once ROOT.'lib/squall.php';
\Squall\initEx(NS);

spl_autoload_register(function ($class) {
  if (strrchr($class, '.') !== false) {
    error("Unsafe class name [$class] to autoload - skipping.");
    return;
  }

  if (substr($class, 0, strlen(NS)) === NS) {
    $short = substr($class, 7);
    $lower = strtolower($short);

    $files = array(
      USER."user/$short.php",
      USER."user/$lower.php",
      ROOT."sys/$lower.php",
    );

    if (substr($short, 0, 4) === 'Task') {
      $files[] = ROOT.'task/'.substr($lower, 4).'.php';
    }
  } else {
    $lower = strtolower($class);

    $files = array(
      ROOT."lib/$class.php",
      ROOT."lib/$lower.php",
      USER."user/$class.php",
      USER."user/$lower.php",
    );
  }

  if ($path = cfg("class $class")) {
    if (substr($path, -4) === '.php') {
      $path[0] === '$' and $path = ROOT.'lib/'.substr($path, 1);
      array_unshift($files, $path);
    } else {
      return class_alias($path, $class);
    }
  }

  foreach ($files as $file) {
    if (is_file($file)) {
      include_once $file;
      return class_exists($class, false) and fire("class $class", $file);
    }
  }

  warn("Cannot autoload class [$class] from either of these paths:".
       join("\n  ", S::prepend($files, '')));
});

hook('class MiMeil', function () {
  \MiMeil::$onEvent = function ($event, $args) {
    return fire("mail $event", $args);
  };

  \MiMeil::RegisterEventsUsing(function ($event, $callback) {
    hook("mail $event", $callback);
  });
});

register_shutdown_function(function () {
  $chdir = opt('chdir', ROOT) and chdir($chdir);

  $error = error_get_last();
  $ignore = E_WARNING | E_NOTICE | E_USER_WARNING | E_USER_NOTICE | E_STRICT |
            E_DEPRECATED | E_USER_DEPRECATED;

  if ($error and ($error['type'] & $ignore) == 0) {
    $e = new \ErrorException($error['message'], 0, $error['type'],
                             $error['file'], $error['line']);

    foreach (array_reverse((array) Core::$onFatal) as $func) {
      try {
        call_user_func($func, $e);
      } catch (\Exception $e) {
        // ignoring all uncaught exceptions that would otherwise termiante the script.
        error('Exception in onFatal() handler: '.exLine($e));
      }
    }
  }
});

set_error_handler(function ($severity, $msg, $file, $line) {
  throw new \ErrorException($msg, 0, $severity, $file, $line);
}, -1);

onFatal(function ($e) {
  log('Terminated with error: '.exLine($e), 'fatal');
});

Core::loadConfig(ROOT.'default.conf');

if (Core::cli() and isset($argv)) {
  array_shift($argv);   // script.php
  reset($argv) === '--' and array_shift($argv);
  Core::$cl = S::parseCL($argv, true);
}

$chdir = opt('chdir', USER) and chdir($chdir);
$delay = opt('delay') and usleep(1000 * ($delay + $delay / 3));

Core::loadConfig('default.conf');

foreach ((array) opt('config', 'main') as $file) {
  Core::loadConfig($file.'.conf');
}

foreach ((array) opt('cfg') as $config => $value) {
  Core::$config[$config] = $value;
}

if (is_file($user = USER.'user/init.php')) {
  include $user;
}

if (!date_default_timezone_set($tz = cfg('timeZone'))) {
  warn("Cannot set default timezone to $tz, using ".date_default_timezone_get().".");
}
