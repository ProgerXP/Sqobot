<?php namespace Sqobot;

class TaskWebstatus extends Task {
  public $title = 'Queue status';

  function do_(array $args = null) {
    echo HLEx::pre_q( Task::make('queue')->capture('stats') );
  }
}