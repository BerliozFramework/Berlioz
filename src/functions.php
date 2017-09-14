<?php
/**
 * This file is part of Berlioz framework.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2017 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

// Constants for functions of Berlioz or Berlioz Core
const B_FORM_TYPE_GET = 1;
const B_FORM_TYPE_POST = 2;
const B_FORM_MUST_BE_DEFINED = 1;
const B_FORM_CANNOT_BE_EMPTY = 2;
const B_FORM_LENGTH_CANNOT_BE_0 = 4;
const B_TRUNCATE_LEFT = 1;
const B_TRUNCATE_RIGHT = 2;
const B_TRUNCATE_MIDDLE = 3;


// DATES

/**
 * Return the number of seconds since midnight with hour param in format (H:m:i)
 *
 * @param  string $hour Hour, minutes and second in string format (23:12:54)
 *
 * @return int Number of seconds
 */
function b_time_to_sec(string $hour): int
{
    $hour = explode(":", $hour);

    $time = 0;
    if (isset($hour[0])) {
        $time += $hour[0] * 3600;
    }
    if (isset($hour[1])) {
        $time += $hour[1] * 60;
    }
    if (isset($hour[2])) {
        $time += $hour[2];
    }

    return $time;
}

/**
 * Format time with timestamp in entry
 *
 * @param  int     $time    Timestamp
 * @param  boolean $withSec Format @return with seconds or not
 * @param  string  $sepHour Separator between hours and minutes
 * @param  string  $sepMin  Separator between minutes and seconds
 *
 * @return string Formatted time
 */
function b_sec_to_time(int $time, bool $withSec = true, string $sepHour = ":", string $sepMin = ":"): string
{
    $hours = floor($time / 3600);
    $minutes = floor($time % 3600 / 60);
    $seconds = $time % 60;

    if ($withSec) {
        return sprintf("%02d{$sepHour}%02d{$sepMin}%02d", $hours, $minutes, $seconds);
    } else {
        return sprintf("%02d{$sepHour}%02d", $hours, $minutes);
    }
}

/**
 * Format date/time object or timestamp to the given pattern
 *
 * @param  \DateTime|int $datetime DateTime object or timestamp
 * @param  string        $pattern  Pattern of date result waiting
 * @param  string        $locale   Locale for pattern translation
 *
 * @return string Formatted date with pattern given in param
 */
function b_date_format($datetime, string $pattern = 'dd/MM/yyyy', string $locale = 'en_US'): string
{
    $fmt = new \IntlDateFormatter((string) $locale, \IntlDateFormatter::FULL, \IntlDateFormatter::FULL);
    $fmt->setPattern((string) $pattern);

    if ($datetime instanceof \DateTime) {
        return $fmt->format($datetime);
    } else {
        if (is_numeric($datetime)) {
            return $fmt->format((int) $datetime);
        } else {
            return $fmt->format(strtotime($datetime));
        }
    }
}

/**
 * Calculation of age with birthday
 *
 * @param  string $birthday Date to the computer format (2016-05-19)
 * @param  int    $today    Timestamp of comparison (default now)
 *
 * @return int Age
 */
function b_age(string $birthday, int $today = null): int
{
    list($year, $month, $day) = preg_split('[-.]', $birthday);

    $todayExploded = [];
    $todayExploded['year'] = \date('Y', $today);
    $todayExploded['month'] = \date('n', $today);
    $todayExploded['day'] = \date('j', $today);
    $age = $todayExploded['year'] - $year;

    if ($todayExploded['month'] <= $month) {
        if ($month == $todayExploded['month']) {
            if ($day > $todayExploded['day']) {
                $age--;
            }
        } else {
            $age--;
        }
    }

    return $age;
}

/**
 * Convert date in entry to the database format (computer format)
 *
 * @param  string $date   Date
 * @param  string $format Format of the date
 *
 * @return string|null Formatted date for database (computer format)
 */
function b_db_date($date, $format = "d/m/Y")
{
    $time = null;
    $withTime = false;

    switch ($format) {
        case 'd/m/Y': {
            $time = mktime(0,
                           0,
                           0,
                           substr($date, 3, 2),
                           substr($date, 0, 2),
                           substr($date, 6, 4));
            break;
        }
        case 'd/m/Y H:i:s': {
            $time = mktime(substr($date, 11, 2),
                           substr($date, 14, 2),
                           substr($date, 17, 2),
                           substr($date, 3, 2),
                           substr($date, 0, 2),
                           substr($date, 6, 4));
            $withTime = true;
            break;
        }
        default: {
            $time = \strtotime($date);
        }
    }

    if ($time === false) {
        return null;
    } else {
        return \date('Y-m-d' . ($withTime === true ? ' H:i:s' : ''), $time);
    }
}


