<?php
/*
  HTMLki - seamless templating with the HTML spirit
  in public domain | by Proger_XP
  http://proger.i-forge.net/HTMLki/SZS

 *** Supports PHP 5.2 and up ***

  Instruction for getting HTMLki work with pre-5.3:
  1. Replace all '?:' operators with expanded '? <value> :' form.
  2. Replace all 'static' words with 'self'. In instance methods '$this' can be used.
*/

HTMLki::$config = new HTMLkiConfig;

class HTMLkiError extends Exception {
  public $obj;            //= null, object, HTMLkiObject

  function __construct($obj, $msg, $code = 0) {
    parent::__construct($msg, $code);
    $this->obj = $obj;
  }
}

class HTMLkiPcreError extends HTMLkiError {
  public $pcreCode;         //= int as returned by preg_last_error()

  function __construct($code = null) {
    $code = $this->pcreCode = isset($code) ? $code : preg_last_error();
    parent::__construct(null, "PCRE error #$code. Make sure your files are".
                        ' encoded in UTF-8.');
  }
}

class HTMLki {
  static $config;             //= HTMLkiConfig
  static $configs = array();  //= hash of HTMLkiConfig

  // function (string $name)                      - return $name
  // function (string $name, HTMLkiConfig $new)   - set $new to $name and return it
  // function ('', HTMLkiConfig $new)             - set default config
  // function (HTMLkiConfig $config)              - return $config
  // function ()                                  - return default config (not its copy)
  static function config($return = null, HTMLkiConfig $new = null) {
    if (is_string($return)) {
      if ("$return" === '') {
        $return = &static::$config;
      } else {
        $return = &static::$configs[$return];
      }

      isset($new) and $return = $new;
    }

    return $return ?: static::$config;
  }

  //= string
  static function compile($str, $config = null) {
    $obj = new HTMLkiCompiler(static::config($config), $str);
    return $obj->compile();
  }

  //= string
  static function compileFile($file, $config = null) {
    $str = file_get_contents($file);

    if (!is_string($str)) {
      throw new HTMLkiError(null, "Cannot compile template [$file] - it doesn't exist.");
    }

    return static::compile($str, $config);
  }

  //= HTMLkiTemplate
  static function template($str, $config = null) {
    $obj = new HTMLkiTemplate(static::config($config));
    return $obj->loadStr($str);
  }

  //= HTMLkiTemplate
  static function templateFile($file, $config = null) {
    $obj = new HTMLkiTemplate(static::config($config));
    return $obj->loadFile($file);
  }

  //= $result
  static function pcreCheck($result = null) {
    $code = preg_last_error();

    if ($code == PREG_NO_ERROR) {
      return $result;
    } else {
      throw new HTMLkiPcreError($code);
    }
  }
}

class HTMLkiConfig {
  /*-----------------------------------------------------------------------
  | COMMON OPTIONS
  |----------------------------------------------------------------------*/

  public $regexpMode = 'u';

  //= callable ($msg, HTMLkiObject $issuer)
  public $warning;

  /*-----------------------------------------------------------------------
  | COMPILER-SPECIFIC
  |----------------------------------------------------------------------*/

  public $compilers = array('php', 'varSet', 'tags', 'echo', 'lang', 'varEcho');

  public $singleTags = array('area', 'base', 'basefont', 'br', 'col', 'frame',
                             'hr', 'img', 'input', 'link', 'meta', 'param',
                             'lang', 'include');

  public $loopTags = array('each', 'if');

  public $addLineBreaks = true;

  //= string to start each compiled .php file with
  public $compiledHeader = '';

  //= string to end each compiled .php file with
  public $compiledFooter = '';

  /*-----------------------------------------------------------------------
  | RENDERING-SPECIFIC
  |----------------------------------------------------------------------*/

  public $xhtml = true;

  // Used to quote HTML strings.
  public $charset = 'utf-8';

  //= null autodetect from short_open_tag from php.ini, bool
  public $shortPhp = null;

  // If true and current template's config has been changed (see
  // HTMLkiObject->ownConfig()) included templates will inherit the changed
  // copy; otherwise they will get the config the parent template initially had.
  public $inheritConfig = true;

  //= string prepended to the evaluating string expression. See ->compiledHeader.
  public $evalPrefix = '';

  //= string appended to the evaluating string expression. See ->compiledFooter.
  public $evalSuffix = '';

  // Tag used when tag name is omitted, e.g. <"class"> -> <span "class">.
  // Works for closing tag as well: </> -> </span>.
  // Is used exactly as if it appears in the template - it can be a multitag,
  // regular tag attributes (flags, defaults) are used and so on.
  public $defaultTag = 'span';    //= string

  // Format of member items:
  // * string => string - alias of another tag: 'tag[ attr=value[ a2=v2 ...]]'
  // * string => array - the same as above but in prepared form:
  //   array( 'tag'[, 'attr' => 'v a l u e'[, 'a2' => 'v2', ...]] ).
  //   Note that if 'tag' starts with a capital letter or is an object
  //   this is considered a callable (see below).
  // * string => callable - function (HTMLkiTagCall $tag)
  //
  // Aliases are resolved recursively; attributes are set after each iteration
  // so you can create multiple aliases and their attributes will be set
  // (later aliases do not override attributes that already exist).
  //
  // Unlisted tags are handled by HTMLkiTemplate or its default tag method.
  public $tags = array(
    'password' => 'input type=password',  'hidden' => 'input type=hidden',
    'file' => 'input type=file',          'check' => 'input type=checkbox',
    'checkbox' => 'input type=checkbox',  'radio' => 'input type=radio',
    'submit' => 'button type=submit',     'reset' => 'button type=reset',
    'get' => 'form method=get',           'post' => 'form method=post',
  );

