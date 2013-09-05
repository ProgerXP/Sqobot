<?php
/*
  MiMeil - flexible MIME message builder covering most useful specification features.
  in public domain | by Proger_XP | http://proger.i-forge.net/MiMeil

  Supports PHP 5.3 and up. Standalone; iconv and mbstring extensions recommended.
*/

// Base class for all MiMeil-originating exceptions.
class MiMeilError extends Exception {
  function __toString() {
    return '['.get_class($this).'] '.$this->getMessage();
  }
}

// The main class. Each instance represents a complete message with headers,
// attachments, body and so on. Static fields provide general customizations.
class MiMeil {
  //= string    number for MIME header used in multipart messages
  static $mimeVersion = '1.0';

  //= hash      maps MIME identifiers to short friendly names
  static $bodyTypes = array('text/plain' => 'text', 'text/html' => 'html');

  //= string    MIME type used when attachment's MIME cannot be guessed
  static $defaultMIME = 'application/octet-stream';

  //= string    used to prefix generated MIME boundaries
  static $boundaryPrefix = '_hello-uverse_';

  //= string    used in multipart messages instead of (old) message body
  static $mimeStub = 'Your e-mail client does not support multi-part MIME messages.';

  //= hash      maps file extensions to their MIME types
  static $mimeByExt = array(
    'jpeg' => 'image/jpeg', 'jpg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif',
    'bmp' => 'image/x-ms-bmp', 'svg' => 'image/svg+xml', 'ico' => 'image/vnd.microsoft.icon',
    'txt' => 'text/plain', 'log' => 'text/plain',  'wiki' => 'text/plain',
    'wacko' => 'text/plain', 'ini' => 'text/plain', 'sh' => 'text/plain',
    'conf' => 'text/plain', 'pas' => 'text/x-pascal', 'c' => 'text/x-c',
    'h' => 'text/x-c', 'cpp' => 'text/x-c', 'hpp' => 'text/x-c',
    'pdf' => 'application/pdf', 'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'xls' => 'application/msexcel',
    'xlsx application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'rtf' => 'application/rtf', 'tar' => 'application/x-tar', 'gz' => 'application/x-gzip',
    'bz2' => 'application/x-bzip2', 'rar' => 'application/x-rar-compressed',
    'zip' => 'application/zip', '7z' => 'application/x-7z-compressed',
    'htm' => 'text/html', 'html' => 'text/html', 'php' => 'text/html',
    'xml' => 'application/xhtml+xml', 'mht' => 'message/rfc822',
    'swf' => 'application/x-shockwave-flash', 'css' => 'text/css',
    'dtd' => 'application/xml-dtd', 'cfg' => 'text/plain', 'manifest' => 'text/plain',
    'exe' => 'application/x-msdownload', 'dll' => 'application/x-msdownload');

  // Routine to initiate events calling listed handlers. Must be set or an exception
  // when trying to send a message. See also Fire(). Implementation details are up
  // to the user of this class as long as it follows these rules:
  // * each listener is called with $args as its parameters (not as a single array).
  // * if any listener returns a value that's !== null remaining listeners are
  //   skipped and that value is returned by the assigned $onEvent handler.
  // * note that $args may contain &references which must be kept intact.
  //
  //= callable  ($event, array $args)
  static $onEvent;

  // Routine to convert between UTF-8 and another character set.
  //
  //= null      use ::ConvertUTF8()
  //= callable  ($charset, bool $toUTF8, $str, MiMeil $mail)
  static $utf8Convertor;

  //= has       default settings
  static $defaults = array(
    //= bool    if set message headers will be seorted (not required for anything but neat)
    'sortHeaders' => true,
    //= bool    if set and if message contains only HTML body MiMeil will automaticalky
    //          create plain text version by removing HTML tags and entities
    'makeTextBodyFromHTML' => true,
    //= string  Content-Transfer-Encoding to use for outgoing messages
    'bodyEncoding' => 'base64',
    //= array of string     character set identifiers to sequentially try and encode
    //                      message body to; if none could represent all symbols it
    //                      contains UTF-8 is used
    'bodyCharsets' => array('iso-8859-1'),
    //= string  format to append to 'Content-Type: ...; format=XXX'
    'textBodyFormat' => 'flowed',
    //= array of string     MIME types in addition to ::$bodyTypes that message body
    //                      can be of (these are not attachments' but body's)
    'allowedBodyMIME' => array(),
    // Specifies valid attachment MIME types; files not matching these are removed.
    // See ParseAttachments() for the list of values.
    'allowedAttachments' => '1',
    // String sequence used to terminate each header line with.
    // There are reports that "\r\n" in headers makes some relays/mail clients treat
    // it as a double line break and thus the end of headers; switcing it to non-RFC
    // "\n" fixes the problem.
    'headerEOLN' => "\n",
  );

  //= string    e-mail addresses
  public $from, $returnPath;

  //= string    message subject line
  public $subject;

  // to/copyTo/bccTo addressee is of form [name ]e@mail OR name <e@mail> - in the first
  // form, if name is followed by a space it's converted to the second (full) form.
  public $to = array(), $copyTo = array(), $bccTo = array();

  //= array of string       e-mail addresses
  public $deliveryNotifications = array();

  // Headers listed here override standard (From, Content-Type, etc.) headers
  // created by MiMeil.
  //
  //= array of string
  public $headers = array();

