<?php namespace Sqobot;

/*
  http://ru.iccup.com/dota/matchlist/2227918/5x5.html

  User's match list. Enqueues further pages and also individual matches storing
  game's mode (All Pick, etc.) in $extra as it's unavailable from match page.
*/

class SIccupMatches extends SIccup {
  protected function doSlice($data, array $extra) {
    list($base, $page) = $this->initIccup($data, true);

    // Matching following pages.
    $regexp = '~<a href="/dota/(matchlist/[^"]+)">\s*(\d+)\s*</a>~u';
    $pages = $page ? $this->matchPages($data, $regexp, $page) : array();

    if ($pages and !$page) {
      throw new ERegExpMismatch($this, "Found pagebar but no current page. Format has changed?");
    }

    foreach ($pages as $page => $url) {
      $this->enqueue($base.$url, 'iccup.matches');
    }

    try {
      $regexp = '~<a href="(details/[^"]+)" class="game-details"~u';
      $links = $this->regexpAll($data, $regexp);
    } catch (ERegExpNoMatch $e) {
      if (preg_match('~width100">\s*\{list}~u', $data)) {
        // ICCup bug, empty list with no matches - ignore.
        return;
      } else {
        throw $e;
      }
    }

    $regexp = '~<div class="dotaMode">\s*([\w ]+)~u';
    $modes = $this->regexpAll($data, $regexp);

    if (count($links[1]) != count($modes[1])) {
      throw new ERegExpMismatch($this, "Count of 'Details' links (".count($links[1]).")".
                                " and game modes (".count($modes[1]).") doesn't match.");
    }

    foreach ($links[1] as $i => $url) {
      $id = (int) basename($url, '.html');

      if ($id > 0 and !MatchPool::hasPage('iccup.match', $id)) {
        $mode = $modes[1][$i];
        $this->enqueue($base.$url, 'iccup.match', compact('mode'));
      }
    }
  }
}