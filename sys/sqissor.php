<?php namespace Sqobot;

abstract class Sqissor {
  public $name;

  //= null not set, Queue
  public $queue;

  public $sliceXML = false;
  public $transaction = true;
  public $queued = array();

  //= Sqissor that has successfully operated
  static function dequeue(array $options = array()) {
    $self = get_called_class();

    return Queue::pass(function ($queue) use ($self) {
      return $self::factory($queue->site, $queue)->sliceURL($queue->url);
    }, $options);
  }

  static function factory($site, Queue $queue = null) {
    $class = cfg("class $site", NS.'$');

    if (!$class) {
      $class = NS.'S'.preg_replace('/\.+(.)/e', 'strtoupper("\\1")', ".$site");
    }

    if (class_exists($class)) {
      return new $class($queue);
    } else {
      throw new ENoSqissor("Undefined Sqissor class [$class] of site [$site].".
                           "You can list custom class under 'class $site=YourClass'".
                           "line in any of Sqobot's *.conf.");
    }
  }

  static function siteNameFrom($class) {
    is_object($class) and $class = get_class($class);

    if (S::unprefix($class, NS.'S')) {
      return strtolower( trim(preg_replace('/[A-Z]/', '.\\0', $class), '.') );
    } else {
      return $class;
    }
  }

  static function make(Queue $queue = null) {
    return new static($queue);
  }

  function __construct(Queue $queue = null) {
    $this->name = static::siteNameFrom($this);
    $this->queue = $queue;
  }

  function sliceURL($url) {
    if ($this->skipURL($url)) {
      log("Skipping queued URL for [".$this->name."]: $url.");
    } else {
      $referrer = dirname($url);
      strrchr($referrer, '/') === false and $referrer = null;
      $this->slice(download($url, $referrer));
    }

    return $this;
  }

  //
  // Overridable method used to determine if given $url has already been crawled
  // (present in database). Used by ->sliceURL() to optimize and not crawl already
  // processed pages - some items can be enqueued multiple times depending on
  // the resource being crawled (links from different pages might lead to one place).
  //
  // Unless overriden always returns false meaning no URLs are skipped.
  //
  //* $url str  - URL to test database against. The same URL given to ->sliceURL()
  //  and ->enqueue().
  //
  //= bool
  //
  function skipURL($url) { return false; }

  //
  // Processes given $data string. 'Processing' means that it's parsed and new
  // URLs are ->enqueue()'d, Pool entries created and so on. The actual purpose
  // of the robot.
  //
  //* $data str   - typically is a fetched URL content unless ->sliceURL() is
  //  overriden in a child class.
  //* $transaction  null read value from ->$transaction
  //                bool whether to wrap processing in a database transaction or not
  //
  //? slice('<!DOCTYPE html><html>...</html>')
  //
  function slice($data, $transaction = null) {
    if ( isset($transaction) ? $transaction : $this->transaction ) {
      $self = $this;
      atomic(function () use (&$data, $self) {
        $self->slice($data, false);
      });
    } else {
      $this->sliceXML and $data = parseXML($data);
      $this->doSlice($data, $this->extra());
    }

    return $this;
  }

  //
  // Overridable method that contains the actual page parsing logics.
  //
  //* $data str     - value given to ->slice() - typically fetched URL (HTML code).
  //* $extra array  - associated with this queue item (see ->enqueue()). The same
  //  value can be accessed by ->extra().
  //
  //= mixed return value is ignored
  //
  //? doSlice('<!DOCTYPE html><html>...</html>', array('a' => 'b'))
  //
  protected abstract function doSlice($data, array $extra);

  //
  // Returns extra data associated with this queue item. See ->enqueue().
  // Empty array is returned if no extra was assigned.
  //
  //= array
  //
  function extra() {
    return $this->queue ? (array) $this->queue->extra() : array();
  }

  //
  // Returns a queued item this Sqissor is processing. Might be unset if it's
  // called directly; typically a Sqissor instance is created by pulling an item
  // from database queue (see Queue).
  //
  //* $must bool  - errors if true and no queue item is assigned, otherwise returns null.
  //
  //= null  if no queue item is assigned and $must is false
  //= Queue instance - $this->queue
  //
  function queue($must = true) {
    if ($this->queue) {
      return $this->queue;
    } elseif ($must) {
      throw new ESqissor($this, "{$this->name} expects an assigned ->queue.");
    }
  }

