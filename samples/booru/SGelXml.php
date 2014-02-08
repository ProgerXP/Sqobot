<?php namespace Sqobot;

class SGelXml extends SBase {
  static $imageURL = '/index.php?page=post&s=view&id=';
  static $xmlPageRegExp = '/[?&]pid=(\d+)(&|$)/';

  static $xmlFields = array('height', 'score', 'file_url', 'sample_url', 'rating', 'tags',
                            'id' => 'site_id', 'width', 'md5', 'created_at' => 'uploaded');

  function skipURL($url) {
    return ImagePool::hasPage($this->host(), $this->xmlPageIndex());
  }

  protected function doSlice($data, array $extra) {
    $regexp = '~<posts[^>]+?count="(\d+)" +offset="(\d+)"~u';
    list(, $pos, $offset) = $this->regexp($data, $regexp);

    if (!$offset and $pos > 0) {
      // Gelbooru returns <posts count="1703655" offset="0" /> for out-of-bounds
      // pages as well asthe first (0th) page.
      log('Reached end of pages, rewinding to the first page.');
      return $this->enqueue($this->xmlPageURL(0), $this->name);
    }

    $records = array();

    // Retrieving essential fields from the XML.
    foreach (static::$xmlFields as $xmlField => $dbField) {
      is_int($xmlField) and $xmlField = $dbField;

try {
      $regexp = '~[<\s]'.$xmlField.'="([^"]*)"(?=\s|/|>)~u';
      $matches = $this->regexpAll($data, $regexp);
} catch (Exception $e) {
  file_put_contents('out/-g-'.mt_rand().'.xml', $data);
  throw $e;
}

      foreach ($matches[1] as $i => $value) {
        if ($xmlField === 'created_at') {
          $value = strtotime($value);
          if (!$value) {
            throw new ERegExpMismatch($this, 'strtotime() failed: '.$matches[1][$i]);
          }
        }

        $records[$i][$dbField] = $value;
      }
    }

    // Adding missing IbSearch-specific fields.
    $records = $this->addIbSearchFields($records);

    // Validate result.
    $records = $this->normalizeImages($records);

    // Generating thumbnails from remote full-size images.
    $this->generateThumbs($records);

    // Finally storing normalizing image records.
    S($records, NS.'ImagePool.createOrReplaceWith');
  }

  function xmlPageURL($page) {
    $page < 0 and $page = 0;
    $page === true and $page = $this->xmlPageIndex() + 1;

    $url = $this->queue()->url;
    $url = preg_replace(static::$xmlPageRegExp, '', $url);
    return $url.(strrchr($url, '?') === false ? '?' : '&')."pid=$page";
  }

  function xmlPageIndex() {
    return $this->regexp($this->queue()->url, static::$xmlPageRegExp, 1);
  }
}