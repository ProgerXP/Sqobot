<?php namespace Sqobot;

class SIccupMatch extends SIccup {
  function skipURL($url) {
    return MatchPool::hasPage($this->name, (int) basename($url));
  }

  protected function doSlice($data, array $extra) {
    list($base) = $this->initIccup($data);

    $host = parse_url($base, PHP_URL_HOST);
    $language = strtok($host, '.');
    $matchID = $this->regexp($data, '~/ (\d+)</title>~u', 1);

    $match = array(
      'game'              => 'dota',
      'mode'              => static::shortMode($extra['mode']),
      'source'            => $this->queue()->url,
      'site'              => $this->name,
      'site_id'           => $matchID,
    );

    // Retrieving match info.
    $info = $this->regexp($data, '~<!-- Match info-->(.+)<div class="clear">~us', 1);

    $date = $dateStr = $this->getInfo($info, 'Дата|Date');
    $duration = $this->getInfo($info, 'Продолжительность игры|Game length');
    $map = $this->getInfo($info, 'Версия карты|Map version');

    $date = strtotime(strtr($date, '@', ','));
    if (!$date) {
      throw new ERegExpMismatch($this, "Cannot convert match date [$dateStr] to timestamp.");
    }

    $obj = new \DateTime;
    $date = $obj->setTimestamp($date);

    list(, $min, $sec) = $this->regexp($duration, '~(\d+)m\s*:\s*(\d+)s~u');
    $duration = $min * 60 + $sec;

    $map = $this->htmlToText($map);

    $match += compact('date', 'duration', 'map');

    // Retrieving hero statistics.
    $regexp = '~<!--Replay info-->(.+)<!--MAPS AND RACE statistics-->~us';
    $table = $this->regexp($data, $regexp, 1);

    $heroes = explode('class="t-corp2 ', $table);
    array_shift($heroes);

    if (!in_array($count = count($heroes), array(2, 6, 10))) {
      throw new ERegExpMismatch($this, "Wrong count ($count) of match heroes -".
                                " must be: 2 (1x1), 6 (3x3) or 10 (5x5).");
    }

    $mean = $match['count'] = $count / 2;
    $match['winner'] = $winner = $this->detectWinner($data, $mean);

    if (!$winner) {
      // ignore draws.
      return;
    }

    foreach ($heroes as $i => &$html) {
      $hero = $this->parseHero($html);
      $hero['hero'] = static::idByHeroName($hero['name']);

      $side = $i >= $mean ? 'scourge' : 'sentinel';
      $isWinner = $side === $winner;
      $hero += compact('side') + array('winner' => (int) $isWinner);

      $html = $hero;
    }

    // Retrieving hero artifacts.
    $regexp = '~<div class="details-items">(.+?)</div>~us';
    $items = $this->regexpAll($data, $regexp);

    if (count($items[1]) != $count) {
      throw new ERegExpMismatch($this, "Wrong count of artifact bags (".count($items[1]).")".
                                " - must be the same as hero count ($count).");
    }

    foreach ($items[1] as $i => &$html) {
      $names = $this->regexpAll($html, '~ alt=[\'"]([^\'"]+)[\'"]~u');

      if (count($names[1]) != 6) {
        warn($this->name.': incomplete artifact list? Expected 6 items, got '.
             count($names[1]).". Host: $host, match ID: $matchID, URL: $match[source]");
      }

      $heroes[$i]['items'] = $names[1];
    }

    $this->submit($match, $heroes);
  }

  function detectWinner($data, $teamCount) {
    // $data has:
    // ...
    // The Sentinel
    //   <info>
    // The Scourge
    //   <info>
    // Replay info
    // ...

    $sentinelPos = strpos($data, '<p>The Sentiel</p>');
    // Iccup has a typo: "Senti[n]el".
    $sentinelPos or $sentinelPos = strpos($data, '<p>The Sentinel</p>');

    $scourgePos = strpos($data, '<p>The Scourge</p>');
    $endPos = strpos($data, '<!--Replay info-->');

    if (!$sentinelPos or !$scourgePos or !$endPos or
        !($sentinelPos < $scourgePos and $scourgePos < $endPos)) {
      $blocks = compact('sentinelPos', 'scourgePos', 'endPos');
      throw new ERegExpMismatch($this, "Wrong structure of team blocks when detecting".
                                " game winner: ".var_export($blocks, true));
    }

    $looser = '<font color=\'darkred\'>';
    $winner = '<font color=\'darkgreen\'>';

    $sentinel = array(
      substr_count($data, $looser, $sentinelPos, $scourgePos - $sentinelPos),
      substr_count($data, $winner, $sentinelPos, $scourgePos - $sentinelPos),
    );

    $scourge = array(
      substr_count($data, $looser, $scourgePos, $endPos - $scourgePos),
      substr_count($data, $winner, $scourgePos, $endPos - $scourgePos),
    );

    if (array_sum($sentinel) != $teamCount or array_sum($scourge) != $teamCount) {
      throw new ERegExpNoMatch($this, "Found too few looser/winner substrings;".
                               " team size is $teamCount members.");
    }

    if ($sentinel[1] ^ $scourge[1]) {
      // one of the two teams has at least one winner.
      return $sentinel[1] ? 'sentinel' : 'scourge';
    } else {
      // either no teams have winners or both teams have winners.
      return false;
    }
  }

  function getInfo($data, $anchor) {
    $regexp = '~">(?:'.$anchor.')</div>\s*<div[^>]*>(?:<a [^>]+>)?([^<]+)</(?:div|a)>~u';
    return $this->regexp($data, $regexp, 1);
  }

  function parseHero($data) {
    $titles = $this->regexpAll($data, '~<img [^>]*title=[\'"]([^\'"]+)[\'"]~u');
    $name = $titles[1][0];

    $stats = $this->regexpAll($data, '~"field2 width\d+c">\s*(\d+)\s*<~u');
    $names = array('kills', 'deaths', 'assists', 'creeps', 'gold', 'towers');

    if (count($names) != count($stats[1])) {
      throw new ERegExpMismatch($this, "Count of matched stats is wrong; expected: ".
                                join(', ', $names).'.');
    }

    $stats = array_combine($names, $stats[1]);
    return compact('name') + $stats;
  }

  function submit(array $match, array $heroes) {
    atomic(function () use (&$match, &$heroes) {
      $match = MatchPool::createIgnoreWith(S::trimScalar($match));

      foreach ($heroes as $hero) {
        $items = $hero['items'];
        unset($hero['items']);

        $hero['match'] = $match->id;
        HeroRow::createWith(S::trimScalar($hero));

        foreach ($items as $name) {
          $item = array(
            'match'       => $match->id,
            'hero'        => $hero['hero'],
            'item'        => $name,
          );

          ItemRow::createWith(S::trim($item));
        }
      }
    });
  }
}