<?php namespace Sqobot;

class TaskPatch extends Task {
  static $standard;

  static function addTo(\ZipArchive $zip, $path, $localBase = '', $zipFile = null) {
    $localBase === '' or $localBase = rtrim($localBase, '\\/').'/';
    $zipFile and $zipFile = realpath($zipFile);
    $count = 0;

    if (is_dir($path)) {
      $recurse = substr($path, -1) !== '/';
      $path = rtrim($path, '\\/').'/';

      foreach (scandir($path) as $file) {
        if ($file !== '.' and $file !== '..') {
          $isDir = is_dir($path.$file);

          if ( $isDir ? $recurse : (!$zipFile or realpath($path.$file) !== $zipFile) ) {
            $base = $localBase.($isDir ? $file : '');
            $count += static::addTo($zip, $path.$file, $base);
          }
        }
      }
    } elseif (is_file($path)) {
      $zip->addFile($path, $localBase.basename($path));
      ++$count;
    } else {
      echo "  $path doesn't exist", PHP_EOL;
    }

    return $count;
  }

  function do_make(array $args = null) {
    if ($args === null) {
      return print 'patch make [add/ [...]] --out=out/patch.zip --over';
    }

    if (!class_exists('ZipArchive')) {
      return print 'ZipArchive class (php_zip extension) is required.';
    }

    $dest = S::pickFlat($args, 'out', 'out/patch.zip');
    S::mkdirOf($dest);

    if (file_exists($dest) and empty($args['over'])) {
      return print "Target ZIP file already exists: [$dest] - use --over to overwrite.";
    }

    $zip = new \ZipArchive;
    $mode = is_file($dest) ? \ZipArchive::OVERWRITE : \ZipArchive::CREATE;

    if (($error = $zip->open($dest, $mode)) !== true) {
      return print "Cannot create ZIP archive [$dest], ZipArchive error code $error.";
    }

    $toAdd = array_merge(static::$standard, opt());
    $total = 0;

    foreach ($toAdd as $path) {
      $name = $path;
      is_dir($path) and $name = rtrim($name, '\\/').'/';
      S::unprefix($name, ROOT) and $name = "ROOT/$name";
      S::unprefix($name, USER) and $name = "USER/$name";
      echo $name, PHP_EOL;

      $localBase = is_dir($path) ? basename(rtrim($path, '\\/')) : '';
      $total += $count = static::addTo($zip, $path, $localBase, $dest);

      if (is_dir($path) and $count) {
        $s = $count == 1 ? '' : 's';
        echo "  $count file$s", PHP_EOL;
      }
    }

    $s = $total == 1 ? '' : 's';
    $message = "Archived $total file$s to $dest";

    $comment = "$message ".join("\n", Atoms::selfSign());
    $zip->setArchiveComment($comment);
    $zip->close();

    echo PHP_EOL, "$message.", PHP_EOL;
  }
}

TaskPatch::$standard = array(
  ROOT.'lib', ROOT.'sys', ROOT.'task', ROOT.'web',
  ROOT.'cli', ROOT.'cli.bat', ROOT.'cli.php',
  ROOT.'default.conf', ROOT.'index.php',
);