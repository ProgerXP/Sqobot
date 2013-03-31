<?php namespace Sqobot;

class TaskWebindex extends Task {
  public $title;

  function do_(array $args = null) {
    $tasks = array();
    $toAdd = explode(' ', cfg('webIndexOrder'));

    while ($task = array_shift($toAdd)) {
      if ($task === '*') {
        $rest = array_diff( Web::tasks(), array_keys($tasks) );
        sort($rest);
        array_splice($toAdd, 0, 0, $rest);
      } elseif ($task !== 'index' and Web::canRun($task)) {
        try {
          $output = Web::runTitled($task, $title);

          $regexp = 'Web'.strtolower($task).' task has no default method';
          $regexp = "~</h2>\s*$regexp\.?\s*</div>\s*$~u";
          preg_match($regexp, trim($output)) or $tasks[$task] = $output;
        } catch (\Exception $e) {
          $tasks[$task] =
            "<p class=\"task error border\">Problem running task ".HLEx::b_q($task).": ".
            HLEx::kbd_q(exLine($e))."</p>";
        }
      }
    }

    $tasks or Web::deny('because the user cannot access any of webIndexOrder tasks.');
    echo join($tasks);
  }
}