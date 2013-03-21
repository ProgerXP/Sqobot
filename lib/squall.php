<?php
/*
  Squall - functional programming for PHP with a syntax that makes sense
  in public domain | by Proger_XP | http://proger.i-forge.net
  http://github.com/ProgerXP/Squall
*/
namespace Squall;

use ArrayAccess;
use Closure;
use Traversable;

define(__NAMESPACE__.'\\NS', __NAMESPACE__.'\\');
define(NS.'DS', DIRECTORY_SEPARATOR);
define(NS.'VERSION', '1.0-rc');
define(NS.'MBSTRING', function_exists('mb_get_info'));

MBSTRING and mb_internal_encoding('UTF-8');

class Error extends \Exception { }
class EWrongEvalSyntax extends Error { }

// Chain wraps around a value so you can avoid passing it to Functions' methods and
// storing the result back here. Call get() to retrieve the current value.
//
//? Chain::make(array(0, 1, 2))->omit('?')->map('? + 1')->get();
//      //=> array(2, 3)
class Chain implements ArrayAccess, \IteratorAggregate, \Countable {
  // Function names that instead of changing wrapped value have their result returned.
  static $retrievers = array('get');

  //= mixed current value
  protected $value;
  // Specifies if php_mbstring should be used to handle string operations on this object.
  //= null use global setting (Functions::$mbstring), bool override it
  protected $mbstring;

  // Creates instance. Useful to avoid variable assignment in PHP 5.3.
  //* $v mixed - initial value to use in the first Functions call.
  static function make($v) {
    return new static($v);
  }

  //* $v mixed - initial value to use in the first Functions call.
  function __construct($v) {
    $this->value = $v;
  }

  function __call($name, $params) {
    if ($name === 'get' and !$params) {
      return $this->value;
    } else {
      isset($this->mbstring) and Functions::$mbstring = $this->mbstring;

      try {
        array_unshift($params, $this->value);
        $result = call_user_func_array(array(NS.'Functions', $name), $params);
        Functions::$mbstring = MBSTRING;
      } catch (\Exception $e) {
        Functions::$mbstring = MBSTRING;
        throw $e;
      }

      return in_array($name, static::$retrievers) ? $result : $this->set($result);
    }
  }

  // Sets current value.
  function set($v) {
    $this->value = $v;
    return $this;
  }

  // Disables mbstring for all Functions on this wrapped object. Can be used to
  // speed up string operations.
  //= $this
  function ansi() {
    return $this->mbstring(false);
  }

  // Enables mbstring (if available) for all Functions on this wrapped object.
  //= $this
  function unicode() {
    return $this->mbstring(true);
  }

  // function ()
  // Returns current mbstring usage state.
  //= null using global setting (Functions::$mbstring), bool overriden
  function mbstring($enable = null) {
    func_num_args() and $this->mbstring = ($enable and MBSTRING);
    return func_num_args() ? $this : $this->mbstring;
  }

  // Alias to calling get(...).
  //= mixed current value or its part
  function __invoke() {
    return call_user_func_array(array($this, 'get'), func_get_args());
  }

  function offsetGet($offset) {
    return Functions::pickFlat($this->value, $offset);
  }

  function offsetExists($offset) {
    $has = true;
    Functions::pickFlat($this->value, $offset, function () use (&$has) { $has = false; });
    return $has;
  }

  function offsetSet($offset, $value) {
    $this->value = Functions::map($this->value);
    $this->value[$offset] = $value;
  }

  function offsetUnset($offset) {
    $this->value = Functions::map($this->value);
    unset( $this->value[$offset] );
  }

  function getIterator() {
    if ($this->value instanceof Traversable) {
      return $this->value;
    } elseif (is_array($this->value)) {
      $array = &$this->value;
    } elseif ($this->value === null) {
      $array = array();
    } else {
      $array = array(&$this->value);
    }

    return new \ArrayIterator($array);
  }

  function count() {
    return Functions::count($this->value);
  }
}

/*-------------------------------------------------------------------------
| EXTERIOR FUNCTIONS
|------------------------------------------------------------------------*/

// Contains functional Squall routines. Methods of this class are static
// and operate on both arrays and scalars (typically strings). Most of them
// also operate on Traversable objects and some handle ArrayAccess.
//
// By convention, functions with $v argument operate on all possible types
// while $array argument indicates they expect exactly an array. Sometimes
// $array can be protected with a type hint; if it's not the function will
// return default value on a non-array argument (which can be null, array()
// or something else).
//
// Most functions will return null on null $v.
//
// $func argument is a Squall Callback instance or expression to be parsed
// into one, such as array('|trim|strtolower', array('uniqid', 'pf_')).
class Functions {
  // Specifies if mbstring functions should be used in string operations or not.
  // Ignored if php_mbstring extension isn't available.
  //= bool
  static $mbstring = MBSTRING;

  // Maps function names (key) to external routines (value). Only used when calling
  // a method not defined in Functions.
  //= array of callable
  static $extra = array();

  // function ($v)
  // Creates a new Chain used to invoke multiple Functions changing the same value.
  //= Chain
  //
  //? Functions::make(array(' X '))->map('trim')->get()   //=> array('X')
  //
  // function ($v, $toMap)
  // Shortcut to calling map(). Returns the new mapped value.
  //= mixed
  //
  //? Functions::make(array(' X '), 'trim')       //=> array('X')
  static function make($v, $toMap = null) {
    return func_num_args() > 1 ? static::map($v, $toMap) : new Chain($v);
  }

  static function __callStatic($name, $params) {
    $func = &static::$extra[$name];

    if (isset($func)) {
      return call_user_func_array($func, $params);
    } else {
      fail("No built-in or extra function [$name].", '\\BadMethodCallException');
    }
  }

  // function ($v[, $key[, ...]])
  // If no arguments given returns $v. If one $key is given is equivalent to pick().
  // If several $keys given returns an array with those picked values ignoring
  // missing ones. If a null value is present it means $v contained it null too.
  //= mixed
  static function get($v, $key_1) {
    switch (func_num_args()) {
    case 0:   return $v;
    case 1:   return static::pick($v, $key);
    default:
      $result = array();

      foreach (func_get_args() as $i => $key) {
        if ($i > 0) {
          $has = true;
          $value = static::pick($v, $key, function () use (&$has) { $has = false; });
          $has and $result[$key] = $value;
        }
      }

      return $result;
    }
  }

  // Returns array() on null $v or array($key => $v) otherwise. Unlike '(array')
  // won't cause Fatal Error on Closures and won't convert objects but wrap them.
  //= array
  static function arrize($v, $key = 0) {
    return $v === null ? array() : static::arrizeAny($v, $key);
  }

  // Returns array($key => $v) on any $v (even null). Unlike '(array')
  // won't cause Fatal Error on Closures and won't convert objects but wrap them.
  //= array
  static function arrizeAny($v, $key = 0) {
    return is_array($v) ? $v : array($key => $v);
  }

  //* $value Closure to call and return result, mixed to return as is
  static function unclosure($v) {
    return ($v instanceof Closure) ? $v() : $v;
  }

  // It intentionally won't preserve keys (even non-numeric); use array_spice(..., true)
  // for this. Also, unlike substr() it will return empty string instead of null if
  // $from is out of bounds.
  //= string, array, null
  static function slice($v, $from = 1, $length = null) {
    ($v instanceof Traversable) and $v = static::map($v);
    $func = typeFunc($v, mbstring('substr'), 'array_slice');

    if ($func) {
      $v = isset($length) ? $func($v, $from, $length) : $func($v, $from);
      return $func[0] === 'a' ? $v : (string) $v;
    }
  }