  //
  // Places a new item into the queue.
  //
  //* $url str      - URL to be crawled.
  //* $site str     - Sqissor-descendant class identifier used to process given $url.
  //  Dots can be used to create namespaces and group similar parsers together.
  //  The actual class name responding to such identifier is typically of form
  //  'Sqobot\\S' + pretty$site) where pretty() makes 'foo.bar.bar' => 'FooBarBaz'.
  //* $extra array  - extra data to associate with the new queue item. It's passed
  //  to $site verbatim. Useful when more than one page is needed to be parsed
  //  in order to construct the full entity (Pool item) - $extra can accumulate
  //  such data until the final $site handler brings them all together and
  //  constructs the actual Pool item.
  //
  //? enqueue('http://example.com/post/123456', 'example.parser', array('a' => 'b'))
  //      // Enqueues given URL to be handled by 'example.parser' which usually
  //      // is Sqobot\SExampleParser class. $extra array is accessible from within
  //      // its instance by $this->extra().
  //
  function enqueue($url, $site, array $extra = array()) {
    $item = Queue::make(compact('url', 'site'))
      ->extra($extra)
      ->createIgnore();

    // $id will be 0 if this url + site combination is already enqueued.
    $item->id and $this->queued[] = $item;
    return $this;
  }

  //
  // Matches given $regexp against string $str optionally returning pocket of given
  // index $return. If $return is null all pockets are returned. If not an error
  // occurs. Same happens if preg_last_error() wasn't 0 (e.g. for non-UTF-8 string
  // when matching with /u (PCRE8) modifier.
  //
  //* $str str    - input string to match $regexp against like 'To MATCH 123, foo.'.
  //* $regexp str - full regular expression like '/match (\d+)/i'.
  //* $return null  get all matches,
  //          true  get all matches but fail if $regexp has failed,
  //          int   pocket index to return
  //
  //= null    if $return is null and none matched or if there's no match with
  //          $return index.
  //= array   if $return is null.
  //= str     given match by index.
  //
  //? regexp('To MATCH 123, foo.', '/match (\d+)/i')
  //      //=> array('MATCH 123', '123')
  //? regexp('To MATCH 123, foo.', '/match (\d+)/i', 1)
  //      //=> '123'
  //? regexp('To MATCH 123, foo.', '/match (\d+)/i', 4444)
  //      //=> null
  //? regexp('foo!', '/bar/')
  //      //=> null
  //? regexp('foo!', '/bar/')
  //      // exception occurs
  //? regexp('foo!', '/bar/', 1)
  //      // exception occurs
  //
  function regexp($str, $regexp, $return = null) {
    if (preg_match($regexp, $str, $match)) {
      if (isset($return) and $return !== true) {
        return S::pickFlat($match, $return);
      } else {
        return $match;
      }
    } elseif ($error = preg_last_error()) {
      throw new ERegExpError($this, "Regexp error code #$error for $regexp");
    } elseif (isset($return)) {
      throw new ERegExpNoMatch($this, "Regexp $regexp didn't match anything.");
    }
  }

  //
  // Takes all matches of given $regexp against string $str. $flags specify
  // preg_match_all() flags; if boolean true is set to PREG_SET_ORDER. Errors if
  // no matches were found. Checks for preg_last_error() as well as ->regexp() does.
  //
  //* $str str    - input string to match $regexp against like 'A 123, b 456.'.
  //* $regexp str - full regular expression like '/[a-z] (\d+)/i'.
  //* $flags int given to preg_match_all(), true set to PREG_SET_ORDER
  //
  //= array   all matches of $regexp that were found in $str.
  //
  //? regexpAll('A 123, b 456.', '/[a-z] (\d+)/i')
  //      //=> array(array('A 123', 'b 456'), array('123', '456'))
  //? regexpAll('A 123, b 456.', '/[a-z] (\d+)/i', true)
  //      //=> array(array('A 123', '123'), array('b 456', '456'))
  //? regexpAll('A 123, b 456.', '/[a-z] (\d+)/i', PREG_SET_ORDER)
  //      // equivalent to the above
  //? regexpAll('...', '...', PREG_SET_ORDER | PREG_OFFSET_CAPTURE)
  //      // calls preg_match_all() with these two flags and returns
  //      // an array with pockets and their offsets in original string
  //
  function regexpAll($str, $regexp, $flags = 0) {
    $flags === true and $flags = PREG_SET_ORDER;

    if (preg_match_all($regexp, $str, $matches, $flags)) {
      return $matches;
    } elseif ($error = preg_last_error()) {
      throw new ERegExpError($this, "Regexp error code #$error for $regexp");
    } else {
      throw new ERegExpNoMatch($this, "Regexp $regexp didn't match anything.");
    }
  }