  // Lists header names that are accepted in outgoing messages; others are removed.
  // If contains optional '-' member listed headers are removed, otherwise only
  // listed headers are kept and all others are removed.
  //
  //= array of string       header names
  public $allowedHeaders;

  //= string    Content-Transfer-Encoding to use for outgoing messages
  public $bodyEncoding;

  //= array of string       character set identifiers; see $defaults property above
  public $bodyCharsets;

  //= true      the first charset from $bodyCharsets is used to encode the message
  //            body even if it can't represent all of its symbols
  //= false     listed charsets are tried in turn and if none could contain all the
  //            symbols UTF-8 is used instead
  public $forceBodyCharset;

  // See ::$defaults property above for the description of these options.
  public $makeTextBodyFromHTML;
  public $allowedBodyMIME, $textBodyFormat;
  public $allowedAttachments;
  public $headerEOLN, $sortHeaders;

  //= string    custom command-line parameters for sendmail (last argument of PHP's mail())
  public $params;

  //= string    line feed sequence used outside the headers preface
  public $eoln = "\r\n";

  // If set and if this message doesn't contain a text/html body all multipart/related
  // attachments are discarded. Such attachments are normally used to embed media
  // (images, etc.) into HTML body are are invisible in the attachments list of
  // the mail agent.
  //
  //= bool
  public $skipRelatedAttIfNoHtmlBody = true;

  // Is set after calling Send(); array with final information used to dispatch
  // the message with. Has keys: subject, headers (array), message (array), status
  // (return value of mail() or null if it wasn't used, e.g. only simulated).
  //
  //= hash
  public $mailed;

  //= bool      indicating if this message should be prepared and delivered or only
  //            prepared; is initially set by Send() and SimulateSending()
  public $simulateSending;

  //= hash of scalar      with keys 'text', 'html'
  protected $body = array();

  //= array of hashes     with keys: name, mime (attachment Content-Type), data,
  //                      headers (array), isRelated (puts attachment in
  //                      multipart/related group)
  protected $attachments = array();

  // Value for X-Priority and Importance headers: 0 (low), 1 (normal), 2 (high).
  //
  //= integer   0-2
  protected $priority = 1;

  /*---------------------------------------------------------------------
  | STANDALONE UTILITIES
  |----------------------------------------------------------------------
  | Small methods used to convert, normalize, encode various values.
  |--------------------------------------------------------------------*/

  // Encodes $str with Base-64 and splits the result using RFC 2045-compatible
  // line width and breaks.
  //
  //= string
  static function chunkBase64($str) {
    return chunk_split(base64_encode($str));
  }

  // Turns a UTF-8 string (such as a subject line) into a 7-bit ASCII safe to
  // be transmitted over the wire. Returns $str as is if it's already 7-bit safe.
  //
  //= string
  static function mangleUTF8($str) {
    if (!isset($str[0]) or utf8_decode($str) === $str) {
      return $str;
    } else {
      return '=?UTF-8?B?'.base64_encode($str).'?=';
    }
  }

  // Decodes "mangled" 7-bit safe UTF-8 string produced by MangleUTF8() or
  // another agent into real UTF-8.
  static function demangleUTF8($str) {
    static $pf = '=?UTF-8?B?';

    if (substr($str, -2) === '?=' and substr($str, 0, strlen($pf)) === $pf) {
      return base64_decode( substr($str, strlen($pf), -2) );
    } else {
      return $str;
    }
  }

  // Constructs plain text representation of $html string (always given as UTF-8).
  //
  //= string    with stripped tags and entities
  static function textFromHTML($html) {
    return html_entity_decode(strip_tags($html), ENT_QUOTES, 'utf-8');
  }

  // Checks if $str conforms to common wildcard pattern (with '*' and '?').
  //
  //= bool
  static function matchWildcard($wildcard, $str, $caseSensitive = true) {
    $caseSensitive = $caseSensitive ? 0 : FNM_CASEFOLD;
    $flags = $caseSensitive | FNM_NOESCAPE | FNM_PATHNAME | FNM_PERIOD;
    return fnmatch($wildcard, $str, $flags);
  }

  // Converts $str between UTF-8 and another $charset or vice-versa ($toUTF-8 is set).
  // Requires iconv() in most cases. When converting from UTF-8 symbols that can't
  // be represented by $charset are skipped.
  //
  //= string
  static function convertUTF8($charset, $toUTF8, $str) {
    if (strtolower($charset) === 'iso-8859-1') {
      $str = $toUTF8 ? utf8_encode($str) : utf8_decode($str);
    } elseif ($charset) {
      if (function_exists('iconv')) {
        $toUTF8 or $charset .= '//IGNORE';
        $str = iconv($toUTF8 ? $charset : 'UTF-8', $toUTF8 ? 'UTF-8' : $charset, $str);
      } else {
        throw new MiMeilError('Iconv for PHP module is needed for charset conversion.');
      }
    }

    if (is_string($str)) {
      return $str;
    } else {
      $to = $toUTF8 ? 'from' : 'to';
      throw new MiMeilError("Cannot convert charset $to $charset.");
    }
  }

