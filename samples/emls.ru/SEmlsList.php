<?php namespace Sqobot;

class SEmlsList extends Sqissor {
  protected function doSlice($data, array $extra) {
    $domain = 'http://emls.ru';
    $nextPage = $this->regexp($data, '~<a href="(/zagorod/page[^"]+)">след~');
    $nextPage and $this->enqueue($domain.$nextPage[1], 'emls.list');

    $table = $this->regexp($data, '~<table border="0" class="html_table_1" cellpadding="7" cellspacing="1">(.+)</table>\s*<span class="noPrint">~s', 1);
    $rows = explode("</tr><tr", $table);

    foreach ($rows as $row) {
      list($trAttrs, $row) = explode('>', $row, 2);

      if (strpos($trAttrs, 'table_with_data"') !== false) {
        $pageURL = $domain.$this->regexp($trAttrs, '~\bdata-href="([^"]+)~', 1);
        $pageURL = str_replace('?click=1', '', $pageURL);

        $text = html_entity_decode(preg_replace('~<[^>]*>~', ' ', $row), ENT_QUOTES, 'cp1251');
        $text = preg_replace('~\s+~', ' ', $text);
        $text = iconv('cp1251', 'utf-8', trim($text));

        AdRow::createIgnoreWith(array(
          'source' => $this->queue->url,
          'site' => $this->queue->site,
          'pageURL' => $pageURL,
          'text' => $text,
        ));
      }
    }
  }
}

class AdRow extends Pool {
  static $defaultTable = 'ads';

  static $fields = array('id', 'source', 'site', 'site_id', 'created', 'pageURL', 'text');
  public $pageURL, $text;
}