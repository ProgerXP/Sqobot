<?php

class Upweave {
  //= str name displayed to the user in his browser's 'Save To' dialog
  public $name;
  //= null autodetect, int bytes
  public $size;
  //= null defaults to 'application/octet-stream', str
  public $mime;
  //= null autodetect for files, int timestamp, false don't use 'Last-Modified'
  public $mtime;

  //= bool
  public $inline;
  //= int seconds (0 disables caching), null defaults to 1 hour
  public $expires;
  //= null autoset, false don't use, str for 'Etag' and 'If-None-Match'
  public $etag;
  //= bool if partial downloads are enabled or not
  public $partial;
  //= int defaults to 4 MiB or the amount of free memory, str like '123K'
  public $chunk;
  //= null autodetect (leaving 1M margin)
  public $freeMem;
  //= null when sent from data or resource stream, str path to file
  protected $localFile;

  //= hash 'Name' => 'value'
  protected $headers;
  //= str HTTP "code status"
  protected $status;
  //= str, Closure
  protected $data;

  static function freeMem() {
    static $margin = 1048576;   // 1 MiB.
    return static::strToSize(ini_get('memory_limit')) - memory_get_usage() - $margin;
  }

  static function strToSize($str) {
    $str = trim($str);

    switch (substr($str, -1)) {
    case 'G': case 'g':  $str *= 1024;
    case 'M': case 'm':  $str *= 1024;
    case 'K': case 'k':  $str *= 1024;
    }

    return (int) $str;
  }

  static function sendFile($file, $options = array()) {
    is_array($options) or $options = array('mime' => $options);
    $options += array('quit' => true);
    return static::make($options)->fromFile($file)->send($options['quit']);
  }

  static function sendData($data, $options = array()) {
    is_array($options) or $options = array('name' => $options);
    $options += array('quit' => true);
    return static::make($options)->fromData($data)->send($options['quit']);
  }

  static function make(array $options = array()) {
    return new static($options);
  }

  function __construct(array $options = array()) {
    $this->set($options);
  }

  function set(array $options) {
    foreach ($options as $prop => &$value) { $this->$prop = $value; }
    return $this;
  }

  function error($msg) {
    throw new InvalidArgumentException(get_class($this).": $msg");
  }

  function getHeader($name) {
    $key = 'HTTP_'.str_replace('-', '_', strtoupper($name));
    return isset($_SERVER[$key]) ? $_SERVER[$key] : null;
  }

  function fromFile($file) {
    if (!file_exists($file)) {
      return $this->error("File [$file] to be sent doesn't exist.");
    } elseif (!is_file($file)) {
      return $this->error("File [$file] to be sent isn't a regular file.");
    } elseif (!is_readable($file)) {
      return $this->error("File [$file] to be sent isn't readable.");
    }

    clearstatcache();

    isset($this->name)    or $this->name = basename($file);
    isset($this->size)    or $this->size = filesize($file);
    isset($this->mtime)   or $this->mtime = filemtime($file);

    if (!isset($this->etag)) {
      $this->etag = sprintf('%x-%x-%x', fileinode($file), $this->size, $this->mtime);
    }

    $this->localFile = $file;
    return $this->fromData(fopen($file, 'rb'));
  }

  // $data - string or resource (don't call fclose() on it, it may be used in send()).
  function fromData($data) {
    $isBuf = !is_resource($data);
    $isBuf and $data = (string) $data;

    extract((array) $this, EXTR_SKIP);
    $name = basename($name);

    if (!isset($size)) {
      if ($isBuf) {
        $size = strlen($data);
      } else {
        return $this->error('Cannot detect $size for a resource $data.');
      }
    }

    isset($expires) or $expires = 3600;

    if (!isset($etag) and $mtime) {
      $etag = sprintf('%08x-%08x', $size, $mtime);
    }

    $this->headers = array();

    if (!is_int($mtime) or $expires <= 0) {
      $this->headers += array(
        'Cache-Control'   => 'no-cache',
        'Expires'         => -1,
      );
    } else {
      $result = $this->ifActual($expires, $mtime, $etag);

      if (is_array($result)) {
        $this->headers += $result;
      } else {
        $this->status = '304 Not Modified';
        return $this;
      }
    }

    $this->data = $this->handleData($data);
    return $this;
  }

