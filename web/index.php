<?php namespace Sqobot;

$root = __DIR__;
is_file($root.'/init.php') or $root = "$root/..";
is_file($root.'/init.php') or $root = "$root/sys";
require_once "$root/init.php";

$code = &$_REQUEST['quit'] and Web::quit($code);

if (cfg('forceHTTPS') and !Web::https()) {
  Web::quit(403, 'Please switch to HTTPS. Access via plain HTTP is denied due to'.
            '  forceHTTPS option.');
}

Web::perms() or Web::deny('due to empty user permissions.');

$task = Web::get('task') ?: 'index';
Web::canRun($task) or Web::deny($task == 'index' ? 'index page.' : "task $task.");

try {
  Web::sendType('text/html');

  if (Web::is('naked')) {
    echo Web::runNaked($task, $title);
  } else {
    echo Web::wrap(Web::runTitled($task, $title), $title);
  }
} catch (ENoTask $e) {
  Web::quit(404, $e->getMessage());
} catch (\Exception $e) {
  $e = Error::initial($e);

  if (Web::is('naked')) {
    // this is likely a remote AJAX request and user won't see the message.
    error("Problem running task [$task]: ".$e->getMessage());
  }

  $info =
    "<p class=\"task error\">Problem running task ".HLEx::b_q($task).": ".
    HLEx::kbd_q(exLine($e))."</p><hr>".
    HLEx::pre_q($e->getTraceAsString(), array('style' => 'text-align: left'));

  echo Web::quit(500, $info);
}
