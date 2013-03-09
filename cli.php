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
  exit(2);
} elseif ($task === 'help') {
  S::rotate(array_shift($cl), array(&$func, &$task));
  $args = null;
} else {
  $func === 'do' and $func = '';
  $args = Core::$cl['options'];
}

$silent = !empty($args['silent']) and ob_start();

try {
  Task::make($task)->call($func, $args);
} catch (ENoTask $e) {
  echo $e->getMessage();
}

$args === null and print PHP_EOL;
$silent and ob_end_clean();
