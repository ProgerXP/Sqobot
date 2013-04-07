<?php namespace Sqobot;

abstract class Task {
  public $name;

  //* $web null list both CLI and web tasks, bool
  //= array of str 'atoms', 'queue', ... suitable for factory()
  static function all($web = false) {
    $standard = S(glob(ROOT.'task/*.php', GLOB_NOESCAPE), array('.basename', '.php'));
    $user = S(glob(USER.'user/[Tt]ask*.php', GLOB_NOESCAPE),
              array('|', 'basename', array('.substr', 4, -4)));
    $tasks = array_unique(S::down(array_merge($standard, $user)));

    if ($web === null) {
      return $tasks;
    } else {
      return S::build($tasks, function ($name) use ($web) {
        if (($web ^ S::unprefix($name, 'web')) == 0) { return $name; }
      });
    }
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

  function __construct() {
    $this->name = S::tryUnprefix(get_class($this), __CLASS__);
  }

  function do_(array $args = null) {
    return print $this->name.' task has no default method.';
  }

  function start() { }
  function end() { }
  function before(&$task, array &$args = null) { }
  function after($task, array $args = null, &$result) { }

  function capture($task, $args = array()) {
    ob_start();
    $this->call($task, $args);
    return ob_get_clean();
  }

  // Typically returns an integer - exit code.
  function call($task, $args = array()) {
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

  //= array of str '' (default), 'unpack', ... suitable for call()
  function methods() {
    return S::build(get_class_methods($this), function ($func) {
      if (S::unprefix($func, 'do_')) { return $func; }
    });
  }
}