// FORMS

/**
 * Protect data passed into form values
 *
 * @param mixed $str String to protect
 *
 * @return string Data protected for inputs
 */
function b_form_protect(mixed $str): string
{
    $str = str_replace('"', '&quot;', $str);

    return $str;
}

/**
 * Control form input data (from $_GET or $_POST)
 *
 * @param string   $varName   Variable name
 * @param int      $type      Type: "B_FORM_TYPE_GET" or "B_FORM_TYPE_POST" constant
 * @param int|null $options   Control option: "B_FORM_MUST_BE_DEFINED", "B_FORM_CANNOT_BE_EMPTY" or
 *                            "B_FORM_LENGTH_CANNOT_BE_0" constant
 * @param int      $minLength Minimum length of string
 * @param int|null $maxLength Maximum length of string
 *
 * @return bool
 * @throws \Berlioz\Core\Exception\BerliozException If not acceptable type of check
 */
function b_form_control(string $varName, int $type, int $options = null, int $minLength = 0, int $maxLength = null): bool
{
    $checkResult = true;

    if (is_null($options)) {
        $options = B_FORM_MUST_BE_DEFINED & B_FORM_CANNOT_BE_EMPTY;
    }

    if (is_array($varName)) {
        foreach ($varName as $value) {
            if (b_form_control($value, $type, $options, $minLength, $maxLength) === false) {
                $checkResult = false;
            }
        }
    } else {
        switch ($type) {
            case B_FORM_TYPE_GET:
                $checkData = &$_GET;
                break;
            case B_FORM_TYPE_POST:
                $checkData = &$_POST;
                break;
            default:
                throw new \Berlioz\Core\Exception\BerliozException('Not good check type, acceptable: "B_FORM_TYPE_GET" or "B_FORM_TYPE_POST" constant');
        }

        if (($options | B_FORM_MUST_BE_DEFINED) == B_FORM_MUST_BE_DEFINED) {
            if (!isset($checkData[$varName])) {
                $checkResult = false;
            }
        }

        if (($options | B_FORM_CANNOT_BE_EMPTY) == B_FORM_CANNOT_BE_EMPTY) {
            if (empty($checkData[$varName])) {
                $checkResult = false;
            }
        }

        if (($options | B_FORM_LENGTH_CANNOT_BE_0) == $options) {
            if (!isset($checkData[$varName]) || mb_strlen($checkData[$varName]) == 0) {
                $checkResult = false;
            }
        }
    }

    return $checkResult;
}

/**
 * Control form input data from $_GET
 *
 * @param string   $varName   Variable name
 * @param int|null $options   Control _b_options: "B_FORM_MUST_BE_DEFINED", "B_FORM_CANNOT_BE_EMPTY" or
 *                            "B_FORM_LENGTH_CANNOT_BE_0" constant
 * @param int      $minLength Minimum length of string
 * @param int|null $maxLength Maximum length of string
 *
 * @return bool
 * @see b_form_control()
 */
function b_form_control_get(string $varName, int $options = null, int $minLength = 0, int $maxLength = null): bool
{
    return b_form_control($varName, B_FORM_TYPE_GET, $options, $minLength, $maxLength);
}

/**
 * Control form input data from $_POST
 *
 * @param string   $varName   Variable name
 * @param int|null $options   Control _b_options: "B_FORM_MUST_BE_DEFINED", "B_FORM_CANNOT_BE_EMPTY" or
 *                            "B_FORM_LENGTH_CANNOT_BE_0" constant
 * @param int      $minLength Minimum length of string
 * @param int|null $maxLength Maximum length of string
 *
 * @return bool
 * @see b_form_control()
 */
function b_form_control_post(string $varName, int $options = null, int $minLength = 0, int $maxLength = null): bool
{
    return b_form_control($varName, B_FORM_TYPE_POST, $options, $minLength, $maxLength);
}


// SECURITY

/**
 * Is secured page ?
 *
 * @return bool
 */
function b_is_secured_page(): bool
{
    if (isset($_SERVER["HTTP_HOST"]) &&
        ((isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] == "on") ||
         (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) ||
         (isset($_SERVER['HTTP_X_FORWARDED_PORT']) && $_SERVER['HTTP_X_FORWARDED_PORT'] == 443))
    ) {
        return true;
    } else {
        return false;
    }
}

/**
 * Get secured page of given url
 *
 * @param string $url Url to parse (default: null = current)
 *
 * @return string
 */
