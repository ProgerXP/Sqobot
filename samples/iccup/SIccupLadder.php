<?php namespace Sqobot;

/*
  http://ru.iccup.com/dota/ladder/5x5[/pageN].html

  Initial ladder pages. Only goes forward, previous pages are not processed.
*/

class SIccupLadder extends SIccup {
  protected function doSlice($data, array $extra) {
    list($base, $page) = $this->initIccup($data, true);

    // Matching following pages.
    $regexp = '~<a href="(ladder/[^"]+)">\s*(\d+)\s*</a>~u';
    $pages = $this->matchPages($data, $regexp, $page);

    if ($pages and !$page) {
      throw new ERegExpMismatch($this, "Found pagebar but no current page. Format has changed?");
    }

    foreach ($pages as $page => $url) {
      $this->enqueue($base.$url, 'iccup.ladder');
    }

    // Matching user profiles.
    $regexp = '~<!-- UPCOMING MATCHES -->(.+)<!-- end UPCOMING MATCHES -->~us';
    $list = $this->regexp($data, $regexp, 1);

    $regexp = '~<a href="(gamingprofile/[^"]+)~u';
    $links = $this->regexpAll($list, $regexp);

    foreach ($links[1] as $i => $url) {
      // Links to match lists are contained within user's profile.
      $i and $last = remoteDelay($last);
      $profile = download($base.$url, $this->queue->url);
      $last = remoteDelay(true);

      $regexp = '~>\s*(\d+) \| <a href=[\'"](matchlist/[^\'"]+)~u';
      $lists = $this->regexpMap($profile, $regexp, 1, 2);

      foreach ($lists as $count => $url) {
        // $count refers to how many matches the user has played with this setup
        // (3x3 or 5x5). Not enqueuing lists that we know are empty.
        $count > 0 and $this->enqueue($base.$url, 'iccup.matches');
      }
    }
  }
}