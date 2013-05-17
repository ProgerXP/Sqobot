<?php namespace Px;
/*
  Part of Plarx | http://proger.i-forge.net/Plarx
*/

class HLEx {
  static $removeEmptyAttributes = array('class', 'style');

  static $defaultAttributes = array('a' => 'href', 'img' => 'src', 'link' => 'href',
                                    'option' => 'value', 'script' => 'src');

  // Builds a list of HTML attributes.
  //* $attributes - str defaults to $defAttr, hash - null values are skipped.
  //* $defAttr str - only used if $attributes is a string.
  //= str
  //
  //? attr('text warn')               //=> class="text warn"
  //? attr(array('title' => 'A&B'))   //=> title="A&amp;B"
  static function attr($attributes, $defAttr = 'class') {
    is_array($attributes) or $attributes = array($defAttr => "$attributes");

    foreach ($attributes as &$attribute) {
      $attribute === null or $attribute = trim($attribute);
    }

    foreach (static::$removeEmptyAttributes as $attr) {
      if (isset($attributes[$attr]) and trim($attributes[$attr]) === '') {
        $attributes[$attr] = null;
      }
    }

    $result = array();

    foreach ($attributes as $name => $value) {
      is_int($name) and $name = $value;
      isset($value) and $result[] = static::q($name).'="'.static::q($value).'"';
    }

    return $result ? ' '.join(' ', $result) : '';
  }

  // Quotes HTML entities in UTF-8 mode. Can operate on arrays.
  //= str HTML, array if $str is an array
  static function q($str, $quotes = ENT_COMPAT, $doubleEncode = true) {
    if (is_array($str)) {
      foreach ($str as &$s) { $s = static::q($s, $quotes, $doubleEncode); }
      return $str;
    } else {
      return htmlspecialchars($str, $quotes, 'utf-8', $doubleEncode);
    }
  }

  static function tag($tag, $attributes = array()) {
    $default = &static::$defaultAttributes[$tag];
    return "<$tag".static::attr($attributes, $default ?: 'class').'>';
  }

  // Builds a HTML tag with given content and attributes.
  //* $tag str - tag name like 'a'.
  //* $content str HTML is output as is, array for multiple tags.
  //* $attributes str defaults to 'class', hash - see attr().
  //= str HTML, array if $content is an array
  static function wrap($tag, $content, $attributes = array()) {
    if (is_array($content)) {
      foreach ($content as &$s) { $s = static::wrap($tag, $s, $attributes); }
      return $content;
    } else {
      return static::tag($tag, $attributes).$content."</$tag>";
    }
  }

  //? HLEx::span('<b>Nice!</b>')    //=> <span><b>Nice!</b></span>
  //? HLEx::a('Home', array('href' => URL::home()))
  //    //=> <a href="http://localhost/">Home</a>
  //? HLEx::li_q('quoted>')         //=> <li>quoted&gt;</li>
  //? HLEx::h2_q_if('A&B')          //=> <h2>A&amp;B</h2>
  //? HLEx::h2_if('   ')            //=> null
  static function __callStatic($name, $arguments) {
    if (substr($name, -3) === '_if') {
      if (ltrim($arguments[0]) === '') { return; }
      $name = substr($name, 0, -3);
    }

    if (substr($name, -2) === '_q') {
      $name = substr($name, 0, -2);
      $arguments[0] = static::q($arguments[0]);
    }

    array_unshift($arguments, $name);
    return call_user_func_array(array(get_called_class(), 'wrap'), $arguments);
  }

  //= str HTML tag with quoted $content
  static function wrap_q($tag, $content, $attributes = array()) {
    return static::wrap($tag, static::q($content), $attributes);
  }

  //= str HTML tag if $content is not an empty string, null otherwise
  static function wrap_if($tag, $content, $attributes = array()) {
    if (ltrim($content) !== '') {
      return static::wrap($tag, $content, $attributes);
    }
  }

  // Useful for setting link attributes - since attr() ignores null values
  // setting it on a local link won't output any target (not even target="").
  //* $url bool, str - if true or if isExternal()
  //= str "_blank" if $url is external, null otherwise
  static function target($url) {
    is_bool($url) or $url = static::isExternal($url);
    return $url ? '_blank' : null;
  }

  // Indicates if $url is an external URL - i.e. containing scheme and its domain
  // not matching current domain.
  //= bool
  static function isExternal($url) {
    $head = substr($url, 0, 15);

    if ((strrchr($head, ':') !== false or strpos($head, '//') !== false) and
        !starts_with($url, \Laravel\URL::home())) {
      strtok($url, '/');
      return strtok('/') !== Request::server('host');
    }
  }

  //= str HTML
  static function select($name, array $options = null, $selected = null, $attrs = array()) {
    $result = '';

    foreach ((array) $options as $label => $options) {
      if ($isOptgroup = is_array($options)) {
        $result .= static::tag('optgroup', compact('label'));
      } else {
        $options = array($label => $options);
      }

      foreach ($options as $value => $label) {
        if (is_array($label)) {
          $attrs = array_except($label, 'label') + compact('value');
          $label = &$label['label'];
        } else {
          $attrs = compact('value');
        }

        $attrs['value'] == $selected and $attrs['selected'] = 'selected';
        $result .= static::wrap_q('option', $label, $attrs);
      }

      $isOptgroup and $result .= '</optgroup>';
    }

    $attrs += compact('name');
    return static::wrap_if('select', $result, $attrs);
  }

