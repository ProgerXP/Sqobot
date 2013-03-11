<?php
namespace Sqobot;

require_once is_file(__DIR__.'/init.php') ? __DIR__.'/init.php' : __DIR__.'/sys/init.php';

$cl = &Core::$cl['index'];
$task = array_shift($cl);
$func = array_shift($cl);

if ("$task" === '' or ($task === 'help' and "$func" === '')) {
  echo 'You didn\'t pass task name to run.', PHP_EOL,
       PHP_EOL,
       '  cli [help] TASK [do|FUNC] [arg#1[ ...]] [--opt[=[val]]] [-f[lags...]]', PHP_EOL,
       PHP_EOL,
       '  --options and -flags can appear in any position.', PHP_EOL,
       '  "do" lets you call default task while passing arguments by index.', PHP_EOL,
       PHP_EOL,
       'Global options:', PHP_EOL,
       PHP_EOL,
       '  --silent[=1]', PHP_EOL;
  exit(-1);
} elseif ($task === 'help') {
  S::rotate(array_shift($cl), array(&$func, &$task));
  $args = null;
} else {
  $func === 'do' and $func = '';
  $args = Core::$cl['options'];
}

$silent = !empty($args['silent']) and ob_start();

try {
  $code = Task::make($task)->call($func, $args);
} catch (ENoTask $e) {
  $code = 2;
  echo $e->getMessage();
}

// $code === 1 is returned by print.
($args === null or $code === 1) and print PHP_EOL;
$silent and ob_end_clean();
exit($code);
