<?php namespace Sqobot;

class TaskWebtasks extends Task {
  public $title = 'Task shell';

  function do_(array $args = null) {
    $ran = (($help = Web::is('help')) or Web::is('exec'));
    $full = ($ran and S::pickFlat($args, 'exec') === 'full');
    $output = '';

    if ($ran) {
      $output = $this->capture('exec', compact('help') + $args);
      if (!empty($args['output'])) { return print $output; }
    }

    $optionsLegend =
      '-flags --option[=value]. Options on the first line are split with spaces.'.
      ' Other lines are options by themselves (use them when value contains spaces).';
  ?>
    <table class="<?php echo ($ran and !$full) ? 'pivot' : ''?>">
      <tr>
        <td>
          <form action="." accept-charset="utf-8">
            <input type="hidden" name="task" value="tasks">

            <table class="gen col-2">
              <tr>
                <th>Task:</th>
                <td>
                  <?php
                    $tasks = S::keys(Task::all(), function ($task) {
                      $methods = S::combine(Task::make($task)->methods());

                      if (isset($methods[''])) {
                        $methods['(default)'] = '';
                        unset($methods['']);
                      }

                      ksort($methods);
                      $methods = S::prefix($methods, "$task-");
                      return array($task, array_flip($methods));
                    });

                    ksort($tasks);
                    $selected = $ran ? $args['to_call'] : Web::cookie('tasks-last');
                    echo HLEx::select('to_call', $tasks, $selected);
                  ?>

                  <button type="submit" name="help" value="1">Help</button>
                </td>
              </tr>
              <tr>
                <th>Parameters:</th>
                <td class="args">
                  <?php for ($i = 1; $i <= 3; ++$i) {?>
                    <input name="args[]" placeholder="arg <?php echo $i?>" size="12">
                  <?php }?>
                </td>
              </tr>
              <tr>
                <th>Options:</th>
                <td>
                  <textarea name="options" class="wide" rows="5" cols="40"
                            placeholder="<?php echo $optionsLegend?>"></textarea>
                </td>
              </tr>
              <tr class="btn right">
                <td colspan="2">
                  <button type="submit" name="exec" value="1">Execute</button>
                  <button type="submit" name="exec" value="full">Full-screen</button>
                </td>
              </tr>
            </table>
          </form>
        </td>
        <td class="output">
          <?php echo $full ? '' : $output?>
        </td>
      </tr>
    </table>
  <?php
    $full and print HLEx::div($output, 'full-screen');
  }

  function do_exec(array $args = null) {
    $task = &$args['to_call'];
    $task or Web::quit(400, HLEx::p('The <b>to_call</b> parameter is missing.'));

    Web::cookie('tasks-last', $task);
    list($task, $method) = explode('-', "$task-");

    $opt = trim(S::pickFlat($args, 'options'));
    $opt = $opt === '' ? array() : S::trim(explode("\n", $opt));
    $opt and array_splice($opt, 0, 1, explode(' ', $opt[0]));

    $indexed = (array) S::pickFlat($args, 'args');
    Core::$cl = S::parseCL(array_merge($indexed, $opt), true);

    try {
      $obj = Task::make($task);
      $output = $obj->capture($method, empty($args['help']) ? array() : null);
      Core::$cl = null;
    } catch (ENoTask $e) {
      Web::quit(400, HLEx::p_q($e->getMessage()));
    } catch (\Exception $e) {
      Web::quit(500, HLEx::p_q('Exception '.exLine($e)));
    }

    $output = HLEx::q($output);

    if (!empty($args['help'])) {
      $output = HLEx::b(strtok($output, "\n")).strtok(null);
      echo "<p><b>Help:</b></p>";
    }

    echo HLEx::pre($output, 'gen output');
  }
}