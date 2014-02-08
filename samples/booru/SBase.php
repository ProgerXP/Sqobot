<?php namespace Sqobot;

abstract class SBase extends Sqissor {
  static $imageURL = '/posts/';
  static $ratings = array('0', 's', 'q', 'e');
  static $extensions = array('png', 'gif', 'jpg');

  //= ThumbGen
  static function thumbGen($imageURL) {
    extract(cfgGroup('thumb'), EXTR_SKIP);

    return ThumbGen::make($imageURL)
      ->type($type, $quality)
      ->remoteCacheTTL($remoteCacheTTL)
      ->size($width, $height)
      ->step(1)
      ->fill($fill);
  }

  function addIbSearchFields(array $records) {
    $source = $this->queue()->url;
    $host = $this->host();
    $home = $this->pageUrlHome();

    mt_rand(0, 1) and mt_srand(ord(openssl_random_pseudo_bytes(1)));

    foreach ($records as &$fields) {
      $ext = ltrim(strrchr($fields['file_url'], '.'), '.');
      if ($ext === 'jpeg' or $ext === 'jpe') { $ext = 'jpg'; }

      $fields += array(
        'source'          => $source,
        'site'            => $host,
        'random'          => mt_rand(0, 65535),
        'hits'            => 0,
        'page_url'        => $this->pageUrlFrom($fields),
        'ext'             => $ext,
      );
    }

    return $records;
  }

  function normalizeImages(array $records) {
    foreach ($records as &$one) {
      foreach (get_class_methods($this) as $func) {
        if (S::unprefix($func, 'norm_') and
            !$this->{"norm_$func"}( $one[$func] )) {
          error("Invalid image field of [$func] - skipping entry: $one[page_url]");

          $one = null;
          break;
        }
      }
    }

    return array_filter($records);
  }

    protected function norm_site_id(&$id) {
      return filter_var($id, FILTER_VALIDATE_INT);
    }

    protected function norm_uploaded(&$time) {
      if ($this->norm_site_id($time)) {
        $obj = new \DateTime;
        return $time = $obj->setTimestamp($time);
      } else {
        return false;
      }
    }

    protected function norm_md5(&$hash) {
      $hash = strtolower($hash);
      return strlen($hash) === 32 and ltrim($hash, 'a..z0..9') === '';
    }

    protected function norm_width(&$size) {
      return filter_var($size, FILTER_VALIDATE_INT) and $size >= 10;
    }

    protected function norm_height(&$size) {
      return $this->norm_width($size);
    }

    protected function norm_rating(&$str) {
      $str = strtolower($str);
      return in_array($str, static::$ratings);
    }

    protected function norm_score(&$score) {
      return filter_var($score, FILTER_VALIDATE_INT) !== false;
    }

    protected function norm_ext(&$ext) {
      $str = strtolower($ext);
      return in_array($str, static::$extensions);
    }

    protected function norm_tags(&$str) {
      $str = mb_strtolower(trim($str));
      return strlen($str) > 1;
    }

  function generateThumbs(array $records) {
    foreach ($records as $fields) {
      $url = S::pickFlat($fields, 'sample_url', $fields['file_url']);
      echo "Generating thumb for $url... ";
      $thumb = $this->generateThumb($url, $fields['md5']);
      echo S::sizeStr(filesize($thumb)), ', ok', PHP_EOL, PHP_EOL;
    }
  }

  //= str generated thumb file name
  function generateThumb($imageURL, $md5) {
    $path = rtrim(cfg('thumb path'), '\\/');
    S::mkdirOf($path);

    require_once __DIR__.'/ShortMD5.php';
    $md5 = \MD5_32to24($md5);
    $dest = "$path/".substr($md5, 0, 2).'/'.substr($md5, 2).'.'.cfg('thumb type');

    return static::thumbGen($imageURL)
      ->cacheFile($dest)
      ->scaled();
  }

  function pageUrlFrom(array $fields) {
    return $this->pageUrlHome().static::$imageURL.$fields['site_id'];
  }

  function pageUrlHome() {
    return 'http://'.$this->host();
  }

  function host() {
    return parse_url($this->queue()->url, PHP_URL_HOST);
  }
}

class ThumbGen extends \ThumbGen {
  // hide libpng and other non-catchable warnings.
  static function scaleImage($file, $maxWidth, $maxHeight, array $options) {
    return @parent::scaleImage($file, $maxWidth, $maxHeight, $options);
  }

  protected function remoteFetchContext() {
    return Download::make($this->source)->createContext();
  }
}