  //= hash of array of string attribute names
  public $defaultAttributes = array(
    // for all tags not listed here:
    ''          => array('class'),
    'a'         => array('href', 'class'),
    'base'      => array('href'),
    'button'    => array('name', 'class'),
    'embed'     => array('src', 'class'),
    'form'      => array('action', 'class'),
    'img'       => array('src', 'class'),
    'input'     => array('name', 'class'),
    'link'      => array('href'),
    'meta'      => array('name', 'content'),
    'object'    => array('data', 'class'),
    'optgroup'  => array('label', 'class'),
    'option'    => array('value'),
    'param'     => array('name', 'value'),
    'script'    => array('src'),
    'select'    => array('name', 'class'),
    'source'    => array('src'),
    'style'     => array('media'),
    'textarea'  => array('name', 'class'),
    'track'     => array('src'),
  );

  //= hash of array of string attribute names
  public $defaults = array(
    // for all tags including those listed here (tag-specific ones override these):
    ''          => array(),
    'button'    => array('type' => 'button', 'value' => 1),
    'form'      => array('method' => 'post', 'accept-charset' => 'utf-8'),
    'input'     => array('type' => 'text'),
    'link'      => array('rel' => 'stylesheet'),
    'script'    => array('type' => 'text/javascript'),
    'style'     => array('type' => 'text/css'),
    'textarea'  => array('cols' => 50, 'rows' => 5),
  );

  //= hash of array of string attribute names
  public $flagAttributes = array(
    // for all tags including those listed here:
    ''          => array('disabled'),
    'area'      => array('nohref'),
    'audio'     => array('autoplay', 'controls', 'loop'),
    'button'    => array('autofocus', 'formnovalidate'),
    'command'   => array('checked'),
    'details'   => array('open'),
    'frame'     => array('noresize'),
    'hr'        => array('noshade'),
    'img'       => array('ismap'),
    'input'     => array('autofocus', 'checked', 'readonly',
                         'formnovalidate', 'required'),
    'keygen'    => array('autofocus', 'challenge', 'disabled'),
    'option'    => array('selected'),
    'object'    => array('declare'),
    'script'    => array('defer'),
    'select'    => array('multiple'),
    'style'     => array('scoped'),
    'th'        => array('nowrap'),
    'td'        => array('nowrap'),
    'textarea'  => array('readonly'),
    'time'      => array('pubdate'),
    'track'     => array('default'),
    'video'     => array('autoplay', 'controls', 'loop', 'muted'),
  );

  // Format: 'attribute[=value...]' - if 'value' is omitted shortcut's name is used.
  //= hash of hash of strings (or arrays)
  public $shortAttributes = array(
    // for all tags including those listed here (tag-specific ones override these):
    ''          => array(
      'left' => 'align', 'center' => 'align', 'right' => 'align', 'justify' => 'align',
      'top' => 'align', 'middle' => 'align', 'bottom' => 'align',
      'ltr' => 'dir', 'rtl' => 'dir',
    ),
    'a'         => array('new' => 'target=_blank'),
    'button'    => array('submit' => 'type', 'reset' => 'type', 'button' => 'type'),
    'command'   => array('checkbox' => 'type', 'command' => 'type', 'radio' => 'type'),
    'input'     => array(
      'button' => 'type', 'checkbox' => 'type', 'file' => 'type', 'hidden' => 'type',
      'image' => 'type', 'password' => 'type', 'radio' => 'type', 'reset' => 'type',
      'submit' => 'type', 'text' => 'type',
    ),
    'keygen'    => array('rsa' => 'keytype', 'dsa' => 'keytype', 'ec' => 'keytype'),
    'form'      => array(
      'get' => 'method', 'post' => 'method', 'file' => 'enctype=multipart/form-data',
      'upload' => 'enctype=multipart/form-data',
      'multipart' => 'enctype=multipart/form-data',
    ),
    'li'        => array('disc' => 'type', 'square' => 'type', 'circle' => 'type'),
    'param'     => array('data' => 'valuetype', 'ref' => 'valuetype',
                         'object' => 'valuetype'),
    'script'    => array('preserve' => 'xml:space'),
  );

  //= hash of hash of callable ($value, HTMLkiTagCall $call)
  public $attributes = array();

  //= callable ($str, array $format)
  public $language;

  // Returns string (path to the compiled template) or HTMLkiTemplate.
  //= callable ($template, HTMLkiTemplate $parent, HTMLkiTagCall $call).
  public $template;

  //= callable ($name, HTMLkiTemplate $tpl)
  public $listVariable;

  function defaultsOf($tag) {
    return ((array) @$this->defaults[$tag]) + ((array) @$this->defaults['']);
  }

  function flagAttributesOf($tag) {
    return array_merge((array) @$this->flagAttributes[$tag],
                       (array) @$this->flagAttributes['']);
  }

  function defaultAttributesOf($tag) {
    $attributes = &$this->defaultAttributes[$tag];
    isset($attributes) or $attributes = &$this->defaultAttributes[''];

    return is_array($attributes) ? $attributes : array();
  }

  function expandAttributeOf($tag, $attr, array &$attributes) {
    $full = &$this->shortAttributes[$tag][$attr];
    $full or $full = &$this->shortAttributes[''][$attr];
    $full or $full = "$attr=$attr";

    foreach ((array) $full as $full) {
      @list($name, $value) = explode('=', $full, 2);
      isset($value) or $value = $attr;
      $attributes[$name] = $value;
    }
  }

  function callAttributeHookOf($tag, $attribute, $value) {
    $hook = &$this->attributes[$tag][$attribute];
    $hook or $hook = &$this->attributes[''][$attribute];

    $hook and $value = call_user_func($hook, $value, $this);
    return $value;
  }
}

class HTMLkiTagCall {
  public $raw;                      //= string raw parameter string

