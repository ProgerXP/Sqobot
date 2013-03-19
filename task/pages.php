<?php namespace Sqobot;

class TaskPages extends TaskAtoms {
  function do_pack(array $args = null) {
    if ($args === null or !opt(0)) {
      return print 'pages pack TABLE [...]'.PHP_EOL.
                   '  --out[=out/pages.sql] --over --zip'.PHP_EOL.
                   '  --batch=15000';
    }

    if (!empty($args['zip']) and $error = static::prereq()) { return $error; }

    $batch = S::pickFlat($args, 'batch', 15000);
    $dest = S::pickFlat($args, 'out', 'out/pages.sql');
    S::mkdirOf($dest);

    if (file_exists($dest) and empty($args['over'])) {
      return print "Target SQL file already exists: [$dest] - use --over to overwrite.";
    } elseif (!($h = fopen($dest, 'wb'))) {
      return print "Cannot fopen($dest).";
    }

    $flush = function ($table = null) use (&$count, &$sql, $h) {
      $count = 0;
      $sql and fwrite($h, substr($sql, 0, -1).";\n\n");

      $sql = "INSERT IGNORE INTO `%TABLE%` (`table`, `site`, `site_id`) VALUES";
      $table and $sql = "-- Pages of $table --\n\n$sql";
    };

    foreach (opt() as $table) {
      $flush($table);
      $total = 0;

      echo $table, '... ';
      $stmt = exec('SELECT site, site_id FROM `'.$table.'`');

      while ($row = $stmt->fetch(\PDO::FETCH_NUM)) {
        array_unshift($row, $table);
        $sql .= "\n  (".join(', ', S($row, array(db(), 'quote'))).'),';
        ++$count >= $batch and $flush();
        ++$total;
      }

      $stmt->closeCursor();
      echo $total, PHP_EOL;
    }

    $flush();
    fclose($h);

    if (!empty($args['zip'])) {
      $destZIP = S::newExt($dest, '.zip');
      echo PHP_EOL, "Packing to $destZIP... ";

      $zip = new \ZipArchive;

      if (($error = $zip->open($destZIP, \ZipArchive::CREATE)) !== true) {
        return print "cannot create file or it exists, ZipArchive error code $error.";
      } elseif (!$zip->addFile($dest, basename($dest))) {
        return print "cannot add [$dest] to it.";
      }

      $zip->close();
      unlink($dest);

      echo 'ok', PHP_EOL;
    }
  }

  function do_unpack(array $args = null) {
    if ($args === null) {
      return print 'pages unpack [pages.zip|.sql] --table=pages --keep --merge';
    }

    $src = opt(0, 'pages.zip');
    $zipMode = S::ends($src, '.zip');
    $table = S::pickFlat($args, 'table', cfg('dbPrefix').'pages');

    if (!is_file($src)) {
      return print "Source file [$src] doesn't exist.";
    }

    if ($zipMode) {
      if ($error = static::prereq()) { return $error; }

      $zip = new \ZipArchive;

      if (($error = $zip->open($src)) !== true) {
        return print "Cannot open ZIP archive [$src], ZipArchive error code $error.";
      } elseif (!($name = $zip->getNameIndex(0))) {
        return print "ZIP archive [$src] is empty.";
      } elseif (!S::ends($name, '.sql')) {
        return print "First archived file [$name] must have .sql extension.";
      } elseif (!($h = $zip->getStream($name))) {
        return print "Cannot getStream() to archived file [$name].";
      }
    } elseif (!($h = fopen($src, 'rb'))) {
      return print "Cannot fopen($src).";
    }

    if (empty($args['merge'])) {
      exec('TRUNCATE `'.$table.'`');
      echo "Cleared $table.", PHP_EOL;
    }

    $sql = '';
    $total = 0;

    while (!feof($h)) {
      $line = trim(fgets($h));

      if (substr($line, 0, 3) === '-- ') {
        // skip comments.
      } elseif ($line) {
        $sql or $line = str_replace('%TABLE%', $table, $line);
        $sql .= $line;

        if (substr($sql, -1) === ';') {
          $total += $count = EQuery::exec(prep($sql), true)->rowCount();
          $sql = '';

          $s = $count == 1 ? '' : 's';
          echo "Inserted $count row$s.", PHP_EOL;
        }
      }
    }

    $s = $count == 1 ? '' : 's';
    echo PHP_EOL, "Done inserting $total row$s.", PHP_EOL;

    $stmt = exec("SELECT COUNT(1) AS count FROM `$table`");
    $count = $stmt->fetch()->count;
    $stmt->closeCursor();

    $s = $count == 1 ? '' : 's';
    echo "$table now contains $count row$s in total.", PHP_EOL;

    fclose($h);
    $zipMode and $zip->close();
    empty($args['keep']) and unlink($src);
  }

  function do_populate(array $args = null) {
    if ($args === null or !opt(0)) {
      return print 'pages populate TABLE [...] --table=pages --clear';
    }

    if (!PageIndex::enabled()) {
      PageIndex::$defaultTable = S::pickFlat($args, 'table', 'pages');
    }

    if (!empty($args['clear'])) {
      exec('TRUNCATE `'.($table = cfg('dbPrefix').PageIndex::$defaultTable).'`');
      echo "Cleared $table.", PHP_EOL, PHP_EOL;
    }

    foreach (opt() as $table) {
      echo $table, '...', PHP_EOL;
      $stmt = exec('SELECT site, site_id FROM `'.$table.'`');

      while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
        PageIndex::make($row + compact('table'))->createIgnore();
      }

      $stmt->closeCursor();
    }
  }

  function do_clear(array $args = null) {
    if ($args === null) {
      return print 'pages clear --table=pages';
    }

    $table = S::pickFlat($args, 'table', cfg('dbPrefix').'pages');
    exec('TRUNCATE `'.$table.'`');
    echo "Cleared $table.", PHP_EOL;
  }
}