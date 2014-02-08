<?php namespace Sqobot;

class MatchPool extends Pool {
  static $defaultTable = 'matches';
  static $fields = array('game', 'map', 'date', 'duration', 'mode', 'winner', 'count');

  public $game, $map, $date, $duration, $mode, $winner, $count;

  function created() {
    return toTimestamp($this->date);
  }
}
MatchPool::$fields = array_merge(Pool::$fields, MatchPool::$fields);