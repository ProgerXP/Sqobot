<?php namespace Sqobot;

class Queue extends Row {
  static $defaultTable = 'queue';
  static $fields = array('id', 'url', 'created', 'started', 'error', 'site', 'extra');

  public $url, $started, $error, $site, $extra;

  //= null nothing in queue, Queue
  static function nextFree($table = null) {
    $table = static::tableName($table);

    while (true) {
      $stmt = exec('SELECT * FROM `'.$table.'` WHERE started IS NULL LIMIT 1');
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
  static function pass($callback, $table = null) {
    $table = static::tableName($table);
    $timedOut = static::markTimeout($table);
    $current = static::nextFree($table);

    return rescue(
      function () use ($callback, $current) {
        return call_user_func($callback, $current);
      },
      function ($e) use ($table, $current) {
        error(get_called_class().'::pass() has failed on row '.$current->id.
              ' in '.$table.': '.exLine($e));

        exec('UPDATE `'.$table.'` SET error = ? WHERE id = ?',
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