<?php namespace Sqobot;

class Download {
  static $agents;
  static $maxFetchSize = 20971520;      // 20 MiB

  public $contextOptions;
  public $url;
  public $headers;
  //= str URL-encoded data, array upload data (handles and/or strings)
  public $data;

  public $handle;
  public $responseHeaders;
  public $reply;

  static function agents() {
    if (!isset(static::$agents)) {
      if (is_file($file = 'agents.txt')) {
        $list = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        static::$agents = array_filter(S::trim($list));
      } else {
        log("No User-Agents file $file - use it for better cloaking.");
        static::$agents = array();
      }
    }

    return (array) static::$agents;
  }

  static function randomAgent() {
    if ($all = static::agents()) {
      return $all[ array_rand($all) ];
    }
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
    $this->contextOptions = array(
      'follow_location'   => cfg('dlRedirects') > 0,
      'max_redirects'     => max(0, (int) cfg('dlRedirects')),
      'protocol_version'  => cfg('dlProtocol'),
      'timeout'           => (float) cfg('dlTimeout'),
      'ignore_errors'     => !!cfg('dlFetchOnError'),
    );

    $this->url($url);
    $this->headers = S::downKeys(S::arrize($headers, 'referer'));
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

  function url($new = null, $aliased = true) {
    if ($new) {
      $aliased and $new = realURL($new);

      if (!filter_var($new, FILTER_VALIDATE_URL)) {
        throw new EWrongURL("[$new] doesn't look like a valid URL.");
      }

      $this->url = $new;
      return $this;
    } else {
      return $this->url;
    }
  }

  function method($new = null) {
    return S::access(func_num_args(), $new, $this, $this->contextOptions['method']);
  }

  function post($data, $method = 'post') {
    $this->method($method)->freeData();
    $this->data = urlencode($data);
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
      throw new EDownload("Cannot fopen({$this->url}).");
    }

    $this->responseHeaders = (array) stream_get_meta_data($f);
    return $this;
  }

  function read($limit = -1, $offset = -1) {
    $limit === -1 and $limit = PHP_INT_MAX;
    $limit = min(static::$maxFetchSize, $limit);

    $this->reply = stream_get_contents($this->open()->handle, $limit, $offset);
    if (!is_string($this->reply)) {
      throw new EDownload("Cannot get remote stream contents of [{$this->url}].");
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
    preg_match('~<body>(.*)</body>~ui', $reply, $match) and $reply = $match[1];
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

  function encodeMultipartData() {
    $data = &$this->data;
    $mail = new \MiMeil('', '');

    foreach ($data as $var => &$file) {
      if (is_resource($h = $file['data'])) {
        $read = stream_get_contents($h);
        fclose($h);
        $file['data'] = $read;
      }

      $name = $file['name'];

      $file['headers'] = array(
        'Content-Type' => $mail->MimeByExt(S::ext($name, 'application/octet-stream')),
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

    return S::build($this->headers, function ($value, $header) {
      if (!is_int($header)) {
        $header = preg_replace('~(^|-).~e', 'strtoupper("\\0")', strtolower($header));
      }

      if (is_array($value)) {
        if (!is_int($header)) {
          $value = S::prefix($value, "$header: ");
        }

        return array_values($value);
      } elseif (is_int($header)) {
        return array($value);
      } elseif (($value = trim($value)) !== '') {
        return array("$header: $value");
      }
    });
  }

  function has($header) {
    return isset($this->headers[$header]);
  }

  function header_accept_language() {
    return static::makeQuotientHeader(cfg('dl languages'));
  }

  function header_accept_charset() {
    return static::makeQuotientHeader(cfg('dl charsets'), '*');
  }

  function header_accept() {
    return static::makeQuotientHeader(cfg('dl mimes'), '*/*');
  }

  function header_user_agent() {
    return static::randomAgent();
  }

  function header_cache_control() {
    return mt_rand(0, 1) ? 'max-age=0' : '';
  }

  function header_referer() {
    return 'http://'.$this->header_host().'/';
  }
}