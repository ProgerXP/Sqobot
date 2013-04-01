<?php namespace Sqobot;

class TaskPages extends Task {
  static function prereq() {
    if (!class_exists('ZipArchive')) {
      return print 'ZipArchive class (php_zip extension) is required.';
    }
  }

  static function packZIP($dest) {
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
    return $destZIP;
  }

  function do_pack(array $args = null) {
    if ($args === null or !opt(0)) {
      return print 'pages pack [*]TABLE [...]'.PHP_EOL.
                   '  --out[=out/pages.sql] --over --zip'.PHP_EOL.
                   '  --batch=15000';
    }

    if (!empty($args['zip']) and $error = static::prereq()) { return $error; }

    $batch = S::pickFlat($args, 'batch', 15000);
    $dest = S::pickFlat($args, 'out', 'out/pages.sql');
    S::mkdirOf($dest);

    $destCheck = empty($args['zip']) ? $dest : S::newExt($dest, '.zip');
    if (file_exists($destCheck) and empty($args['over'])) {
      return print "Target SQL file already exists: [$dest] - use --over to overwrite.";
    } elseif (!($h = fopen($dest, 'wb'))) {
      return print "Cannot fopen($dest).";
    }

    $db = db();

    $flush = function ($table = null, $withTime = true)
                  use (&$count, &$sql, $h, $db) {
      $count = 0;
      $sql and fwrite($h, substr($sql, 0, -1).";\n\n");

      $sql = "INSERT IGNORE INTO `%TABLE%` (`table`, `site`, `site_id`) VALUES";

      if ($table and $withTime) {
        $sql = "-- Pages of $table --\n\n".
               "DELETE FROM `%TABLE%` WHERE `table` = ".
               $db->quote($table)." AND `site` = '';\n\n".
               "$sql\n".
               "  (".$db->quote($table).", '', ".time()."),";
      }
    };

    foreach (opt() as $table) {
      $fromPages = S::unprefix($table, '*');
      $table === '' and $table = cfg('dbPrefix').PageIndex::$defaultTable;

      if (!$table) {
        echo $fromPages ? 'dbPageIndex config option is unset, ignoring "*" table.'
                        : 'Empty table name - ignoring.';
        continue;
      }

      $flush($table, !$fromPages);
      $total = 0;

      echo $table, '... ';
      $col = $fromPages ? '`table`, ' : '';
      $stmt = exec("SELECT {$col}site, site_id FROM `$table`");

      while ($row = $stmt->fetch(\PDO::FETCH_NUM)) {
        $sql .= "\n  (";
        $fromPages or array_unshift($row, $table);

        foreach ($row as $i => &$value) {
          $sql .= ($i > 0 ? ', ' : '').$db->quote($s);
        }

        $sql .= '),';
        ++$count >= $batch and $flush();
        ++$total;
      }

      $stmt->closeCursor();
      $total or $sql = '';
      echo $total, PHP_EOL;
    }

    isset($table) and $flush($table);
    fclose($h);

    empty($args['zip']) or static::packZIP($dest);
  }