  //= string, mixed, null
  static function at($pos, $v) {
    if (is_scalar($v) or $v === null) {
      $v = (string) $v;
      return isset($v[$pos]) ? $v[$pos] : '';
    } elseif (is_array($v) and $sub = array_slice($v, $pos, 1)) {
      return $sub[0];
    } elseif (iterable($v)) {
      foreach ($v as $value) {
        if (--$pos < 0) { return $value; }
      }
    }
  }

  //= bool, null
  static function isCapitalized($v) {
    if (is_string($v)) {
      return $v !== '' and static::up($v[0]) === $v[0];
    } elseif (iterable($v)) {
      foreach ($v as &$item) {
        if (!static::isCapitalized($item)) { return false; }
      }

      return true;
    }
  }

  //= string, array, null
  static function up($v) {
    return transform($v, mbstring('strtoupper'));
  }

  //= string, array, null
  static function down($v) {
    return transform($v, mbstring('strtolower'));
  }

  //= array
  static function upKeys($v, $down = false) {
    if (!iterable($v)) {
      return;
    } elseif (static::$mbstring) {
      $func = $down ? 'mb_strtolower' : 'mb_strtoupper';
      return static::keys($v, ".#$func#");
    } else {
      return array_change_key_case(static::map($v), $down ? \CASE_LOWER : \CASE_UPPER);
    }
  }

  //= array
  static function downKeys($v) {
    return static::upKeys($v, true);
  }

  //= string, array, null
  static function capitalize($v) {
    return transform($v, mbstring('ucfirst', function ($v) {
      return "$v" === '' ? '' : mb_strtoupper(mb_substr($v, 0, 1)).mb_substr($v, 1);
    }));
  }

  //= string, array, null
  static function replace($v, $from, $to = null) {
    func_num_args() == 3 and $from = array($from => $to);

    if (static::$mbstring) {
      $fromRE = static::map(static::keys($from), function ($value) {
        return '~'.preg_quote($value, '~').'~u';
      });

      return preg_replace($fromRE, array_values($from), $v);
    } else {
      return transform($v, function ($value) use ($from) {
        return strtr($value, $from);
      });
    }
  }

  //= bool
  static function starts($v, $sub, $ignoreCase = false) {
    $slice = static::first($v, static::count($sub));
    return $slice === $sub or ($ignoreCase and is_string($slice) and
                               static::down($slice) === static::down($sub));
  }

  //= bool
  static function ends($v, $sub, $ignoreCase = false) {
    $slice = static::last($v, static::count($sub));
    return $slice === $sub or ($ignoreCase and is_string($slice) and
                               static::down($slice) === static::down($sub));
  }

  //= bool
  static function unprefix(&$v, $prefix, $ignoreCase = false) {
    if (is_string($v) and ($ignoreCase
          ? !strncasecmp($v, $prefix, strlen($prefix))
          : (substr($v, 0, strlen($prefix)) === $prefix))) {
      // optimization for quick ANSI
      $v = (string) substr($v, strlen($prefix));
      return true;
    }

    if (iterable($v) and !iterable($prefix)) {
      foreach ($v as &$item) {
        is_scalar($item) and static::unprefix($item, $prefix, $ignoreCase);
      }

      return $v;
    } else {
      $starts = static::starts($v, $prefix, $ignoreCase);
      $starts and $v = static::slice($v, static::count($prefix));
      return $starts;
    }
  }

  //= bool
  static function unsuffix(&$v, $suffix, $ignoreCase = false) {
    if (is_string($v) and ($ignoreCase
          ? !strcasecmp(substr($v, -1 * strlen($suffix)), $suffix)
          : (substr($v, -1 * strlen($suffix)) === $suffix))) {
      // optimization for quick ANSI
      $v = (string) substr($v, 0, -1 * strlen($suffix));
      return true;
    }

    if (iterable($v) and !iterable($suffix)) {
      foreach ($v as &$item) {
        is_scalar($item) and static::unsuffix($item, $suffix, $ignoreCase);
      }

      return $v;
    } else {
      $ends = static::ends($v, $suffix, $ignoreCase);
      $ends and $v = static::chop($v, static::count($suffix));
      return $ends;
    }
  }

  //= string, array, null
  static function tryUnprefix($v, $prefix, $ignoreCase = false) {
    static::unprefix($v, $prefix, $ignoreCase);
    return $v;
  }

  //= string, array, null
  static function tryUnsuffix($v, $suffix, $ignoreCase = false) {
    static::unsuffix($v, $suffix, $ignoreCase);
    return $v;
  }

  //= null, string, array
  static function prefix($v, $prefix) {
    return transform($v, function ($s) use ($prefix) { return $prefix.$s; });
  }

  //= null, string, array
  static function suffix($v, $suffix) {
    return transform($v, function ($s) use ($suffix) { return $s.$suffix; });
  }

  //= true, null
  static function has($v, $sub, $ignoreCase = false) {
    $isArray = iterable($v) and $v = static::map($v);

    if (is_scalar($v) or $isArray) {
      $strFunc = mbstring($ignoreCase ? 'stripos' : 'strpos');

      foreach ((array) $sub as $item) {
        if ($isArray) {
          $pos = array_search($item, $v);
        } else {
          $pos = "$item" === '' ? false : $strFunc($v, $item);
        }

        if ($pos !== false) { return true; }
      }

      return false;
    }
  }

  //= mixed
  static function pick(array $array, $path, $default = null, $delimiter = '.') {
    return static::extract($array, $path, $default, $delimiter, false);
  }

  //= mixed
  static function extract(&$array, $path, $default = null, $delimiter = '.', $create = true) {
    $path = explode($delimiter, $path);

    while ($path) {
      $key = array_shift($path);

      if (!$path and $array instanceof ArrayAccess and isset($array[$key])) {
        return $array[$key];
      } elseif (!is_array($array) or !array_key_exists($key, $array)) {
        return static::unclosure($default);
      }

      if ($create and !$path) {
        $result = $array[$key];
        unset($array[$key]);
        return $result;
      } else {
        $array = &$array[$key];
      }
    }

    return $array;
  }

  // Unlike ::pick() does not process $delimiter.
  //= mixed
  static function pickFlat($v, $key, $default = null) {
    if ((is_array($v) or $v instanceof ArrayAccess) and isset($v[$key])) {
      return $v[$key];
    } elseif (iterable($v)) {
      foreach ($v as $aKey => $value) {
        if ($key === $aKey) { return $value; }
      }
    }

    return static::unclosure($default);
  }

  //= mixed $value
  static function put(array &$array, $path, $value, $delimiter = '.') {
    $keys = explode($delimiter, $path);
    $last = array_pop($keys);

    foreach ($keys as $key) {
      if (!isset($array[$key])) {
        $array[$key] = array();
      } elseif (!is_array($array[$key])) {
        $array[$key] = array($array[$key]);
      }

      $array = &$array[$key];
    }

    return $array[$last] = $value;
  }

  //= null, string, array
  static function first($v, $count = 1, $func = null) {
    if (func_num_args() == 1 and is_array($v)) {
      return reset($v);   // optimization
    } elseif ($func or !is_int($count)) {
      return static::doFirstLast($v, $count, $func);
    } else {
      return static::slice($v, 0, $count);
    }
  }

