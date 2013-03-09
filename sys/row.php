<?php namespace Sqobot;

class Row {
  static $defaultTable;
  static $fields = array('id');

  public $table;
  public $id;

  static function tableName($table = null) {
    if (!$table) {
      if ($table = static::$defaultTable) {
        $table = cfg('dbPrefix').$table;
      } else {
        $class = get_called_class();
        throw new Error("No default table specified for Row class $class.");
      }
    }

    return $table;
  }

  //= Row new entry
  static function createWith($fields) {
    return static::make($fields)->create();
  }

  static function make($fields = array()) {
    return new static($fields);
  }

  //* $fields stdClass, hash
  function __construct($fields = array()) {
    $this->defaults()->fill($fields);
  }

  //* $fields stdClass, hash
  function fill($fields) {
    is_object($fields) and $fields = get_object_vars($fields);
    foreach ($fields as $field => $value) { $this->$field = $value; }
    return $this;
  }

  // Must return $this.
  function defaults() {
    return $this;
  }

  function create() {
    $bind = $this->sqlFields();
    unset($bind['id']);
    list($fields, $bind) = S::divide($bind);

    $sql = 'INSERT INTO `'.$this->table().'` (`'.join('`, `', $fields).'`) VALUES'.
           ' ('.join(', ', S($bind, '"??"')).')';
    $id = exec($sql, $bind);
    in_array('id', static::$fields) and $this->id = $id;
    return $this;
  }

  function update() {
    $fields = S(static::$fields, '"`?` = ?"');
    $bind = array_values($this->sqlFields());

    $sql = 'UPDATE `'.$this->table().'` SET `'.join(', ', $fields);
    exec($sql, $bind);
    return $this;
  }

  function table($new = null) {
    if (func_num_args()) {
      $this->table = $new;
      return $this;
    } else {
      return static::tableName($this->table);
    }
  }

  function sqlFields() {
    $result = array();

    foreach (static::$fields as $field) {
      if ($this->$field instanceof \DateTime) {
        $result[$field] = S::sqlDateTime($this->$field->getTimestamp());
      } else {
        $result[$field] = $this->$field;
      }
    }

    return $result;
  }
}