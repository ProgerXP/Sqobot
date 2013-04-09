<?php namespace Sqobot;

Atoms::$baseDest = 'out/atoms/'.date('ymd-His-').
                   substr(str_replace('.', '', uniqid('', true)), mt_rand(0, 14), 8).'_';

register_shutdown_function(array(NS.'Atoms', 'shutdown'));
onFatal(array(NS.'Atoms', 'panic'));

class Atoms {
  static $baseDest;
  //= has of Atom
  static $stack = array();
  static $accumulated = array();
  static $written = 0;
  //= str must be valid as a PHP name part
  static $varPrefix = "\x7F\x85";

  static function enabled($opType = null) {
    if ($opType) {
      return strpos(' '.cfg('atomate').' ', " $opType ") !== false;
    } else {
      return trim(cfg('atomate')) !== '';
    }
  }

  static function selfSign() {
    return array('on '.date('D, Y-m-d \a\t H:i:s'),
                 'by '.S::first(get_included_files()));
  }

  static function enter() {
    if (!static::$stack) {
      S::mkdir($dest = dirname(static::$baseDest));

      if (!is_dir($dest) or !is_writable($dest)) {
        throw new EAtoms("Atoms directory [$dest] isn't writable.");
      }
    }

    static::$stack[] = array(new FirstAtom);
  }

  static function active() {
    return !!static::$stack;
  }

  static function depth() {
    return count(static::$stack);
  }

  static function &current() {
    if (static::$stack) {
      $result = &static::$stack[static::depth() - 1];
    } else {
      $result = null;
    }

    return $result;
  }

  //* $ignoreLimit bool, Exception as true
  static function commit($ignoreLimit = false) {
    $atoms = array_pop(static::$stack);
    if (!is_array($atoms)) {
      return error('Bad nesting of Atoms calls - no stack to commit().');
    }

    static::$accumulated[] = $atoms;

    if (!$ignoreLimit) {
      $total = array_sum(array_map('count', static::$accumulated))
               - count(static::$accumulated);   // excluding FirstAtom's.
      if ($total < cfg('atomsPerFile')) { return; }
    }

    $transactions = static::$accumulated;
    // null buffer indicates that a commit is taking place.
    static::$accumulated = null;

    $code = "<?php\n// Committed ";

    if ($ignoreLimit instanceof \Exception) {
      $code .= "due to exception:\n//   ".exLine($ignoreLimit)."\n//\n// ";
    } elseif ($ignoreLimit) {
      $code .= "ignoring atomsPerFile limit (typically flushing on shutdown)\n// ";
    }

    $code .= join("\n// ", static::selfSign());

    foreach ($transactions as $atoms) {
      if (count($atoms) > 1 or !(reset($atoms) instanceof FirstAtom)) {
        foreach ($atoms as $id => $atom) {
          $lines = $atom->code($id, $atoms);
          $lines and $code .= "\n\n".join("\n", $lines);

          // if kept following atoms will recognize its 0 key as atom ID and
          // use it in expression like '$0->id' for zero-value fields.
          if ($id === 0 and $atom instanceof FirstAtom) { unset($atoms[$id]); }
        }
      }
    }

    if (isset($lines)) {
      $dest = static::generateDest();

      if (!file_put_contents($dest, $code, LOCK_EX)) {
        throw new EAtoms("Cannot commit atom transaction to [$dest].");
      }
    }

    // indicating that the commit has been successfully finished.
    static::$accumulated = array();
  }

  static function generateDest() {
    $base = static::$baseDest.++static::$written;
    $file = "$base.php";

    $i = 1;
    while (is_file($file)) { $file = "$base-".++$i.".php"; }

    S::mkdirOf($file);
    touch($file);
    return $file;
  }

  // Is called when script is abruptly halted by a Fatal Error attempting to save
  // what's not yet lost.
  static function panic($e) {
    if (static::$accumulated === null) {
      error("Critical error while committing transaction - accumulated data is".
            " lost: ".exLine($e));
      static::abandon();
    }

    // remove atoms not finalized by a call to  commit().
    static::$stack = array();
    static::shutdown($e);
  }

  static function abandon() {
    static::$accumulated = array();
  }

  static function rollback() {
    array_pop(static::$stack);
  }

  static function shutdown($e = null) {
    if ($count = static::depth()) {
      $s = $count == 1 ? '' : 's';
      error("Ignoring leftover $count atom stack$s on shutdown.");
    }

    try {
      static::$stack = array(array());
      // commit accumulated items.
      static::commit( is_object($e) ? $e : true );
    } catch (\Exception $e) {
      error('Exception while committing accumlated atoms on shutdown: '.exLine($e));
    }
  }

  //= str atom ID
  static function addRow($class, array $fields, $opType = 'create') {
    return static::add(new RowAtom($class, $fields, $opType));
  }

  //= str atom ID
  static function addCode($code) {
    return static::add(new CodeAtom($code));
  }

  //= str atom ID
  static function add(Atom $atom) {
    if (!static::$stack) {
      throw new EAtoms("Cannot add() new atom - no transaction is active.");
    } else {
      $current = &static::current();

      do {
        $key = static::$varPrefix.dechex(mt_rand());
      } while (isset($current[$key]));

      $current[$key] = $atom;
      return $key;
    }
  }
}

abstract class Atom {
  //* $known hash of str - 'atomMask' => 'varName'.
  //= array of code lines
  abstract function code($class, array $fields);
}

class RowAtom extends Atom {
  public $class;
  public $fields;
  public $opType;   //= str method name like 'create', 'createIgnore' or 'update'

  function __construct($class, array $fields, $opType = 'create') {
    $this->class = is_object($class) ? get_class($class) : $class;
    $this->fields = $fields;
    $this->opType = $opType;
  }

  function code($id, array $known) {
    $depends = array();
    $code = array("$$id = {$this->class}::{$this->opType}With(array(");

    foreach ($this->fields as $field => $value) {
      $key = sprintf('  %-23s => ', var_export($field, true));

      if (isset($known[$value])) {
        $depends[] = "\$$value";
        $code[] = "$key\${$value}->id,";
      } else {
        $code[] = "$key".var_export($value, true).",";
      }
    }

    $code[] = '));';

    if ($depends) {
      $code = S::prefix($code, '  ');
      array_unshift($code, join(' && ', $depends).' &&');
    }

    return $code;
  }
}

class CodeAtom extends Atom {
  public $code;

  function __construct($code) {
    $this->code = $code;
  }

  function code($id, array $known) {
    return array("$$id = {$this->code};");
  }
}

class FirstAtom extends Atom {
  public $started;

  function __construct() {
    $this->started = time();
  }

  function code($id, array $known) {
    return array($separ = '// '.str_repeat('-', 76),
                 '// Transaction started at '.date('H:i:s', $this->started),
                 $separ);
  }
}