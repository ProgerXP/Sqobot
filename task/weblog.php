<?php namespace Sqobot;

class TaskWeblog extends Task {
  public $title = 'Log viewer';

  static function formatEntry($msg) {
    $replaces = array('[' => '[<kbd>', ']' => '</kbd>]');
    $msg = strtr(HLEx::q($msg), $replaces);
    $msg = preg_replace('~^(\$ )(\w+)~', '\\1<b>\\2</b>', $msg);
    return $msg;
  }

  function do_(array $args = null) {
    echo Web::run('log-list', $title);
    $current = logFile();

    if ($query = &$args['file']) {
      $current = realpath(dirname($current)."/$query");
      if (!$current) {
        $current = $query;
        $entries = array();
      }
    }

    if (is_file($current)) {
      $entries = explode("\n\n", file_get_contents($current));
      $entries = array_filter(S::trim($entries));
    } else {
      $entries = array();
    }

    if ($entries) {
      $entries = array_reverse(array_slice($entries, -20));
      $entries = S($entries, array(__CLASS__, 'formatEntry'));
      echo HLEx::div(join(S($entries, NS.'HLEx.pre')), 'entries');
    } else {
      echo HLEx::p('Log file '.HLEx::kbd_q($current).' is empty.', 'none');
    }
  }

  function do_list(array $args = null) {
    $selected = basename(S::pickFlat($args, 'file'));
    $current = logFile();
    $all = array();

    foreach (scandir($root = dirname($current)) as $file) {
      if (substr($file, -4) === '.log') { $all[$file] = "$root/$file"; }
    }

    if (!$all) { return; }

    krsort($all);

    foreach ($all as &$file) {
      $base = $query['file'] = basename($file);
      $file = HLEx::a_q( basename($file, '.log'), taskURL('log', $query) );

      $class = '';
      $base === basename($current) and $class .= ' latest';
      $base === $selected and $class .= ' selected';
      $file = HLEx::li($file, trim($class));
    }

    echo HLEx::ol(join($all));
  }
}