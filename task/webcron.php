<?php namespace Sqobot;

class TaskWebcron extends Task {
  public $title = 'Cron Jobs';

  static function splitArgv($cl) {
    $argv = array();
    $joiner = null;

    foreach (explode(' ', $cl) as $part) {
      if ($joiner) {
        if ($part !== '' and substr($part, -1) === $joiner) {
          $joiner = null;
          $argv[count($argv) - 1] .= ' '.substr($part, 0, -1);
        }
      } elseif ($part === '""' or $part === "''") {
        $argv[] = '';
      } elseif ($part === '') {
        // skip.
      } elseif ($tail = strpbrk($part, '"\'')) {
        $argv[] = str_replace($tail[0], '', $part, $count);
        $count % 2 == 1 and $joiner = $tail[0];
      } else {
        $argv[] = $part;
      }
    }

    return $argv;
  }

  static function mailTo($addr, $subject, $body) {
    $host = Web::info('HTTP_HOST') ?: dirname(S::first(get_included_files()));

    $mail = new \MiMeil($addr, "$subject — Sqobot at $host");
    $mail->from = cfg('mailFrom');
    $mail->body('html', Web::template('mail', compact('body')));
    $mail->send() or warn("Problem sending e-mail to $addr (\"$subject\").");
  }

  function do_(array $args = null) {
    $tasks = S(cfgGroup('webcron'), function ($cl, $name) {
      @list($action, $query) = explode('?', taskURL('cron-exec', compact('name')), 2);

      $button = HLEx::tag('form', compact('action')).
                  HLEx::hiddens($query).
                  HLEx::button_q($name, array('type' => 'submit')).
                '</form>';

      return HLEx::tr(HLEx::th($button).HLEx::td(HLEx::kbd_q($cl)));
    });

    if ($tasks) {
      $tasks = HLEx::table(join($tasks), 'tasks');
    } else {
      $tasks = HLEx::p('No tasks defined with a <b>webcron</b> setting.', 'none');
    }

    if (!Node::exists()) {
      $nodes = '';
    } else {
      ob_start();
    ?>
      <form action="." class="poll">
        <input type="hidden" name="task" value="cron-poll">

        <p>
          <b>Poll node with task:</b> <input name="name">
        </p>
    <?php
      $nodes = S(Node::all(), function ($node) {
        return HLEx::button_q($node->name, array(
          'type'          => 'submit',
          'name'          => 'node',
          'value'         => $node->name,
        ));
      });

      array_unshift($nodes, '<button type="submit" name="node" value="">All</button>');
      $nodes = ob_get_clean().HLEx::p(join(' ', $nodes), 'btn').'</form>';
    }

    if ($nodes) {
      echo HLEx::table("<tr><td>$tasks</td><td>$nodes</td></tr>", 'pivot');
    } else {
      echo $tasks;
    }
  }

  function do_exec(array $args = null) {
    $name = &$args['name'];
    $name or Web::quit(400, HLEx::p('The <b>name</b> parameter is missing.'));

    Web::can("cron-$name") or Web::deny("cron task $name.");

    $cl = cfg("webcron $name");
    $cl or Web::quit(404, HLEx::p('No '.HLEx::b_q("webcron $name").' setting.'));

    Core::$cl = S::parseCL(static::splitArgv($cl), true);

    Core::$cl['index'] += array('', '');
    $task = array_shift(Core::$cl['index']);
    $method = array_shift(Core::$cl['index']);

    echo HLEx::p(HLEx::kbd_q("# $task $method"));

    try {
      $obj = Task::make($task);
      $output = $obj->capture($method, Core::$cl['options']);
      Core::$cl = null;
    } catch (ENoTask $e) {
      Web::quit(400, HLEx::p_q($e->getMessage()));
    } catch (\Exception $e) {
      Web::quit(500, HLEx::p_q('Exception '.exLine($e)));
    }

    echo HLEx::pre_q(trim($output, "\r\n"), 'gen output');
  }

  function do_poll(array $args = null) {
    $args += S::combine(array('name', 'node'), opt());

    if ($args === null or !$args['name']) {
      return print 'webcron poll [--name=]TASK [[--node=]NODE]';
    }

    $nodes = $args['node'] ? array(Node::get($args['node'])) : Node::all();

    foreach ($nodes as $node) {
      $name = $node->name;
      echo HLEx::h3_q($name);

      try {
        echo $response = $node
          ->call('cron-exec', array('name' => $args['name']))
          ->fetchData();

        if ($addr = cfg('webcronMailOK')) {
          $msg = HLEx::p('Successfully polled node '.HLEx::b_q($name).': ').$response;
          static::mailTo($addr, "Polled $name", $msg);
          echo HLEx::p('Sent output to '.HLEx::b_q($addr).'.');
        }
      } catch (\Exception $e) {
        echo $msg = HLEx::p_q(exLine($e), 'error');

        if ($addr = cfg('webcronMailErrors')) {
          $msg = HLEx::p('Problem polling node '.HLEx::b_q($name).': ').$msg;
          static::mailTo($addr, "Problem polling $name", $msg);
          echo HLEx::p('Sent report to '.HLEx::b_q($addr).'.');
        }
      }
    }
  }
}