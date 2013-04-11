<?php namespace Sqobot;

class Download extends \Downwind {
  static function logFile() {
    return strftime( opt('dlLog', cfg('dlLog')) );
  }

  static function summarize($url, $context, $file = null) {
    $meta = $file ? stream_get_meta_data($file) : array();
    $options = stream_context_get_options($context);

    $separ = '+'.str_repeat('-', 73)."\n";
    $result = "$separ$url\n";

    if ($meta and $meta['uri'] !== $url) {
      $result .= "Stream URL differs: $meta[uri]\n";
    }

    $result .= "$separ\n";

    if (!$meta) {
      $result .= "Stream metadata is unavailable.\n\n";
    } else {
      $result .= "$meta[wrapper_type] wrapper, $meta[stream_type] stream\n\n";

      if ($filters = &$meta['filters']) {
        $result .= '  Filters: '.join(', ', $filters)."\n";
      }

      $flags = array();
      empty($meta['eof']) or $flags[] = 'At EOF';
      empty($meta['timed_out']) or $flags[] = 'Timed out';
      $flags and $result .= '  State: '.join(', ', $flags)."\n";

      if ($filters or $flags) { $result .= "\n"; }
    }

    if (!$options) {
      $result .= "Stream context options are unavailable\n\n";
    } else {
      $options = reset($options);

      if ($headers = &$options['header']) {
        $result .= "Request:\n\n".static::joinHeaders($headers)."\n\n";
        unset($options['header']);
      }

      if ($version = &$options['protocol_version']) {
        $version = sprintf('%1.1f', $version);
      }

      ksort($options);
      $result .= "Context options:\n\n".static::joinIndent($options)."\n\n";
    }

    if ($data = &$meta['wrapper_data']) {
      isset($data['headers']) and $data = $headers['headers'];
      $data and $result .= "Response:\n\n".static::joinHeaders($data)."\n";
    }

    return $result;
  }

  static function joinHeaders(array $list, $indent = '  ') {
    $keyValues = array();

    foreach ($list as $value) {
      if (!is_string($value) or strrchr($value, ':') === false) {
        $keyValues[] = $value;
      } else {
        $keyValues[strtok($value, ':')] = trim(strtok(null));
      }
    }

    return static::joinIndent($keyValues, $indent);
  }

  static function joinIndent(array $keyValues, $indent = '  ') {
    $length = 0;

    foreach ($keyValues as $key => &$value) {
      $length = max($length, strlen($key) + 4);
      $value = is_scalar($value) ? var_export($value, true) : gettype($value);
    }

    return join("\n", S($keyValues, function ($value, $key) use ($indent, $length) {
      return $indent.str_pad("$key:", $length)."$value";
    }));
  }

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

  protected function opened($context, $file = null) {
    if ($log = static::logFile()) {
      if (is_file($log) and filesize($log) >= S::size(cfg('dlLogMax'))) {
        file_put_contents($log, '', LOCK_EX);
      }

      S::mkdirOf($log);
      $info = static::summarize($this->url, $context, $file);
      $ok = file_put_contents($log, "$info\n\n", LOCK_EX | FILE_APPEND);
      $ok or warn("Cannot write to dlLog file [$log].");
    }
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