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

  static function count(array $fields = null) {
    $sql = 'SELECT COUNT(1) AS count FROM `'.static::tableName().'`';
    $fields and $sql .= ' WHERE '.join(' AND ', S($fields, '#"`?` = ??"'));

    $stmt = exec($sql, array_values((array) $fields));
    $count = $stmt->fetch()->count;
    $stmt->closeCursor();

    return $count;
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

    foreach (static::$fields as $field) {
      isset($fields[$field]) and $this->$field = $fields[$field];
    }

    return $this;
  }

  // Must return $this.
  function defaults() {
    return $this;
  }

  // See create(), createWith(), createIgnore() and others.
  protected function doCreate($method, $sqlVerb) {
    $bind = $this->sqlFields();
    unset($bind['id']);

    if (Atoms::enabled('create')) {
      $id = Atoms::addRow($this, $bind, $method);
    } else {
      list($fields, $bind) = S::divide($bind);

      $sql = "$sqlVerb INTO `".$this->table().'`'.
             ' (`'.join('`, `', $fields).'`) VALUES'.
             ' ('.join(', ', S($bind, '"??"')).')';

      $id = exec($sql, $bind);
    }

    in_array('id', static::$fields) and $this->id = $id;
    return $this;
  }

  // See update(), updateWith(), updateIgnore() and others.
  protected function doUpdate($method, $sqlVerb) {
    if (Atoms::enabled('update')) {
      Atoms::addRow($this, $bind, $method);
    } else {
      $fields = S(static::$fields, '"`?` = ?"');
      $bind = array_values($this->sqlFields());

      $sql = "$sqlVerb `".$this->table().'` SET `'.join(', ', $fields).
             ' WHERE site = :site AND site_id = :site_id';
      exec($sql, $bind);
    }

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

  /*---------------------------------------------------------------------
  | RECORD MANIPULATION VERBS
  |--------------------------------------------------------------------*/

  //= Row new entry
  static function createWith($fields) {
    return static::make($fields)->create();
  }

  //= Row new entry
  static function createIgnoreWith($fields) {
    return static::make($fields)->createIgnore();
  }

  //= Row new entry
  static function createOrReplaceWith($fields) {
    return static::make($fields)->createOrReplace();
  }

  //= Row updated entry
  static function updateWith($fields) {
    return static::make($fields)->update();
  }

  //= Row updated entry
  static function updateIgnoreWith($fields) {
    return static::make($fields)->updateIgnore();
  }

  //= $this
  function create() {
    return $this->doCreate(__FUNCTION__, 'INSERT');
  }

  //= $this
  function createIgnore() {
    return $this->doCreate(__FUNCTION__, 'INSERT IGNORE');
  }

  //= $this
  function createOrReplace() {
    return $this->doCreate(__FUNCTION__, 'REPLACE');
  }

  //= $this
  function update() {
    return $this->doUpdate(__FUNCTION__, 'UPDATE');
  }

  //= $this
  function updateIgnore() {
    return $this->doUpdate(__FUNCTION__, 'UPDATE IGNORE');
  }
}