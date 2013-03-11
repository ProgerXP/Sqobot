<?php namespace Sqobot;

abstract class Task {
  public $name;

  function start() { }
  function end() { }
  function before(&$task, array &$args = null) { }
  function after($task, array $args = null, &$result) { }

  function __construct() {
    $this->name = S::tryUnprefix(get_class($this), __CLASS__);
  }

  function do_(array $args = null) {
    echo $this->name.' task has no default method.';
  }

  static function make($task) {
    $class = static::factory($task);
    return new $class;
  }

  static function factory($task, $fail = true) {
    $class = NS.'Task'.ucfirst(strtolower(trim($task)));

    if (class_exists($class)) {
      return $class;
    } elseif ($fail) {
      throw new ENoTask("Unknown task [$task] - class [$class] is undefined.");
    }
  }

  // Typically returns an integer - exit code.
  function call($task, $args) {
    $func = strtolower("do_$task");
    $id = get_class($this)."->$func";

    if (!method_exists($this, $func)) {
      throw new ENoTask($this, "Task method $id doesn't exist.");
    }

    $args === null or $args = S::arrize($args);
    $this->before($task, $args);

    try {
      $result = $this->$func($args);
    } catch (\Exception $e) {
      ETaskError::re($e, "Exception while running task [$id].");
    }

    $this->after($task, $args, $result);
    return $result;
  }
}