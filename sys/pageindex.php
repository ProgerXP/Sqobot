<?php namespace Sqobot;

class PageIndex extends Row {
  static $fields = array('table', 'site', 'site_id', 'created');

  public $site, $site_id, $created;

  static function enabled() {
    return !!static::$defaultTable;
  }

  static function has($table, $site, $site_id) {
    return static::count(compact('table', 'site', 'site_id')) > 0;
  }

  function table($new = null) {
    return static::tableName();
  }

  function created() {
    return toTimestamp($this->created);
  }
}

if ($table = cfg('dbPageIndex')) {
  PageIndex::$defaultTable = $table == '1' ? 'pages' : $table;
}