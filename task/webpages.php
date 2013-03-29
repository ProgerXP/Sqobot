<?php namespace Sqobot;

class TaskWebpages extends Task {
  public $title = 'Page Index';

  function do_pack(array $args = null) {
    Core::$cl['index'] = S::trim( explode(',', S::pickFlat($args, 'tables', '*')) );
    unset($args['out']);

    $log = Task::make('pages')->capture('pack', $args);
    $dest = 'out/pages.'.(empty($args['zip']) ? 'sql' : 'zip');

    if (is_file($dest)) {
      readfile($dest);
      unlink($dest);
    } else {
      Web::quit(500, HLEx::pre_q($log));
    }
  }

  function do_unpack(array $args = null) {
    $file = Core::$cl['index'][0] = Web::upload('pages.tmp_name');
    $file or Web::quit(400, '<p>Upload variable <b>file</b> is missing.</p>');

    return Task::make('pages')->call('unpack', $args);
  }
}