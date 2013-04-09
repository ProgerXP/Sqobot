<?php namespace Sqobot;

class Qurl {
  // Must be in order of regexp in parseDeltas().
  static $deltaDefaults = array('initial' => 1, 'step' => 1, 'end' => 0,
                                'stops' => false, 'pool' => 50);

  public $url;
  public $site;
  public $deltas;

  static function all($groups) {
    return S::build((array) $groups, function ($group) {
      $group = trim($group);

      $items = cfgGroup("qurl $group");
      $item = cfg("qurl $group") and $items += array('' => $item);
      $group === '' or $items = S::keys($items, array('*#"? ?"', $group));

      return $items;
    });
  }

  static function parse($str) {
    list($site, $url, $deltas) = explode(' ', $str, 3) + array('', '', '1');
    return static::make($url, $site)->deltas($deltas);
  }

  static function parseDeltas($str) {
    if (!is_array($str)) {
      $regexp = '~^ (-?\d+)? ([-+]\d+)? (?: =(\d+) (\.)?)? (?: /(\d+))? ()$~x';

      if (preg_match($regexp, trim($str), $match)) {
        $str = array_combine(array_keys(static::$deltaDefaults),
                             array_slice($match, 1, -1));
      } else {
        $str = null;
      }
    }

    if ($str) {
      $str['stops'] and $str['stops'] = 1;
      return S(S::omit($str, '? === ""'), '(int) ?') + static::$deltaDefaults;
    }
  }

  static function make($url, $site) {
    return new static($url, $site);
  }

  function __construct($url, $site) {
    if (!$url or !$site) {
      throw new \InvalidArgumentException('Missing $url and/or $site arguments for '.
                                          get_class($this).' constructor.');
    }

    $this->url = $url;
    $this->site = $site;
  }

  function deltas($new = null) {
    if (func_num_args()) {
      $this->deltas = $new;
      return $this;
    } else {
      $deltas = static::parseDeltas($this->deltas);

      if (!$deltas) {
        warn("Cannot parse qurl deltas string [{$this->deltas}] - using defaults.");
        $deltas = static::$deltaDefaults;
      }

      return $this->deltas = $deltas;
    }
  }

  function initial($new = null) {
    return $this->delta(__FUNCTION__, $new);
  }

  function step($new = null) {
    return $this->delta(__FUNCTION__, $new);
  }

  function end($new = null) {
    return $this->delta(__FUNCTION__, $new);
  }

  function stops($new = null) {
    return $this->delta(__FUNCTION__, $new);
  }

  function pool($new = null) {
    return $this->delta(__FUNCTION__, $new);
  }

  function delta($key, $new = null) {
    $deltas = $this->deltas();

    if (isset($new)) {
      $deltas[$key] = $new;
      $this->deltas = $deltas;
      return $this;
    } else {
      return S::pickFlat($deltas, $key);
    }
  }

  function lastQueued($table = null) {
    $stmt = $this->fetchQueued("ORDER BY created DESC, id DESC LIMIT 1", '*', $table);
    $row = $stmt->fetch();
    $stmt->closeCursor();
    return $row;
  }

  function countQueued($table = null) {
    $stmt = $this->fetchQueued('', 'COUNT(1) AS count', $table);
    $row = $stmt->fetch();
    $stmt->closeCursor();
    return $row->count;
  }

  function fetchQueued($sql, $columns = '*', $table = null) {
    $table or $table = Queue::tableName();
    $url = preg_replace('~\$|\{[^}]*\}~', '%', $this->url);
    $sql = "SELECT $columns FROM `$table` WHERE site = ? AND url LIKE ? $sql";
    return exec($sql, array($this->site, $url));
  }

  function enqueue($page, $table = null) {
    $fields = Queue::hop($this->makeURL($page), $this->site);
    $item = new Queue($fields);
    $table and $item->table = $table;
    $item->createIgnore();
    return $item->id ? $item : null;
  }

  function pageFrom($url) {
    $regexp = preg_quote($this->url, '~');
    $regexp = preg_replace('~\\\\\$|\\\\\{[^}]*\\\\\}~', '(.*?)', $regexp);
    if (preg_match("~^$regexp$~", $url, $match)) { return $match[1]; }
  }

  function makeURL($page) {
    $maker = function ($match) use ($page) {
      if ($match[0] === '$') {
        return $page;
      } elseif (($code = trim($match[1])) !== '') {
        $p = $page;
        return eval('namespace '.__NAMESPACE__.'; return '.$code.';');
      }
    };

    return preg_replace_callback('~\$|\{([^}]*)\}~', $maker, $this->url);
  }
}