  // Ensures that $str doesn't contain any control characters such as line feeds -
  // they all are replaced with spaces. Since tab is a control symbol they also
  // become spaces. Useful for removing unexpected symbols from subjects and addresses.
  //
  //= string    with no symbols with code below 32 (space)
  static function oneLiner($str) {
    $str = trim($str);
    for ($i = 0; isset($str[$i]); ++$i) { ord($str[$i]) < 32 and $str[$i] = ' '; }
    return $str;
  }

  // Normalizes e-mail address string. Takes care of person name (wrapping it in
  // quotes and protecting UTF-8 if necessary) and produces proper address like
  // "Name" <mail@example.com>. Name part is omitted if it's not present in $addr.
  // Errors if $addr contains no '@' symbol.
  //
  //= string
  static function normAddress($addr) {
    $addr = static::oneLiner($addr);
    $delim = mb_strpos($addr, '<');

      $delim === false and $delim = mb_strrpos($addr, ' ');
      $delim === false and $delim = -1;

      $name = $delim == -1 ? '' : trim( mb_substr($addr, 0, $delim) );
      $addr = rtrim(trim( mb_substr($addr, $delim + 1) ), '>');

    if (mb_strpos($addr, '@') === false) {
      throw new MiMeilError("Wrong e-mail address format: [$addr].");
    }

    $name === '' or $name = '"'.static::mangleUTF8($name).'" ';
    return "$name<$addr>";
  }

  // Converts regular address (e.g. mail@example.com or "My Name" <m@e.com>) into
  // another form used in some headers: '<mailto:mail@example.com> (My Name)'.
  // Brackets are only present if $addr contains person's name.
  //
  //= string
  static function addressToAngular($addr) {
    $addr = static::normAddress($addr);

    if ($addr[0] === '"') {
      list(, $caption, $addr) = explode('"', $addr, 3);
    } else {
      $caption = '';
    }

    return static::angularAddr(trim($addr, '<> '), $caption);
  }

  // Similar to AddressToAngular() but doesn't require $addr to be an e-mail address
  // so it can be an URL. Prepends it with missing 'mailto:' if it contains '@'.
  //= string
  static function angularAddr($addr, $caption = '') {
    $addr = static::oneLiner($addr);
    $caption = static::oneLiner($caption);

    if (substr($addr, 0, 7) !== 'mailto:' and strrchr($addr, '@')) {
      $addr = "mailto:$addr";
    }

    return "<$addr>".($caption === '' ? '' : " ($caption)");
  }

  // Extracts e-mail address from a format produced by NormAddress() such as
  // "Name" <mail@example.com>.
  //
  //= string    e-mail address alone like 'mail@example.com'
  static function extractFrom($addr) {
    $head = strtok(static::normAddress($addr), '<');
    $tail = strtok('>');
    return trim($tail === false ? $head : $tail, ' "<>');
  }

  // Saves this object's contents as a valid .eml message readable by most agents.
  // $prefix can end on \ or / to save the .eml to this directory (unique index
  // will be used as a file name starting from 1). If it doesn't end with them
  // it's a file name to which an index might be appended if it already exists.
  // Directories are created as necessary with 0775 perms.
  //
  //* $prefix string      - location of the new .eml file.
  //* $eml string         - complete message with all headers and data prepared for
  //                        submission to a mail relay.
  //
  //= integer   number of bytes written
  static function saveTo($prefix, $eml) {
    $path = strpbrk(substr($prefix, -1), '\\/') ? $prefix : dirname($prefix);

    $index = is_dir($path) ? max(1, count(scandir($path)) - 1) : 1;
    while (is_file($file = "$prefix$index.eml")) { ++$index; }

    is_dir(dirname($file)) or mkdir(dirname($file), 0775, true);
    return file_put_contents($file, $eml, LOCK_EX);
  }

  // MiMeil is event-driven; all actions it does (header and attachment nlization,
  // multipart body building, message dispatching, etc.) are initiated by events.
  // For MiMeil to be functional its listeners should be attached to corresponding
  // events. This function accepts a callback that is called per each standard
  // handler that can be registered.
  //
  // $callback is a callable (string $event, array $callback).
  static function registerEventsUsing($callback) {
    $class = get_called_class();

    call_user_func($callback, 'send', array($class, 'SetDefaultsTo'));
    call_user_func($callback, 'send', array($class, 'SetHeadersTo'));
    call_user_func($callback, 'send', array($class, 'Mail'));

    // standard $bodyEncoding's and $bodyCharset's are hardcoded in EncodeBody();
    // however, extra values can be added by simply prepending new hook to this
    // and checking $email->bodyEncoding/Charset - if they match the hook can
    // modify $body, add $header['Content-Transfer-Encoding'] and set
    // bodyEncoding/Charset to 'plain' - $body is not changed in this case and since
    // the header is already set it will not be replaced by EncodeBody() either.
    call_user_func($callback, 'encode body', array($class, 'EncodeBody'));
    call_user_func($callback, 'encode attachment', array($class, 'DenyAttachment'));
    call_user_func($callback, 'encode attachment', array($class, 'EncodeAttachment'));

    call_user_func($callback, 'transmit', array($class, 'Transmit'));
  }

  /*---------------------------------------------------------------------
  | STANDARD EVENT HANDLERS
  |----------------------------------------------------------------------
  | These methods are used to handle various phases of e-mail dispatching.
  | They can be registered en masse with RegisterEventsUsing().
  |
  | Event chain stops if any of its listeners returns a non-null value.
  |--------------------------------------------------------------------*/

