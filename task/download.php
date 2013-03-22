<?php namespace Sqobot;

class Download extends \Downwind {
  static function randomAgent() {
    isset(static::$agents) or static::$agents = static::loadAgents();
    return parent::randomAgent();
  }

  static function loadAgents() {
    if (is_file($file = 'agents.txt')) {
      $list = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
      return array_filter(array_map('trim', $list));
    } else {
      log("No User-Agents file $file - use it for better cloaking.");
      return array();
    }
  }

  function __construct($url, $headers = array()) {
    parent::__construct(realURL($url), $headers);

    $this->contextOptions = array(
      'follow_location'   => cfg('dlRedirects') > 0,
      'max_redirects'     => max(0, (int) cfg('dlRedirects')),
      'protocol_version'  => cfg('dlProtocol'),
      'timeout'           => (float) cfg('dlTimeout'),
      'ignore_errors'     => !!cfg('dlFetchOnError'),
    );
  }

  function header_accept_language($str = '') {
    return parent::header_accept_language(cfg('dl languages'));
  }

  function header_accept_charset($str = '') {
    return parent::header_accept_charset(cfg('dl charsets'));
  }

  function header_accept($str = '') {
    return parent::header_accept(cfg('dl mimes'));
  }
}