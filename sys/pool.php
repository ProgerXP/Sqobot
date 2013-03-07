<?php namespace Sqobot;

class Pool extends Row {
  static $fields = array('id', 'source', 'site', 'site_id', 'created');

  public $source, $site, $site_id, $created;

  function defaults() {
    $this->created = new \DateTime;
    $this->source = '';
    $this->site_id = 0;
    return $this;
  }

  function created() {
    return toTimestamp($this->created);
  }
}