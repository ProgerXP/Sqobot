<?php namespace Sqobot;

class TaskWebnodes extends Task {
  public $title = 'Other nodes';

  function do_(array $args = null) {
    $rows = S(Node::all(), function ($node) use (&$i) {
      $link = HLEx::a($node->id(), array('href' => $node->url(), 'target' => '_blank'));
      return HLEx::th(++$i.'. '.$link).HLEx::td($node->status());
    });

    if ($rows) {
      echo HLEx::table(join(S($rows, '"<tr>?</tr>"')), 'nodes');
    } else {
      echo HLEx::p('No configured nodes.', 'none');
    }
  }
}