  public $lists = array();          // list variable names without leading '$'
  public $defaults = array();       // default attributes without wrapping quotes
  public $attributes = array();     // 'attr' => array('keyWr', 'valueWr', 'value')
  public $values = array();         //= array of array('valueWr', string)

  public $tag;                      //= string
  public $isEnd = false;
  public $isSingle = false;

  public $tpl;                      //= HTMLkiTemplate
  public $vars;                     //= hash

  function __construct() {
    $this->clear();
  }

  function clear() {
    $this->lists = $this->defaults = array();
    $this->attributes = $this->values = array();

    $this->raw = $this->tag = null;
    $this->isEnd = $this->isSingle = false;
  }

  function handle() {
    return $this->regularTag($this);
  }

  function config() {
    return $this->tpl->config();
  }

  function call($tag) {
    $call = clone $this;
    $call->tag = $tag;
    return $this->tpl->callTag($call);
  }

  function __call($name, $arguments) {
    return call_user_func_array(array($this->tpl, $name), $arguments);
  }

  function attributes($config = null) {
    $config or $config = $this->tpl->config();

    $attributes = $this->attributes;
    $defaults = $config->defaultAttributesOf($this->tag);
    $flags = array_flip( $config->flagAttributesOf($this->tag) );

    $result = $config->defaultsOf($this->tag);

    $values = $this->values;
    foreach ($values as &$value) { $value = $value[1]; }

    foreach ($this->defaults as $str) {
      $default = array_shift($defaults);
      if ($default) {
        $result[$default] = $str;
      } else {
        $values[] = $str;
      }
    }

    foreach ($values as $str) {
      $str = trim($str);
      $str === '' or $config->expandAttributeOf($this->tag, $str, $result);
    }

    foreach ($attributes as $name => $value) { $result[$name] = $value[2]; }

    foreach ($result as $name => &$value) {
      $value = $config->callAttributeHookOf($this->tag, $name, $value);

      if (isset($flags[$name])) {
        if ($value == true) {
          $value = $name;
        } else {
          unset($result[$name]);
        }
      }
    }

    return $result;
  }
}

interface HTMLkiTemplateEnv {
  // Methods that accept $tag always receive it in lower case form.

  function startTag($tag, $params = '', array $vars = array());
  function endTag($tag, $params = '', array $vars = array());
  function singleTag($tag, $params = '', array $vars = array());

  function lang($string, array $vars = array());
  function setTagAttribute($tag, $key, array $attributes = array());
}

class HTMLkiObject {
  protected $config;          //= HTMLkiConfig
  protected $originalConfig;  //= HTMLkiConfig, null

  //= HTMLkiConfig, $this
  function config(HTMLkiConfig $new = null) {
    if ($new) {
      $this->config = $new;
      $this->originalConfig = null;
      return $this;
    } else {
      return $this->config;
    }
  }

  //= HTMLkiConfig
  function ownConfig() {
    if (!$this->originalConfig) {
      $this->originalConfig = $this->config;
      $this->config = clone $this->config;
    }

    return $this->config;
  }

  //= HTMLkiConfig
  function originalConfig() {
    return $this->originalConfig ?: $this->config;
  }

  function error($msg) {
    throw new HTMLkiError($this, $msg);
  }

  function warning($msg) {
    $func = $this->config->warning;
    $func and call_user_func($func, $msg, $this);
  }
}