  //= null, string, array
  static function last($v, $count = 1, $func = null) {
    if (func_num_args() == 1 and is_array($v)) {
      return end($v);     // optimization
    } elseif ($func or !is_int($count)) {
      return static::doFirstLast($v, $count, $func, true);
    } else {
      return static::slice($v, -1 * $count);
    }
  }

  protected static function doFirstLast($v, $count, $func, $getLast = false) {
    if (is_scalar($v)) {
      $v = str_split((string) $v);
    } elseif (!iterable($v)) {
      return;
    }

    $func or static::rotate(1, array(&$count, &$func));
    $func = Callback::parse($func);
    $last = null;

    foreach ($v as $key => $value) {
      if ($func($value, $key)) {
        $last = $value;
        if (!$getLast) { break; }
      }
    }

    return $last;
  }

  //= null, string, array
  static function chop($v, $count = 1) {
    return static::slice($v, 0, -1 * $count);
  }

  //= string with leading period, null
  static function ext($str, $delimiter = '.', $stoppers = '\\/') {
    list(, $ext) = static::chopTo($delimiter, $str);

    if (isset($ext) and strpbrk($ext, $stoppers) === false) {
      return $delimiter.$ext;
    }
  }

  //* $ext string - with leading period, if necessary.
  //= string
  static function newExt($str, $ext = '') {
    $old = static::ext($str);
    isset($old) and $str = static::first($str, -1 * static::count($old));
    return $str.$ext;
  }

  //= mixed $value
  static function rotate($value, array $into) {
    foreach ($into as &$var) {
      $prev = $var;
      $var = $value;
      $value = $prev;
    }

    return $value;
  }

  //= mixed &$b
  static function &swap(&$a, &$b) {
    $temp = $a;
    $a = $b;
    $b = $temp;

    return $b;
  }

  //? Util:listable(compact('path', 'query'))
  //      //=> array('path' => 'a/b/', 'query' => '?a=b', 'a/b/', '?a=b')
  static function listable(array $array) {
    return $array + array_values($array);
  }

  // function (array $array, [$chunk = 2], [$retainKeys = true])
  //= array
  static function segment($v, $chunk = 2, $retainKeys = true) {
    is_bool($chunk) and static::rotate(2, array(&$chunk, &$retainKeys));

    if (is_scalar($v)) {
      $v = str_split((string) $v, $chunk);
    } else {
      $v = array_chunk($v, $chunk, $retainKeys);

      if (count($v) % $chunk > 0) {
        $last = $retainKeys ? static::last(array_keys($v)) : (count($v) - 1);
        $v[$last] = array_pad($v[$last], $chunk, null);
      }
    }

    return $v;
  }

  //= array
  static function flatten(array $array, $level = 1) {
    if (--$level < 0) {
      return $array;
    } else {
      $result = array();

      foreach ($array as $key => $item) {
        $item = is_array($item) ? static::flatten($item, $level) : array($key => $item);
        $result = array_merge($result, $item);
      }

      return $result;
    }
  }

  static function map($v, $func = null) {
    if (is_array($v)) {
      $iterable = &$v;
    } elseif ($v instanceof Traversable) {
      $iterable = array();
      foreach ($v as $key => $value) { $iterable[$key] = $value; }
    } elseif (is_int($v)) {
      return static::map(range(0, $v), $func);
    } elseif (is_scalar($v)) {
      return (string) $v;
    } else {
      return;
    }

    $func = Callback::parse($func);

    if (!$func) {
      return $iterable;
    } else {
      $result = array();
      foreach ($iterable as $key => $value) { $result[$key] = $func($value, $key); }
      return $result;
    }
  }

  //* $depth int, bool - if true will act recursively on any depth; if 0 or false -
  //    will return $v as is; other values will be decreasing for each nested array.
  //= array, mixed
  static function deepMap($v, $func = null, $depth = true, $key = null) {
    if (is_array($v)) {
      static::deepMapper($key, $v, Callback::parse($func), $depth);
      return $v;
    } else {
      return $func($v, $key);
    }
  }

  //* $func - already parsed callback.
  protected static function deepMapper($parent, array &$array, $func, $depth = false) {
    if ($depth <= 0) {
      return;
    } elseif ($depth !== true) {
      --$depth;
    }

    $parent === null or $parent .= '.';

    foreach ($array as $key => &$value) {
      if (is_array($value)) {
        $value = static::deepMapper($parent.$key, $value, $func, $depth);
      } else {
        $func($value, $parent.$key);
      }
    }
  }

  static function build($v, $func, $nonObjects = null) {
    if (is_int($v)) {
      return static::build(range(0, $v), $func, $nonObjects);
    } elseif (is_scalar($v)) {
      return join( static::map(str_split((string) $v), $func) );
    } elseif (iterable($v)) {
      $func = Callback::parse($func);
      $isObject = $func instanceof MemberObjectCallback;
      $isClosure = $func instanceof Closure;
      $skipNonObjects = func_num_args() > 2;

      $result = array();

      foreach ($v as $key => $value) {
        if (!$isObject or is_object($value)) {
          $value = $func($value, $key);
        } elseif ($skipNonObjects) {
          continue;
        } else {
          $value = $nonObjects;
        }

        $result = Utils::smartMerge($result, static::arrize($value, $key));
      }

      return $result;
    }
  }

  //= hash
  //* $func callable, mixed - function ($key, $value), returns either
  //   array($newKey[, $newValue]) ($newValue = $newKey if only one member),
  //   array(null[, $newValue = null]) to set $newKey to item index, or just $newKey.
  static function keys($v, $func = null) {
    $v = (array) static::map($v);
    $func = Callback::parse($func);

    if (!$func) {
      $result = array_keys($v);
    } else {
      $result = array();

      foreach ($v as $key => $value) {
        $call = $func($value, $key);

        if (!is_array($call)) {
          $result[$call] = $value;
        } elseif (!isset($call[0])) {
          $result[] = isset($call[1]) ? $call[1] : null;
        } else {
          $result[$call[0]] = isset($call[1]) ? $call[1] : null;
        }
      }
    }

    return $result;
  }

  // function ($keys)             - combine $keys with $keys
  // function ($keys, $values)
  //
  //* $keys array, mixed empty array is returned
  //* $values array, mixed becomes value for every key
  //= hash
  static function combine($keys, $values = null) {
    func_num_args() == 1 and $values = $keys;

    if (!is_array($keys)) {
      $keys = null;
    } elseif (is_array($values)) {
      $values = array_pad($values, count($keys), null);
      $values = array_slice($values, 0, count($keys));
    } elseif ($keys) {
      $values = array_fill(0, count($keys), $values);
    }

    return $keys ? array_combine($keys, $values) : array();
  }

  //= array with two items
  static function divide($v, $func = null) {
    $func = Callback::parse($func);

    if (!$func) {
      return array(array_keys($v), array_values($v));
    } elseif (is_int($func)) {
      if ($func < 0) {
        return array(static::last($v, $func), static::first($v, -1 * $func));
      } else {
        return array(static::first($v, $func), static::last($v, -1 * $func));
      }
    } elseif (is_string($v)) {
      $v = str_split($v);
    }

    if (iterable($v)) {
      $kept = $rejected = array();

      foreach (static::map($v) as $key => $value) {
        if ($func($value, $key)) {
          $kept[$key] = $value;
        } else {
          $rejected[$key] = $value;
        }
      }

      if (is_string($v)) {
        return array(join($kept), join($rejected));
      } else {
        return array($kept, $rejected);
      }
    }
  }