  // Builds a list of <input type="hidden"> inputs according to a query.
  //* $query hash, str [...?]a=b&c=...
  //* $prefix str - is prepended to inputs' name.
  //* $xhtml - whether to add '/>' or not.
  //= str HTML
  static function hiddens($query, $prefix = '', $xhtml = false) {
    if (is_string($query)) {
      $queryStr = strrchr($query, '?') and $query = substr($queryStr, 1);
      parse_str($query, $query);
    }

    if (is_array($query)) {
      foreach ($query as $name => &$value) {
        if (is_array($value)) {
          $name = "$prefix" === '' ? $name : $prefix."[$name]";
          $value = static::hiddens($value, $name, $xhtml);
        } else {
          "$prefix" === '' or $name = $prefix."[$name]";
          $type = 'hidden';
          $value = static::tag('input', compact('name', 'value', 'type'));
          $xhtml and $value = substr($value, 0, -1).' />';
        }
      }

      return join("\n", $query);
    }
  }

  // Builds a set of checkboxes wrapped in labels.
  //= str HTML
  static function checkboxes($name, array $checkboxes = null, $checked = array()) {
    $checked = (array) $checked;
    $result = array();

    foreach ((array) $checkboxes as $value => $label) {
      $attributes = array('type' => 'checkbox', 'name' => $name.'[]', 'value' => $value);
      in_array($value, $checked) and $attributes['checked'] = 'checked';

      $result[] = static::label(static::tag('input', $attributes)." $label");
    }

    return join(' ', $result);
  }

  // Builds a set of radio buttons wrapped in labels.
  //= str HTML
  static function radios($name, array $options = null, $selected = null, $attrs = array()) {
    $result = array();

    foreach ((array) $options as $value => $label) {
      if (isset($label)) {
        $type = 'radio';
        $checked = $value == $selected ? 'checked' : null;

        $radio = static::tag('input', compact('type', 'name', 'value', 'checked'));
        $result[] = static::label("$radio $label");
      }
    }

    return join(' ', $result);
  }

  // Builds a set of radio buttons if there are no more than 3 $options or
  // a select box otherwise.
  //= str HTML
  static function selorad($name, array $options = null, $selected = null, $attrs = array()) {
    $func = (is_array($options) and count($options) > 3) ? 'select' : 'radios';
    $func === 'radios' and $options and $options = static::q($options);
    return static::$func($name, $options, $selected, $attrs);
  }

  // Similar to var_dump() but builds more concise and easy to grasp output.
  // All but array $value's are output as is (but HTML-quoted) and arrays are
  // recursively output as nested tables.
  //= str HTML
  static function visualize($value) {
    is_object($value) and $value = (array) $value;

    if (is_array($value)) {
      $rows = '';

      foreach ($value as $key => &$item) {
        $item = static::visualize($item);
        $rows .= static::tr(static::th_q($key).static::td($item));
      }

      return static::table($rows);
    } else {
      return static::q((string) $value);
    }
  }

  //= str HTML
  static function table2D(array $keyValues) {
    foreach ($keyValues as $key => &$value) {
      $value = static::tr(static::th_q($key).static::td_q($value));
    }

    return $keyValues ? static::table(join($keyValues)) : '';
  }

  // Formats a proper <time> tag. Note that pubdate's status is dubious in HTML 5:
  //* $text str - tag's contents; HTML is preserved.
  //* $time int timestamp, DateTime
  //* $attributes array, mixed if == true adds 'pubdate' attribute
  //= str HTML
  static function time($text, $time = null, $attributes = null) {
    if (!is_array($attributes)) {
      $attributes = array('pubdate' => $attributes ? 'pubdate' : '');
    }

    is_object($time) and $time = $time->getTimestamp();
    isset($time) or $time = time();

    $attributes += array('datetime' => date(DATE_ATOM, $time));
    return static::tag('time', $attributes).$text.'</time>';
  }

  //* $num int, float
  //* $options str decimal point, hash options - see Str::number().
  //= str formatted number
  static function number($num, $options = null) {
    return Str::number($num, array('html' => true) + arrize($options, 'point'));
  }

  // Formats a number according to localization rules, i.e. using '-s' in English.
  //= str
  static function langNum($strings, $number) {
    return Str::langNum($strings, $number, true);
  }

  // Wraps each variable into span with a class and translates the string as usual.
  //= str
  static function lang($string, $replaces = array(), $quote = true) {
    is_object($string) and $string = "$string";
    $replaces = arrize($replaces);
    $tag = $quote ? 'span_q_if' : 'span_if';

    foreach ($replaces as $key => $value) {
      $string = str_replace(':'.$key, static::$tag($value, $key), $string);
    }

    return $string;
  }
}