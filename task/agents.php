<?php namespace Sqobot;

class TaskAgents extends Task {
  static $uaOrgXML = 'http://www.user-agents.org/allagents.xml';

  static $uasCom = array('B' => 'http://www.useragentstring.com/pages/Browserlist/',
                         'R' => 'http://www.useragentstring.com/pages/Crawlerlist/');

  static function writeList(array $agents) {
    $substr = (array) opt('substr');
    $total = count($agents);

    $s = $total == 1 ? '' : 's';
    echo "Found $total suitable agent string$s.", PHP_EOL;

    if (!$total) { return; }

    $max = min($total, opt('max', 500));
    $taken = array();
    $ignored = 0;

    $f = fopen('agents.txt', opt('append') ? 'ab' : 'wb');

    while ($max > 0 and count($taken) < $total) {
      do {
        $i = mt_rand(0, $total - 1);
      } while (isset($taken[$i]));

      $taken[$i] = true;
      $agent = trim($agents[$i]);

      if (!$substr or S::first($substr, array('.*strpos', " $agent"))) {
        fwrite($f, $agent."\n");
        --$max;
      } else {
        ++$ignored;
      }
    }

    fclose($f);

    $s = $ignored == 1 ? '' : 's';
    $ignored and print "Ignored $ignored agent$s not matching --substr[].".PHP_EOL;

    if ($ignored >= $total) {
      return print "Warning: nothing written!";
    }
  }

  function do_uaorg(array $args = null) {
    if ($args === null) {
      return print
        'agents uaorg [B|*]'.PHP_EOL.
        '  --max=500 --with[]=caseSensitive-Substr'.PHP_EOL.
        '  --local[=out/allagents.xml] --append'.PHP_EOL.
        PHP_EOL.
        'Agent type legend:'.PHP_EOL.
        PHP_EOL.
        '  B = Browser'.PHP_EOL.
        '  C = Link-, bookmark-, server- checking'.PHP_EOL.
        '  D = Downloading tool'.PHP_EOL.
        '  P = Proxy server, web filtering'.PHP_EOL.
        '  R = Robot, crawler, spider'.PHP_EOL.
        '  S = Spam or bad bot';
    }

    $local = &$args['local'];
    $dl = !isset($local);
    is_string($local) or $local = 'out/allagents.xml';

    if ($dl) {
      echo 'Downloading ', $url = static::$uaOrgXML, '... ';
      $data = file_get_contents($url);
      echo 'ok', PHP_EOL;
    } else {
      $data = file_get_contents($local);
    }

    echo 'Parsing...', PHP_EOL;
    $doc = parseXML($data);

    $types = opt(0, 'B');
    if ($types and $types !== '*') {
      $pf = 'contains(Type, "';
      $sf = '")';
      $types = '['.$pf.join( "$sf or $pf", str_split(strtoupper($types)) ).$sf.']';
    } else {
      $types = '';
    }

    $xpath = new \DOMXPath($doc);
    $nodes = $xpath->query("/user-agents/user-agent$types/String");
    $agents = S($nodes, '?->nodeValue');

    return static::writeList($agents);
  }

  function do_uascom(array $args = null) {
    if ($args === null) {
      return print
        'agents uascom [B|*]'.PHP_EOL.
        '  --max=500 --with[]=caseSensitive-Substr'.PHP_EOL.
        '  --append'.PHP_EOL.
        PHP_EOL.
        'Agent type legend:'.PHP_EOL.
        PHP_EOL.
        '  B = Browser'.PHP_EOL.
        '  R = Robot, crawler, spider'.PHP_EOL;
    }

    $urls = array();
    $types = strtoupper(opt(0, 'B'));

    if ($types and $types !== '*') {
      foreach (str_split($types) as $type) {
        $url = &static::$uasCom[$type];
        $url and $urls[] = $url;
      }
    } else {
      $urls = static::$uasCom;
    }

    $agents = array();

    foreach ($urls as $url) {
      echo "Downloading $url... ";
      $data = file_get_contents($url);
      echo 'ok', PHP_EOL;

      if (!preg_match_all('~<li><a [^>]+>([^<]+)</~', $data, $matches)) {
        echo "Cannot match regexp on $url.", PHP_EOL;
      } else {
        foreach ($matches[1] as $agent) {
          $agents[] = html_entity_decode($agent, ENT_QUOTES);
        }
      }
    }

    return static::writeList($agents);
  }
}