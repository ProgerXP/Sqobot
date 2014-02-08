<?php namespace Sqobot;

abstract class SIccup extends Sqissor {
  static $heroIDs;

  static $shortModes = array('random draft' => 'rd', 'all random' => 'ar',
                             'all pick' => 'ap', 'captains mode' => 'cm',
                             'single draft' => 'sd');

  static function idByHeroName($name) {
    static::$heroIDs or static::$heroIDs = require __DIR__.'/hero-ids.php';
    $name = strtolower(trim($name));

    if (isset(static::$heroIDs[$name])) {
      return static::$heroIDs[$name];
    } else {
      throw new ESqissor("Cannot determine ID of unknown hero [$name].");
    }
  }

  static function heroNameByID($id) {
    static::$heroIDs or static::$heroIDs = require __DIR__.'/hero-ids.php';

    if ($name = array_search($id, static::$heroIDs)) {
      return $name;
    } else {
      throw new ESqissor("Cannot determine hero name by unknown ID [$id].");
    }
  }

  static function shortMode($long) {
    $long = strtolower(trim($long));

    if (isset(static::$shortModes[$long])) {
      return static::$shortModes[$long];
    } else {
      throw new ESqissor("Unknown long mode name [$long].");
    }
  }

  function initIccup($data, $withPage = false) {
    $base = $this->regexp($data, '~<base href="([^"]+)"~u', 1);

    if ($withPage) {
      $regexp = '~(?:</a|pagelist")>'.
                 '<span class="current">(\d+)</span>'.
                 '<(?:a href="|/div>)~u';
      $page = $this->regexp($data, $regexp);
      $page and $page = $page[1];
    }

    return S::listable(compact('base', 'page'));
  }
}