function b_get_secured_page(string $url = null): string
{
    if (is_null($url)) {
        return "https://" . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"];
    } else {
        $urlParsed = parse_url($url);

        return "https://"
               . (isset($urlParsed["user"]) ? "{$urlParsed["user"]}" : "")
               . (isset($urlParsed["pass"]) ? ":{$urlParsed["pass"]}@" : "")
               . (isset($urlParsed["host"]) ? "{$urlParsed["host"]}" : "")
               . (isset($urlParsed["port"]) ? ":{$urlParsed["port"]}" : "")
               . (isset($urlParsed["path"]) ? "{$urlParsed["path"]}" : "")
               . (isset($urlParsed["query"]) ? "?{$urlParsed["query"]}" : "")
               . (isset($urlParsed["anchor"]) ? "#{$urlParsed["anchor"]}" : "");
    }
}


// STRINGS

/**
 * mb_detect_encoding() alternative.
 *
 * This function use iconv functions if classic mb_detect_encoding(...) function failed.
 *
 * @param string          $str           The string being detected
 * @param string[]|string $encoding_list List of character encoding. Encoding order may be specified by array or
 *                                       comma separated list string. Only uses for classic function test.
 * @param bool            $strict        Specifies whether to use the strict encoding detection or not. Default is TRUE.
 *
 * @return string|false
 */
function b_mb_detect_encoding(string $str, $encoding_list = null, bool $strict = true)
{
    // Try classic function
    $encoding = \mb_detect_encoding($str, $encoding_list ?? mb_detect_order(), $strict);

    // Else uses iconv...
    if ($encoding === false) {
        $list = ['UTF-32', 'UTF-8', 'ISO-8859-1', 'ASCII', 'Windows-1251', 'UTF-16LE', 'UTF-16BE'];

        foreach ($list as $item) {
            $sample = @iconv($item, $item, $str);

            if (sha1($sample) == sha1($str)) {
                return $item;
            }
        }
    } else {
        return $encoding;
    }

    return false;
}


/**
 * Detect UTF encoding of string or files.
 *
 * @param string $data       String data or file name
 * @param bool   $dataIsFile If first parameter is file name
 *
 * @return string|null
 */
function b_detect_utf_encoding(string $data, bool $dataIsFile = true)
{
    // Unicode BOM is U+FEFF, but after encoded, it will look like this.
    $UTF32_BIG_ENDIAN_BOM = chr(0x00) . chr(0x00) . chr(0xFE) . chr(0xFF);
    $UTF32_LITTLE_ENDIAN_BOM = chr(0xFF) . chr(0xFE) . chr(0x00) . chr(0x00);
    $UTF16_BIG_ENDIAN_BOM = chr(0xFE) . chr(0xFF);
    $UTF16_LITTLE_ENDIAN_BOM = chr(0xFF) . chr(0xFE);
    $UTF8_BOM = chr(0xEF) . chr(0xBB) . chr(0xBF);

    // Get first characters of file
    if ($dataIsFile === true) {
        $handle = fopen($data, 'rb');
        $data = fread($handle, 5);
        fclose($handle);
    }

    $first2 = substr($data, 0, 2);
    $first3 = substr($data, 0, 3);
    $first4 = substr($data, 0, 4);

    if ($first3 == $UTF8_BOM) {
        return 'UTF-8';
    } else {
        if ($first4 == $UTF32_BIG_ENDIAN_BOM) {
            return 'UTF-32BE';
        } else {
            if ($first4 == $UTF32_LITTLE_ENDIAN_BOM) {
                return 'UTF-32LE';
            } else {
                if ($first2 == $UTF16_BIG_ENDIAN_BOM) {
                    return 'UTF-16BE';
                } else {
                    if ($first2 == $UTF16_LITTLE_ENDIAN_BOM) {
                        return 'UTF-16LE';
                    } else {
                        return null;
                    }
                }
            }
        }
    }
}


/**
 * Remove the BOM of UTF string or files.
 *
 * @param string $data       String data or file name
 * @param string $encoding   Encoding (default automatic detection)
 * @param bool   $dataIsFile If first parameter is file name
 *
 * @return string Data without bom
 */
function b_remove_bom(string $data, string &$encoding = null, bool $dataIsFile = false): string
{
    if (is_null($encoding)) {
        $encoding = b_mb_detect_encoding($data, $dataIsFile);
    }

    if ($dataIsFile === true) {
        $data = file_get_contents($data);
    }

    if (!is_null($encoding)) {
        switch ($encoding) {
            case 'UTF-8':
                $data = substr($data, 3);
                break;
            case 'UTF-32BE':
            case 'UTF-32LE':
                $data = substr($data, 4);
                break;
            case 'UTF-16BE':
            case 'UTF-16LE':
                $data = substr($data, 2);
                break;
        }
    }

    return $data;
}

/**
 * Truncate string.
 *
 * @param string $str          String
 * @param int    $nbCharacters Number of characters
 * @param int    $where        Where option: B_TRUNCATE_LEFT, B_TRUNCATE_MIDDLE or B_TRUNCATE_RIGHT
 * @param string $separator    Separator string
 *
 * @return string
 */
