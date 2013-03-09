<?php namespace Sqobot;

class TaskCycle extends Task {
  const YEAR = 31104000;

  function do_(array $args = null) {
    $queuer = Task::make('queue');

    if ($args === null) {
      echo 'cycle [...] - accepts all parameters of `queue [do]`, plus:', PHP_EOL,
           '  --max=TIMES --for=MINUTES --delay=20', PHP_EOL, PHP_EOL;
      $queuer->call('', null);
      return;
    }

    $args += array('max' => -1, 'for' => '', 'delay' => 20);

    $times = 1 + $args['max'];
    $until = $args['for'] ? (int) (time() + 60 * $args['for']) : PHP_INT_MAX;
    $delay = 1000 * $args['delay'];

    if ($times < 1 and $until > time() + static::YEAR) {
      echo 'Warning: no conditions given, running forever.', PHP_EOL, PHP_EOL;
    }

    for ($i = 1; $i != $times and time() < $until; ++$i) {
      echo $separ = '+'.str_repeat('-', 68).'+', PHP_EOL,
           sprintf('| ITERATION %-56s |', $i), PHP_EOL,
           $separ, PHP_EOL, PHP_EOL;

      $queuer->call('', $args);
      usleep($delay);
    }
  }
}