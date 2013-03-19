<?php namespace Sqobot;

if ($table = cfg('dbPageIndex')) {
  PageIndex::$defaultTable = $table == '1' ? 'pages' : $table;
}

class PageIndex extends Row {
  static $fields = array('table', 'site', 'site_id');

  public $site, $site_id;

  static function enabled() {
    return !!static::$defaultTable;
  }

  static function has($table, $site, $site_id) {
    return static::count(compact('table', 'site', 'site_id')) > 0;
  }

  function table($new = null) {
    return static::tableName();
  }
}