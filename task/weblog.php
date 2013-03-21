<?php namespace Sqobot;

class TaskWeblog extends Task {
  public $title = 'Log viewer';

  function do_(array $args = null) {
    $entries = explode("\n\n", file_get_contents(logFile()));
    $entries = array_slice($entries, -20);
    echo S($entries, NS.'HLEx.pre_q');
  }
}