function b_truncate(string $str, int $nbCharacters = 128, int $where = B_TRUNCATE_RIGHT, string $separator = '...')
{
    $str = html_entity_decode($str);

    if (mb_strlen(trim($str)) > 0 && mb_strlen(trim($str)) > $nbCharacters) {
        switch ($where) {
            case B_TRUNCATE_LEFT:
                $str = $separator . ' ' . mb_substr($str, mb_strlen($str) - $nbCharacters, mb_strlen($str));
                break;
            case B_TRUNCATE_RIGHT:
                $str = mb_substr($str, 0, $nbCharacters) . ' ' . $separator;
                break;
            case B_TRUNCATE_MIDDLE:
                $str = mb_substr($str, 0, ceil($nbCharacters / 2)) . ' ' . $separator . ' ' . mb_substr($str, mb_strlen($str) - floor($nbCharacters / 2), mb_strlen($str));
                break;
        }
    }

    return $str;
}

/**
 * Remove entities from string
 *
 * @param string $str
 *
 * @return string
 */
function b_remove_entities(string $str): string
{
    $encoding = mb_detect_encoding($str);
    $str = mb_convert_encoding($str, mb_internal_encoding(), ($encoding === false ? "ASCII" : $encoding));

    $entities = ['á', 'Á', 'â', 'Â', 'à', 'À', 'å', 'Å', 'ã', 'Ã', 'ä', 'Ä', 'æ', 'Æ', 'ç', 'Ç', 'é', 'É', 'ê', 'Ê', 'è', 'È', 'ë', 'Ë', 'í', 'Í', 'î', 'Î', 'ì', 'Ì', 'ï', 'Ï', 'ñ', 'Ñ', 'ó', 'Ó', 'ô', 'Ô', 'ò', 'Ò', 'ø', 'Ø', 'õ', 'Õ', 'ö', 'Ö', 'œ', 'Œ', 'š', 'Š', 'ú', 'Ú', 'û', 'Û', 'ù', 'Ù', 'ü', 'Ü', 'ý', 'Ý', 'ÿ', 'Ÿ'];
    $noEntities = ['a', 'A', 'a', 'A', 'a', 'A', 'a', 'A', 'a', 'A', 'a', 'A', 'ae', 'AE', 'c', 'C', 'e', 'E', 'e', 'E', 'e', 'E', 'e', 'E', 'i', 'I', 'i', 'I', 'i', 'I', 'i', 'I', 'n', 'N', 'o', 'O', 'o', 'O', 'o', 'O', 'o', 'O', 'o', 'O', 'o', 'O', 'oe', 'OE', 's', 'S', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'y', 'Y', 'y', 'Y'];

    // Convert encoding
    $entities = array_map(function ($value) {
        return mb_convert_encoding($value, mb_internal_encoding(), 'UTF-8');
    }, $entities);

    return str_replace($entities, $noEntities, $str);
}

/**
 * Minify HTML string.
 *
 * @param string $str
 *
 * @return string
 * @link https://stackoverflow.com/a/5324014
 */
function b_minify_html(string $str): string
{
    // Save and change PHP configuration value
    $oldPcreRecursionLimit = ini_get('pcre.recursion_limit');
    if (PHP_OS == 'WIN') {
        ini_set('pcre.recursion_limit', '524');
    } else {
        ini_set('pcre.recursion_limit', '16777');
    }

    $regex = <<<EOT
%# Collapse whitespace everywhere but in blacklisted elements.
(?>             # Match all whitespans other than single space.
  [^\S ]\s*     # Either one [\t\r\n\f\v] and zero or more ws,
| \s{2,}        # or two or more consecutive-any-whitespace.
) # Note: The remaining regex consumes no text at all...
(?=             # Ensure we are not in a blacklist tag.
  [^<]*+        # Either zero or more non-"<" {normal*}
  (?:           # Begin {(special normal*)*} construct
    <           # or a < starting a non-blacklist tag.
    (?!/?(?:textarea|pre|script)\b)
    [^<]*+      # more non-"<" {normal*}
  )*+           # Finish "unrolling-the-loop"
  (?:           # Begin alternation group.
    <           # Either a blacklist start tag.
    (?>textarea|pre|script)\b
  | \z          # or end of file.
  )             # End alternation group.
)  # If we made it here, we are not in a blacklist tag.
%Six
EOT;

    // Reset PHP configuration value
    ini_set('pcre.recursion_limit', $oldPcreRecursionLimit);

    return preg_replace($regex, ' ', $str);
}

/**
 * Treat string for url
 *
 * @param string $str
 *
 * @return string
 */
