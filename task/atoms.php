<?php namespace Sqobot;

use ZipArchive;

class TaskAtoms extends Task {
  static function prereq() {
    if (!class_exists('ZipArchive')) {
      return print 'ZipArchive class (php_zip extension) is required.';
    }
  }

  static function rmdir($dir) {
    $h = opendir($dir);

    while (($file = readdir($h)) !== false) {
      $file[0] === '.' or unlink("$dir/$file");
    }

    closedir($h);
    rmdir($dir);
  }

  function do_pack(array $args = null) {
    if ($args === null) {
      return print 'atoms pack [out/atoms.zip] --abandoned --over --keep';
    }

    if ($error = static::prereq()) { return $error; }

    $dir = dirname(Atoms::$baseDest);
    $temp = "$dir-packing";
    $dest = rtrim(opt(0, 'out/atoms.zip'), '\\/');

    S::mkdirOf($dest);

    if (file_exists($dest) and empty($args['over'])) {
      return print "Target ZIP file already exists: [$dest] - use --over to overwrite.";
    } elseif (!empty($args['abandoned'])) {
      if (!is_dir($dir = $temp)) {
        return print "No abandoned atoms directory [$temp].";
      }
    } elseif (file_exists($temp)) {
      return print "Found abandoned old package directory [$temp] - not continuing.";
    } elseif (!is_dir($dir)) {
      return print "No atoms directory [$dir].";
    } elseif (!rename($dir, $temp)) {
      return print "Error renaming [$dir] to [$temp].";
    }

    $zip = new ZipArchive;
    $mode = is_file($dest) ? ZipArchive::OVERWRITE : ZipArchive::CREATE;

    if (($error = $zip->open($dest, $mode)) !== true) {
      return print "Cannot create ZIP archive [$dest], ZipArchive error code $error.";
    } elseif (!($h = opendir($temp))) {
      return print "Cannot opendir($temp).";
    }

    $count = 0;

    while (($file = readdir($h)) !== false) {
      if ($file[0] !== '.' and substr($file, -4) === '.php') {
        echo ++$count, ". $dir/$file... ";

        if (!$zip->addFile("$temp/$file", $file)) {
          return print "cannot add it to ZIP archive!";
        }

        echo 'ok', PHP_EOL;
      }
    }

    closedir($h);
    $s = $count == 1 ? '' : 's';

    $comment = "Packed $count atom$s to $dest ".join("\n", Atoms::selfSign());
    $zip->setArchiveComment($comment);
    $zip->close();

    if (!$count) {
      rmdir($temp);
      return print "No atoms found in $dir.";
    }

    echo PHP_EOL, "Finished packing $count atom$s to $dest.", PHP_EOL;

    if (empty($args['keep'])) {
      echo "Removing old atoms from $temp... ";
      static::rmdir($temp);
      echo 'ok', PHP_EOL;
    }
  }

  function do_unpack(array $args = null) {
    if ($args === null) {
      return print 'atoms unpack [atoms.zip [out/atoms-in]] --keep';
    }

    if ($error = static::prereq()) { return $error; }

    $src = opt(0, 'atoms.zip');
    $zip = new ZipArchive;

    $dest = rtrim(opt(1, 'out/atoms-in'), '\\/');
    S::mkdir($dest);

    if (!is_file($src)) {
      return print "Source ZIP archive [$src] doesn't exist.";
    } elseif ($zip->open($src) !== true) {
      return print "Error opening ZIP archive [$src].";
    }

    $comment = $zip->getArchiveComment();
    if ($comment) {
      echo 'Archive comment:', PHP_EOL, PHP_EOL,
           '  ', str_replace("\n", "\n  ", rtrim($comment)),
           PHP_EOL, PHP_EOL;
    }

    if (!$zip->extractTo($dest)) {
      return print "Error extracting ZIP contents to [$dest].";
    }

    $zip->close();
    empty($args['keep']) and unlink($src);

    $count = -2;

    $h = opendir($dest);
    while (($file = readdir($h)) !== false) { ++$count; }
    closedir($h);

    $s = $count == 1 ? '' : 's';
    echo "There are now $count file$s in $dest.", PHP_EOL;
  }

  function do_in(array $args = null) {
    if ($args === null) {
      return print 'atoms in [out/atoms-in] --keep --all-or-none --max=N';
    }

    $src = rtrim(opt(0, 'out/atoms-in'), '\\/');

    if (!is_dir($src)) {
      return print "No directory [$src].";
    } elseif (!($h = opendir($src))) {
      return print "Cannot opendir($src).";
    }

    $keep = !empty($args['keep']);
    $transactEach = empty($args['all-or-none']);
    $max = (int) S::pickFlat($args, 'max', -1);

    Core::$config['atomate'] = '';
    $noRmDir = false;
    $count = $errors = 0;

    $transactEach or db()->beginTransaction();

    while (($file = readdir($h)) !== false) {
      if ($file[0] === '.' or substr($file, -4) !== '.php') {
        // skip.
      } elseif ($max >= 0 and $count >= $max) {
        $s = $max == 1 ? '' : 's';
        echo "Reached maximum count of $max atom$s to import - stopping (but there",
             " are more).";
        $noRmDir = true;
        break;
      } else {
        $full = "$src/$file";
        echo ++$count, ". $full... ";

        if (!$keep and !is_writable($full)) {
          echo 'is not writable, --keep not active - ignoring', PHP_EOL;
          $noRmDir = ++$errors;
          continue;
        }

        try {
          if ($transactEach) {
            atomic(function () use ($full) { include $full; });
          } else {
            include $full;
          }
        } catch (\Exception $e) {
          echo 'exception ', exLine($e), '.', PHP_EOL;
          $noRmDir = ++$errors;
          continue;
        }

        $keep or unlink($full);
        echo 'ok', PHP_EOL;
      }
    }

    if (!$transactEach) {
      echo 'Committing changes to the database as per --all-or-none... ';
      db()->commit();
      echo 'ok', PHP_EOL;
    }

    closedir($h);

    $count and print PHP_EOL;
    $count -= $errors;

    $es = $errors == 1 ? '' : 's';
    $errors = $errors ? " ($errors error$es)" : '';
    $s = $count == 1 ? '' : 's';
    echo "Done importing $count atom$s$errors.", PHP_EOL;

    if (!$keep and !$noRmDir) {
      rmdir($src) and print "Removed source directory $src.".PHP_EOL;
    }
  }
}