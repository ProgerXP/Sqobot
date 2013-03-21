<?php namespace Sqobot;

class TaskWebtasks extends Task {
  public $title = 'Task shell';

  function do_(array $args = null) {?>
    <table "task-launcher pivot">
      <tr>
        <td>
          <table class="col-2">
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

                    return array($task, array_values($methods));
                  });

                  echo HLEx::select('method', $methods, null);
                ?>
              </td>
            </tr>
            <tr>
              <th>Parameters:</th>
              <td>
                <input name="args" placeholder="arg#1 &quot;arg 2&quot; ...">
              </td>
            </tr>
            <tr>
              <th>Options:</th>
              <td>
                <textarea name="options" rows="5" cols="40"
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
        <td>
          <pre class="output"></pre>
        </td>
      </tr>
    </table>
  <?php
  }
}