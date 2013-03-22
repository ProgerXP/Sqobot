<?php
/*
  Downwind - stream context wrapper for flexible up- & download.
  in public domain | by Proger_XP | http://proger.i-forge.net/PHP_Downwind

  Standalone unless you're doing multipart/form-data upload() - in this case
  http://proger.i-forge.net/MiMeil class is necessary (see encodeMultipartData()).
  Note that you'll need to not only require() it but also set up as described there.
*/

class Downwind {
  //= array of str
  static $agents;
  static $maxFetchSize = 20971520;      // 20 MiB

  public $contextOptions = array();
  public $url;
  public $headers;
  //= str URL-encoded data, array upload data (handles and/or strings)
  public $data;

  public $handle;
  public $responseHeaders;
  public $reply;

  static function queryStr(array $query, $noQuestionMark = false) {
    $query = http_build_query($query, '', '&');
    if (!$noQuestionMark and "$query" !== '') { $query = "?$query"; }
    return $query;
  }

  static function randomAgent() {
    return static::$agents ? static::$agents[ array_rand(static::$agents) ] : '';
  }

  static function makeQuotientHeader($items, $append = null) {
    is_string($items) and $items = array_filter(explode(' ', $items));
    shuffle($items);

    $count = mt_rand(1, 4) + isset($append);
    $qs = range(1, 1 / $count, 1 / $count);
    isset($append) and array_splice($items, $count - 1, 0, array($append));

    $parts = array();

    for ($i = 0; $i < $count and $items; ++$i) {
      $parts[] = array_shift($items).($i ? ';q='.round($qs[$i], 1) : '');
    }

    return join(',', $parts);
  }

  static function it($url, $headers = array()) {
    return static::make($url, $headers)->fetchData();
  }

  static function make($url, $headers = array()) {
    return new static($url, $headers);
  }

  function __construct($url, $headers = array()) {
    $this->url($url);

    is_array($headers) or $headers = array('referer' => $headers);
    $this->headers = array_change_key_case($headers);
  }

  function __destruct() {
    $this->close()->freeData();
  }

  function freeData($name = null) {
    if (is_array($this->data)) {
      if (!isset($name)) {
        foreach ($this->data as &$file) {
          is_resource($h = $file['data']) and fclose($h);
        }
      } elseif (isset($this->data[$name])) {
        is_resource($h = $this->data[$name]['data']) and fclose($h);
        unset($this->data[$name]);
      }
    }

    isset($name) or $this->data = null;
    return $this;
  }

  function url($new = null) {
    if ($new) {
      if (!filter_var($new, FILTER_VALIDATE_URL)) {
        throw new InvalidArgumentException("[$new] doesn't look like a valid URL.");
      }

      $this->url = $new;
      return $this;
    } else {
      return $this->url;
    }
  }

  function urlPart($part) {
    return parse_url($this->url, $part);
  }

  function addQuery($vars) {
    is_array($vars) or $vars = array($vars => 1);
    return $this->query($vars + $this->query());
  }

  function query(array $vars = null) {
    if (func_num_args()) {
      $this->url = strtok($this->url, '?').static::queryStr($vars);
      return $this;
    } else {
      strtok($this->url, '?');
      parse_str(strtok(null), $vars);
      return $vars;
    }
  }

  //= str method in upper case
  function method($new = null) {
    if (func_num_args()) {
      $this->contextOptions['method'] = strtoupper($new);
      return $this;
    } else {
      return $this->contextOptions['method'];
    }
  }

  function post(array $data, $method = 'post') {
    $this->method($method)->freeData();
    $this->data = static::queryStr($data, true);
    return $this;
  }

  //* $data str, resource - resources are freed by this instance.
  function upload($var, $originalName, $data) {
    $this->method('post')->freeData($var);
    is_array($this->data) or $this->data = array();
    $this->data[$var] = array('data' => $data, 'name' => $originalName);
    return $this;
  }

  function basicAuth($user, $password) {
    $this->headers['Authorization'] = 'Basic '.base64_encode("$user:$password");
    return $this;
  }

