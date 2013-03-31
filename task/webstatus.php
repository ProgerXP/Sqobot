<?php namespace Sqobot;

class TaskWebstatus extends Task {
  public $title = 'Node Status';

  function do_(array $args = null) {
    if (empty($args['short'])) {
      $atoms = rtrim(Task::make('atoms')->capture('count'));
      $pages = rtrim(Task::make('pages')->capture('stats'));
      $queue = rtrim(Task::make('queue')->capture('stats'));

      echo strpos($atoms, "\n") ? HLEx::pre_q($atoms, 'atoms') : '',
           strpos($pages, "\n") ? HLEx::pre_q($pages, 'pages') : '',
           HLEx::pre_q($queue, 'gen output queue');
    } else {
      $this->outputShort($args);
    }
  }

  function outputShort(array $args = null) {
    echo '<p>';
    $this->outputShortQueue(Task::make('queue')->capture('stats'));
    echo '</p><p>';
    $this->outputShortPages(Task::make('pages')->capture('stats'));
    echo '</p>';
  }

  function outputShortQueue($stats) {
    $parts = preg_split('~^\d+\. (.+)~m', $stats, -1, PREG_SPLIT_DELIM_CAPTURE);
    if (!$parts) { return print '<em>Queue is empty.</em>'; }

    $result = array();
    $allTotal = $allErrors = 0;

    foreach ($parts as $i => $part) {
      if ($i == 0) {
        // skip preamble.
      } elseif ($i % 2 == 0) {
        $allTotal += $total = $this->matchAttribute('Total', $part) ?: '-';
        $allErrors += $errors = $this->matchAttribute('Errors', $part);
        $errors = $errors ? ', '.HLEx::span("$errors!", 'errors') : '';

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

  function outputShortPages($stats) {
    $parts = preg_split('~^\d+\. (.+)~m', $stats, -1, PREG_SPLIT_DELIM_CAPTURE);

    $result = array();
    $allTotal = 0;

    foreach ($parts as $i => $part) {
      if ($i == 0) {
        // skip preamble.
      } elseif ($i % 2 == 0) {
        $allTotal += $total = $this->matchAttribute('Total', $part) ?: '-';

        $timestamp = $this->matchAttribute('Populated on', $part, false);
        $timestamp = strtotime(preg_replace('~[a-z]*~i', '', $timestamp));

        if ($timestamp) {
          $time = date('H:i', $timestamp);
          if (date('d.m.Y') !== date('d.m.Y', $timestamp)) {
            $time .= ' on '.date('d.m', $timestamp);
          }

          $time = ', pop. at '.timeTag($time, $timestamp);
        }

        $regexp = "~^  ([^A-Z][^:]+): *(\d+)~im";
        if (preg_match_all($regexp, $part, $matches, PREG_SET_ORDER)) {
          $sites = join( ', ', S(S::combine($matches[1], $matches[2]), '"? ?"') );
        } else {
          $sites = '';
        }

        $result[] = HLEx::q($parts[$i - 1])." ($total$time$sites)";
      }
    }

    if ($allTotal) {
      $s = $allTotal == 1 ? '' : 's';
      echo "<b>$allTotal</b> page$s: ", join(', ', $result), '.';
    }
  }

  function matchAttribute($caption, $part, $num = true) {
    $num = $num ? '\d' : '.';
    if (preg_match("~^  $caption: *($num+)~im", $part, $match)) {
      return $match[1];
    }
  }
}