function b_strtouri(string $str): string
{
    $str = trim($str);
    $str = html_entity_decode($str);
    $str = b_remove_entities($str);
    $str = str_replace(['\'', '’', ' ', '/', '.'], '-', $str);
    $str = str_replace(['@', '*', '\\', '&', '^', '~', '$', '¤', '£', '¨', '%', '§', ',', '?', ';', ':', '!', '<', '>', '#', '{', '\'', '(', '[', '|', '`', ')', ']', '°', '=', '}', '²', '+'], '', $str);
    $str = str_replace(['-€', '€'], '-euros', $str);
    $str = strtolower($str);
    $str = preg_replace('/[^a-z0-9\-\.]/', '-', $str);

    while (strpos($str, '--') !== false) {
        $str = str_replace('--', '-', $str);
    }

    if (substr($str, -1) == '-') {
        $str = substr($str, 0, -1);
    }
    if (substr($str, 0, 1) == '-') {
        $str = substr($str, 1);
    }

    return $str;
}

/**
 * Generate an hazard string
 *
 * @param int  $length                    Length of string
 * @param bool $withNumber                String with numbers (default: true)
 * @param bool $onlyLowerCase             String with lower cases characters (default: false)
 * @param bool $withSpecialCharacter      String with specials characters (default: false)
 * @param bool $needAllRequiredParameters Need all required parameters (default: true)
 *
 * @return string
 */
function b_hazard_string(int $length = 12, bool $withNumber = true, bool $onlyLowerCase = false, bool $withSpecialCharacter = false, bool $needAllRequiredParameters = true): string
{
    // Defaults
    $characters_lowercase = 'abcdefghkjmnopqrstuvwxyz';
    $characters_uppercase = 'ABCDEFGHKJMNOPQRSTUVWXYZ';
    $numbers = '0123456789';
    $specialCharacters = '~!@#$%^&*()-_=+[]{};:,.<>/?';

    // Make global source
    $source = $characters_lowercase . ($onlyLowerCase === false ? $characters_uppercase : '') . ($withNumber === true ? $numbers : '') . ($withSpecialCharacter === true ? $specialCharacters : '');

    $length = abs(intval($length));
    $n = strlen($source);
    $str = [];

    // If all parameters are required
    if ($needAllRequiredParameters === true) {
        // Lower case
        $str[] = $characters_lowercase{mt_rand(1, strlen($characters_lowercase)) - 1};
        $length--;

        // Upper case
        if ($onlyLowerCase === false) {
            $str[] = $characters_uppercase{mt_rand(1, strlen($characters_uppercase)) - 1};
            $length--;
        }

        // Numbers
        if ($withNumber === true) {
            $str[] = $numbers{mt_rand(1, strlen($numbers)) - 1};
            $length--;
        }

        // Special characters
        if ($withSpecialCharacter === true) {
            $str[] = $specialCharacters{mt_rand(1, strlen($specialCharacters)) - 1};
            $length--;
        }
    }

    // Generate the main string
    for ($i = 0; $i < $length; $i++) {
        $str[] = $source{mt_rand(1, $n) - 1};
    }

    // Shuffle the string
    shuffle($str);

    return implode('', $str);
}

/**
 * Valid email format.
 *
 * @param string $email
 *
 * @return bool
 */
function b_valid_email(string $email): bool
{
    $test = strtr($email, ' ', '#');
    $test = trim(strtr($test,
                       'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789.@_-',
                       '                                                                  '));

    if (strlen($test) > 0) {
        return false;
    } else {
        if (preg_match("#^[A-z0-9][\w.-]*@[A-z0-9][\w\-\.]+\.[A-z0-9]{2,6}$#", $email)) {
            return true;
        } else {
            return false;
        }
    }
}

/**
 * Extract account part of email
 *
 * @param string $email
 *
 * @return string
 */
function b_email_account(string $email): string
{
    $atPos = strrpos($email, '@');

    if ($atPos === false) {
        $account = $email;
    } else {
        $account = substr($email, 0, $atPos);
    }

    return $account;
}

/**
 * Extract domain of email
 *
 * @param string $email
 *
 * @return string
 */
function b_email_domain(string $email): string
{
    $atPos = strrpos($email, '@');

    if ($atPos === false) {
        $domain = $email;
    } else {
        $domain = substr($email, $atPos + 1);
    }

    return $domain;
}

/**
 * Surrounds paragraphs with "P" HTML tag and inserts HTML line breaks before all newlines; in a string
 *
 * @param string $str
 *
 * @return string
 */
function b_nl2p(string $str): string
{
    $str = preg_split('/(\r?\n){2,}/', $str);
    array_walk(
        $str,
        function (&$str) {
            $str = '<p>' . nl2br(trim($str)) . '</p>';
        });

    return implode("\n", $str);
}