  function do_unpack(array $args = null) {
    if ($args === null) {
      return print 'pages unpack [pages.zip|.sql] --table=pages --keep --merge --zip';
    }

    $src = opt(0, 'pages.zip');
    $zipMode = (!empty($args['zip']) or S::ends($src, '.zip'));
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
    $db = db();

    while (!feof($h)) {
      $line = trim(fgets($h));

      if (substr($line, 0, 3) === '-- ') {
        // skip comments.
      } elseif ($line) {
        $sql or $line = str_replace('%TABLE%', $table, $line);
        $sql .= $line;

        if (substr($sql, -1) === ';') {
          $count = $db->exec($sql);

          if (substr(ltrim($sql), 0, 7) !== 'DELETE ') {
            $total += $count;
            $s = $count == 1 ? '' : 's';
            echo "Inserted $count row$s.", PHP_EOL;
          }

          $sql = '';
        }
      }
    }

    $s = $total == 1 ? '' : 's';
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

      PageIndex::make(array(
        'table'           => $table,
        'site'            => '',
        'site_id'         => time(),
      ))->createIgnore();

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

  function do_stats(array $args = null) {
    if ($args === null) {
      return print 'pages stats --table=pages';
    }

    $table = S::pickFlat($args, 'table', cfg('dbPrefix').'pages');

    $stmt = exec("SELECT COUNT(1) AS count FROM`$table`");
    $total = $stmt->fetch()->count;
    $stmt->closeCursor();

    $stmt = exec("SELECT `table`, COUNT(1) AS count FROM $table GROUP by `table`");
    $tables = $stmt->fetchAll();
    $stmt->closeCursor();

    $stotal = $total == 1 ? '' : 's';
    $stables = count($tables) == 1 ? '' : 's';
    echo "$total page$stotal of ", count($tables), " table$stables.", PHP_EOL;

    if (!$total) {
      return;
    }

    usort($tables, function ($a, $b) { return strcmp($a->table, $b->table); });

    $stTimes = prep("SELECT site_id FROM $table WHERE `table` = ? AND site = ''");

    $sql = "SELECT site, COUNT(1) AS count, MIN(site_id) AS min, MAX(site_id) AS max".
           " FROM`$table` WHERE `table` = ? AND site != '' GROUP BY site";
    $stSites = prep($sql);

    foreach ($tables as $i => $table) {
      $stTimes->bindParam(1, $table->table);
      $populatedAt = EQuery::exec($stTimes)->fetch();
      $stTimes->closeCursor();

      echo PHP_EOL,
           $i + 1, '. ', $table->table, PHP_EOL,
           PHP_EOL,
           '  Total:          ', $table->count, PHP_EOL,
           '  Populated on:   ';

       if ($populatedAt) {
          echo date('d.m.Y \a\t H:i:s', $populatedAt->site_id), PHP_EOL;
        } else {
          echo 'unknown, no site = \'\' row', PHP_EOL;
        }

      $stSites->bindParam(1, $table->table);
      EQuery::exec($stSites);

      while ($row = $stSites->fetch()) {
        echo sprintf('  %-16s', $row->site.':'), $row->count, ' total';

        if ($row->count) {
          echo ';', PHP_EOL,
               '                  min site ID = ', $row->min, ', max = ', $row->max;
        }

        echo PHP_EOL;
      }

      $stSites->closeCursor();
    }
  }

  function do_sync(array $args = null) {
    $started = microtime(true);
    $pager = Task::make('pages');

    if ($args === null or !opt(0)) {
      echo 'pages sync TABLE [...]'.
           ' - accepts all parameters of `pages pack`, plus:', PHP_EOL,
           '  --nodes=node,node,... --keep', PHP_EOL, PHP_EOL;
      $pager->call('pack', null);
      return;
    } elseif ($error = static::prereq()) {
      return $error;
    }

    $zip = new \ZipArchive;

    $main = S::pickFlat($args, 'out', 'out/pages.sql');
    $pager->call('pack', $args);

    if (!is_file($main) or !($hmain = fopen($main, 'ab'))) {
      return print "Exiting - no packed pages file [$main] exists.";
    }

    echo PHP_EOL, 'Retrieving current pages from nodes...', PHP_EOL;

    $fatal = onFatal(function () {
      echo PHP_EOL,
           'NOTE: if you\'re getting fopen() error it might indicate that', PHP_EOL,
           '      dlTimeout is insufficient for a node to complete requested', PHP_EOL,
           '      operation - try increasing this value.', PHP_EOL;
    });

    foreach (Node::all() as $node) {
      echo "  {$node->id()}... ";

      $file = 'out/pages-node.zip.tmp';
      $time = microtime(true);

      $data = $node->call('pages-pack')
        ->addQuery(array('tables' => '*', 'zip' => 1))
        ->fetchData();

      echo round(microtime(true) - $time, 1).' sec; ';

      if (!file_put_contents($file, $data)) {
        throw new Error($this, "Cannot write temp file [$file].");
      } elseif (($error = $zip->open($file)) !== true) {
        echo "cannot open its ZIP archive, ZipArchive error code $error.";
      } elseif (!($name = $zip->getNameIndex(0)) or S::ext($name) !== '.sql') {
        echo "first archived file [$name] must have .sql extension.";
      } elseif (!($h = $zip->getStream($name))) {
        echo "cannot open stream of its ZIP\'s first file [$name].";
      } else {
        echo "$name: ";

        fwrite($hmain, "\n-- == Node {$node->id()} == --\n\n");
        $bytes = 0;

        while (!feof($h)) {
          $bytes += fwrite($hmain, fread($h, 65536));
        }

        fclose($h);
        $zip->close();

        echo "$bytes bytes, ok", PHP_EOL;
      }
    }

    fclose($hmain);
    isset($file) and unlink($file);

    echo PHP_EOL;

    Core::$cl['index'][0] = $main;
    $this->do_unpack(array('keep' => true) + $args);

    if (!is_string($main = static::packZIP($main))) {
      return;
    }

    echo PHP_EOL, 'Unpacking on nodes:', PHP_EOL;

    foreach (Node::all() as $i => $node) {
      echo PHP_EOL, $i + 1, ". {$node->id()}... ";
      $time = microtime(true);

      $log = $node->call('pages-unpack')
        ->addQuery('zip')
        ->upload('pages', 'pages.zip', fopen($main, 'rb'))
        ->fetchData();

      echo round(microtime(true) - $time, 1).' sec', PHP_EOL,
           str_replace(PHP_EOL, PHP_EOL.'  ', PHP_EOL.$log), PHP_EOL;
    }

    offFatal($fatal);

    empty($args['keep']) and unlink($main);
    echo 'Finished in ', round(microtime(true) - $started, 1), ' sec.', PHP_EOL;
  }
}