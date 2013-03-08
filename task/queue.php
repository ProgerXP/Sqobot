<?php namespace Sqobot;

class TaskQueue extends Task {
  function do_add(array $args = null) {
    if ($args === null or !opt(1)) {
      return print 'queue add URL SITE --extra={json} --table=queue';
    }

    $queue = Queue::make(array('url' => opt(0), 'site' => opt(1)));

    if ($extra = &$args['extra']) {
      $extra = json_decode($extra, true);
      if (!is_array($extra)) {
        return print 'Bad JSON string passed with --extra - it must evaluate to'.
                     ' array but got:'.PHP_EOL.var_export($extra, true).PHP_EOL;
      }

      $queue->extra($extra);
    }

    $table = &$args['table'] and $queue->table = cfg('dbPrefix').$table;
    $queue->create();

    echo "Created queue item #{$queue->id} in {$queue->table()} table.", PHP_EOL;

    $extra and print 'Extra data attached:'.PHP_EOL.
                     var_export($extra, true).PHP_EOL;
  }
}