<?php namespace Sqobot;

class TaskWebpatch extends Task {
  public $title = 'Patching';

  static function patchNodes(array $uploads, $rawZIP = false) {
    foreach (Node::all() as $node) {
      echo HLEx::h3_q('Node '.$node->id());

      try {
        $req = $node->call('patch')->addQuery('self');
        $rawZIP and $req->addQuery('rawzip');

        foreach ($uploads as $var => $upload) {
          if ($upload) {
            $req->upload($var, $upload['name'], fopen($upload['tmp_name'], 'rb'));
          }
        }

        echo $req->fetchData();
      } catch (\Exception $e) {
        echo HLEx::p('Error sending request: '.HLEx::kbd_q($msg = exLine($e)).'.');
        error("Error contacting node {$node->id()} to patch: $msg.");
      }
    }
  }

  function do_(array $args = null) {
    $files = Web::upload('files');
    $sql = Web::upload('sql');

    if ($files or $sql) {
      if (empty($args['nodes'])) {
        $files and $this->patchFiles($files['tmp_name'], $files['name']);
        $files and $sql and print '<hr>';
        $sql and $this->patchSQLs($sql['tmp_name'], $sql['name']);
      }

      if (empty($args['self'])) {
        static::patchNodes(compact('files', 'sql'), Web::is('rawzip'));
      }

      return;
    }?>

    <form action="." method="post" enctype="multipart/form-data">
      <input type="hidden" name="task" value="patch">

      <p>
        <b>Replace files</b>
        (<span class="help">ZIP or single file;</span>
         <label><input type="checkbox" name="rawzip" value="1"> raw ZIP</label>):
        <input type="file" name="files">
      </p>

      <p>
        <b>Execute SQLs</b> (can be ZIP):
        <input type="file" name="sql">
      </p>

  <?php
    echo '<p class="btn">';

    if (Node::all()) {
      echo HLEx::button_q('Patch self & nodes'), ' ',
           HLEx::button('Only self', array('name' => 'self', 'value' => 1)), ' ',
           HLEx::button('Only nodes', array('name' => 'nodes', 'value' => 1));
    } else {
      echo HLEx::button('Patch');
    }

    echo '</p></form>';
  }

  function patchFiles($local, $name) {
    $isZip = (!Web::is('rawzip') and S::ext($name) === '.zip');

    if (!$isZip) {
      if (copy($local, $name)) {
        echo HLEx::p('Written '.HLEx::kbd_q($name).'.', 'ok');
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

      $count = static::countZip($zip);
      $zip->close();

      $sfiles = $count['files'] == 1 ? '' : 's';
      $sdirs = $count['dirs'] == 1 ? '' : 's';
      echo HLEx::p("Extracted $count[files] file$sfiles and".
                   " $count[dirs] folder$sdirs.", 'ok');
    }
  }

    static function countZip($zip) {
      for ($files = $dirs = $i = 0; $stat = $zip->statIndex($i); ++$i) {
        substr($stat['name'], -1) === '/' ? ++$dirs : ++$files;
      }

      return compact('files', 'dirs');
    }

  function patchSQLs($local, $name) {
    $isZip = S::ext($name) === '.zip';

    if (!$isZip) {
      $this->patchSQL(file_get_contents($local));
    } elseif (!class_exists('ZipArchive')) {
      Web::quit(500, 'ZipArchive class (php_zip extension) is required.');
    } else {
      $zip = new \ZipArchive;

      if (($error = $zip->open($local)) !== true) {
        Web::quit(500, "Cannot open archive, ZipArchive error #$error.");
      }

      $files = $affected = 0;
      ob_start();
      echo '<ol>';

      for ($i = 0; $name = $zip->getNameIndex($i); ++$i) {
        if (S::ext($name) === '.sql') {
          $affected += $this->patchSQL($zip->getFromIndex($i), $name);
          ++$files;
        }
      }

      echo '</ol>';

      $sfiles = $files == 1 ? '' : 's';
      $saff = $affected == 1 ? '' : 's';
      $summary = "<b>$affected row$saff affected</b> after running $files file$sfiles:";

      echo HLEx::p($summary, 'summary').
           ob_get_clean();
    }
  }

  function patchSQL($sql, $file = '') {
    $affected = 0;
    $errors = array();

    while ($sql) {
      try {
        $affected += dbImport($sql);
        $sql = array();
      } catch (EDbImport $e) {
        $errors[] = HLEx::p_q($e->getPrevious()->getMessage(), 'error').
                    HLEx::pre_q($e->sql, 'gen sql');
        $affected += $e->affected;
        $sql = $e->remainingSQLs;
      }
    }

    if ($file) {
      echo HLEx::tag('li', $errors ? 'errors' : ''), HLEx::kbd_q("$file: ");
    } else {
      echo '<p>';
    }

    $s = $affected == 1 ? '' : 's';
    echo "$affected row$s affected.", join($errors);
    echo $file ? '</li>' : '</p>';

    return $affected;
  }
}