  function open() {
    $f = $this->handle = fopen($this->url, 'rb', false, $this->createContext());
    if (!$f) {
      throw new RuntimeException("Cannot fopen({$this->url}).");
    }

    $this->responseHeaders = (array) stream_get_meta_data($f);
    return $this;
  }

  function read($limit = -1, $offset = -1) {
    $limit === -1 and $limit = PHP_INT_MAX;
    $limit = min(static::$maxFetchSize, $limit);

    $this->reply = stream_get_contents($this->open()->handle, $limit, $offset);
    if (!is_string($this->reply)) {
      throw new RuntimeException("Cannot get remote stream contents of [{$this->url}].");
    }

    return $this;
  }

  function close() {
    $f = $this->handle and fclose($f);
    $this->handle = null;
    return $this;
  }

  function fetch($limit = -1) {
    $this->read($limit)->close();
    return $this->freeData();   // clean up after request has been completed.
  }

  function fetchData($limit = -1) {
    return $this->fetch($limit)->reply;
  }

  //= str HTML within <body> or entire response if no such tag
  function docBody() {
    $reply = $this->fetchData();
    preg_match('~<body>(.*)</body>~uis', $reply, $match) and $reply = $match[1];
    return trim($reply);
  }

  function createContext() {
    $options = array('http' => $this->contextOptions());
    return stream_context_create($options);
  }

  function contextOptions() {
    $options = $this->contextOptions;

    if (isset($this->data)) {
      if (is_array($this->data)) {
        $this->encodeMultipartData();
      } else {
        $this->headers['Content-Type'] = 'application/x-www-form-urlencoded';
      }

      $this->headers['Content-Length'] = strlen($this->data);
      $options['content'] = $this->data;
    }

    return array('header' => $this->normalizeHeaders()) + $options;
  }

  protected function encodeMultipartData() {
    $data = &$this->data;
    $mail = new MiMeil('', '');
    $mail->SetDefaultsTo($mail);

    foreach ($data as $var => &$file) {
      if (is_resource($h = $file['data'])) {
        $read = stream_get_contents($h);
        fclose($h);
        $file['data'] = $read;
      }

      $name = $file['name'];
      $ext = ltrim(strrchr($name, '.'), '.');

      $file['headers'] = array(
        'Content-Type' => $mail->MimeByExt($ext, 'application/octet-stream'),
        'Content-Disposition' => 'form-data; name="'.$var.'"; filename="'.$name.'"',
      );
    }

    // join all data strings into one string using generated MIME boundary.
    $data = $mail->BuildAttachments($data, $this->headers, 'multipart/form-data');
  }

  //= array of scalar like 'Accept: text/html'
  function normalizeHeaders() {
    foreach (get_class_methods($this) as $func) {
      if (substr($func, 0, 7) === 'header_') {
        $header = strtr(substr($func, 7), '_', '-');

        if (!isset( $this->headers[$header] )) {
          $this->headers[$header] = $this->$func();
        }
      }
    }

    $result = array();

    foreach ($this->headers as $header => $value) {
      if (!is_int($header)) {
        $header = preg_replace('~(^|-).~e', 'strtoupper("\\0")', strtolower($header));
      }

      if (is_array($value)) {
        if (!is_int($header)) {
          foreach ($value as &$s) { $s = "$header: "; }
        }

        $result = array_merge($result, array_values($value));
      } elseif (is_int($header)) {
        $result[] = $value;
      } elseif (($value = trim($value)) !== '') {
        $result[] = "$header: $value";
      }
    }

    return $result;
  }

  function has($header) {
    return isset($this->headers[$header]);
  }

  function header_accept_language($str = '') {
    return $str ? static::makeQuotientHeader($str) : '';
  }

  function header_accept_charset($str = '') {
    return $str ? static::makeQuotientHeader($str, '*') : '';
  }

  function header_accept($str = '') {
    return $str ? static::makeQuotientHeader($str, '*/*') : '';
  }

  function header_user_agent() {
    return static::randomAgent();
  }

  function header_cache_control() {
    return mt_rand(0, 1) ? 'max-age=0' : '';
  }

  function header_referer() {
    return 'http://'.$this->urlPart(PHP_URL_HOST).'/';
  }
}