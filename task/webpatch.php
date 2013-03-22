<?php namespace Sqobot;

class TaskWebpatch extends Task {
  public $title = 'Patching';

  function do_(array $args = null) {
    if ($patch = Web::upload('patch')) {
      empty($args['nodes']) and $this->patch($patch['tmp_name'], $patch['name']);
      empty($args['self']) and $this->patchNodes($patch['tmp_name'], $patch['name']);
      return;
    }?>

    <form action="." method="post" enctype="multipart/form-data">
      <p>
        <input type="hidden" name="task" value="patch">
        <b>Patch with</b> (ZIP or single file):
        <input type="file" name="patch">
      </p>

  <?php
    echo '<p>';

    if (Node::all()) {
      echo HLEx::button_q('Patch self & nodes'), ' ',
           HLEx::button('Only self', array('name' => 'self', 'value' => 1)), ' ',
           HLEx::button('Only nodes', array('name' => 'nodes', 'value' => 1));
    } else {
      echo HLEx::button('Patch');
    }

    echo '</p></form>';
  }

  function patch($local, $name) {
    $isZip = S::ext($name) === '.zip';

    if (!$isZip) {
      if (copy($local, $name)) {
        echo HLEx::p('Patched '.HLEx::kbd_q($name).'.', 'ok');
      } else {
        $local = HLEx::kbd_q($local);
        $name = HLEx::kbd_q(getcwd().DIRECTORY_SEPARATOR.$name);
        Web::quit(500, HLEx::p("Cannot copy $local to $name."));
      }
    } elseif (!class_exists('ZipArchive')) {
      Web::quit(500, 'ZipArchive class (php_zip extension) is required.');
    } else {
      $zip = new \ZipArchive;

      if (($error = $zip->open($local)) !== true) {
        Web::quit(500, "Cannot open archive, ZipArchive error #$error.");
      } elseif (!$zip->extractTo('.')) {
        Web::quit(500, "<p>Cannot extract archive to ".HLEx::kbd_q(getcwd()).".</p>");
      }

      $zip->close();
      echo HLEx::p('Patched OK.', 'ok');
    }
  }

  function patchNodes($local, $name) {
    foreach (Node::all() as $node) {
      echo HLEx::h3_q('Node '.$node->id());

      try {
        echo $node->call('patch')
          ->addQuery('self')
          ->upload('patch', $name, fopen($local, 'rb'))
          ->fetchData();
      } catch (\Exception $e) {
        echo HLEx::p('Error sending request: '.HLEx::kbd_q($msg = exLine($e)).'.');
        error("Cannot contact node {$node->id()} to patch: $msg.");
      }
    }
  }
}