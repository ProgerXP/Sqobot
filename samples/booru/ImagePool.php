<?php namespace Sqobot;

class ImagePool extends Pool {
  static $defaultTable = 'images';
  static $fields = array('md5', 'random', 'hits', 'width', 'height', 'page_url',
                         'file_url', 'rating', 'score', 'ext', 'tags', 'uploaded');

  public $md5, $random, $hits, $width, $height, $page_url,
         $file_url, $rating, $score, $ext, $tags, $uploaded;
}
ImagePool::$fields = array_merge(Pool::$fields, ImagePool::$fields);