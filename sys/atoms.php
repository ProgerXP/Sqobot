<?php namespace Sqobot;

Atoms::$baseDest = 'out/atoms/'.date('ymd-').
                   substr(str_replace('.', '', uniqid('', true)), mt_rand(0, 14), 8).'_';

class Atoms {
  static $baseDest;
  //= has of Atom
  static $stack = array();
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
    static::$stack[] = array();
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

  static function abandon() {
    array_pop(static::$stack);
  }

  static function commit() {
    if (!static::$stack) {
      error('Bad nesting of Atoms calls - no stack to commit().');
    } elseif ($atoms = array_pop(static::$stack)) {
      $code = "<?php\n".
              "// Committed ".join("\n// ", static::selfSign());

      foreach ($atoms as $id => $atom) {
        $lines = $atom->code($id, $atoms);
        $lines and $code .= "\n\n".join("\n", $lines);
      }

      $dest = static::generateDest();

      if (!file_put_contents($dest, $code, LOCK_EX)) {
        throw new EAtoms("Cannot commit atom transaction to [$dest].");
      }
    }
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
  public $opType;   //= str method name like 'create' or 'update'

  function __construct($class, array $fields, $opType = 'create') {
    $this->class = is_object($class) ? get_class($class) : $class;
    $this->fields = $fields;
    $this->opType = $opType;
  }

  function code($id, array $known) {
    $code = array("$$id = {$this->class}::{$this->opType}With(array(");

    foreach ($this->fields as $field => $value) {
      $key = sprintf('  %-23s => ', var_export($field, true));

      if (isset($known[$value])) {
        $code[] = "$key\${$value}->id,";
      } else {
        $code[] = "$key".var_export($value, true).",";
      }
    }

    $code[] = '));';
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
