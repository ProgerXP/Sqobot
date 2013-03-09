<?php
namespace Sqobot;

require_once is_file(__DIR__.'/init.php') ? __DIR__.'/init.php' : __DIR__.'/sys/init.php';

$cl = &Core::$cl['index'];
$task = array_shift($cl);
$func = array_shift($cl);

if ("$task" === '') {
  die("You didn't pass name of the task to run. You can use 'help TASK FUNC'.");
} elseif ($task === 'help') {
  S::rotate(array_shift($cl), array(&$func, &$task));
  $args = null;
} else {
  $func === 'do' and $func = '';
  $args = Core::$cl['options'];
}

try {
  Task::make($task)->call($func, $args);
} catch (ENoTask $e) {
  echo $e->getMessage();
}

$args === null and print PHP_EOL;
