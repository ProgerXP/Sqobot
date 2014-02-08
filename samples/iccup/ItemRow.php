<?php namespace Sqobot;

class ItemRow extends Row {
  static $defaultTable = 'items';
  static $fields = array('match', 'hero', 'item');
  public $match, $hero, $item;
}