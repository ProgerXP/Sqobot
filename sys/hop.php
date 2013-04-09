<?php namespace Sqobot;

class Hop {
  // Must be in order so that longer operators go before their prefixes, e.g.
  // '<>' must go before '<' or the latter will match instead of '<>'.
  static $operators = array('=', '<>', '>=', '<=', '>', '<');

  //= null when not initialized by all(), hash of Hop
  static $all;

  protected $sites = array();
  protected $pattern;
  protected $hops = array();

  static function compare($left, $operator, $right) {
    switch ($operator) {
    case '>':   return $left >  $right;
    case '>=':  return $left >= $right;
    case '<':   return $left <  $right;
    case '<=':  return $left <= $right;
    case '=':   return $left =  $right;
    case '<>':  return $left != $right;
    }
  }

  //= bool
  static function tryAll(&$url, &$site) {
    do {
      $restart = false;
      $seen = array();

      foreach (static::all() as $i => $hop) {
        $new = $hop->rewrite($url, $site);

        if (isset($new)) {
          if (isset($seen[$i])) {
            $msg = "Curricular hopping while rewriting page in [$url] of site [$site]:".
                   join(' -> ', $seen)." -> {$hop->pattern}.";
            warn($msg);
          } else {
            $seen[$i] = $hop->pattern;
            $url = $new;
            $restart = true;
          }

          break;
        }
      }
    } while ($restart);

    return func_get_arg(0) !== $url;
  }

  //= hash of Hop
  static function all() {
    if (static::$all) {
      return static::$all;
    } else {
      $result = array();

      foreach (cfgGroup('hop') as $key => $hops) {
        list($sites, $pattern) = explode(' ', "$key ", 3);

        $hop = $result[] = static::make()
          ->pattern($pattern)
          ->hop($hops);

        foreach (explode(',', $sites) as $site) {
          $site === '' or $hop->site(trim($site));
        }
      }

      return static::$all = $result;
    }
  }

  static function make() {
    return new static;
  }

  function pattern($new = null) {
    if (func_num_args()) {
      $new = trim($new);
      $new === '' and $new = '\d+/?$';
      $new[0] === '~' or $new = "~$new~";
      $this->pattern = $new;
      return $this;
    } else {
      return $this->pattern;
    }
  }

  function site($add) {
    $this->sites[] = $add;
    return $this;
  }

  function hop($str) {
    is_array($str) or $str = explode(',', $str);
    $ops = static::$operators;

    foreach ($str as $part) {
      $part = trim($part);
      list($conds, $target) = explode('->', $part.'->', 2);
      $target === '' or $target = (int) $target;

      $conds = S::build(explode(',', $conds), function ($cond) use ($ops, $part) {
        $cond = trim($cond);

        foreach ($ops as $op) {
          if (!strncasecmp($cond, $op, strlen($op))) {
            $value = substr($cond, strlen($op));

            if (is_numeric($value)) {
              return array(array('op' => $op, 'value' => (int) $value));
            } else {
              warn("Non-numeric value [$cond] of a hop setting [$part] - skipping.");
            }
          }
        }

        warn("Unrecognized operator in hop setting [$part] - skipping.");
      });

      if (!$conds) {
        warn("Skipping entire hop setting [$part] - no conditions recognized.");
      } elseif ($target === '' and (count($conds) > 1 or $conds[0]['op'] === '<>')) {
        warn("Skipping entire hop setting [$part] - '->' cannot be omitted for this".
             " combination of conditions (must be exactly 1 and not '<>').");
      } else {
        $this->hops[] = compact('conds', 'target');
      }
    }

    return $this;
  }

  //= null if doesn't match ->$sites or ->$pattern, str new URL
  function rewrite($url, $site) {
    $matched = S::first($this->sites, function ($bound) use ($site) {
      return S::wildcard($site, $bound);
    });

    if ($matched and preg_match($this->pattern, $url, $match, PREG_OFFSET_CAPTURE)) {
      list($page, $offset) = S::pickFlat($match, 'page', function () use ($match) {
        return isset($match[1]) ? $match[1] : $match[0];
      });

      $new = $this->changePage((int) $page);
      if (isset($new)) {
        return substr($url, 0, $offset).$new.substr($url, $offset + strlen($page));
      }
    }
  }

  //* $page int - page to be changed.
  //= null if no ->$hops matched, int new page
  function changePage($page) {
    foreach ($this->hops as $hop) {
      $target = $hop['target'];

      foreach ($hop['conds'] as $cond) {
        $matches = static::compare($page, $cond['op'], $cond['value']);

        if ($target !== '') {
          if ($matches) { return $target; }
        } elseif (!$matches) {
          switch ($cond['op']) {
          case '>':   return $cond['value'] + 1;
          case '>=':  return $cond['value'];
          case '<':   return 1;
          case '<=':  return 0;
          case '=':   return $cond['value'];
          default:
            throw new Error("Condition operator [$cond[op]] requires hop target.");
          }
        }
      }
    }
  }
}