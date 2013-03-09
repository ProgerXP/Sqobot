<?php namespace Sqobot;

class ESqissor extends Error { }

abstract class Sqissor {
  //= null not set, Queue
  public $queue;

  public $sliceXML = false;
  public $transaction = true;
  public $queued = array();

  //= Sqissor that has successfully operated
  static function dequeue($site = null, $table = null) {
    return Queue::pass(function ($queue) {
      return static::factory($queue->site, $queue)->sliceURL($queue->url);
    }, $site, $table);
  }

  static function make(Queue $queue = null) {
    return new static($queue);
  }

  function __construct(Queue $queue = null) {
    $this->queue = $queue;
  }

  function sliceURL($url) {
    return $this->slice(download($url));
  }

  function slice($data, $transaction = null) {
    if ( isset($transaction) ? $transaction : $this->transaction ) {
      $self = $this;
      return atomic(function () use (&$data, $self) {
        return $self->slice($data, false);
      });
    } else {
      $this->sliceXML and $data = parseXML($data);
      $this->doSlice($data, $this->extra());
      return $this;
    }
  }

  protected abstract function doSlice($data, array $extra);

  //= array
  function extra() {
    return $this->queue ? (array) $this->queue->extra() : array();
  }

  function enqueue($url, $site, array $extra = array()) {
    $this->queued[] = Queue::make(compact('url', 'site'))
      ->extra($extra)
      ->create();

    return $this;
  }

  function regexp($str, $regexp, $return = null) {
    if (preg_match($regexp, $str, $match)) {
      return isset($return) ? S::pickFlat($match, $return) : $match;
    } elseif ($error = preg_last_error()) {
      throw new ERegExpError("Regexp error code #$error for $regexp");
    } elseif (isset($return)) {
      throw new ERegExpNoMatch("Regexp $regexp didn't match anything.");
    }
  }

  function regexpAll($str, $regexp, $flags = 0) {
    $flags === true and $flags = PREG_SET_ORDER;

    if (preg_match_all($regexp, $str, $matches)) {
      return $matches;
    } elseif ($error = preg_last_error()) {
      throw new ERegExpError("Regexp error code #$error for $regexp");
    } else {
      throw new ERegExpNoMatch("Regexp $regexp didn't match anything.");
    }
  }

  function regexpMap($str, $regexp, $keyIndex, $valueIndex = null) {
    return S::keys(
      $this->regexpAll($str, $regexp, true),
      function ($match) use ($keyIndex, $valueIndex) {
        if (isset($valueIndex)) {
          $value = &$match[$valueIndex];
          return array($match[$keyIndex], $value);
        } else {
          return $match[$keyIndex];
        }
      }
    );
  }

  //= hash int page => 'url'
  function matchPages($data, $pageRegexp, $onlyAfter = 0) {
    $links = $this->regexpAll($data, $pageRegexp, true);

    $pages = S::keys($links, function ($match) {
      $page = empty($match['page']) ? $match[2] : $match['page'];
      $url = empty($match['url']) ? $match[1] : $match['url'];
      return array($page, $url);
    });

    $onlyAfter and $pages = S::keep($pages, '#? > '.((int) $onlyAfter));
    return $pages;
  }

  function htmlToText($html, $charset = 'utf-8') {
    return html_entity_decode(strip_tags($map), ENT_QUOTES, $charset);
  }
}