<?php namespace Sqobot;

class Node {
  //= null when not initialized by all(), hash of Node
  static $all;

  public $url;
  public $user, $password;
  public $name;

  static function exists($name = null) {
    $all = static::all();
    return isset($name) ? isset($all[$name]) : !empty($name);
  }

  //= hash of Node by 'name'
  static function all() {
    if (static::$all) {
      return static::$all;
    }

    $result = array();

    foreach (cfgGroup('node') as $name => $value) {
      list($host, $user, $password, $extra) = explode(' ', "$value   ");

      if (isset($result[$name])) {
        warn("Duplicated configuration for node named [$name] - overriding last.");
      }

      $result[$name] = static::make($host)->name($name)->credentials($user, $password);
    }

    return static::$all = $result;
  }

  static function make($url) {
    return new static($url);
  }

  function __construct($url) {
    $this->url = $url;
  }

  function name($new = null) {
    return S::access(func_num_args(), $new, $this, $this->name);
  }

  function credentials($user, $password = '') {
    $this->user = $user;
    $this->password = $password;
    return $this;
  }

  function id() {
    return isset($this->name) ? $this->name : $this->urlPart(PHP_URL_HOST);
  }

  function urlPart($part, $default = null) {
    return parse_url($this->url, $part) ?: $default;
  }

  function url() {
    $url = $this->url;
    $this->urlPart(PHP_URL_SCHEME) or $url = "http://$url";
    return $url;
  }

  function status($short = true) {
    return $this->call('status')->addQuery(compact('short'))->fetchData();
  }

  function call($task, array $post = array()) {
    $call = NodeCall::make($this->url())
      ->addQuery(array('task' => $task, 'naked' => 1))
      ->post($post);

    $call->node = $this;
    $this->user and $call->basicAuth($this->user, $this->password);

    return $call;
  }
}

class NodeCall extends Download {
  public $node;   //= Node

  function headerlessBody() {
    $body = $this->docBody();
    $less = preg_replace('~<h2>.*?</h2>~uis', '', $body, 1);
    return preg_last_error() ? $body : $less;
  }
}