// OBJECTS

/**
 * Get property value to an object when we don't know getter format
 *
 * @param mixed  $object   Object
 * @param string $property Property name
 * @param bool   $exists   If property exists (passed by reference)
 *
 * @return mixed
 */
function b_property_get($object, string $property, &$exists = null)
{
    if (is_object($object)) {
        // If property is public
        if (isset($object->$property)) {
            $exists = true;

            return $object->$property;
        } else {
            // Format Camel Case : getMyProperty(...)
            $getterCamelCase = 'get' .
                               preg_replace_callback(
                                   '/(?:^|_)(.?)/',
                                   function ($matches) {
                                       return mb_strtoupper($matches[0]);
                                   }, $property);

            if (method_exists($object, $getterCamelCase)) {
                $exists = true;

                return call_user_func([$object, $getterCamelCase]);
            } else {
                // Format : get_myproperty(...)
                $setter = 'get_' . $property;

                if (method_exists($object, $setter)) {
                    $exists = true;

                    return call_user_func([$object, $setter]);
                } else {
                    $exists = false;

                    return null;
                }
            }
        }
    } else {
        $exists = false;

        return null;
    }
}

/**
 * Set property value to an object when we don't know setter format
 *
 * @param mixed  $object   Object
 * @param string $property Property name
 * @param mixed  $value    Property value
 *
 * @return bool
 */
function b_property_set($object, string $property, $value = null): bool
{
    $bReturn = true;

    if (is_object($object)) {
        // If property is public
        if (isset($object->$property)) {
            $object->$property = $value;
        } else {
            // Format Camel Case : setMyProperty(...)
            $setterCamelCase = 'set' .
                               preg_replace_callback(
                                   '/(?:^|_)(.?)/',
                                   function ($matches) {
                                       return mb_strtoupper($matches[0]);
                                   }, $property);

            if (method_exists($object, $setterCamelCase)) {
                call_user_func([$object, $setterCamelCase], $value);
            } else {
                // Format : set_myproperty(...)
                $setter = 'set_' . $property;

                if (method_exists($object, $setter)) {
                    call_user_func([$object, $setter], $value);
                } else {
                    $bReturn = false;
                }
            }
        }
    } else {
        $bReturn = false;
    }

    return $bReturn;
}


// ARRAYS

/**
 * Traverse array with keys
 *
 * @param mixed         $mixed    Source
 * @param array         $keys     Keys
 * @param bool          $exists   If element exists (passed by reference)
 * @param callable|null $callback Callback
 *
 * @return mixed|null
 * @throws \Berlioz\Core\Exception\BerliozException if first argument is not a traversable data
 */
function b_array_traverse($mixed, array $keys, &$exists = null, callable $callback = null)
{
    if (!(is_array($mixed) || $mixed instanceof \Traversable || $mixed instanceof \Berlioz\Core\Entity\Entity)) {
        throw new \Berlioz\Core\Exception\BerliozException('First argument must be a traversable mixed data');
    }

    $key = array_shift($keys);

    // Array or \Traversable ?
    if (is_array($mixed) || $mixed instanceof \Traversable) {
        if (isset($mixed[$key])) {
            if (count($keys) > 0) {
                return b_array_traverse($mixed[$key], $keys, $exists, $callback);
            } else {
                $exists = true;

                if (!is_null($callback)) {
                    $callback($mixed[$key]);
                }

                return $mixed[$key];
            }
        } else {
            $exists = false;

            return null;
        }
    } else {
        // Object ?
        if (is_object($mixed)) {
            $subExists = false;

            if (($result = b_property_get($mixed, $key, $subExists)) && $subExists === true) {
                if (count($keys) > 0) {
                    return b_array_traverse($result, $keys, $exists, $callback);
                } else {
                    $exists = true;

                    if (!is_null($callback)) {
                        $callback($result);
                    }

                    return $result;
                }
            } else {
                $exists = false;

                return null;
            }
        } else {
            $exists = false;

            return null;
        }
    }
}

/**
 * Merge two or more arrays recursively
 *
 * Difference between native array_merge_recursive() is that b_array_merge_recursive() do not merge strings values into
 * an array.
 *
 * @param array   $arraySrc  Array source
 * @param array[] ...$arrays Arrays to merge
 *
 * @return array
 */
function b_array_merge_recursive(array $arraySrc, array ...$arrays)
{
    foreach ($arrays as $array) {
        foreach ($array as $key => $value) {
            if (!isset($arraySrc[$key])) {
                $arraySrc[$key] = $value;
            } else {
                if (is_array($arraySrc[$key])) {
                    $arraySrc[$key] = b_array_merge_recursive($arraySrc[$key], $value);
                } else {
                    $arraySrc[$key] = $value;
                }
            }
        }
    }

    return $arraySrc;
}


