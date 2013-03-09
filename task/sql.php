<?php namespace Sqobot;

class TaskSql extends Task {
  function do_init(array $args = null) {
    if ($args === null) {
      return print 'sql init [TABLE] --exec=0 --drop=0';
    }

    $table = cfg('dbPrefix').opt(0, 'queue');
    $engine = cfg('dbEngine', ' ENGINE=$');

    $sql = '';

    empty($args['drop']) or $sql .= "DROP TABLE IF EXISTS `$table`;\n\n";

    $sql .= <<<SQL
CREATE TABLE `$table` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `url` TEXT NOT NULL,
  `created` DATETIME NOT NULL,
  `started` DATETIME DEFAULT NULL,
  `error` TEXT COLLATE latin1_general_ci NOT NULL,
  `site` VARCHAR(50) NOT NULL,
  `extra` TEXT NOT NULL,

  PRIMARY KEY (`id`),
  KEY `error` (`error`(1)),
  KEY `site` (`site`),
  KEY `started` (`started`,`site`)
)$engine DEFAULT COLLATE=latin1_bin;
SQL;

    echo $sql, PHP_EOL;
    empty($args['exec']) or dbImport($sql);
  }

  function do_pool(array $args = null) {
    if ($args === null) {
      return print 'sql pool [TABLE]';
    }

    $table = cfg('dbPrefix').opt(0, 'pool');
    $engine = cfg('dbEngine', ' ENGINE=$');

    $sql = <<<SQL
CREATE TABLE `$table` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `source` CHAR(100) NOT NULL,
  `site` VARCHAR(50) NOT NULL,
  `site_id` VARBINARY(16) NOT NULL,
  `created` DATETIME NOT NULL,
  -- your fields here --

  PRIMARY KEY (`id`),
  KEY `site` (`site`,`site_id`),
  KEY `created` (`created`)
)$engine DEFAULT COLLATE=latin1_bin;
SQL;

    echo $sql, PHP_EOL;
  }
}