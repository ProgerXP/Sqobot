# Sqobot

Standalone PHP framework for writing distributed crawlers &amp; website parsers. Requires PHP 5.3 and MySQL 5; no other libraries are needed (including cURL).

Supports crawlers distributed on the same and/or other machines. Also supports extract-and-go upgrading as core files (`lib`, `sys`, `task`, `web`) are separated from user files (`out`, `user`).

Can work without database or with only a partial database, offloading new entries/changes to _atoms_ that are later imported on the main system.

Sqobot is specifically focused on stability and maximum independence from human interaction (apart from main _node_). It has sophisticated error logging/reporting and transaction mechanisms.

Licensed in public domain. Uses [MiMeil](https://github.com/ProgerXP/MiMeil) for message delivery and [Squall](https://github.com/ProgerXP/Squall) for concise & functional PHP programming.

Visit author's homepage at http://proger.me.

## Basic concepts

**Node** is a remote Sqobot instance. Typically nodes are located on physically different machines although its not a requirement. Node has its own access URL (web interface address) and username/password for HTTP Basic Authentication.

**Atom** is an atomary transaction unit containing changes made per that transaction (all-or-none). Atoms are very similar to database operations; one atom can contain one or multiple `CREATE` or `UPDATE` operations. Atoms are means of offloading changes made by dependent _nodes_ to be imported on the main host.

**Queue** is a table of URLs to be processed; each URL is assigned so-called _Sqissor_ class name - the parser used to crawl URL of that type; for this reason it's named "site" but since some "websites" can share similar strucutre (for example, running the same engine like WordPress) one "site", or _Sqissor_, can handle multiple physical "websites".

**Sqissor** (from "scissors" - to "parse and cut") is a parser class. It receives remote data (typically downloaded from a _ququed_ URL) and does what it needs. Normally it will either add more _queue_ items (for example, if that page contains pagebar the following pages can be enqueued for later crawler) or _row_ items.

**Row** is a finite unit of processed data. Normally, the goal of a crawler is to take remote page, pull the necessary data from it and transform it to be in the certain form suitable for other application needs. _Row_ is such a transformed item.

## Writing your crawler

After you extract Sqobot's source code you will have a working Sqobot instance (or _node_): you can access its web interface, run CLI tools, etc. However, it won't do anything yet, it's empty.

The goal of Sqobot is to parse remote data. For this you need to write parser(s) that will process certain form(s) of data. This is done by extending **Sqissor** class with your own `doSlice` method, for example:
```PHP
// Class name must begin with "S" and be placed under "Sqobot".
// You can avoid these requirements by using "class" config option.
class SMyParser extends Sqissor {
  protected function doSlice($data, array $extra) {
    $nextPage = $this->regexp($data, '~<a class="page" href="([^"]+)~', 1);
    $this->enqueue($nextPage, 'myparser');

    $weather = $this->regexp($data, '~<b id="wtype">([^<]+)~', 1);
    $temperature = $this->regexp($data, '~<span class="cel">.*?(\d+)C~', 1);
    $date = time();
    WeatherRow::createWith(compact('weather', 'temperature', 'date'));
  }
}
```

Sqobot's default autoloader will look for this class in `user/SMyParser.php` so if we put the above sample there it will be hooked right away.

This code will take a remote page, pull URL of the next page from it and enqueue it for later crawling, then take two fields (weather and temperature) and place them into the weather table which contains 3 fields: `weather` (`VARCHAR`), `temperature` (`INT`) and `date` (`TIMESTAMP`).

As you might notice there's no **WeatherRow** class in Sqobot. Just like our new _Sqissor_ it's a class-to-be-inherited from **Row** and it might look like this:
```PHP
// Unlike Sqissor this class can have any name as it's not accessed by Sqobot core
// but only from within your code. It can be placed into user/WeatherRow.php.
class WeatherRow extends Row {
  static $defaultTable = 'weather';

  static $fields = array('weather', 'temperature', 'date');
  public $weather, $temperature, $date;

  // If we define this optional method we can avoi setting $date = time()
  // in SMyParser->doSlice() above.
  function defaults() {
    // $weather has no default value.
    $this->temperature = 0;
    $this->date = time();
    return $this;
  }
}
```

## Running

Sqobot can run from web or CLI interface. Normally you would run it as a cronjob but some ISPs (especially free ones) don't provide it so you might need to resort to web polling.

Sqobot's web interface can be accessed by simply opening its home directory where you have extracted the sources, or `web/` folder inside it. Command-line interface is accessed by rnning `./cli` on *nix, `cli.bat` on Windows or `php cli.php` universally.

When using web interface Sqobot uses access rights management based on HTTP Basic Auth. When using CLI Sqobot assumes you are the superuser and allows everything.

Since remote _nodes_ access Sqobot via web interface it's often useful to configure their ACLs to separate human admin account rights from limited robot remote calls. This is explained in detail in `defaults.conf`.

Both web and command-line interfaces are based on **tasks**. Each task has **methods** (much like controllers in MVC concept); if not method name is provided default one ("empty") is used.

Basic tasks are:
* **atoms** - lets you pack, extract and import offloaded transactions
* **cycle** - starts crawling; takes items off current queue, processes them and records the result (either error or success); essentially executes `queue` task infinitely or until specific conditions are met
* **queue** - the main workhorse that parses individual queue items or adds new ones
* **pages** - supports distributed crawlers; with this task main node keeps all its satellites in sync so that they don't recrawl already processed pages
* **patch** - packs Sqobot scripts and/or other files into a ZIP archive for later redistribution on remote nodes, or just a backup
* **sql** - produces SQL templates for populating the database

Normal setup is to have a cronjob like this to process enqueued items for 59 minutes, then cool down for 1 minute and restart:
```
0   *   *   *   *   /home/sqobot/cli cycle --for=59
```

However, if your webhost doesn't support cron you can always use web polling via Sqobot's web interface using the **cron** web task. There are many services like *Iron.io* or EasyCron.com that let you schedule HTTP requests to custom URL(s) at regular intervals.

## Support

If you have questions or suggestions feel free to contact me at proger.xp@gmail.com or via other means at http://proger.me.
