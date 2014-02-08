<?php namespace Sqobot;

class SDanImage extends SBase {
  function skipURL($url) {
    return ImagePool::hasPage($this->host(), (int) basename($url));
  }

  protected function doSlice($data, array $extra) {
    $fields = array(
      'site_id'           => (int) basename($this->queue()->url),
    );

    $regexp = '~<li>\s*Size:\s*<a href="(?P<file_url>/[^"]+)">[\d.]+\s*\w+</a>\s*'.
              '\((?P<width>\d+)x(?P<height>\d+)\)\s*</li>~u';

    try {
      $fields += $this->regexp($data, $regexp, true);
    } catch (ERegExpNoMatch $e) {
      $ignore = array('You need a privileged account to see this image',
                      'Save this \w+ \(right click and save\)',
                      'The artist requested removal of this image');

      if (preg_match('~'.join('|', $ignore).'~', $data)) {
        return;
      } else {
        throw $e;
      }
    }

    $fields['file_url'] = 'http://'.$this->host().$fields['file_url'];
    $fields['md5'] = S::newExt(basename( $fields['file_url'] ));

    $regexp = '~<li>\s*Rating: ([SQE])[\w\s]*</li>~u';
    $fields['rating'] = $this->regexp($data, $regexp, 1);

    $regexp = '~<span id="score-for-post-\d+">\s*(-?\d+)\s*</span>~u';
    $fields['score'] = $this->regexp($data, $regexp, 1);

    $regexp = '~<meta name="tags" content="([^"]+)~u';
    $fields['tags'] = $this->htmlToText($this->regexp($data, $regexp, 1));

    $regexp = '~<li>\s*Date: .*?<time datetime="([^"]+)~u';
    $fields['uploaded'] = strtotime($this->regexp($data, $regexp, 1));

    $records = $this->addIbSearchFields(array($fields));
    $records = $this->normalizeImages($records);
    $this->generateThumbs($records);
    S($records, NS.'ImagePool.createOrReplaceWith');
  }
}