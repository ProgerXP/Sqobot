<?php namespace Sqobot;

$root = __DIR__;
is_file($root.'/init.php') or $root = "$root/..";
is_file($root.'/init.php') or $root = "$root/sys";
require_once "$root/init.php";

$code = &$_REQUEST['quit'] and Web::quit($code);

if (cfg('forceHTTPS') and !Web::https()) {
  Web::sendStatus(403)->sendType('text/plain');
  die('Please switch to HTTPS. Access via plain HTTP is denied due to forceHTTPS option.');
}

Web::perms() or Web::deny('due to empty user permissions.');

$task = Web::get('task');
Web::canRun($task ?: 'index') or Web::deny($task ? "task $task." : 'index page.');

Web::sendType('text/html');

if ($task) {
  try {
    echo Web::wrap(Web::run($task, $title), $title);
  } catch (ENoTask $e) {
    Web::sendStatus(404)->quit(404, $e->getMessage());
  } catch (\Exception $e) {
    $info =
      "<p class=\"task-error\">Problem running task ".HLEx::b_q($task).": ".
      HLEx::kbd_q(exLine($e))."</p><hr>".HLEx::pre_q($e->getTraceAsString());

    echo Web::quit(500, $info);
  }
} else {
  $tasks = array();
  $toAdd = explode(' ', cfg('webIndexOrder'));

  while ($task = array_shift($toAdd)) {
    if ($task === '*') {
      $rest = array_diff( Web::tasks(), array_keys($tasks) );
      sort($rest);
      array_splice($toAdd, 0, 0, $rest);
    } elseif (Web::canRun($task)) {
      try {
        $tasks[$task] = Web::run($task, $title);
      } catch (\Exception $e) {
        $tasks[$task] =
          "<p class=\"task-error\">Problem running task ".HLEx::b_q($task).": ".
          HLEx::kbd_q(exLine($e))."</p>";
      }
    }
  }

  $tasks or Web::deny('because the user cannot access any of indexOrder tasks.');
  echo Web::wrap(join($tasks));
}
