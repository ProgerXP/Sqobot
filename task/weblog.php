<?php namespace Sqobot;

class TaskWeblog extends Task {
  public $title = 'Log Viewer';

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
      strpbrk($query, '\\/') === false or Web::deny();
      $current = dirname($current)."/$query";
    }

    if (is_file($current)) {
      $entries = explode("\n\n", file_get_contents($current));
      $entries = array_filter(S::trim($entries));
    } else {
      $entries = array();
    }

    $max = S::pickFlat($args, 'max', $query ? count($entries) : 20);

    if ($entries) {
      $remaining = count($entries) - $max;
      $remaining <= 3 and $max = $remaining = 0;

      $entries = array_reverse(array_slice($entries, -$max));
      $entries = S($entries, array(__CLASS__, 'formatEntry'));

      $remaining = HLEx::p($remaining > 0 ? "$remaining more." : 'EOF', 'eof');
      echo HLEx::div(join(S($entries, NS.'HLEx.pre')).$remaining, 'entries');
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