  //= array
  static function keep($v, $func) {
    list($kept) = static::divide($v, $func);
    return $kept;
  }

  //= array
  //* $func mixed - if omitted remove all null elements (using strict comparison).
  static function omit($v, $func = null) {
    isset($func) or $func = function ($v) { return $v === null; };
    list(, $rejected) = static::divide($v, $func);
    return $rejected;
  }

  //= array, null
  static function chopTo($delimiter, $v, $ignoreCase = false) {
    if (is_array($v)) {
      $i = 0;
      $item = end($v);

      while ($i < count($v)) {
        if ($item === $delimiter) {
          return array(array_slice($v, 0, $i, true),
                       array_slice($v, $i + 1, count($v), true));
        }

        $item = prev($v);
      }

      return array($v, null);
    } elseif (is_scalar($v)) {
      $pos = mbstringDo($ignoreCase ? 'strripos' : 'strrpos', $v, $delimiter);

      if ($pos === false) {
        return array($v, null);
      } else {
        $substr = mbstring('substr');
        $length = mbstringDo('strlen', $delimiter);
        return array((string) $substr($v, 0, $pos), (string) $substr($v, $pos + $length));
      }
    }
  }

  //= string
  static function firstPart($v, $delimiter = ' ') {
    list($v) = explode($delimiter, $v, 2);
    return $v;
  }

  //= array, string, null
  static function lastPart($v, $delimiter = ' ', $ignoreCase = false) {
    list($v) = static::chopTo($delimiter, $v, $ignoreCase);
    return $v;
  }

  //= array, mixed
  static function trimScalar($v, $depth = false) {
    return static::deepMap($v, function ($v) {
      return (is_scalar($v) or $v === null) ? trim($v) : $v;
    }, $depth);
  }

  //= string, array of string, null
  static function trim($v, $chars = null) {
    if (func_num_args() == 1) {
      $trim = 'trim';
    } else {
      $trim = function ($s) use ($chars) { return trim($s, $chars); };
    }

    return transform($v, $trim);
  }

  //= string, array, null
  static function squeeze($v, $symbols = null) {
    isset($symbols) or $symbols = "\r\n\t\v ";

    if (is_scalar($v)) {
      return str_replace($symbols, '', $v);
    } elseif (iterable($v)) {
      $result = array();

      foreach (static::map($v) as $key => $item) {
        if (is_scalar($item) or $item === null) {
          $item = trim($item, $symbols);
          if ($item === '') { continue; }
        }

        $result[$key] = $item;
      }

      return $result;
    }
  }

  //= int, null
  static function count($v, $sub = null) {
    if (func_num_args() < 2) {
      if (is_array($v) or $v instanceof \Countable) {
        return count($v);
      } elseif (iterable($v)) {
        return count(static::map($v));
      } elseif (is_scalar($v)) {
        return mbstringDo('strlen', (string) $v);
      }
    } elseif (iterable($v)) {
      $count = 0;
      foreach (static::map($v) as $item) { $item === $sub and ++$count; }
      return $count;
    } elseif (is_scalar($v)) {
      return "$sub" === '' ? 0 : mbstringDo('substr_count', (string) $v, $sub);
    }
  }

  static function splitBy($delimiters, $v, $withDelim = true) {
    return static::splitAs('d', $delimiters, $v, $withDelim);
  }

  //= string, array, null
  //* $flags string - a combination of 'e' (no empty pieces), 'd' (with delimiter),
  //   'o' (with offsets, makes each resulting member as array('string', offset),
  //   'a' (ASCII, no 'u' modifier that is added by default).
  //   Can also contain a number - max number of chunks to split into.
  static function splitAs($flags, $delimiters, $v) {
    $flags = strtolower($flags);
    $limit = null;

    $flags = array_reduce(str_split("$flags "), function ($current, $flag) use (&$limit) {
      switch ($flag) {
      case 'e': $current |= PREG_SPLIT_NO_EMPTY; break;
      case 'd': $current |= PREG_SPLIT_DELIM_CAPTURE; break;
      case 'o': $current |= PREG_SPLIT_OFFSET_CAPTURE; break;

      default:
        is_numeric($flag) and $limit .= $flag;
      }

      return $current;
    });

    isset($limit) or $limit = -1;

    $delimiters = static::map(static::arrize($delimiters), array('.preg_quote', '~'));
    $regexp = '~('.join('|', $delimiters).')~';
    strrchr($flags, 'a') === false and $regexp .= 'u';

    $func = function ($v) use ($regexp, $limit, $flags) {
      return preg_split($regexp, $v, $limit, $flags);
    };

    return transform($v, $func);
  }

  // function ($v[, $item[, ...]])
  //= string, array, mixed
  static function append($v) {
    if (is_scalar($v)) {
      return $v.join( static::slice(func_get_args()) );
    } elseif (iterable($v)) {
      $v = static::map($v);
      foreach (static::slice(func_get_args()) as $item) { $v[] = $item; }
      return $v;
    }
  }

  // function ($v[, $item[, ...]])
  //= string, array, mixed
  static function prepend($v) {
    if (is_scalar($v)) {
      return join( static::slice(func_get_args()) ).$v;
    } elseif (iterable($v)) {
      $args = func_get_args();
      $v = static::map($v);
      $args[0] = &$v;
      call_user_func_array('array_unshift', $args);
      return $v;
    }
  }
}

/*-------------------------------------------------------------------------
| UTILITIES
|--------------------------------------------------------------------------
| This class contains functions of various purposes - parsers, normalizers,
| string convertors, etc. They don't follow the convention of Functions but
| still can be useful in Squall callbacks or on their own.
|------------------------------------------------------------------------*/

class Utils extends Functions {
  static $expire = array('s' => 0, 'session' => 0,
                         'h' => 3600, 'hour' => 3600, 'd' => 86400, 'day' => 86400,
                         /* 7 days: */    'w' => 604800,    'week' => 604800,
                         /* 30 days: */   'm' => 2592000,   'month' => 2592000,
                         /* 365 days: */  'y' => 31536000,  'year' => 31536000,
                         /* 10 years: */  'f' => 315360000, 'forever' => 315360000,
                                          'e' => 315360000, 'ever' => 315360000);

  static function mkdirOf($file) {
    static::mkdir(dirname($file));
  }

  static function mkdir($path, $perms = 0755) {
    is_dir($path) or mkdir($path, $perms, true);
    is_dir($path) or fail("Error creating directory [$path].", '\\RuntimeException');
  }

  // Tries to remove directories starting from $path; stops on first non-empty dir.
  //* $limit int - if < 0, removes as much as possible, if 0 - removes none,
  //   otherwise removes at most $limit empty directories. Note that it will never
  //   go beyond $path if it's relative so do realpath() to remove empty dirs all
  //   the way down to root.
  static function rmEmpty($path, $limit = -1) {
    $path = explode('/', strtr($path, '\\', '/'));
    while (--$limit != -1 and @rmdir( join(DS, $path) )) { array_pop($path); }
  }

  // function ($path[, $append[, ...]])
  //= string without trailing slash unless last argument is ''
  //? path('/etc\\foo/', '\\rock/n', 'r011')    //=> /etc/foo/rock/n/roll (*nix) or with \ (Windows)
  static function pathize($path, $append_1 = null) {
    $result = rtrim($path, '\\/');

    $append = static::slice(func_get_args());
    foreach ($append as $str) { $result .= DS.trim($str, '\\/'); }

    return strtr($result, DS === '\\' ? '/' : '\\', DS);
  }