  // Sets default configuration to $email object. See also ::$defaults property.
  static function setDefaultsTo(MiMeil $email) {
    foreach (static::$defaults as $name => $value) {
      $email->$name === null and $email->$name = $value;
    }
  }

  // Adds missing headers to given $email object.
  static function setHeadersTo(MiMeil $email) {
    static $xPriority = array('5 (Lowest)', '3 (Normal)', '1 (Highest)');
    static $importance = array('Low', 'Normal', 'High');

    if (!$email->to and !$email->copyTo and !$email->bccTo) {
      return true;    // No recipients - stop e-mail sending event.
    }

    // Adding missing headers.
    $priority = $email->priority();

    $email->headers += array(
      'MIME-Version'      => static::$mimeVersion,
      'X-Mailer'          => 'MiMeil.php',
      'Date'              => date('r'),
      'X-Priority'        => $xPriority[$priority],
      'Importance'        => $importance[$priority],
    );

    // Adding headers for message recipients.
    $to = $email->to;
    // First "To" recipient is the primary receiver so he's not listed in 'To:'
    // but rather given to the message relay (and mail() function).
    array_shift($to);

    if ($to) {
      $email->headers += array('To' => $email->addressHeader($to));
    }

    if ($email->copyTo) {
      $email->headers += array('Cc' => $email->addressHeader($email->copyTo));
    }

    if ($email->bccTo) {
      $email->headers += array('Bcc' => $email->addressHeader($email->bccTo));
    }

    // Adding 'From:' header. If author is set we can make unique Message-ID with it.
    if ($from = $email->from) {
      $email->headers += array(
        'From'            => static::normAddress($from),
        'Message-ID'      => '<'.time().'-'.static::extractFrom($from).'>',
      );
    }

    if ($returnPath = $email->returnPath) {
      $email->headers += array('Return-Path' => static::normAddress($returnPath));
    }

    if ($delivAddrs = &$email->deliveryNotifications) {
      $delivHdr = &$email->headers['Disposition-Notification-To'];
      $delivHdr = (array) $delivHdr;
      $delivHdr[] = $email->addressHeader($delivAddrs);
    }
  }

  // Initiates message sending on given $email message.
  static function Mail(MiMeil $email) {
    $email->doMail();
  }

  // Encodes message $body as $type. See also ::$bodyEncoding property.
  // It's basically a protection of 7-bit unsafe symbols like Unicode bodies.
  static function encodeBody(&$body, &$type, array &$headers, MiMeil $email) {
    $charset = $email->convertCharsetOf($body);

    $encoding = strtolower($email->bodyEncoding);
    switch ($encoding) {
    case 'base64':
      $body = static::chunkBase64($body);
      break;

    case 'qp':
    case 'quotedprintable':
      $encoding = 'quoted-printable';
    case 'quoted-printable':
      $body = quoted_printable_encode($body);
      break;

    case 'plain':
      $encoding = '8bit';
      break;
    }

    $format = '';
    $email->textBodyFormat and $format = '; format='.$email->textBodyFormat;

    $mime = $type === 'html' ? 'text/html' : 'text/plain';

    $headers += array(
      'Content-Type'              => "$mime; charset=$charset$format",
      'Content-Transfer-Encoding' => $encoding,
    );
  }

  // Removes attachment by setting it to null of its MIME type is not allowed.
  static function denyAttachment(&$att, MiMeil $email) {
    // internal attachments (e.g. mail body) have neither name nor mime.
    if (isset($att['name']) and isset($att['mime'])) {
      $allowed = &$email->allowedAttachments;
      is_array($allowed) or $allowed = $email->parseAllowedAttachments($allowed);

      $ext = strtolower(ltrim(strrchr($att['name'], '.'), '.'));
      $mime = strtolower( $att['mime'] );

      foreach ($allowed as $key => $item) {
        if ($key !== '-') {
          if ($item[0] === '.') {
            $matches = ($ext !== '' and static::matchWildcard($item, $ext, true));
          } else {
            $matches = ($mime and MatchWildcard($item, $mime, true));
          }

          if ( empty($allowed['-']) ? !$matches : $matches ) {
            $att = null;
            return;
          }
        }
      }
    }
  }

  // Encodes attachment data (possibly binary and thus unsafe for wire transfer).
  static function encodeAttachment(&$att, MiMeil $email) {
    if ($mime = &$att['mime']) {
      if (empty($att['isRelated'])) {
        $name = '';
      } else {
        $name = '; name="'.static::mangleUTF8($att['name'], true).'"';
      }

      $att['headers'] += array('Content-Type' => $mime.$name);

      $encHeader = &$att['headers']['Content-Transfer-Encoding'];
      if (empty($encHeader)) {
        if (strtok($mime, '/') === 'text' and
            strtolower($email->bodyEncoding) !== 'base64') {
          $encHeader = 'quoted-printable';
          $att['data'] = quoted_printable_encode($att['data']);
        } else {
          $encHeader = 'base64';
          $att['data'] = static::chunkBase64($att['data']);
        }
      }
    }

    if (!empty( $att['isRelated'] )) {
      // Attachment is part of multipart/related message body - invisible to
      // the recipient in his agent's Attachments list but used to decorate HTML
      // with (backgrounds, illustrations, sounds, etc.). Content-ID is unique
      // identifier used in place of normal URL in src attribute, CSS url(), etc.
      $name = static::mangleUTF8($att['name']);
      $att['headers'] += array('Content-ID' => "<$name>");
    } elseif (isset($att['name'])) {
      $disp = 'attachment; filename="'.static::mangleUTF8($att['name'], true).'"';
      $att['headers'] += array('Content-Disposition' => $disp);
    }
  }