  //= true if client has up-to-date cache, array of headers with caching info
  protected function ifActual($expires, $mtime, $etag = null) {
    if (strtotime($this->getHeader('If-Modified-Since')) == $mtime
        or (isset($etag) and $this->getHeader('If-None-Match') == $etag)) {
      return true;
    } else {
      $headers = array(
        'Pragma'          => 'public',
        'Cache-Control'   => "max-age=$expires",
        'Date'            => gmdate('D, d M Y H:i:s').' GMT',
        'Expires'         => gmdate('D, d M Y H:i:s', time() + $expires).' GMT',
      );

      $etag and $headers['Etag'] = $etag;
      $mtime and $headers['Last-Modified'] = gmdate('D, d M Y H:i:s', $mtime).' GMT';

      return $headers;
    }
  }

  protected function handleData($data) {
    $freeMem = isset($this->freeMem) ? $this->freeMem : static::freeMem();
    $partial = (!isset($this->partial) or $this->partial);
    $disposition = $this->inline ? 'inline' : 'attachment';

    $start = 0;
    $end = $this->size - 1;
    $partial and $this->headers += $this->setupRange($start, $end, $this->size);

    $partial = ($start > 0 or $end < $this->size - 1);
    $this->status = $partial ? '206 Partial Content' : '200 OK';
    $remaining = $end - $start + 1;

    $this->headers += array(
      'Content-Type'              => $this->mime ?: 'application/octet-stream',
      'Content-Length'            => $remaining,
      'Content-Description'       => 'File Transfer',
      'Content-Disposition'       => $disposition.'; filename="'.$this->name.'"',
      'Content-Transfer-Encoding' => 'binary',
    );

    if (!$partial and $this->localFile) {
      if ($this->freeMem >= $this->size) {
        return file_get_contents($this->localFile);
      } else {
        return function ($self) { readfile($self->localFile); };
      }
    } elseif ( $isBuf ? !isset($data[$start]) : (fseek($data, $start) != 0) ) {
      $this->headers['Content-Range'] = '*/'.$this->size;   // as per HTTP spec.
      $this->status = '416 Requested Range Not Satisfiable';
      return "Cannot seek to the requested range [$start].";
    } elseif ($this->freeMem >= $remaining) {
      return $isBuf ? substr($data, $start, $remaining) : fread($data, $remaining);
    } elseif ($isBuf) {
      return function () use ($data, $start, $remaining) {
        echo substr($data, $start, $remaining);
      };
    } else {
      $this->data = function ($self) use ($data, $start, $end) {
        set_time_limit(0);
        while (ob_get_level()) { ob_end_flush(); }
        flush();

        $chunk = $self->chunk ? $self->strToSize($this->chunk)
                              : max(4 * 1024 * 1024, $this->freeMem);

        if ($end + 1 >= $self->size) {
          fpassthru($data);
        } else {
          while (!feof($data) and !connection_aborted() and $start <= $end) {
            echo fread($data, min($chunk, $end - $start + 1));
            $start += $chunk;
          }
        }
      };
    }
  }

  //= array of headers
  protected function setupRange(&$start, &$end, $size) {
    if ($range = $this->getHeader('Range') and
        preg_match('/bytes=\h*(\d*)-(\d*)[\D.*]?/i', $range, $matches)) {
      if ($matches[0] === '') {
        $start = $size - $matches[1];
      } else {
        $start = (int) $matches[0];
        empty($matches[1]) or $end = (int) $matches[1];
      }
    }

    return array(
      'Accept-Ranges'     => 'bytes',
      'Content-Range'     => "$start-$end/".($end + 1),
      'Content-Length'    => $end - $start + 1,
    );
  }

  function send($quit = false) {
    if (!$this->status) {
      return $this->error('No data prepared - call fromXXX() before send().');
    }

    header('HTTP/1.0 '.$this->status);
    header('Status: '.$this->status);

    foreach ($this->headers as $header => $value) {
      header("$header: $value");
    }

    is_object($data = $this->data) ? $data($this) : print $data;
    $quit and exit(0);
  }
}