  //= string with leading and trailing slashes, null if no DOCUMENT_ROOT is set
  static function baseUrl() {
    if ($root = static::pickFlat($_SERVER, 'DOCUMENT_ROOT')) {
      $hostRoot = static::pathize($root, '');
      $scriptRoot = dirname( static::first(get_included_files()) );

      static::unprefix($scriptRoot, $hostRoot);
      return '/'.strtr($scriptRoot, '\\', '/').'/';
    }
  }

  //= array sorted (preferred languages go first)
  static function parseAcceptLanguage($accepts, $withSublang = false) {
    $languages = array();

    foreach (explode(',', $accepts) as $piece) {
      list($lang, $quotient) = explode(';', "$piece;");

      if ($quotient and static::unprefix($quotient, 'q=')) {
        $quotient = (float) trim($quotient);
      } else {
        $quotient = 1.0;
      }

      if (($lang = trim($lang)) !== '') {
        $quotient = round($quotient * 1000); // because ksort() discards float keys.
        while (isset($languages[$quotient])) { --$quotient; }

        $languages[$quotient] = $withSublang ? $lang : strtok($lang, '_-');
      }
    }

    $languages = array_unique($languages);
    krsort($languages);

    return $languages;
  }

  //= object $object, mixed $property
  static function access($numArgs, $newValue, $object, &$property) {
    if ($numArgs == 0) {
      return $property;
    } else {
      $property = $newValue;
      return $object;
    }
  }

  //= object $object, mixed $property
  static function accessIf($newValue, $object, &$property) {
    isset($newValue) and $property = $newValue;
    return isset($newValue) ? $object : $property;
  }

  static function isNumeric($str) {
    $rem = static::isFloat($str, false);
    return ($rem === '') or ($rem === '.');
  }

  static function isNatural($str) {
    return is_int($str) or static::isFloat($str, false) === '';
  }

  static function isFloat($str, $test = true) {
    if (is_string($str)) {
      $str = "$str";

      if (isset($str[0])) {
        if ($str[0] === '-' or $str[0] === '+') {
          $str = (string) substr($str, 1);
        }

        $rem = isset($str[0]) ? trim($str, '0..9') : null;
        return $test ? ($rem === '.') : $rem;
      }
    } elseif ($test) {
      return is_float($str);
    } else {
      return is_int($str) ? '' : (is_float($str) ? '.' : null);
    }
  }

  static function toNatural($value) {
    if (static::isNatural($value)) { return (int) $value; }
  }

  static function toFloat($value) {
    is_string($value) and $value = strtr($value, ',', '.');
    if (static::isNumeric($value)) { return (float) $value; }
  }

  static function sqlDate($timestamp = null) {
    return isset($timestamp) ? gmdate('Y-m-d', $timestamp) : gmdate('Y-m-d');
  }

  static function sqlTime($timestamp = null) {
    return isset($timestamp) ? gmdate('h:i:s', $timestamp) : gmdate('h:i:s');
  }

  static function sqlDateTime($timestamp = null) {
    $format = 'Y-m-d h:i:s';
    return isset($timestamp) ? gmdate($format, $timestamp) : gmdate($format);
  }

  static function parseSqlDate($str) {
    return static::parseTime('Y-m-d', $str);
  }

  static function parseSqlTime($str) {
    return static::parseTime('H:i:s', $str);
  }

  static function parseSqlDateTime($str) {
    return static::parseTime('Y-m-d H:i:s', $str);
  }

  //= null, int timestamp
  static function parseTime($format, $str) {
    $info = date_parse_from_format($format, $str);

    if (!$info['errors'] and !$info['warnings']) {
      return gmmktime($info['hour'], $info['minute'], $info['second'],
                      $info['month'], $info['day'], $info['year']);
    }
  }

  static function sqlValue($type, $timestamp = null) {
    $func = 'sql'.ucfirst($type);

    if (method_exists(get_called_class(), $func)) {
      return static::$func($timestamp);
    } else {
      wrongArg("Invalid \$type [$type] passed for ".__FUNCTION__."().");
    }
  }

  static function parseSqlValue($type, $str) {
    $func = 'parseSql'.ucfirst($type);

    if (method_exists(get_called_class(), $func)) {
      return static::$func($str);
    } else {
      wrongArg("Invalid \$type [$type] passed for ".__FUNCTION__."().");
    }
  }

  //= int 0 to indicate "for session" or if $str is invalid
  static function expire($str) {
    return (int) static::pickFlat(static::$expire, $str, $str);
  }

  //= integer
  static function size($str) {
    $str = trim($str);

    switch (static::last($str)) {
    case 'G': case 'g':  $str *= 1024;
    case 'M': case 'm':  $str *= 1024;
    case 'K': case 'k':  $str *= 1024;
    }

    return (int) $str;
  }

  //= string
  static function sizeStr($size, array $suffixes = null) {
    $suffixes or $suffixes = array('B', 'K', 'M', 'G');

    foreach ($suffixes as $suffix) {
      if ($size >= 1024) {
        $size /= 1024;
      } else {
        break;
      }
    }

    return ((int) $size)." $suffix";
  }

  // function ($str, int $rounds = 8)
  //= string hash
  //
  // function ($hash, $str)
  //= bool
  //
  //? hash('mY-pAss')
  //? hash('mY-pAss', 16)     // 16 rounds instead of default 8
  //? hash('$2a$08$9u...', $input['password'])
  static function hash($str, $check = null, $openSSL = true) {
    if (isset($check) and !is_int($check)) {
      return crypt($check, $str) === $str;
    } else {
      $check or $check = 8;
      $rounds = str_pad($check ?: 8, 2, 0, STR_PAD_LEFT);

      if ($openSSL and function_exists('openssl_random_pseudo_bytes')) {
        // this function might slow down requests on some systems and isn't
        // vital for debugging.
        $salt = openssl_random_pseudo_bytes(16);
      } elseif ($h = @fopen('/dev/urandom', 'rb')) {
        $salt = fread($h, 22);
        fclose($h);
      } else {
        $salt = uniqid('', true);
      }

      $salt = substr(strtr(base64_encode($salt), '+', '.'), 0 , 22);
      return crypt($str, '$2a$'.$rounds.'$'.$salt);
    }
  }

  static function encrypt($v, $cipher = MCRYPT_RIJNDAEL_256, $mode = 'cbc', $block = 32) {
    $randomizer = defined('MCRYPT_DEV_URANDOM') ? MCRYPT_DEV_URANDOM :
                  (defined('MCRYPT_DEV_RANDOM') ? MCRYPT_DEV_RANDOM : MCRYPT_RAND);

    return static::transform($v, function ($str) use ($cipher, $mode, $block, $randomizer) {
      srand();
      $iv = mcrypt_create_iv(mcrypt_get_iv_size($cipher, $mode), $randomizer);

      $padding = $block - strlen($str) % $block;
      $str .= str_repeat(chr($padding), $padding);

      $str = mcrypt_encrypt($cipher, Core::appKey(), $str, $mode, $iv);
      return base64_encode($iv.$str);
    });
  }