// IMAGE

/**
 * Calculate a gradient destination color
 *
 * @param string $color        Source color (hex)
 * @param string $colorToAdd   Color to add (hex)
 * @param float  $percentToAdd Percent to add
 *
 * @return string
 */
function b_gradient_color(string $color, string $colorToAdd, float $percentToAdd)
{
    $cColor = $color;

    if (mb_strlen($color) == 7 && substr($color, 0, 1) == "#"
        && mb_strlen($colorToAdd) == 7 && substr($colorToAdd, 0, 1) == "#") {
        // RGB of color
        $rgb1[0] = hexdec(substr($color, 1, 2));
        $rgb1[1] = hexdec(substr($color, 3, 2));
        $rgb1[2] = hexdec(substr($color, 5, 2));
        $rgb_final = $rgb1;
        // RGB of color to add
        $rgb2[0] = hexdec(substr($colorToAdd, 1, 2));
        $rgb2[1] = hexdec(substr($colorToAdd, 3, 2));
        $rgb2[2] = hexdec(substr($colorToAdd, 5, 2));

        // Add percent
        for ($i = 0; $i < 3; $i++) {
            if ($rgb1[$i] < $rgb2[$i]) {
                $rgb_final[$i] = round(((max($rgb1[$i], $rgb2[$i]) - min($rgb1[$i], $rgb2[$i])) / 100) * $percentToAdd + min($rgb1[$i], $rgb2[$i]));
            } else {
                $rgb_final[$i] = round(max($rgb1[$i], $rgb2[$i]) - ((max($rgb1[$i], $rgb2[$i]) - min($rgb1[$i], $rgb2[$i])) / 100) * $percentToAdd);
            }
        }

        $cColor = "#" . sprintf("%02s", dechex($rgb_final[0])) . sprintf("%02s", dechex($rgb_final[1])) . sprintf("%02s", dechex($rgb_final[2]));
    }

    return $cColor;
}

/**
 * Calculate sizes with new given width and height
 *
 * @param int  $newWidth       New width
 * @param int  $newHeight      New height
 * @param int  $originalWidth  Original width
 * @param int  $originalHeight Original height
 * @param bool $evenIfMoreBig  Even if new sizes is more big than original sizes (default: true)
 * @param bool $fillSpace      Fill space (default: false)
 *
 * @return array
 */
function b_img_size(int $newWidth = null, int $newHeight = null, int $originalWidth, int $originalHeight, bool $evenIfMoreBig = false, bool $fillSpace = false)
{
    $size = [];
    $calculateSize =
        function ($newWidth, $newHeight, $originalWidth, $originalHeight) {
            $size = ["width" => 0, "height" => 0];

            if (is_null($newWidth)) {
                $size["height"] = $newHeight;
                $size["width"] = \ceil($newHeight * $originalWidth / $originalHeight);
            } else {
                $size["width"] = $newWidth;
                $size["height"] = \ceil($newWidth * $originalHeight / $originalWidth);
            }

            return $size;
        };

    if ((!is_null($newWidth) && $newWidth < $originalWidth)
        || (!is_null($newHeight) && $newHeight < $originalHeight)
        || $evenIfMoreBig === true) {
        if (!is_null($newWidth) && !is_null($newHeight)) {
            $size = $calculateSize($newWidth, null, $originalWidth, $originalHeight);

            if (($fillSpace === true && ($size["height"] < $newHeight || $size["width"] < $newWidth))
                || ($fillSpace === false && ($size["height"] > $newHeight || $size["width"] > $newWidth))) {
                $size = $calculateSize(null, $newHeight, $originalWidth, $originalHeight);
            }
        } else {
            if (is_null($newWidth)) {
                $size = $calculateSize(null, $newHeight, $originalWidth, $originalHeight);
            } else {
                if (is_null($newHeight)) {
                    $size = $calculateSize($newWidth, null, $originalWidth, $originalHeight);
                }
            }
        }
    } else {
        $size = ["width" => $originalWidth, "height" => $originalHeight];
    }

    return $size;
}

/**
 * Resize image
 *
 * @param string|resource $img       File name or image resource
 * @param int             $newWidth  New width
 * @param int             $newHeight New height
 * @param bool            $force     Force resizing (default: false)
 *
 * @return resource
 * @throws \Berlioz\Core\Exception\BerliozException if not valid input resource or file name
 */
