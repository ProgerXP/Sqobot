<?php namespace Sqobot;

class TaskWebfiles extends Task {
  public $title = 'File Browser';

  function do_(array $args = null) {
    $base = 'out/';
    $path = trim(S::pickFlat($args, 'path'), '\\/');

    if (!is_dir($base) or !($baseAbs = S::expand($base))) {
      return HLEx::p('Base directory '.HLEx::kbd_q($base).' doesn\'t exist.', 'none');
    }

    $baseAbs = strtr($baseAbs, '\\', '/').'/';
    $path = strtr(S::expand($path, $baseAbs), '\\', '/');

    if (!S::unprefix($path, $baseAbs)) {
      $path = '';
    }

    if (!file_exists($base.$path)) {
      Web::quit(404, HLEx::p('Path doesn\'t exist: '.HLEx::kbd_q($base.$path).'.'));
    } elseif (!empty($args['delete'])) {
      is_dir($base.$path) ? S::rmrf($base.$path) : unlink($base.$path);

      $path = dirname($path);
      $naked = S::pickFlat($args, 'naked');
      Web::redirect(taskURL('files', compact('path', 'naked')));
    } elseif (!is_dir($base.$path)) {
      $options = S::combine(array('mime', 'inline', 'expires', 'partial'), 1);
      $options = array_intersect_key($args, $options);
      empty($options['mime']) and $options['mime'] = Download::mimeByExt(S::ext($path));
      \Upweave::sendFile($base.$path, $options);
    } elseif (!($h = opendir($base.$path))) {
      Web::quit(500, HLEx::p('Cannot opendir('.HLEx::kbd_q($base.$path).').'));
    } elseif ($path !== '') {
      static::outputPath($path);
      $path .= '/';
    }

    $list = array();

    while (($file = readdir($h)) !== false) {
      if ($file !== '.' and $file !== '..') {
        $full = $base.$path.$file;
        $dir = is_dir($full);

        $classes = array();

        if ($file[0] === '.') {
          $classes[] = 'sys';
        } elseif (!$dir) {
          $classes[] = 'file-'.ltrim(S::ext($file), '.');
        }

        $dir and $classes[] = 'dir';
        static::isEmpty($full) and $classes[] = 'empty';

        $html = HLEx::tag('tr', join(' ', $classes));
        $link = $this->pathLink($path.$file, $file);
        $links = array();

        if ($dir) {
          $html .= HLEx::td($link, array('class' => 'name', 'colspan' => 3));
        } else {
          $query = array('path' => $path.$file, 'inline' => 1);
          $links[] = HLEx::a(HLEx::tag('img', 'zoom.png'), taskURL('files', $query));

          $time = $time = filemtime($full);
          $title = S::sqlDateTime($time);
          $time = HLEx::time(date('d M', $time), $time, compact('title'));

          $html .= HLEx::td($link, 'name');
          $html .= HLEx::td(S::sizeStr(filesize($full)), 'size').
                   HLEx::td($time);
        }

        $query = array('path' => $path.$file, 'delete' => 1);
        $links[] = HLEx::a(HLEx::tag('img', 'cancel.png'), array(
          'class'         => 'confirm delete',
          'href'          => taskURL('files', $query),
        ));

        $html .= HLEx::td(join(' ', $links), 'btn');

        $list[ ((int) !$dir).$file ] = $html.'</tr>';
      }
    }

    closedir($h);

    if ($list) {
      ksort($list);
      echo HLEx::table(join($list), 'files');
    }
  }

  static function outputPath($path) {
    $parts = explode('/', $path);
    $last = array_pop($parts);
    $current = '';

    echo '<p class="path">', static::pathLink('', 'root');

    foreach ($parts as $part) {
      echo ' / ', static::pathLink($current.$part, $part);
      $current .= "$part/";
    }

    echo ' / ', HLEx::b_q($last), '</p>';
  }

  static function isEmpty($path) {
    if (is_dir($path) and is_readable($path)) {
      $h = opendir($path);

      while (($file = readdir($h)) !== false) {
        if ($file !== '.' and $file !== '..') {
          closedir($h);
          return false;
        }
      }

      return true;
    } else {
      return !file_exists($path) or filesize($path) <= 0;
    }
  }

  static function pathLink($path, $text = null) {
    return HLEx::a(isset($text) ? $text : $path, array(
      'href'              => taskURL('files', compact('path')),
      'class'             => 'name',
    ));
  }
}