  static function decrypt($v, $cipher = MCRYPT_RIJNDAEL_256, $mode = 'cbc', $block = 32) {
    $self = get_called_class();

    return static::transform($v, function ($str) use ($cipher, $mode, $block, $self) {
      $str = base64_decode($str);
      list($iv, $str) = $self::divide($str, mcrypt_get_iv_size($cipher, $mode));
      $str = mcrypt_decrypt($cipher, Core::appKey(), $str, $mode, $iv);

      $padding = ord( substr($str, -1) );

      if ($padding > 0 and $padding < $block) {
        if (ltrim(substr($str, -1 * $padding), chr($padding)) === '') {
          $str = substr($str, 0, -1 * $padding);
        } else {
          $self::raise('Decrypted string has wrong padding.');
        }
      }

      return $str;
    });
  }

  //= string, array, null
  static function html($str, $quotes = ENT_COMPAT, $doubleEncode = true, $charset = 'utf-8') {
    return static::transform($str, function ($str) use ($quotes, $doubleEncode) {
      return htmlspecialchars($str, $quotes, $charset, $doubleEncode);
    });
  }

  static function queryStr(array $query, $noQuestionMark = false) {
    $query = http_build_query($query, '', '&');
    if (!$noQuestionMark and "$query" !== '') { $query = "?$query"; }
    return $query;
  }

  // function (array/null $data)
  //= string
  //
  // Encodes data with unescaped UTF-8. Credits:
  // http://ru2.php.net/manual/ru/function.json-encode.php#107250
  //
  // function (string $data)
  //= hash
  static function json($data) {
    if (is_scalar($data)) {
      $result = json_decode($data, true);
    } elseif (defined('JSON_UNESCAPED_UNICODE')) {
      $result = json_encode($data, JSON_UNESCAPED_UNICODE);
    } else {
      $result = str_replace('\\\\u', '\\u', addcslashes(json_encode($data), '\\"'));
      $result = addcslashes(json_decode('"'.$result.'"'), "\r\n");
    }

    if ($error = json_last_error()) {
      $op = is_scalar($data) ? 'decode' : 'encode';
      fail("Cannot $op a JSON object - error code $error.", '\\RuntimeException');
    }

    return $result;
  }

  // Parses typical *nix-style command line string, such as '--option=X -abc'.
  // For details see http://proger.i-forge.net/ein#command-line.
  //
  //* $args array - PHP's $argv, list of raw command-line options given by index.
  //* $arrays true, array - listed keys will be always arrays in returned 'options'.
  //  If exactly 'true' then arrays will be created on demand.
  //
  //= array with 3 arrays: flags (array of true), options (hash of str), index (array of str)
  //
  //? parseCL(array('--option=X', '-abc'))
  //    //=> array( array('options' => array('option' => 'X'), 'flags' => ...) )
  //? parseCL(array('--option=X', '-abc'), array('option'))
  //    //=> array( array('options' => array('option' => array('X')), 'flags' => ...) )
  //
  //? parseCL(array('--arr[]=1', '--arr[]=2'))
  //    // 'options' is array('arr' => '2')
  //? parseCL(array('--arr[]=1', '--arr[]=2'), array('woo'))
  //    // exactly as above
  //? parseCL(array('--arr[]=1', '--arr[]=2'), array('arr'))
  //    // 'options' is array('arr' => array('1', '2'))
  //? parseCL(array('--arr=1''), array('arr'))
  //    // 'options' is array('arr' => array('1'))
  //? parseCL(array('--arr=1''), true)
  //    // 'options' is array('arr' => '1')
  //? parseCL(array('--arr=1''))
  //    // 'options' is array('arr' => '1')
  //? parseCL(array('--arr[]=1''), true)
  //    // 'options' is array('arr' => array('1'))
  static function parseCL($args, $arrays = array()) {
      $arrays === true or $arrays = array_flip($arrays);
      $flags = $options = $index = array();

      foreach ($args as $i => &$arg) {
        if ($arg !== '') {
          if ($arg[0] == '-') {
            if ($arg === '--') {
              $index = array_merge($index, array_slice($args, $i + 1));
              break;
            } elseif ($argValue = ltrim($arg, '-')) {
              if ($arg[1] == '-') {
                if (strrchr($argValue, '=') === false) {
                  $key = $argValue;
                  $value = true;
                } else {
                  list($key, $value) = explode('=', $argValue, 2);
                }

                $key = strtolower($key);

                if (preg_match('/^(.+)\[(.*)\]$/', $key, $matches)) {
                  list(, $key, $subKey) = $matches;
                } else {
                  $subKey = null;
                }

                if ($subKey !== null and ($arrays === true or isset( $arrays[$key] ))) {
                  if (isset( $options[$key] ) and !is_array( $options[$key] )) {
                    $options[$key] = array($options[$key]);
                  }

                  $subKey === '' ? $options[$key][] = $value
                                 : $options[$key][$subKey] = $value;
                } else {
                  isset( $arrays[$key] ) and $value = array($value);
                  $options[$key] = $value;
                }
              } else {
                $flags += array_flip( str_split($argValue) );
              }
            }
          } else {
            $index[] = $arg;
          }
        }
      }

      $flags = static::combine(array_keys($flags), true);
      return compact('flags', 'options', 'index');
    }

  static function expandSymlinks($path, $cwd = null) {
    return static::followSymlinks(static::expand($path, $cwd));
  }

  // Normalizes \ and / to DIRECTORY_SEPARATOR, removes successive / and \'s,
  // resolves '.' and '..' relative to $cwd.
  //= string
  //* $path string - without trailing slash unless it's just "/" or "c:/".
  //* $cwd string, null use getcwd()
  static function expand($path, $cwd = null) {
    if (!is_scalar($path) and $path !== null) {
      $type = gettype($path);
      wrongArg("Invalid \$path value of type [$type] given to expand().");
    }

    $cwd === null and $cwd = getcwd();
    $cwd = static::pathize($cwd);

    $path = strtr($path, DS === '\\' ? '/' : '\\', DS);
    $firstIsSlash = (isset($path[0]) and strpbrk($path[0], '\\/'));

    if ($path === '' or (!$firstIsSlash and isset($path[1]) and $path[1] !== ':')) {
      $path = $cwd.DS.$path;
    } elseif ($firstIsSlash and isset($cwd[1]) and $cwd[1] === ':') {
      // when a drive is specified in CWD then \ or / refers to that drive's root.
      $path = substr($cwd, 0, 2).$path;
    }

    if ($path !== '' and ($path[0] === DS or (isset($path[1]) and $path[1] === ':'))) {
      list($prefix, $path) = explode(DS, $path, 2);
      $prefix .= DS;
    } else {
      $prefix = '';
    }

    $expanded = array();
    foreach (explode(DS, $path) as $dir) {
      if ($dir === '..') {
        array_pop($expanded);
      } elseif ($dir !== '' and $dir !== '.') {
        $expanded[] = $dir;
      }
    }

    return $prefix.join(DS, $expanded);
  }

  static function followSymlinks($path) {
    if (function_exists('readlink')) {  // prior to PHP 5.3 it only works for *nix.
      while (is_link($path) and ($target = readlink($path)) !== false) {
        $path = $target;
      }
    }

    return $path;
  }

  static function isBinary($data, $preview = 512) {
    return preg_match('/[\x00-\x08\x0C\x0E-\x1F]/', substr($data, 0, $preview));
  }

  static function freeMem($margin = 1048576) {
    return static::size(ini_get('memory_limit')) - memory_get_usage() - $margin;
  }