class HTMLkiTemplate extends HTMLkiObject
                     implements HTMLkiTemplateEnv, ArrayAccess, Countable {
  protected $str;             //= null, string
  protected $file;            //= null, string

  protected $vars = array();  //= hash

  protected $strParseVars;
  protected $strParseEscapeExpr;
  protected $strParseValues;

  function __construct(HTMLkiConfig $config) {
    $this->config = $config;
  }

  function loadStr($str) {
    $this->file = null;
    $this->str = $str;
    return $this;
  }

  function loadFile($file) {
    $this->file = $file;
    $this->str = null;
    return $this;
  }

  function loadedStr()    { return $this->str; }
  function loadedFile()   { return $this->file; }
  function isStr()        { return isset($this->str); }
  function isFile()       { return isset($this->file); }

  function offsetExists($var) {
    return $this->get($var) !== null;
  }

  function offsetGet($var) {
    return $this->get($var);
  }

  function offsetUnset($var) {
    $this->add($var, null);
  }

  function offsetSet($var, $value) {
    $this->add($var, $value);
  }

  function count() {
    return count($this->vars);
  }

  function __toString() {
    return $this->render();
  }

  //= $this if $vars, array otherwise
  function vars(array $vars = null) {
    isset($vars) and $this->vars = $vars;
    return isset($vars) ? $this : $this->vars;
  }

  function get($var) {
    return @$this->vars[$var];
  }

  function add($var, $value = true) {
    if (is_array($var)) {
      $this->vars = $var + $this->vars;
    } elseif ($var) {
      $this->vars[$var] = $value;
    }

    return $this;
  }

  // function (array $vars)
  //
  // function (string $var, callable[, $var, callable[, ...]])
  // Last $var without a callable is ignored.
  function append() {
    $toName = null;

    foreach (func_get_args() as $arg) {
      if (isset($toName)) {
        if (!isset($this->vars[$toName])) {
          is_callable($arg) and $arg = call_user_func($arg, $this, $toName);
          $this->add($toName, $arg);
          $toName = null;
        }
      } elseif (is_array($arg)) {
        $args = array();
        foreach ($arg as $var => $value) { array_push($args, $var, $value); }

        call_user_func_array(array($this, __FUNCTION__), $args);
      } else {
        $toName = $arg;
      }
    }

    return $this;
  }

  function mailto($email, $subject = '', $extra = '') {
    $subject and $subject = "?subject=".rawurlencode($subject);
    return '&#109;a&#x69;&#108;&#x74;&#111;:'.$this->email($email).$subject.$extra;
  }

  function email($email) {
    $replaces = array('.' => '&#46;', '@' => '&#x40;');
    return strtr($email, $replaces);
  }

  //= string
  function render() {
    return $this->evaluate($this->vars);
  }

  protected function evaluate(array $_vars) {
    extract($_vars, EXTR_SKIP);
    $_ki = $this;

    ob_start();
    isset($this->file) ? include($this->file) : eval('?>'.$this->str);
    return ob_get_clean();
  }

  function evaluateStr($_str_, array $_vars_, $_wrapByConfig_ = true) {
    $_str_ = "return $_str_;";

    if ($_wrapByConfig_) {
      $_str_ = $this->config->evalPrefix.$_str_.$this->config->evalSuffix;
    }

    extract($_vars_, EXTR_SKIP);
    return eval($_str_);
  }

  // $...    $$...   {...}   {{...
  function parseStr($str, array &$vars, $escapeExpr = false) {
    $this->strParseValues = array();

    if (strpbrk($str, '${') !== false) {
      $regexp = HTMLkiCompiler::inlineRegExp().'|'.HTMLkiCompiler::braceRegExp('{', '}');
      $regexp = "~$regexp~".$this->config->regexpMode;

      $this->strParseVars = $vars;
      $this->strParseEscapeExpr = $escapeExpr;
      $str = preg_replace_callback($regexp, array($this, 'strParser'), $str);
      HTMLki::pcreCheck($str);
    }

    return array($str, $this->strParseValues);
  }

    function strParser($match) {
      $match[0][0] === '$' or array_splice($match, 1, 1);
      list($full, $code) = $match;

      if (ltrim($code, $full[0]) === '') {
        $value = $code;
      } elseif ($full[0] === '$') {
        if (isset($this->strParseVars[$code])) {
          $value = $this->strParseVars[$code];
        } else {
          $value = $this[$code];
        }
      } else {
        $code = substr($code, 0, -1);

        $escaped = (!$this->strParseEscapeExpr or $code[0] === '=');
        $code[0] === '=' and $code = substr($code, 1);

        $value = $this->evaluateStr($code, $this->strParseVars + $this->vars);
        $escaped or $value = $this->escape($value);
      }

      do {
        $key = HTMLkiCompiler::Raw0. count($this->strParseValues) .HTMLkiCompiler::Raw1;
      } while (isset($this->strParseValues[$key]));

      $this->strParseValues[$key] = $value;
      return $key;
    }

  function formatStr($str, array &$vars) {
    list($str, $values) = $this->parseStr($str, $vars);
    return strtr($str, $values);
  }

  function lang($str, array $vars = array(), $escapeExpr = true) {
    list($str, $values) = $this->parseStr(trim($str), $vars, $escapeExpr);
    return $this->formatLang($str, $values);
  }

  function formatLang($str, array $values = array()) {
    if ($func = $this->config->language) {
      $placeholders = $this->placeholders($values);

      $str = str_replace(array_keys($values), $placeholders, $str);
      $str = call_user_func($func, $str);
      return str_replace($placeholders, $values, $str);
    } else {
      return strtr($str, $values);
    }
  }

  protected function placeholders(array $values) {
    $result = range(1, count($values));
    foreach ($result as &$i) { $i = ":$i"; }
    return $result;
  }

  // * $params string
  // = HTMLkiTagCall
  function parseParams($params, array $vars) {
    $call = new HTMLkiTagCall;
    $call->tpl = $this;

    $call->raw = $params = trim($params);

    while ($params !== '') {
      if ($params[0] === '$') {
        @list($list, $params) = explode(' ', substr($params, 1), 2);

        if (strpos($list, '{') !== false) {
          @list($end, $params) = explode('}', $params, 2);
          $list .= " $end";
        }

        $call->lists[] = $list;
        $params = ltrim($params);
        continue;
      } elseif ($params[0] === '"') {
        list($default, $rest) = explode('"', substr($params, 1), 2);

        if ($rest === '' or $rest[0] !== '=') {
          $call->defaults[] = $this->formatStr($default, $vars);
          $params = ltrim($rest);
          continue;
        }
      }

      break;
    }

    if ($params !== '') {
      $name = HTMLkiCompiler::wrappedRegExp();
      $regexp = "~(\s|^)
                    (?: ($name|[^\s=]+) =)? ($name|[^\s]+)
                  (?=\s|$)~x".$this->config->regexpMode;

      if (!preg_match_all($regexp, $params, $matches, PREG_SET_ORDER)) {
        $original = $call->raw === $params ? '' : "; original: [$call->raw]";
        $this->warning("Cannot parse parameter string [$params]$original.");
      }

      foreach ($matches as $match) {
        list(, , $key, $value) = $match;
        $valueWrapper = $this->wrapperOf($value);

        if ($key === '') {
          $call->values[] = array($valueWrapper, $value);
        } else {
          $keyWrapper = $this->wrapperOf($key);
          $call->attributes[$key] = array($keyWrapper, $valueWrapper, $value);
        }
      }
    }

    return $call;
  }

  protected function wrapperOf($str) {
    switch ($str[0]) {
    case '"':
    case '{':   return $str[0];
    default:    return '';
    }
  }

  function setTagAttribute($tag, $key, array $attributes = array()) {
    $attributes or $attributes = array($key);
    $config = $this->ownConfig();

    foreach ($attributes as $value) {
      $config->shortAttributes[$tag][$key] = $value;

      if (strrchr($value, '=') === false) {
        $config->flagAttributes[$tag][] = $value;
      }
    }
  }

  function startTag($tag, $params = '', array $vars = array()) {
    $call = $this->parseParams($params, $vars);

    $call->tag = $tag;
    $call->vars = $vars;

    return $this->callTag($call);
  }

  function endTag($tag, $params = '', array $vars = array()) {
    $call = $this->parseParams($params, $vars);

    $call->tag = $tag;
    $call->vars = $vars;
    $call->isEnd = true;

    return $this->callTag($call);
  }

  function singleTag($tag, $params = '', array $vars = array()) {
    $call = $this->parseParams($params, $vars);

    $call->tag = $tag;
    $call->vars = $vars;
    $call->isEnd = true;
    $call->isSingle = true;

    return $this->callTag($call);
  }

  function callTag(HTMLkiTagCall $call) {
    $call->tag === '' and $call->tag = $this->config->defaultTag;

    $tag = strrchr($call->tag, '/');
    if ($tag !== false) {
      $this->multitag(substr($call->tag, 0, -1 * strlen($tag)), $call);
      $call->tag = substr($tag, 1);
    }

    $handler = $call->tag;
    $params = '';

    while ($alias = &$this->config->tags[$handler]) {
      if (is_string($alias)) {
        @list($handler, $params) = explode(' ', "$alias ", 2);
      } elseif (is_array($alias) and is_string($alias[0]) and
                ltrim($alias[0], 'A..Z') !== '') {
        $params = $alias;
        $handler = array_shift($params);
      } else {
        $handler = $alias;
        $params = null;
        break;
      }

      $this->applyCallAliasTo($call, $handler, $params);
    }

    if (isset($params)) {
      $func = "tag_$handler";
      method_exists($this, $func) or $func = 'regularTag';

      $result = $this->$func($call);
    } elseif (!is_callable($handler)) {
      $handler = var_export($handler, true);
      $this->warning("Invalid tag handler [$handler]; original tag name: [{$call->tag}].");

      $result = null;
    } else {
      $call->attributes = $this->evaluateWrapped($call->vars, $call->attributes);
      $call->values = $this->evaluateWrapped($call->vars, $call->values);

      $result = call_user_func($handler, $call);
    }

    return is_array($result) ? $result : array();
  }

  protected function applyCallAliasTo($call, $handler, $params) {
    $call->tag = $handler;
    is_array($params) or $params = explode(' ', $params);

    foreach ($params as $key => $value) {
      if (is_int($key)) {
        if ($value === '') { continue; }

        @list($key, $value) = explode('=', $value, 2);
        isset($value) or $value = $key;
      }

      if (!isset($call->attributes[$key])) {
        $call->attributes[$key] = array('', '', $value);
      }
    }
  }

  function multitag($tags, HTMLkiTagCall $call) {
    $tags = array_filter( is_array($tags) ? $tags : explode('/', $tags) );

    if ($call->isEnd) {
      foreach ($tags as $tag) { echo "</$tag>"; }
    } else {
      $call = clone $call;
      $call->clear();

      foreach ($tags as $tag) {
        $call->tag = $tag;
        echo $this->htmlTagOf($call, $tag);
      }
    }
  }

  function evalListName($name, array $vars = array()) {
    $parsed = $this->parseListName($name);
    $parsed[0] = $this->getList($parsed[0], $vars);
    return $parsed;
  }

  // * $name string - format: "[name] [[{] expr [}]]"
  function parseListName($name) {
    @list($prefix, $expr) = explode('{', trim($name), 2);

    if ($expr) {
      $suffix = $prefix;

      if ($prefix !== '') {
        $prefix .= '_';
        $suffix = "_$suffix";
      }

      return array(rtrim($expr, ' }'), $prefix, $suffix);
    } else {
      return array("$$prefix", '', '');
    }
  }

  // * $name string - an expression; if begins with '$' this list var is
  //   retrieved using HTMLkiConfig->listVariable callback.
  function getList($name, array $vars = array()) {
    $name = trim($name);

    if ($name === '') {
      $result = null;
    } elseif ($name[0] === '$' and $func = $this->config->listVariable) {
      $var = substr($name, 1);
      $expr = ltrim($var, 'a..zA..Z0..9_');
      $var = substr($var, 0, strlen($var) - strlen($expr));

      $value = call_user_func($func, $var, $this);

      if (isset($value) and trim($expr) === '') {
        $result = $value;
      } else {
        $vars += array($var => $value);
      }
    }

    isset($result) or $result = $this->evaluateStr($name, $vars);

    if ($result === null or $result === false) {
      return array();
    } elseif (!is_array($result) and !($result instanceof Traversable)) {
      return array($result);
    } else {
      return $result;
    }
  }

  function setList($name, array $value = null) {
    $this[$name] = $value;
    return $this;
  }

  function regularTag($call) {
    $tag = $call->tag;

    $isCollapsed = ($call->isSingle and !in_array($tag, $this->config->singleTags));
    $call->isSingle &= !$isCollapsed;
    $call->isEnd &= !$isCollapsed;

    if ($call->isSingle) {
      // <... />
      if ($call->lists) {
        $this->regularTagSingleLoopCall = $call;
        $this->loop($call, array($this, 'regularTagSingleLoop'));
      } else {
        $call->attributes = $this->evaluateWrapped($call->vars, $call->attributes);
        $call->values = $this->evaluateWrapped($call->vars, $call->values);

        echo $this->htmlTagOf($call, $tag);
      }
    } elseif ($call->isEnd) {
      // </...>
      if ($call->lists) {
        $lists = join(', ', $call->lists);
        $this->warning("</$tag> form cannot be called with list data: [$lists].");
      }

      echo "</$tag>";
    } elseif ($call->lists) {  // <...>
      $this->regularTagListsLoopResult = array();
      $this->loop($call, array($this, 'regularTagListsLoop'));

      if ($this->regularTagListsLoopResult) {
        $call->attributes = $this->evaluateWrapped($call->vars, $call->attributes);
        $call->values = $this->evaluateWrapped($call->vars, $call->values);

        echo $this->htmlTagOf($call, $tag);
      }

      return $this->regularTagListsLoopResult;
    } else {
      $call->attributes = $this->evaluateWrapped($call->vars, $call->attributes);
      $call->values = $this->evaluateWrapped($call->vars, $call->values);

      echo $this->htmlTagOf($call, $tag);
    }

    if ($isCollapsed) { echo "</$tag>"; }
  }

  protected function loop($lists, $callback, array $vars = array()) {
    if ($lists instanceof HTMLkiTagCall) {
      $vars = $lists->vars;
      $lists = $lists->lists;
    }

    foreach ($lists as $list) {
      $i = -1;
      list($list, $prefix, $suffix) = $this->evalListName($list, $vars);

      foreach ($list as $key => $item) {
        $vars = array("key$suffix" => $key, "i$suffix" => ++$i, "item$suffix" => $item,
                      "isFirst$suffix" => $i === 0, "isLast$suffix" => $i >= count($list) - 1,
                      "isEven$suffix" => $i % 2 == 0, "isOdd$suffix" => $i % 2 == 1);

        if (is_array($item)) {
          foreach ($item as $key => $value) {
            $vars[$prefix.$key] = $value;
          }
        }

        call_user_func($callback, $vars);
      }
    }
  }

  // * $tag string
  // = string HTML
  function htmlTagOf(HTMLkiTagCall $call, $tag, $isSingle = null) {
    $isSingle === null and $isSingle = $call->isSingle;
    return $this->htmlTag($tag, $call->attributes(), $isSingle);
  }

  // = string HTML
  function htmlTag($tag, array $attributes = array(), $isSingle = false) {
    $end = ($isSingle and $this->config->xhtml) ? ' /' : '';
    $attributes = $this->htmlAttributes($attributes);
    return "<$tag$attributes$end>";
  }

  function htmlAttribute($str, $trim = true) {
    $trim and $str = trim($str);

    if (strrchr($str, '"') === false) {
      return '"'.$this->escape($str).'"';
    } else {
      return '\''.str_replace("'", '&#039;', $this->escape($str, ENT_NOQUOTES)).'\'';
    }
  }

  function escape($str, $quotes = ENT_COMPAT, $doubleEncode = true) {
    if (is_array($str)) {
      foreach ($str as &$s) { $s = $this->escape($s, $quotes, $doubleEncode); }
    } else {
      return htmlspecialchars($str, $quotes, $this->config->charset, $doubleEncode);
    }
  }

  function htmlAttributes($attributes) {
    $result = '';

    foreach ($attributes as $name => $value) {
      $result .= ' '.$this->escape(trim($name)).'='.$this->htmlAttribute(trim($value));
    }

    return $result;
  }

  function evaluateWrapped(array &$vars, $wrapper, $value = null) {
    if (func_num_args() == 2) {
      $keys = array();

      foreach ($wrapper as $key => &$wrapped) {
        if (is_int($key)) {
          list($valueWrapper, $value) = $wrapped;
        } else {
          list($keyWrapper, $valueWrapper, $value) = $wrapped;
          $keys[] = $this->evaluateWrapped($vars, $keyWrapper, $key);
        }

        $last = count($wrapped) - 1;
        $wrapped[$last - 1] = '-';
        $wrapped[$last] = $this->evaluateWrapped($vars, $valueWrapper, $value);
      }

      return $keys ? array_combine($keys, $wrapper) : $wrapper;
    } elseif ($wrapper === '-') {
      return $value;
    } elseif ($wrapper === '{') {
      $value = substr($value, 1, -1);
      return $this->evaluateStr($value, $vars);
    } else {      // " or (none)
      $wrapper === '"' and $value = substr($value, 1, -1);
      return $this->formatStr($value, $vars);
    }
  }

  protected function tag_include($call) {
    $func = $this->config->template;

    if (!$func) {
      $this->warning("Cannot <include> a template - no \$template config handler set.");
    } elseif (!$call->values) {
      $this->warning("Cannot <include> a template - no name given.");
    } else {
      $values = $this->evaluateWrapped($call->vars, $call->values);
      $file = call_user_func($func, $call->values[0][1], $this, $call);

      if ($file instanceof self) {
        $tpl = $file;
      } elseif (is_string($file)) {
        $config = $this->config->inheritConfig ? 'config' : 'originalConfig';

        $tpl = new static($this->$config());
        $tpl->loadFile($file);
        $tpl->vars($call->vars + $this->vars);
      }

      if ($call->lists) {
        $this->tag_includeTpl = $tpl;
        $this->loop($call, array($this, 'tag_includeLoop'));
      } else {
        echo $tpl->render();
      }
    }
  }

  protected function tag_each($call) {
    if (!$call->isEnd) {
      if ($call->lists) {
        $this->tag_eachLoopResult = array();
        $this->loop($call, array($this, 'tag_eachLoop'));
        return $this->tag_eachLoopResult;
      } else {
        $this->warning('<each> called without list name.');
      }
    }
  }

  protected function tag_if($call) {
    if (!$call->isEnd) {
      $holds = $this->evaluateStr($call->raw, $call->vars);
      return $holds ? array(array()) : array();
    }
  }

  protected function tag_lang($call) {
    $vars = $call->vars + $this->evaluateWrapped($call->vars, $call->attributes);

    foreach ($call->defaults as $lang) {
      if ($values = $call->values) {
        foreach ($values as &$value) { $value = array_pop($value); }
        $values = array_combine($this->placeholders($values), $values);
      }

      echo $this->formatLang($this->evaluateStr("\"$lang\"", $vars), $values);
    }
  }

  protected function tag_mailto($call) {
    if ($call->isEnd and !$call->isSingle) {
      echo '</a>';
    } else {
      $email = reset($call->defaults);
      echo '<a href="'.$this->mailto($email, next($call->defaults)).'">';

      if ($call->isSingle) { echo $this->email($email), '</a>'; }
    }
  }

  /*-----------------------------------------------------------------------
  | PHP 5.2 SUPPORT - closureless callbacks
  |----------------------------------------------------------------------*/

  // for regularTag()
  protected $regularTagSingleLoopCall;
  function regularTagSingleLoop($vars)  {
    $call = clone $this->regularTagSingleLoopCall;

    foreach ($call->defaults as &$s) {
      $s = $this->evaluateWrapped($vars, '', $s);
    }

    $call->attributes = $this->evaluateWrapped($vars, $call->attributes);
    $call->values = $this->evaluateWrapped($vars, $call->values);

    echo $this->htmlTagOf($call, $call->tag);
  }

  // for regularTag()
  protected $regularTagListsLoopResult;
  function regularTagListsLoop($vars) {
    $this->regularTagListsLoopResult[] = $vars;
  }

  // for tag_include()
  protected $tag_includeTpl;
  function tag_includeLoop($vars) {
    $this->tag_includeTpl->add($vars);
    echo $this->tag_includeTpl->render();
  }

  // for tag_each()
  protected $tag_eachLoopResult;
  function tag_eachLoop($vars) {
    $this->tag_eachLoopResult[] = $vars;
  }
}