function b_img_resize($img, int $newWidth = null, int $newHeight = null, bool $force = false)
{
    // Get current size
    if (is_string($img)) {
        list($width, $height, $type) = \getimagesize($img);
    } else {
        if (is_resource($img)) {
            $width = imagesx($img);
            $height = imagesy($img);
            $type = 'RESOURCE';
        } else {
            throw new \Berlioz\Core\Exception\BerliozException('Need valid resource of image or file name');
        }
    }

    // Calculate new size
    if ($force === false) {
        $newSize = b_img_size($newWidth, $newHeight, $width, $height);
        $newWidth = $newSize["width"];
        $newHeight = $newSize["height"];
    }

    // Create image thumb
    $thumb = imagecreatetruecolor($newWidth, $newHeight);
    switch ($type) {
        case 'RESOURCE':
            $source = $img;
            break;
        case \IMAGETYPE_PNG:
            $source = imagecreatefrompng($img);
            imagealphablending($thumb, false);
            imagesavealpha($thumb, true);
            break;
        case \IMAGETYPE_GIF:
            $source = imagecreatefromgif($img);
            break;
        default:
            $source = imagecreatefromjpeg($img);
    }

    // Resizing
    imagecopyresampled($thumb, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

    // Erase source resource
    imagedestroy($source);

    return $thumb;
}

/**
 * Resize support of image
 *
 * @param string|resource $img       File name or image resource
 * @param int             $newWidth  New width
 * @param int             $newHeight New height
 *
 * @return resource
 * @throws \Berlioz\Core\Exception\BerliozException if not valid input resource or file name
 */
function b_img_support($img, int $newWidth = null, int $newHeight = null)
{
    // Get current size
    if (is_string($img)) {
        list($width, $height, $type) = \getimagesize($img);
    } else {
        if (is_resource($img)) {
            $width = imagesx($img);
            $height = imagesy($img);
            $type = 'RESOURCE';
        } else {
            throw new \Berlioz\Core\Exception\BerliozException('Need valid resource of image or file name');
        }
    }

    // Treatment
    switch ($type) {
        case 'RESOURCE':
            $source = $img;
            break;
        case \IMAGETYPE_PNG:
            $source = imagecreatefrompng($img);
            imagealphablending($source, false);
            imagesavealpha($source, true);
            break;
        case \IMAGETYPE_GIF:
            $source = imagecreatefromgif($img);
            break;
        default:
            $source = imagecreatefromjpeg($img);
    }

    // Defaults sizes
    if (is_null($newWidth)) {
        $newWidth = $width;
    }
    if (is_null($newHeight)) {
        $newHeight = $height;
    }

    // Calculate position
    $dest_x = ($newWidth - $width) / 2;
    $dest_y = ($newHeight - $height) / 2;

    if ($newWidth == $width && $newHeight == $height) {
        $destination = $source;
    } else {
        $destination = imagecreatetruecolor($newWidth, $newHeight);

        // Set background to white
        $white = imagecolorallocate($destination, 255, 255, 255);
        imagefill($destination, 0, 0, $white);

        // Resizing
        imagecopyresampled($destination, $source, $dest_x, $dest_y, 0, 0, $width, $height, $width, $height);

        // Erase source resource
        imagedestroy($source);
    }

    return $destination;
}


// NUMERIC

/**
 * Get size in bytes from ini conf file.
 *
 * @param string $size
 *
 * @return int
 */
function b_size_from_ini(string $size): int
{
    switch (mb_strtolower(substr($size, -1))) {
        case 'k':
            return (int) substr($size, 0, -1) * 1024;
        case 'm':
            return (int) substr($size, 0, -1) * 1024 * 1024;
        case 'g':
            return (int) substr($size, 0, -1) * 1024 * 1024 * 1024;
        default:
            return intval($size);
    }
}


/**
 * Get a human see file size.
 *
 * @param int $size
 * @param int $precision
 *
 * @return string
 */
function b_human_file_size(int $size, int $precision = 2): string
{
    $value = 0;
    $unit = "octets";

    if (is_numeric($size)) {
        // Pio
        if (($size / pow(1024, 5)) >= 1) {
            $value = round($size / pow(1024, 5), $precision);
            $unit = 'Po';
        } // Tio
        else {
            if (($size / pow(1024, 4)) >= 1) {
                $value = round($size / pow(1024, 4), $precision);
                $unit = 'To';
            } // Gio
            else {
                if (($size / pow(1024, 3)) >= 1) {
                    $value = round($size / pow(1024, 3), $precision);
                    $unit = 'Go';
                } // Mio
                else {
                    if (($size / pow(1024, 2)) >= 1) {
                        $value = round($size / pow(1024, 2), $precision);
                        $unit = 'Mo';
                    } // Kio
                    else {
                        if (($size / pow(1024, 1)) >= 1) {
                            $value = round($size / pow(1024, 1), $precision);
                            $unit = 'Ko';
                        } // octets
                        else {
                            $value = $size;
                            $unit = 'octets';
                        }
                    }
                }
            }
        }
    }

    return sprintf("%s %s", $value, $unit);
}