  //= array
  static function smartMerge($v_1, $v_2 = null, $v_3 = null) {
    $assoc = $enumerated = $order = array();
    $hasIntKey = false;

    foreach (func_get_args() as $v) {
      $v = static::arrize($v);
      $i = -1;

      foreach ($v as $key => $value) {
        if ($key === ++$i) {
          $order[] = count($assoc + $enumerated);
          $enumerated[] = $value;
        } else {
          $assoc[$key] = $value;
          $hasIntKey or $hasIntKey = is_int($key);
        }
      }
    }

    if ($hasIntKey) {
      $dups = 0;
      $enumKeys = array();

      foreach ($enumerated as $key => &$value) {
        while (array_key_exists($key + $dups, $assoc)) { ++$dups; }
        $enumKeys[] = $key + $dups;
      }
    }

    if (empty($enumerated) or empty($assoc)) {
      $result = $assoc ?: $enumerated;
    } else {
      $result = array();

      $i = 0;
      $assoc[] = null;
      reset($order);
      reset($enumerated);

      foreach ($assoc as $key => &$value) {
        while ($order and count($result) == current($order)) {
          $enumKey = $hasIntKey ? array_shift($enumKeys) : key($enumerated);
          $result[$enumKey] = current($enumerated);

          next($order);
          next($enumerated);
        }

        count($assoc) > ++$i and $result[$key] = &$value;
      }
    }

    return $result;
  }

  static function cat($v_1, $v_2, $v_3 = null) {
    return static::catAll(func_get_args());
  }

  //? any = any
  //? null + null = null
  //? null/other + null/other = non-null
  //? bool + bool = AND-match
  //? scalar/array + scalar/array = merge
  //? any + other = latter
  static function catAll(array $values) {
    foreach (new \ArrayIterator($values) as $key => $value) {
      if ($value === null) { unset($values[$key]); }
    }

    if (empty($values)) {
      return null;
    } elseif (count($values) == 1) {
      return reset($values);
    } elseif ($values) {
      $type = static::first(gettype( reset($values) ));

      switch ($type) {
      case 'b':
      case 'i':
      case 's':
      case 'a':   break;
      case 'd':   $type = 'i'; break;
      default:    return end($values);
      }

      $a = array();
      $i = $s = null;
      $b = true;

      foreach (array_reverse($values) as $index => $value) {
        if ($type === 'a') {
          $a = static::smartMerge(static::arrize($value), $a);
          continue;
        } elseif ($type === 's') {
          if (is_scalar($value)) {
            $s = $value.$s;
            continue;
          }
        } elseif ($type === 'i') {
          if (is_scalar($value)) {
            $i += $value;
            continue;
          }
        } elseif ($type === 'b') {
          is_string($value) and $value = $value !== '';
          $b and $b = (bool) $value;
          continue;
        } else {
          wrongArg("Unknown type to concatenate: [$type].");
        }

        return $index === 0 ? $value : $$type;
      }

      return $$type;
    }
  }

  //* $to string - if '' no convertion is performed.
  static function convertCharset($v, $from, $to) {
    $from = strtolower($from);
    $to = strtolower($to);
    $from === 'utf8' and $from = 'utf-8';
    $to === 'utf8' and $to = 'utf-8';

    if ($from !== $to and $to !== '') {
      if (iterable($v)) {
        foreach ($v as &$item) { $item = static::convertCharset($item, $from, $to); }
        return $v;
      } else {
        $utf = $from === 'utf-8' ? 'f' : ($to === 'utf-8' ? 't' : '');

        if ($utf and strtolower( $utf === 'f' ? $to : $from ) === 'iso-8859-1') {
          $v = $utf === 't' ? utf8_encode($v) : utf8_decode($v);
        } elseif (function_exists('iconv')) {
          $v = iconv($from, "$to//IGNORE", $v);
        } else {
          fail("Iconv PHP module is required to convert string charset".
               " from [$from] to [$to].", '\\RuntimeException');
        }

        if (!is_string($v)) {
          fail("Cannot convert charset from $from to $to.", '\\RuntimeException');
        }
      }
    }

    return $v;
  }

  //= array (bool $allBut, array $numbers)
  static function parseRange($str) {
    $range = explode(' ', trim($str));
    $allBut = static::unprefix($range, array('-'));

    return array($allBut, static::build($range, function ($num) {
      $from = strtok($num, '-');
      return range($from, strtok(null) ?: $from);
    }));
  }

  //* $range array, string see ->parseRange()
  static function inRange($range, $num) {
    is_array($range) or $range = static::parseRange($range);
    return (bool) ($range[0] ^ in_array($num, $range[1]));
  }
}

/*-------------------------------------------------------------------------
| CALLBACK SUPPORT
|------------------------------------------------------------------------*/

abstract class Callback {
  // don't change these values - they're for quick calculation of $keyArg, see parse().
  const KEY_NONE  = 0;
  const KEY_LEFT  = 1;
  const KEY_RIGHT = 2;
  const KEY_ONLY  = 3;

  protected $argsLeft = array();
  protected $argsRight = array();
  protected $hasArgs = false;

  protected $keyArg;
  protected $next;                    //= null, Callback pipe chain part

  static function parsePiped(array $funcs) {
    if ($funcs) {
      $isLast = count($funcs) > 1;
      $obj = static::parse(array_shift($funcs), $isLast);

      if (!$obj) {
        $obj = static::parsePiped($funcs);
      } elseif (!$isLast) {
        $isLast or $obj->next = static::parsePiped($funcs);
      }

      return $obj;
    }
  }

  //* $argCount int - incoming argument count excluding key. -1 means 'any'.
  static function parse($func, $canClosure = true, $argCount = 1) {
    if ($func instanceof self) {
      return clone $func;
    } elseif ($func instanceof Closure) {
      if ($canClosure) {
        return $func;
      } else {
        $func = array($func);
      }
    } elseif ($func === null) {
      return;
    } elseif (is_string($func)) {
      if (strpbrk($func, '.?') === false) {
        $pipe = (isset($func[0]) and $func[0] === '|');
        $func = ($pipe ? '|'.substr($func, 1) : '').".$func";
      }

      $func = array($func);
    } elseif (!iterable($func)) {
      wrongArg("Callback must be a string or an iterable, ".
               " [".gettype($func)."] given.");
    } else {
      $func = array_values($func);
      is_object($func[0]) or $func[0] = (string) $func[0];
    }

    if (empty($func) or $func === array('') or $func === array('.')) {
      return;
    } elseif ($func[0] === '|') {
      return static::parsePiped( array_slice($func, 1) );
    } elseif (is_object($func[0])) {
      $type = 'o';
    } elseif ($func[0][0] === '|') {
      return static::parsePiped( explode('|', substr($func[0], 1)) );
    } elseif (strrchr($func[0], '?') !== false) {
      $type = 'e';
    } else {
      $type = strrchr($func[0], '.') === false ? 's' : 'j';   // (s)plit, (j)oined
    }

    $class = 'UserCallback';

    if ($type === 'j') {
      $target = explode('.', array_shift($func), 2);
    } elseif ($type === 'e') {
      $target = array(null, array_shift($func));

      if (substr($target[1], 0, 2) === '?.' and rtrim($target[1], 'a..zA..Z0..9_') === '?.') {
        $class = 'MethodCallback';
        $target[1] = substr($target[1], 2);
      } else {
        $class = 'EvalCallback';
      }
    } elseif (isset($func[1])) {    // $type is 'o'
      $target = array_splice($func, 0, 2);
      $target[1] = (string) $target[1];
    } elseif ($func[0] instanceof Closure) {
      $callback = new UserCallback(null, array_shift($func));
      $callback->setArgs($func);
      $callback->keyArg = self::KEY_RIGHT;
      return $callback;
    } else {
      wrongArg("Invalid callback function format - must be array('class',".
               " 'method') or array('class.method').");
    }

    $args = static::parseArgs($target[1], $func);
    list($keyLeft, $keyRight) = static::extractModifiers($target[1], '#');

    if (!$args[0] and !$args[1] and ( $argCount === 0 or !($keyLeft ^ $keyRight) )) {
      // the callback always accepts zero or one arguments - optimize it.
      if ($class[0] === 'U' and !$target[0]) {
        $callback = new GlobalFnCallback1A(null, $target[1]);
      } else {
        $class = NS.$class.'1A';
        $callback = new $class($target[0], $target[1]);
      }
    } else {
      $class = NS.$class;
      $callback = new $class($target[0], $target[1]);
      $callback->setArgs($args[0], $args[1]);
    }

    $callback->keyArg = $keyLeft + ($keyRight << 1);
    return $callback;
  }

