<?php namespace Sqobot;

class Pool extends Row {
  static $fields = array('id', 'source', 'site', 'site_id', 'created');

  public $source, $site, $site_id, $created;

  static function hasPage($site, $site_id) {
    if (PageIndex::enabled()) {
      return PageIndex::has(static::tableName(), $site, $site_id);
    } else {
      return static::count(compact('site', 'site_id'));
    }
  }

  function defaults() {
    $this->created = new \DateTime;
    $this->source = '';
    $this->site_id = 0;
    return $this;
  }

  function created() {
    return toTimestamp($this->created);
  }

  function create($mode = '') {
    parent::create($mode);

    if (PageIndex::enabled()) {
      $page = array(
        'table'             => $this->table(),
        'site'              => $this->site,
        'site_id'           => $this->site_id,
      );

      PageIndex::createIgnoreWith($page);
    }

    return $this;
  }
}