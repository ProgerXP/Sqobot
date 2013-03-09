<?php namespace Sqobot;

class Queue extends Row {
  static $defaultTable = 'queue';
  static $fields = array('id', 'url', 'created', 'started', 'error', 'site', 'extra');

  public $url, $started, $error, $site, $extra;

  //= null nothing in queue, Queue
  static function nextFree($site = null, $table = null) {
    $table = static::tableName($table);
    $where = $site ? ' AND site = :site' : '';

    while (true) {
      $sql = "SELECT * FROM `$table` WHERE started IS NULL$where LIMIT 1";
      $stmt = exec($sql, compact('site'));
      $next = $stmt->fetch();
      $stmt->closeCursor();

      if (!$next) {
        log("No next free queue record in $table.");
        return;
      }

      $affected = exec('UPDATE `'.$table.'` SET started = NOW()'.
                       ' WHERE id = ?', array($next->id));

      if ($affected) {
        return new static($next);
      }
    }
  }

  //= int number of timed out rows
  static function markTimeout($table = null) {
    $table = static::tableName($table);

    $started = S::sqlDatetime(time() - $timeout = cfg('queueTimeout'));
    $error = "Timed out after $timeout seconds.";

    $affected = exec('UPDATE `'.$table.'` SET error = ? WHERE started < ?'.
                     ' AND error = \'\'', array($error, $started));

    $affected and log("Marked $affected queue records as timed out in $table.");
    return $affected;
  }

  // Occurred exceptions are logged and re-thrown.
  //* $callback callable - function (Queue)
  //= mixed $callback's result
  static function pass($callback, $site = null, $table = null) {
    $table = static::tableName($table);
    $current = static::nextFree($site, $table);

    return rescue(
      function () use ($callback, $current) {
        $result = call_user_func($callback, $current);
        prep('DELETE FROM `'.$table.'` WHERE id = ? LIMIT 1', $current->id)->execute();
        return $result;
      },
      function ($e) use ($table, $current) {
        error(get_called_class().'::pass() has failed on row '.$current->id.
              ' in '.$table.': '.exLine($e));

        exec('UPDATE `'.$table.'` SET error = ? WHERE id = ? LIMIT 1',
             array('Exception: '.exLine($e), $current->id));

        return $error;
      }
    );
  }

  function defaults() {
    $this->created = new \DateTime;
    $this->error = '';
    $this->extra = '';
    return $this;
  }

  function created() {
    return toTimestamp($this->created);
  }

  function started() {
    return toTimestamp($this->started);
  }

  //= array
  function extra(array $new = null) {
    if (isset($new)) {
      $this->extra = $new ? serialize($new) : '';
      return $this;
    } else {
      return $this->extra ? (array) unserialize($this->extra) : array();
    }
  }
}