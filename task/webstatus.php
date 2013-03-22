<?php namespace Sqobot;

class TaskWebstatus extends Task {
  public $title = 'Queue status';

  function do_(array $args = null) {
    $stats = Task::make('queue')->capture('stats');

    if (empty($args['short'])) {
      return print HLEx::pre_q($stats, 'gen output');
    }

    $parts = preg_split('~^\d+\. (.+)~m', $stats, -1, PREG_SPLIT_DELIM_CAPTURE);
    if (!$parts) { return '<em>Queue is empty.</em>'; }

    $result = array();
    $allTotal = $allErrors = 0;

    foreach ($parts as $i => $part) {
      if ($i == 0) {
        // skip preamble.
      } elseif ($i % 2 == 0) {
        $allTotal += $total = $this->matchAttribute('Total', $part) ?: '-';
        $allErrors += $errors = $this->matchAttribute('Errors', $part);
        $errors = $errors ? ", $errors!" : '';

        $result[] = HLEx::q($parts[$i - 1])." ($total$errors)";
      }
    }

    $remain = $allTotal - $allErrors;

    if ($remain > 0) {
      echo "<b>$remain</b> to go:";
    } else {
      echo HLEx::span('Empty queue, all errors:', 'error');
    }

    echo ' ', join(', ', $result), '.';
  }

  function matchAttribute($caption, $part) {
    if (preg_match("~^  $caption: *(\d+)~im", $part, $match)) {
      return $match[1];
    }
  }
}