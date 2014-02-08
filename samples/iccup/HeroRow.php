<?php namespace Sqobot;

class HeroRow extends Row {
  static $defaultTable = 'heroes';

  static $fields = array('match', 'hero', 'side', 'winner', 'kills', 'deaths',
                         'assists', 'creeps', 'towers');

  static $stats = array('kills', 'deaths', 'assists', 'creeps', 'towers');

  public $match, $hero, $side, $winner, $kills, $deaths, $assists, $creeps, $towers;

  function defaults() {
    foreach (static::$stats as $field) { $this->$field = 0; }
    return $this;
  }
}