class HTMLkiCompiler extends HTMLkiObject {
  const Raw0 = "\5\2";
  const Raw1 = "\2\5";

  protected $str;               //= string

  protected $raw = array();     //= hash of mask => original
  protected $rawSrc = array();  //= hash of mask => string replaced in the template
  protected $nesting = 0;

  //= string
  static function braceRegExp($op, $ed = null, $delimiter = '~') {
    $op = preg_quote($op, $delimiter);
    $ed = isset($ed) ? preg_quote($ed, $delimiter) : $op;
    return "$op($op+|[^$ed\r\n]+$ed)";
  }

  //= string
  static function nestedBraceRegExp($op, $delimiter = '~') {
    $op = preg_quote($op, $delimiter);
    return "$op($op+|(?:$op$op|[^$op\r\n])+$op)";
  }

  //= string
  static function inlineRegExp() {
    return '\$(\$(?=[a-zA-Z_$])|[a-zA-Z_]\w*)';
  }

  static function quotedRegExp() {
    return '"[^"\r\n]*"';
  }

  static function wrappedRegExp() {
    return static::quotedRegExp().'|\{[^}\r\n]+}';
  }
  function __construct(HTMLkiConfig $config, $str) {
    $this->config = $config;
    $this->str = $str;
  }

  //= string original template
  function str() { return $this->str; }

