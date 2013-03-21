<?php namespace Sqobot;

class TaskWebqueue extends Task {
  public $title = 'Other nodes';

  function do_(array $args = null) {
    $rows = S(Nodes::all(), function ($node) use (&$i) {
      $link = HLEx::a($node->id(), array('href' => $node->url(), 'target' => '_blank'));
      return HLEx::th(++$i.'. '.$link).HLEx::td_q($node->status());
    });

    if ($rows) {
      return HLEx::table(join(S($rows, '"<tr>?</tr>"')), 'nodes');
    } else {
      return HLEx::p('No configured nodes.', 'none');
    }
  }
}