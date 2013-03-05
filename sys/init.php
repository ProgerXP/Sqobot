<?php namespace Sqobot;

/*
  Global command-line options

  --log=out/log-...log
    Can be empty. Defaults to opt('log').

  --config=main
    Can be empty.

  --chdir=__DIR__
    Can be empty.
*/

define(__NAMESPACE__.'\\NS', __NAMESPACE__.'\\');
defined(NS.'ROOT') or define(NS.'ROOT', dirname(__DIR__).'/');

error_reporting(-1);
ini_set('display_errors', true);
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

  if (substr($class, 0, 7) === NS) {
    $short = strtolower(substr($class, 7));
    $files = array(
      ROOT."sys/$short.php",
    );

    if (substr($short, 0, 4) === 'task') {
      $files[] = ROOT.'task/'.substr($short, 4).'.php';
    }
  } else {
    $files = array(
      ROOT."lib/$class.php",
      ROOT.'lib/'.strtolower($class).'.php',
    );
  }

  if ($path = cfg("class $class")) {
    $path[0] === '$' and $path = ROOT.'lib/'.substr($path, 1);
    array_unshift($files, $path);
  }

  foreach ($files as $file) {
    if (is_file($file)) { return include_once $file; }
  }

  warn("Cannot autoload class [$class] from either of these paths:".
       join("\n  ", S::prepend($files, '')));
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
  error('Terminated with error: '.exLine($e));
});

Core::loadConfig(ROOT.'default.conf');

if (defined('STDIN') and isset($argv)) {
  array_shift($argv);   // script.php
  reset($argv) === '--' and array_shift($argv);
  Core::$cl = S::parseCL($argv);
}

$chdir = opt('chdir', ROOT) and chdir($chdir);

Core::loadConfig('default.conf');
Core::loadConfig(opt('config', 'main').'.conf');