  //
  // Creates associative array from string $str based on given $regexp using
  // pocket with index $keyIndex as resulting array's keys and $valueIndex
  // as its value (if null uses entire match as a value).
  // Errors if no matches were found or on preg_last_error() (see ->regexpAll()).
  //
  //* $str str    - input string to match $regexp against like 'A 123, b 456.'.
  //* $regexp str - full regular expression like '/[a-z] (\d+)/i'.
  //* $keyIndex int - pocket index to set array's keys from; if there were
  //  identical keys earlier occurrences are overriden by latter.
  //* $valueIndex null  set array's value to the entire match,
  //              int   set it to this pocket index's value or null if none
  //
  //= hash
  //
  //? regexpMap( 'A 123, b 456.', '/[a-z] (\d+)/i', 1)
  //      //=> array('A' => array('A', '123'), 'b' => array('b', '456'))
  //? regexpMap( 'A 123, b 456.', '/[a-z] (\d+)/i', 1, 1)
  //      //=> array('A' => '123', 'b' => '456')
  //? regexpMap( 'A 123, b 456.', '/[a-z] (\d+)/i', 1, 0)
  //      //=> array('A' => 'A', 'b' => 'b')
  //? regexpMap( 'A 1, A 2.', '/[a-z] (\d+)/i', 1)
  //      //=> array('A' => array('A', '2'))
  //? regexpMap( 'A 1, a 2.', '/[a-z] (\d+)/i', 1, 1)
  //      //=> array('A' => '1', 'a' => '2')
  //? regexpMap( 'A 123, b 456.', '/[a-z] (\d+)/i', 1, 4444)
  //      //=> array('A' => null, 'b' => null)
  //
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

  //
  // Creates an associative array of pages index => URL by matching $pageRegexp
  // capturing a single page against string $str. If $onlyAfter is set removes
  // pages with indexes prior to this value from result (useful to avoid going
  // backwards and crawling already processed pages).
  // Errors if no matches were found or on preg_last_error() (see ->regexpAll()).
  //
  //* $str str    - input string containing list of pages (e.g. HTML).
  //* $pageRegexp str - regular expression matching one page entry with URL as
  //  first pocket and page number - as second. Alternatively it may use named
  //  pockets instead of indexes like '(?P<page>\d+)' and/or '(?P<url>[\w:/]+)'.
  //* $onlyAfter int  - removes page numbers prior to this number from result.
  //
  //= hash int pageNumber => 'url'
  //
  //? matchPages('<a href="page2.html">3</a><a href="page3.html">4</a>...',
  //             '/href="([^"]+)">(\d+)/')
  //      //=> array(3 => 'page2.html', 4 => 'page3.html', ...)
  //? matchPages('...as above...', '...', 3)
  //      //=> array(4 => 'page3.html', ...)
  //? matchPages('...as above...', '...', 4444)
  //      //=> array()
  //? matchPages('foo!', '...')
  //      // exception occurs
  //? matchPages('<b>3</b>: page2.html | <b>4</b>: page3.html | ...',
  //             '/<b>(?P<page>\d+)<\/b>\s*(?P<url>[\w.]+)/')
  //      //=> array(3 => 'page2.html', 4 => 'page3.html', ...)
  //
  function matchPages($str, $pageRegexp, $onlyAfter = 0) {
    $links = $this->regexpAll($str, $pageRegexp, true);

    $pages = S::keys($links, function ($match) {
      $page = empty($match['page']) ? $match[2] : $match['page'];
      $url = empty($match['url']) ? $match[1] : $match['url'];
      return array($page, $url);
    });

    $onlyAfter and $pages = S::keep($pages, '#? > '.((int) $onlyAfter));
    return $pages;
  }

  //
  // Removes HTML tags and decodes entities in $html string producing its plain version.
  //
  //* $html str     - like 'See <em>this</em> &amp; <em>that</em>.'.
  //* $charset str  - encoding name $html is in like 'cp1251'.
  //
  //= str     plain text version of $html
  //
  //? htmlToText('See <em>this</em> &amp; <em>that</em>.')
  //      //=> 'See this & that.'
  //? htmlToText('Decodes all &#039; apostrophes and &quot; quotes too.')
  //      //=> 'Decodes all \' apostrophes and " quotes too.'
  //
  function htmlToText($html, $charset = 'utf-8') {
    return html_entity_decode(strip_tags($html), ENT_QUOTES, $charset);
  }
}