  // Does the actual dispatching of normalized $email. Uses standard PHP mail().
  // Removes unallowed headers. This method (event listener) is typically an apex
  // and the end point of the e-mail transmission.
  static function transmit(&$subject, &$headers, &$body, MiMeil $email) {
    if (!$email->simulateSending) {
      $to = $email->to ? $email->to[0] : $email->from;

      $allowed = array_flip((array) $email->allowedHeaders);
      if ($allowed and empty($allowed['-'])) {
        $headers = array_intersect_key($headers, $allowed);
      } else {
        $headers = array_diff_key($headers, $allowed);
      }

      $email->mailed['status'] =
        mail(static::normAddress($to), static::oneLiner($subject),
             $body, $email->joinHeaders($headers), $email->params);
    }
  }

  /*---------------------------------------------------------------------
  | INSTANCE METHODS
  |--------------------------------------------------------------------*/

  //* $to string, array   - one or more recipient e-mail addresses.
  //* $subject string     - the subject line.
  //* $body string        plain text body,
  //        hash          message bodies in different formats like 'html' and
  //                      'text' (see ::$bodyTypes for details)
  function __construct($to, $subject, $body = array()) {
    $this->to = (array) $to;
    $this->subject = $subject;
    $this->body( is_array($body) ? $body : array('text' => $body) );
    $this->init();
  }

  // Is called immediately after constructing this object and setting initial
  // properties (recipients, subject and bodies). Use it when extending this class.
  //
  //= null    return value is ignored
  protected function init() { }

  // Initiates an event with given arguments ($args is autoconverted to array).
  // Requires that ::$onEvent is set to an external routine. See this property's
  // description for details.
  //
  //= mixed     whatever last called event's handler has returned
  //= null      if there were no handlers or all have returned a === null value
  function fire($event, $args) {
    if ($func = static::$onEvent) {
      return call_user_func($func, $event, is_array($args) ? $args : array($args));
    } else {
      throw new MiMeilError('MiMeil::\$onEvent handler is unassigned.');
    }
  }

  // Gets or sets message priority - an integer from 0 (low) to 2 (high) inclusive.
  //
  //= integer
  //= $this
  function priority($level = null) {
    if ($level === null) {
      return $this->priority;
    } elseif (!is_numeric($level) or $level < 0 or $level > 2) {
      throw new MiMeilError("Invalid Priority() argument - 0-2 expected, $level given.");
    } else {
      $this->prioerity = (int) $level;
      return $this;
    }
  }

  // Gets or sets message body of specific MIME type.
  //
  //? $this->body()
  //    //=> array('html' => '...')
  //    // get all bodies of all MIME types
  //
  //? $this->body('html')
  //    //=> '<html>...'
  //    // get body of text/html MIME or null
  //
  //? $this->body('html')
  //    // the same as above with a static::$bodyTypes shortcut
  //
  //? $this->body('html', '<html>...')
  //    //=> $this
  //    // set body of text/html type
  //
  //? $this->body(array('html' => '...', 'text/plain' => '...'))
  //    //=> $this
  //    // set multiple bodies at once; removes all currently assigned bodies
  //
  function body($newOrType = array(), $body = null) {
    if (is_array($newOrType)) {
      $norm = array();

      foreach ($newOrType as $type => &$item) {
        if (!is_string($item) and $item !== null) {
          $dump = var_export($item, true);
          throw new MiMeilError("Wrong \$new[$type] for MiMeil->body(): $dump");
        }

        $type = strtolower($type);
        isset(static::$bodyTypes[$type]) and $type = static::$bodyTypes[$type];
        $norm[$type] = &$item;
      }

      $this->body = $norm;
      return $this;
    } elseif (is_string($newOrType)) {
      isset(static::$bodyTypes[$newOrType]) and $type = static::$bodyTypes[$newOrType];

      if ($body === null) {
        return $this->body[$newOrType];
      } else {
        $this->body[$newOrType] = $body;
        return $this;
      }
    } else {
      return $this->body;
    }
  }

  // Adds an attachment to this message.
  //
  //* $name string        - attachment name visible to the recipient.
  //* $data string        - raw file data (binary-safe).
  //* $mime string, null  - if unset is determined by $name's extension with ->MimeByExt().
  //* $headers hash of string - headers for multipart MIME message to include for
  //  this file along with standard Content-Disposition and others generated by MiMeil.
  //* $isRelated bool     - if set attachment is considered "related" to the HTML body
  //  of this message (if set, see also $this->skipRelatedAttIfNoHtmlBody). This
  //  means it's a decorative media like illustration or background that isn't listed
  //  under normal Attachments list of the mail agent.
  //
  function attach($name, $data, $mime = null, $headers = array(), $isRelated = false) {
    $mime === null and $mime = $this->mimeByExt(strrchr($name, '.'));
    $mime = strtolower($mime);
    $this->attachments[$name] = compact('name', 'mime', 'data', 'headers', 'isRelated');
    return $this;
  }