  static function parseArgs(&$method, array $args) {
    list($mergeLeft, $mergeRight) = static::extractModifiers($method, '*');

    if ($mergeLeft and $mergeRight) {
      $args += array(array(), array());
      is_array($args[0]) or $args[0] = array($args[0]);
      is_array($args[1]) or $args[1] = array($args[1]);
    } else {
      $args = $mergeLeft ? array($args, array()) : array(array(), $args);
    }

    return $args;
  }

  static function extractModifiers(&$method, $char) {
    $left = $method[0] === $char;
    $right = $method[strlen($method) - 1] === $char;
    ($left or $right) and $method = substr($method, $left, $right ? -1 : 999);
    return array($left, $right);
  }

  abstract function __construct($object, $method);

  function setArgs($left = array(), $right = array()) {
    $this->argsLeft = $left;
    $this->argsRight = $right;
    $this->hasArgs = ($left or $right);
    return $this;
  }

  function __invoke($arg_1, $key = null) {
    $args = func_get_args();
    $key = array_pop($args);

    switch ($this->keyArg) {
    case self::KEY_RIGHT:   $args[] = $key; break;
    case self::KEY_LEFT:    array_unshift($args, $key); break;
    case self::KEY_ONLY:    $args = array($key);
    }

    $this->hasArgs and $args = array_merge($this->argsLeft, $args, $this->argsRight);
    $result = $this->invokeWith($args);
    return $this->next ? $this->next($result, $key) : $result;
  }

  abstract function invokeWith(array $args);
}

class UserCallback extends Callback {
  protected $callback;                //= mixed PHP-format callback

  function __construct($object, $method) {
    $this->callback = $object ? array($object, $method) : $method;
  }

  function invokeWith(array $args) {
    return call_user_func_array($this->callback, $args);
  }
}

  class GlobalFnCallback1A extends UserCallback {
    function invokeWith(array $args) {
      $func = $this->callback;

      if (count($args) > 0) {   // don't use isset() because it can be array(null).
        return $func($args[0]);
      } else {
        return $func();
      }
    }
  }

  class UserCallback1A extends UserCallback {
    function invokeWith(array $args) {
      if (count($args) > 0) {
        return call_user_func($this->callback, $args[0]);
      } else {
        return call_user_func($this->callback);
      }
    }
  }

class MethodCallback extends Callback {
  protected $method;                  //= str

  function __construct($object, $method) {
    $this->method = $method;
  }

  function invokeWith(array $args) {
    return call_user_func_array(array(array_shift($args), $this->method), $args);
  }
}

  class MethodCallback1A extends MethodCallback {
    function invokeWith(array $args) {
      if (count($args) > 1) {
        return array_shift($args)->{$this->method}($args[0]);
      } else {
        return array_shift($args)->{$this->method}();
      }
    }
  }

class EvalCallback extends Callback {
  protected $source;                  //= str original expression like '(array) ?'
  protected $callback;                //= Closure

  static function toCode($expr) {
    $code = '';
    $argIndex = -1;
    $escaped = false;

    foreach (explode('?', "$expr ") as $i => $piece) {
      if ($i === 0) {
        // skip.
      } elseif ($escaped) {
        $code .= '?';
        $escaped = false;
      } elseif ($piece === '') {
        $escaped = true;    // e.g.: 'ternary ?? left : right'
      } else {
        $code .= '$args['.++$argIndex.']';
      }

      $code .= $piece;
    }

    return $code;
  }

  function __construct($object, $method) {
    $this->source = $method;
    $code = $this->code();
    $this->callback = eval('return function($args){return '.$code.';};');

    if (!$this->callback) {
      fail("Cannot compile evaluation: [ $method ] -> [ $code ].", 'EWrongEvalSyntax');
    }
  }

  function invokeWith(array $args) {
    try {
      $func = $this->callback;
      return $func($args);
    } catch (\Exception $e) {
      $msg = "Exception during evaluation of: {$this->source}";
      throw new \BadFunctionCallException($msg, 0, $e);
    }
  }

  //= str original expression like '(array) ?'
  function source() {
    return $this->source;
  }

  //= str equivalent PHP code like '(array) $args[0]'
  function code() {
    return static::toCode($this->source);
  }
}

  class EvalCallback1A extends EvalCallback { }

/*-------------------------------------------------------------------------
| INTERNAL FUNCTIONS
|------------------------------------------------------------------------*/

function iterable($v) {
  return is_array($v) or ($v instanceof Traversable);
}

function typeFunc(&$v, $string, $array) {
  return is_scalar($v) ? $string : (iterable($v) ? $array : null);
}

// function ($ansi)
//= $ansi if mbstring is supported, "mb_$ansi" otherwise
//
// function ($ansi, $mbstring)
//= $ansi if mbstring is supported, $mbstring otherwise
function mbstring($ansi, $mbstring = null) {
  return !Functions::$mbstring ? $ansi : ($mbstring ?: "mb_$ansi");
}

// function ($func[, $arg_1[, ...]])
//= mixed
function mbstringDo($func) {
  return call_user_func_array(mbstring($func), array_slice(func_get_args(), 1));
}

//= string, array, null
function transform($v, $func) {
  if ($v === null or is_scalar($v)) {
    return $func((string) $v);
  } elseif (iterable($v)) {
    $result = array();
    foreach ($v as $key => $value) { $result[$key] = $func($value); }
    return $result;
  }
}

function fail($msg, $class = 'Error') {
  $class[0] === '\\' or $class = NS.$class;
  throw new $class("Squall: $msg");
}

function wrongArg($msg) {
  fail($msg, '\\InvalidArgumentException');
}

function init($namespace = '\\', $name = 'S', $class = 'Functions') {
  $namespace = rtrim($namespace, '\\');
  $class = '\\'.NS.$class;

  if (ltrim($namespace.$name, 'a..zA..Z0..9_\\') !== '') {
    wrongArg('Namespace or name given to '.__FUNCTION__.' has wrong characters.');
  }

  eval("namespace $namespace;function $name(){return call_user_func_array(".
       "array('$class', 'make'), func_get_args());}");

  class_alias($class, "$namespace\\$name");
}

function initEx($namespace = '\\', $name = 'S') {
  init($namespace, $name, 'Utils');
}