  //= string PHP code
  function compile() {
    $source = $this->str;

    foreach ($this->config->compilers as $func) {
      if (is_string($func)) {
        if ($this->hasCompiler($func)) {
          $source = $this->{"compile_$func"}($source);
        } else {
          $this->error("Compiler function [$func] is not defined.");
        }
      } else {
        call_user_func($func, $source, $this);
      }
    }

    $source = $this->postCompile($source);
    return $source;
  }

  //= bool
  function hasCompiler($name) {
    return method_exists($this, "compile_$name");
  }

  protected function postCompile(&$str) {
    $str = strtr($str, $this->raw);

    if ($this->config->addLineBreaks) {
      $feeds = array("?>\n" => ";echo \"\\n\"?>\n",
                     "?>\r\n" => ";echo \"\\r\\n\"?>\r\n");
      $str = strtr($str, $feeds);
    }

    return $this->config->compiledHeader.$str.$this->config->compiledFooter;
  }

  //= string masked $str
  function raw($str, $src = null) {
    $str = $this->reraw($str);

    do {
      $key = static::Raw0. count($this->raw) .static::Raw1;
    } while (isset($this->raw[$key]));

    $this->raw[$key] = $str;
    $this->rawSrc[$key] = isset($src) ? $src : $str;

    return $key;
  }

