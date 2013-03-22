<?php namespace Sqobot;

class TaskWebtasks extends Task {
  public $title = 'Task shell';

  function do_(array $args = null) {?>
    <table class="task-launcher">
      <tr>
        <td>
          <table class="gen col-2">
            <tr>
              <th>Task:</th>
              <td>
                <?php
                  $methods = S::keys(Task::all(), function ($task) {
                    $methods = array_flip(Task::make($task)->methods());
                    ksort($methods);

                    if (isset($methods[''])) {
                      $methods['(default)'] = '';
                      unset($methods['']);
                    }

                    asort($methods);
                    return array($task, array_flip($methods));
                  });

                  echo HLEx::select('method', $methods, null);
                ?>
              </td>
            </tr>
            <tr>
              <th>Parameters:</th>
              <td class="args">
                <input placeholder="arg 1">
                <input placeholder="arg 2">
                <input placeholder="arg 3">
              </td>
            </tr>
            <tr>
              <th>Options:</th>
              <td>
                <textarea class="wide" name="options" rows="5" cols="40"
                          placeholder="-flags --option[=value]"></textarea>
              </td>
            </tr>
            <tr class="btn right">
              <td colspan="2">
                <button class="exec">Execute</button>
              </td>
            </tr>
          </table>
        </td>
        <td class="hide">
          <pre class="output"></pre>
        </td>
      </tr>
    </table>
  <?php
  }
}