  // Shortcut for attaching a file related to this message's HTML body. See Attach().
  //
  //= $this
  function attachRelated($name, $data, $mime = null, $headers = array()) {
    return $this->attach($name, $data, $mime, $headers, true);
  }

  // Gets an attachment by its name. If $field is null returns all information as
  // an array, otherwise returns that particular field which can be one of these:
  // name (string), mime (string), data (string), headers (array), isRelated (bool).
  //
  //= mixed     attachment info according to $field
  //= null      if $name isn't attached
  function attachment($name, $field = 'data') {
    if (isset($this->attachments[$name])) {
      return $field ? $this->attachments[$name][$field] : $this->attachments[$name];
    }
  }

  // If $new is an array clears current attachments and sets them to $new,
  // otherwise returns an array with all current message attachments.
  //
  //= array
  //= $this
  function attachments($new = null) {
    if (is_array($new)) {
      $this->clearAttachments();

      foreach ($new as $name => $att) {
        is_array($att) or $att = array('data' => $att);

        $att += array(
          'name'          => $name,
          'mime'          => null,
          'headers'       => array(),
          'isRelated'     => false,
        );

        if (!isset($att['data'])) {
          throw new MiMeilError('MiMeil->attachments() received an array item without'.
                                " 'data' key: ".var_export($att, true));
        }

        $this->attach($att['name'], $att['data'], $att['mime'], $att['headers'], $att['isRelated']);
      }

      return $this;
    } else {
      return $this->attachments;
    }
  }

  //= array of string       currently attached file names
  function attachmentNames() {
    return array_keys($this->attachments);
  }

  // Removes attachments from this message.
  function clearAttachments() {
    $this->attachments = array();
    return $this;
  }

  /*---------------------------------------------------------------------
  | TRANSMISSION METHODS
  |----------------------------------------------------------------------
  | They handle construction of a valid MIME (or single-body) message,
  | encoding of attachments, boundary generation and so on. Many are called
  | indirectly by MiMeil's static event listeners defined in the beginning.
  |--------------------------------------------------------------------*/

  // Sends a message with current setup of this object. Returns whatever was
  // returned by the mailer event handler - for default Transmit() this is the
  // result of calling mail().
  //
  // After this is called this object's properties are modified depending on
  // the settings (body might be reconverted, attachments removed, etc.).
  //
  //= mixed
  function send() {
    $this->dispatch(false);
    return $this->mailed['status'];
  }

  // Does everything that has to be done in order to send this message but doesn't
  // actually transmit it like send(). You can attach your handler to 'send' event
  // and dump the final message to be delivered into a .eml file for review.
  //
  // After calling this object's properties are modified depending on the settings.
  //
  //= hash      final normalized message info
  function simulateSending() {
    return $this->dispatch(true);
  }

  // Initiates message transmission. Returns whatever was returned by the last
  // 'send' event listener (see also Fire()).
  //
  //= hash      final normalized message info
  protected function dispatch($simulate) {
    $this->simulateSending = $simulate;
    $this->mailed = array('subject' => null, 'headers' => null,
                          'message' => null, 'status' => null);

    $this->fire('send', $this);
    return $this->mailed;
  }

  // Attempts to determine MIME type by file extension $ext based on ::$mimeByExt
  // and if fails returns either ::$defaultMIME ($default is true) or $ext ($default
  // is false). Leading period is removed from $ext, if present.
  //
  // This method isn't static to allow overriding it in child classes.
  //
  //= string
  function mimeByExt($ext, $default = true) {
    $ext = strtolower( ltrim($ext, '.') );
    $default = $default ? static::$defaultMIME : $ext;
    return isset(static::$mimeByExt[$ext]) ? static::$mimeByExt[$ext] : $default;
  }

  // Builds a normalized value for a header like 'To:' or 'Bcc:'.
  // This method is static to allow overriding in child classes.
  //= string
  function addressHeader(array $addresses) {
    foreach ($addresses as &$addr) { $addr = static::normAddress($addr); }
    return join(', ', $addresses);
  }

  // Prepares this message for and initiates the final transmission.
  // Merges all headers and message bodies and attachments into one flat string
  // suitable for passing to the relay.
  //
  // Is meant for calling from 'send' event - see default ->doMail() handler.
  function doMail() {
    $name = $mime = null;

    // Start by joining message bodies into one multipart message (if there are
    // multiple) or a flat message with a body of single type.
    $headers = array();
    $body = $this->prepareBody($this->body);
    $hasHtmlBody = isset($body['html']);
    $data = $this->buildBody($body, $headers);

    // Separate multipart/related attachments from normal.
    $files = $this->attachments;
    $related = array();

      foreach ($files as &$file) {
        if ($file['isRelated']) {
          $related[] = $file;
          $file = null;
        }
      }

    $files = array_filter($files);

      // Build message data for multipart/related attachments.
      if ($related and (!$this->skipRelatedAttIfNoHtmlBody or $hasHtmlBody)) {
        array_unshift($related, compact('name', 'mime', 'data', 'headers'));

        $headers = array();
        $data = $this->buildRelatedAttachments($related, $headers);
      }

      // Build data string for normal attachments along with their headers.
      if ($files) {
        array_unshift($files, compact('name', 'mime', 'data', 'headers'));

        $headers = array();
        $data = $this->buildAttachments($files, $headers);
      }

    // Merge headers produced while building attachment stream with the main message.
    $headers += $this->headers;

    // These values are the only ones fed to the relay - all data concatenated together.
    $this->mailed['subject'] = $subject = static::mangleUTF8($this->subject);
    $this->mailed['headers'] = $headers;
    $this->mailed['message'] = &$data;

    // Give prepared message to the relay.
    return $this->fire('transmit', array(&$subject, &$headers, &$data, $this));
  }