  function reraw($str) {
    return strtr($str, $this->rawSrc);
  }

  function rawPhp($code, $src = null) {
    $short = $this->config->shortPhp;
    isset($short) or $short = ini_get('short_open_tag');

    $code = trim($code);

    if ($short) {
      substr($code, 0, 5) === 'echo ' and $code = '='.substr($code, 5);
    } else {
      $code = "php $code";
    }

    return $this->raw("<?$code?>", $src);
  }

  function grabVarsFor($str) {
    if (strrchr($str, '$') !== false or strpos($str, static::Raw0) !== false) {
      return ', get_defined_vars()';
    }
  }

  protected function quote($str) {
    return addcslashes($this->reraw($str), "'\\");
  }

  protected function replacing($method, $regexp, $str) {
    $regexp[0] === '~' or $regexp = "~$regexp~";
    $regexp .= $this->config->regexpMode;

    $method = 'match'.strrchr($method, '_');
    $result = preg_replace_callback($regexp, array($this, $method), $str);
    return HTMLki::pcreCheck($result);
  }

  // <?...? >
  protected function compile_php(&$str) {
    return $this->replacing(__FUNCTION__, '<\?(php\b|=)?([\s\S]+?)\?>', $str);
  }

    protected function match_php($match) {
      list(, $prefix, $code) = $match;

      $prefix === '=' and $code = "echo $code";
      return $this->rawPhp($code);
    }

  // $=...   $$=...   also ^ and *
  protected function compile_varSet(&$str) {
    $ws = '[ \t]';
    $id = '[a-zA-Z_]\w*';
    $regexp = "~^($ws*\\$)(\\$*)(([=^*])($id)(@(?:$id)?)?($ws+.*)?)(?:\r?\n|$)()~m";

    return $this->replacing(__FUNCTION__, $regexp, $str);
  }

    protected function match_varSet($match) {
      list(, $head, $escape, $body, $type, $var, $tag, $value) = $match;
      $value = ltrim($value);

      if ($escape) {
        return $this->raw($head.substr($escape, 1), $head.$escape).$body;
      } elseif ($type === '^' and $value !== '') {
        // "$^var value" is meaningless since inline assignment is just one line.
        return $this->raw($head.$escape).$body;
      } elseif ($type === '=') {
        if ($tag) {
          $attributes = array();

          $regexp = '~(\s|^)([a-zA-Z_]\w*)(=(?:"[^"]*"|[^\s]*))?()(?=\s|$)~'.
                    $this->config->regexpMode;

          if ($value and preg_match_all($regexp, $value, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
              $attr = $match[2].($match[3] ? trim($match[3], '"') : "=$match[2]");
              $attributes[] = "'".$this->quote($attr)."'";
            }
          }

          $tag = strtolower( substr($tag, 1) );
          $attributes = join(', ', $attributes);
          $code = "\$_ki->setTagAttribute('$tag', '$var', array($attributes))";
        } elseif ($value === '') {
          $code = 'ob_start()';
        } else {
          $code = "$$var = $value";
        }
      } elseif ($type === '*' and $value !== '') {
        $value = rtrim($value, ';');
        $code = "echo \$_ki->escape($$var = $value)";
      } else {
        $func = $type === '^' ? 'clean' : 'flush';
        $code = "$$var = ob_get_$func()";
      }

      return $this->rawPhp($code, $head.$body);
    }

  // <...>   <.../>   </...>
  protected function compile_tags(&$str) {
    $quoted = static::quotedRegExp();
    $attr = static::wrappedRegExp().'|->|[^>\r\n]';

    $regexp = "<(/?)($quoted|[$\\w/]+)( +($attr)*|/)?>()";
    return $this->replacing(__FUNCTION__, $regexp, $str);
  }

    protected function match_tags($match) {
      list($match, $isEnd, $tag, $params) = $match;

      $params = trim($params);
      $isVariable = $isElse = $isLoopStart = $isLoopEnd = $isSingle = false;

      if ($tag[0] === '"' or $match === '</>') {
        $isEnd = $match === '</>';
        $isSingle = (!$isEnd and substr($params, -1) === '/');
        $params = $isEnd ? '' : "$tag ".rtrim($params, '/');
        $tag = '';
      } else {
        $isVariable = strrchr($tag, '$') !== false;
        $isVariable or $tag = strtolower($tag);

        $isElse = substr($tag, 0, 4) === 'else';
        $isElse and $tag = (string) substr($tag, 4);

        $isSingle = substr($params, -1) === '/';
        if ($isSingle) {
          $params = substr($params, 0, -1);
        } elseif (!$isVariable and !$isEnd) {
          $isSingle = in_array($tag, $this->config->singleTags);
        }

        if (!$isSingle) {
          if ($isEnd) {
            if (!$isElse) {
              $isLoopEnd = substr($tag, 0, 3) === 'end';

              if ($isLoopEnd) {
                $tag = (string) substr($tag, 3);
              } elseif (!$isVariable) {
                $isLoopEnd = in_array($tag, $this->config->loopTags);
              }
            }
          } else {
            $isLoopStart = (substr($params, 0, 1) === '$' or
                            (!$isVariable and in_array($tag, $this->config->loopTags)));
          }
        }

        if ($tag === '') {
          $code = $isElse ? '} else {' : ($isLoopEnd ? '}' : '');
        }
      }

      if (!isset($code)) {
        $func = $isEnd ? 'endTag' : ($isSingle ? 'singleTag' : 'startTag');

        $tag = $isVariable ? "strtolower(\"$tag\")" : "\"$tag\"";
        $func = "\$_ki->$func($tag";

        $params = $this->quote($params);
        $func .= ", '$params', get_defined_vars())";

        $code = '';
        $isElse and $code .= '} else ';

        if ($isLoopStart and !$isElse) {
          ++$this->nesting;
        }

        $seqVar = sprintf('$_i%03s', $this->nesting);

        if ($isLoopStart) {
          $code .= "if ($seqVar = $func)".
                   " foreach ($seqVar as \$_iteration_) {".
                   " extract(\$_iteration_)";
        } elseif ($isLoopEnd) {
          $code .= "} $seqVar and extract($func)";
          --$this->nesting;
        } else {
          $isElse and $code .= '{ ';
          $code .= "extract($func)";
        }
      }

      return $this->rawPhp($code, $match);
    }

  // {...}   {{...
  protected function compile_echo(&$str) {
    return $this->replacing(__FUNCTION__, static::braceRegExp('{', '}'), $str);
  }

    protected function match_echo($match) {
      if (ltrim($match[1], '{') === '') {
        return $match[1];
      } else {
        $isRaw = $match[1][0] === '=';
        $isRaw and $match[1] = substr($match[1], 1);

        $code = trim( substr($match[1], 0, -1) );

        if ($code !== '' and ltrim($code, 'a..zA..Z0..9_') === '' and
            ltrim($code[0], 'a..z_') === '') {
          $code = "$$code";
        }

        $isRaw or $code = "\$_ki->escape($code)";
        return $this->rawPhp("echo $code", $match[0]);
      }
    }

  // "..."   ""...
  protected function compile_lang(&$str) {
    return $this->replacing(__FUNCTION__, static::nestedBraceRegExp('"'), $str);
  }

    protected function match_lang($match) {
      if (ltrim($match[1], '"') === '') {
        return $match[1];
      } else {
        $lang = $this->quote( str_replace('""', '"', substr($match[1], 0, -1)) );
        $vars = $this->grabVarsFor($lang);
        return $this->rawPhp("echo \$_ki->lang('$lang'$vars)", $match[0]);
      }
    }

  // $abc123_...
  protected function compile_varEcho(&$str) {
    return $this->replacing(__FUNCTION__, static::inlineRegExp(), $str);
  }

    protected function match_varEcho($match) {
      if (ltrim($match[1], '$') === '') {
        return $match[1];
      } else {
        return $this->rawPhp("echo \$_ki->escape($$match[1])", $match[0]);
      }
    }
}