  // Removes message bodies of unallowed MIME types. Constructs plain/text body
  // from text/html if needed.
  //
  //= hash      like 'html' => '<html>...', 'text' => ...
  function &PrepareBody($bodies) {
    $allowed = $this->allowedBodyMIME;

      foreach ($allowed as &$one) { $one = strtolower($one); }
      $allowed = array_flip($allowed);

      foreach (static::$bodyTypes as $mime => $type) {
        isset($allowed[$mime]) and $allowed[$type] = true;
      }

    if ($this->makeTextBodyFromHTML and isset($allowed['html']) and
        !isset($bodies['text']) and isset($bodies['html'])) {
      $bodies['text'] = static::textFromHTML($bodies['html']);
    }

    if (!empty($allowed)) {
      $bodies = array_intersect_key($bodies, $allowed);
    }

    return $bodies;
  }

  // Join bodies into one string using a generated MIME boundary. If there's just
  // one body no boundary is required and thus it's skipped.
  //
  //= string
  function &BuildBody($bodies, array &$headers) {
    if (!$bodies) { throw new MiMeilError('No MiMeil bodies when sending e-mail.'); }

    $result = '';

    if (count($bodies) > 1) {
      $boundary = $this->generateMimeBoundaryFor($bodies);
      $headers += array('Content-Type' => 'multipart/alternative; boundary="'.$boundary.'"');
      $result .= static::$mimeStub.$this->eoln;
    } else {
      $boundary = null;
    }

    $footers = null;

    foreach ($bodies as $type => &$body) {
      // int means "internal" - message body itself (multipart/alternative or related).
      is_int($type) and $type = null;

      $bodyHeaders = array();
      $args = array(&$body, &$type, &$bodyHeaders, $this);
      $this->fire('prepare body', $args);

      if ($type) {
        if (!isset($footers)) {
          $footers = array();
          $this->fire('footer', array(&$footers, $bodies, $this));

          if (!is_array($footers)) {
            throw new MiMeilError('\'footer\' event has returned a non-array".
                                  " &$footers: '.var_export($footers, true));
          }
        }

        $this->setFootersTo($body, $type, $footers);
      }

      // Turn it into a safe representation suitable for wire transfer with
      // base-64, quoted-printable or other encoding.
      $this->fire('encode body', $args);

      if (isset($boundary)) {
        $body = $this->joinHeaders($bodyHeaders, true).$body;
        $result .= "--$boundary{$this->eoln}$body{$this->eoln}";
      } else {
        // There's no boundary if there's just one message body (of one type)
        // so we merge its headers with the global message headers.
        $headers += $bodyHeaders;
        $result = &$body;
      }
    }

    // For multipart messages include the final boundary closing off the last body.
    isset($boundary) and $result .= "--$boundary--".$this->eoln;
    return $result;
  }

  // Appends one or more footers (either HTML or text) to the message body.
  // For HTML they are inserted before the final </body>.
  function setFootersTo(&$body, $type, array $footers) {
    $useHTML = ($type === 'html' and $pos = mb_stripos($body, '</body>'));

    if ($useHTML) {
      $prefix = "{$this->eoln}      <div class=\"%s\">".
                "{$this->eoln}        ";
      $suffix = "{$this->eoln}      </div>";
    } else {
      $prefix = $this->eoln.$this->eoln.'---'.$this->eoln;
      $suffix = '';
    }

    $joined = '';

    foreach ($footers as $footer) {
      $footer = &$footer[$type];

      if (isset($footer)) {
        $footer = (array) $footer;
        $footer += array('', '');
        list($text, $classes) = $footer;

        $classes = "$classes" === '' ? 'mimeil-footer' : "mimeil-footer $classes";
        $pf = $useHTML ? sprintf($prefix, $classes) : $prefix;
        $joined .= $pf.$text.$suffix;
      }
    }

    if ($joined !== '') {
      if ($useHTML) {
        $joined = "{$this->eoln}    <div class=\"footers\">$joined{$this->eoln}    </div>{$this->eoln}  ";
        $body = mb_substr($body, 0, $pos). $joined .mb_substr($body, $pos);
      } else {
        $body .= $joined;
      }
    }
  }

  // Joins all atatchments into one encoded data string using a generated MIME boundary.
  //
  //= string
  function buildAttachments(array $files, array &$headers, $mime = 'multipart/mixed') {
    $boundary = $this->generateMimeBoundaryFor($files);
    $headers += array('Content-Type' => "$mime; boundary=\"$boundary\"");

    $result = '';

    foreach ($files as &$file) {
      $this->fire('encode attachment', array(&$file, $this));
      if (is_array($file)) {
        $result .= "--$boundary{$this->eoln}".
                   $this->joinHeaders($file['headers'], true).$file['data'].$this->eoln;
      }
    }

    $result .= "--$boundary--".$this->eoln;
    return $result;
  }

  // Joins 'related' attachments - used to decorate HTML body but invisible to the
  // recipient in his agent's Attachments list.
  function buildRelatedAttachments(array $files, array &$headers) {
    return $this->buildAttachments($files, $headers, 'multipart/related');
  }

  // Generates a MIME boundary string that is unique for given data or set of data.
  // $bodies can contain strings or arrays containing 'data' keys (used for
  // attachments, see Attachment() for the array format).
  // Does at most 50 attempts after which if no unique string could be generated
  // an exception is thrown.
  //
  //= string
  function generateMimeBoundaryFor(array &$bodies) {
    for ($tries = 0; $tries < 50; ++$tries) {
      $boundary = uniqid(static::$boundaryPrefix, $tries > 0);

      $found = false;

      foreach ($bodies as &$s) {
        $found |= strpos( is_array($s) ? $s['data'] : $s, $boundary ) !== false;
        if ($found) { break; }
      }

      if (!$found) { return $boundary; }
    }

    throw new MiMeilError('Too many tries made to generate e-mail MIME boundary.');
  }

  // Joins headers into a single string using $this->headerEOLN sequence and
  // optionally sorting them beforehand. If $doubleEOLN is set appends an extra
  // line break in the end (useful to immediately append the message data).
  //
  //= string
  function joinHeaders(array $headers, $doubleEOLN = false) {
    $res = '';
    $this->sortHeaders and ksort($headers);

    foreach ($headers as $name => $value) {
      $value = (array) $value;
      foreach ($value as $item) { $res .= "$name: $item{$this->headerEOLN}"; }
    }

    return $res.($doubleEOLN ? $this->headerEOLN : '');
  }

  // Normalizes $this->allowedAttachments value into an array; see Transmit().
  //
  //= array
  function parseAllowedAttachments($allowed) {
    switch ($allowed) {
    case '':  case '-':   case '0':
      return array('-' => true);

    case '1':
      return array('-' => false);

    default:
      $deny = $allowed[0] === '-';
      $allowed = array('-' => $deny);

      foreach (explode(' ', strtolower( ltrim($allowed, '-') )) as $item) {
        $item === '' or $allowed[$item] = true;
      }

      return $allowed;
    }
  }

  // Determines suitable character set for encoding this message $body and does
  // the actual encoding. Depending $this->forceBodyCharset will either use the
  // first specified charset from $this->bodyCharsets or will attempt them in order
  // until it locates the one that could represent all symbols used in $body.
  // If couldn't find such a charset uses UTF-8 instead.
  //
  //= string    final charset name $body is in like 'utf-8' or 'cp1251'
  function convertCharsetOf(&$body) {
    $func = static::$utf8Convertor;
    $func or $func = array($this, 'ConvertUTF8');
    $charset = 'utf-8';

    foreach ($this->bodyCharsets as $newCharset) {
      $converted = call_user_func($func, $newCharset, false, $body, $this);

      $doConvert =
        ($this->forceBodyCharset or
         $body === call_user_func($func, $newCharset, true, $converted, $this));

      if ($doConvert) {
        $body = $converted;
        $charset = $newCharset;
        break;
      }
    }

    return $charset;
  }

  // Builds an RFC-complaint message string using generated $headers, $body and
  // $subject line and 'To:' recipient and saves it in the location specified
  // by $prefix (which can be a directory or a file name - see SaveTo()).
  // If $prefix is null only returns that message string.
  //
  //= string    RFC message
  //= null      if couldn't write the file ($prefix isn't null)
  function saveEML($subject, array $headers, $body, $prefix = null) {
    $headers['To'] = isset($headers['To']) ? ((array) $headers['To']) : array();
    $headers['To'][] = static::normAddress( $this->to[0] );
    $headers['Subject'][] = static::oneLiner($subject);

    $eml = $this->joinHeaders($headers, true).$body;

    if ($prefix === null or static::saveTo($prefix, $eml)) {
      return $eml;
    }
  }
}

if (!function_exists('quoted_printable_encode')) {
  // Reformatted implementation of quoted_printable_encode() for PHP < 5.3 taken from:
  // http://www.php.net/manual/en/function.quoted-printable-encode.php#106078
  function quoted_printable_encode($str, $maxLineLength = 75) {
    $lp = 0;
    $ret = '';
    $hex = '0123456789ABCDEF';
    $length = strlen($str);
    $str_index = 0;

    while ($length--) {
      if ((($c = $str[$str_index++]) == "\015") && ($str[$str_index] == "\012") && $length > 0) {
        $ret .= "\015";
        $ret .= $str[$str_index++];
        $length--;
        $lp = 0;
      } elseif (ctype_cntrl($c) || (ord($c) == 0x7f) || (ord($c) & 0x80) || ($c == '=') ||
                (($c == ' ') && ($str[$str_index] == "\015"))) {
        if (($lp += 3) > $maxLineLength) {
          $ret .= '=';
          $ret .= "\015";
          $ret .= "\012";
          $lp = 3;
        }

        $ret .= '=';
        $ret .= $hex[ord($c) >> 4];
        $ret .= $hex[ord($c) & 0xf];
      } else {
        if ((++$lp) > $maxLineLength) {
          $ret .= '=';
          $ret .= "\015";
          $ret .= "\012";
          $lp = 1;
        }

        $ret .= $c;
      }
    }

    return $ret;
  }
}