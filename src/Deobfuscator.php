
<?php

/**
 * PHP Antimalware Scanner.
 *
 * @author Marco Cesarato <cesarato.developer@gmail.com>
 * @license http://opensource.org/licenses/gpl-3.0.html GNU Public License
 *
 * @see https://github.com/marcocesarato/PHP-Antimalware-Scanner
 */

namespace AMWScan;

/**
 * Class Deobfuscator.
 */
class Deobfuscator
{
    /**
     * Full code.
     *
     * @var string
     */
    private $fullCode = '';

    /**
     * Deobfuscate code.
     *
     * @param $str
     *
     * @return bool|mixed|string|string[]|null
     */
    public function deobfuscate($str)
    {
        $str = str_replace([".''", "''.", '.""', '"".'], '', $str);

        $this->fullCode = $str;

        $type = $this->getObfuscateType($str);
        if (empty($type)) {
            $strDecoded = $this->decode($str);
            $type = $this->getObfuscateType($strDecoded);
            if (!empty($type)) {
                $str = $strDecoded;
            }
        }

        if (in_array('globals', $type, true)) {
            $str = $this->deobfuscateBitrix($str);
        }

        if (in_array('eval', $type, true)) {
            $str = $this->deobfuscateEval($str);
        }

        if (in_array('als', $type, true)) {
            $str = $this->deobfuscateAls($str);
        }

        if (in_array('lockit', $type, true)) {
            $str = $this->deobfuscateLockit($str);
        }

        if (in_array('fopo', $type, true)) {
            $str = $this->deobfuscateFopo($str);
        }

        if (in_array('byterun', $type, true)) {
            $str = $this->deobfuscateByterun($str);
        }

        if (in_array('urldecode_globals', $type, true)) {
            $str = $this->deobfuscateUrldecode($str);
        }

        return $str;
    }

    /**
     * Decode.
     *
     * @param $code
     *
     * @return mixed
     */
    public function decode($code)
    {
        $matchesPhp = CodeMatch::getCode($code);
        foreach ($matchesPhp as $matchPhp) {
            $str = preg_replace("/(<\?(php)?)(.*?)(?!\B\"[^\"]*)(\?>|$)(?![^\"]*\"\B)/si", '$1$3$4', @$matchPhp[0]);

            // Convert dec
            $str = preg_replace_callback('/\\\\(0\d{2})/', function ($match) {
                return chr(hexdec($match[1]));
            }, $str);

            // Convert oct
            $str = preg_replace_callback('/\\\\(1\d{2})/', function ($match) {
                return chr(octdec($match[1]));
            }, $str);

            // Convert hex
            $str = preg_replace_callback('/\\\\x[A-Fa-f0-9]{2}/', function ($match) {
                return @hex2bin(str_replace('\\x', '', $match[0]));
            }, $str);

            // Convert chr
            $str = preg_replace_callback('/(chr|mb_chr)[\s]*\((([\s\(\)]*[\d\.]+[\s\(\)]*[\*\/\-\+]?[\s\(\)]*)+)\)/', function ($match) {
                $calc = (int)$this->calc(trim($match[2]));
                $result = $match[1]($calc);

                return "'" . $result . "'";
            }, $str);

            // Remove point between two strings ex. "ev"."al" to "eval"
            $str = preg_replace("/(\\'|\\\")[\s\r\n]*\.[\s\r\n]*('|\")/", '', $str);
            // Remove multiple spaces
            $str = preg_replace("/([\s]+)/", ' ', $str);

            // Decode strings
            $decoders = [
                'str_rot13',
                'gzinflate',
                'base64_decode',
                'rawurldecode',
                'gzuncompress',
                'strrev',
                'convert_uudecode',
                'urldecode',
            ];
            $patternDecoder = [];
            foreach ($decoders as $decoder) {
                $patternDecoder[] = preg_quote($decoder, '/');
            }
            $lastMatch = null;
            $recursiveLoop = true;
            do {
                // Check decode functions
                $regexPattern = '/((' . implode('|', $patternDecoder) . ')[\s\r\n]*\((([^()]|(?R))*)?\))/';
                preg_match($regexPattern, $str, $match);

                // Get value inside function
                if ($recursiveLoop && isset($match[0]) && preg_match('/(\((?:\"|\\\')(([^\\\'\"]|(?R))*?)(?:\"|\\\')\))/', $match[0], $encodedMatch)) {
                    $value = $encodedMatch[2];
                    $decodersFound = array_reverse(explode('(', $match[0]));
                    foreach ($decodersFound as $decoder) {
                        if (in_array($decoder, $decoders, true) && (is_string($value) && !empty($value))) {
                            $value = @$decoder($value); // Decode
                        }
                    }
                    if (is_string($value) && !empty($value)) {
                        $value = str_replace('"', "'", $value);
                        $value = '"' . $value . '"';
                        $str = str_replace($match[0], $value, $str);
                    } else {
                        $recursiveLoop = false;
                    }
                } else {
                    $recursiveLoop = false;
                }
            } while (!empty($match[0]) && $recursiveLoop);

            $code = str_replace($matchPhp, $str, $code);
        }

        return $code;
    }

    /**
     * Deobfuscate bitrix.
     *
     * @param $str
     *
     * @return string|string[]|null
     */
    private function deobfuscateBitrix($str)
    {
        $res = $str;
        $funclist = [];
        $res = preg_replace("|[\"']\s*\.\s*['\"]|mi", '', $res);
        $res = preg_replace_callback('~(?:min|max)\(\s*\d+[\,\|\s\|+\|\-\|\*\|\/][\d\s\.\,\+\-\*\/]+\)~m', function ($expr) {
            return $this->calc($expr);
        }, $res);
        $res = preg_replace_callback('|(round\((.+?)\))|smi', function ($matches) {
            return round($matches[2]);
        }, $res);
        $res = preg_replace_callback('|base64_decode\(["\'](.*?)["\']\)|smi', function ($matches) {
            return "'" . base64_decode($matches[1]) . "'";
        }, $res);

        $res = preg_replace_callback('|["\'](.*?)["\']|sm', function ($matches) {
            $temp = base64_decode($matches[1]);
            if (base64_encode($temp) === $matches[1] && preg_match('#^[ -~]*$#', $temp)) {
                return "'" . $temp . "'";
            }

            return "'" . $matches[1] . "'";
        }, $res);

        if (preg_match_all('|\$(?:\{(?:"|\'))?GLOBALS(?:(?:"|\')\})?\[(?:"|\')(.+?)(?:"|\')\]\s*=\s*Array\((.+?)\);|smi', $res, $founds, PREG_SET_ORDER)) {
            foreach ($founds as $found) {
                $varname = $found[1];
                $funclist[$varname] = explode(',', $found[2]);
                $funclist[$varname] = array_map(function ($value) {
                    return trim($value, "'");
                }, $funclist[$varname]);

                $res = preg_replace_callback('|\$(?:\{(?:"|\'))?GLOBALS(?:(?:"|\')\})?\[\'' . $varname . '\'\]\[(\d+)\]|smi', function ($matches) use ($varname, $funclist) {
                    return $funclist[$varname][$matches[1]];
                }, $res);
            }
        }

        if (preg_match_all('|function\s*(\w{1,60})\(\$\w+\){\$\w{1,60}\s*=\s*Array\((.{1,30000}?)\);[^}]+}|smi', $res, $founds, PREG_SET_ORDER)) {
            foreach ($founds as $found) {
                $strlist = explode(',', $found[2]);
                $res = preg_replace_callback('|' . $found[1] . '\((\d+)\)|smi', function ($matches) use ($strlist) {
                    return $strlist[$matches[1]];
                }, $res);
            }
        }

        $res = preg_replace('~<\?(php)?\s*\?>~mi', '', $res);
        if (preg_match_all('~<\?\s*function\s*(_+(.{1,60}?))\(\$[_0-9]+\)\{\s*static\s*\$([_0-9]+)\s*=\s*(true|false);.{1,30000}?\$\3=array\((.*?)\);\s*return\s*base64_decode\(\$\3~smi', $res, $founds, PREG_SET_ORDER)) {
            foreach ($founds as $found) {
                $strlist = explode("',", $found[5]);
                $res = preg_replace_callback('|' . $found[1] . '\((\d+)\)|sm', function ($matches) use ($strlist) {
                    return $strlist[$matches[1]] . "'";
                }, $res);
            }
        }

        return $res;
    }

    /**
     * Calc.
     *
     * @param $expr
     *
     * @return mixed
     */
     private function calc($expr, $level = 0)
     {
		if($level>100000) return "";
        if (is_array($expr)) {
            $expr = $expr[0];
        }
        preg_match('~(min|max)?\(([^\)]+)\)~mi', $expr, $exprArr);
        if (!empty($exprArr[1]) && ($exprArr[1] === 'min' || $exprArr[1] === 'max')) {
            return $exprArr[1](explode(',', $exprArr[2]));
        }

        preg_match_all('~([\d\.]+)([\*\/\-\+])?~', $expr, $exprArr);
        if (!empty($exprArr[1]) && !empty($exprArr[2])) {
            if (in_array('*', $exprArr[2], true)) {
                $pos = array_search('*', $exprArr[2], true);
                $res = @$exprArr[1][$pos] * @$exprArr[1][$pos + 1];
                $expr = str_replace(@$exprArr[1][$pos] . '*' . @$exprArr[1][$pos + 1], $res, $expr);
                $expr = $this->calc($expr);
            } elseif (in_array('/', $exprArr[2], true)) {
                $pos = array_search('/', $exprArr[2], true);
                $res = $exprArr[1][$pos] / $exprArr[1][$pos + 1];
                $expr = str_replace($exprArr[1][$pos] . '/' . $exprArr[1][$pos + 1], $res, $expr);
                $expr = $this->calc($expr);
            } elseif (in_array('-', $exprArr[2], true)) {
                $pos = array_search('-', $exprArr[2], true);
                $res = $exprArr[1][$pos] - $exprArr[1][$pos + 1];
                $expr = str_replace($exprArr[1][$pos] . '-' . $exprArr[1][$pos + 1], $res, $expr);
                $expr = $this->calc($expr);
            } elseif (in_array('+', $exprArr[2], true)) {
                $pos = array_search('+', $exprArr[2], true);
                $res = $exprArr[1][$pos] + $exprArr[1][$pos + 1];
                $expr = str_replace($exprArr[1][$pos] . '+' . $exprArr[1][$pos + 1], $res, $expr);
                $expr = $this->calc($expr);
            } else {
                return $expr;
            }
        }

        return $expr;
    }

    /**
     * Eval.
     *
     * @param $matches
     *
     * @return bool|string
     */
    private function decodeEval($matches)
    {
        $string = $matches[0];
        $string = substr($string, 5, -2);

        return $this->decodeString($string);
    }

    /**
     * Decode string.
     *
     * @param $string
     * @param int $level
     *
     * @return bool|string
     */
    private function decodeString($string, $level = 0)
    {
        if (trim($string) === '') {
            return '';
        }
        if ($level > 100) {
            return '';
        }

        if (($string[0] === '\'') || ($string[0] === '"')) {
            return substr($string, 1, -1);
        }

        if ($string[0] === '$') {
            $string = str_replace(')', '', $string);
            preg_match_all('~\\' . preg_quote($string, '~') . '\s*=\s*(\'|")([^"\']+)(\'|")~msix', $this->fullCode, $matches);

            return @$matches[2][0];
        }

        $pos = strpos($string, '(');
        $function = substr($string, 0, $pos);

        $arg = $this->decodeString(substr($string, $pos + 1), $level + 1);
        if (strtolower($function) === 'base64_decode') {
            return @base64_decode($arg);
        }

        if (strtolower($function) === 'gzinflate') {
            return @gzinflate($arg);
        }

        if (strtolower($function) === 'gzuncompress') {
            return @gzuncompress($arg);
        }

        if (strtolower($function) === 'strrev') {
            return @strrev($arg);
        }

        if (strtolower($function) === 'str_rot13') {
            return @str_rot13($arg);
        }

        return $arg;
    }

    /**
     * Deobfuscate eval.
     *
     * @param $str
     *
     * @return mixed
     */
    private function deobfuscateEval($str)
    {
        $res = preg_replace_callback('~eval\((base64_decode|gzinflate|strrev|str_rot13|gzuncompress).*?\);~msix', function ($matches) {
            return $this->decodeEval($matches);
        }, $str);

        return str_replace($str, $res, $this->fullCode);
    }

    /**
     * Get eval code.
     *
     * @param $string
     *
     * @return mixed|string
     */
    private function getEvalCode($string)
    {
        preg_match("/eval\((.*?)\);/", $string, $matches);

        return (empty($matches)) ? '' : end($matches);
    }

    /**
     * Get text inside quotes.
     *
     * @param $string
     *
     * @return mixed|string
     */
    private function getTextInsideQuotes($string)
    {
        if (preg_match_all('/("(.*?)")/', $string, $matches)) {
            $array = end($matches);

            return @end($array);
        }

        if (preg_match_all('/(\'(.*?)\')/', $string, $matches)) {
            $array = end($matches);

            return @end($array);
        }

        return '';
    }

    /**
     * Deobfuscate lockit.
     *
     * @param $str
     *
     * @return string
     */
    private function deobfuscateLockit($str)
    {
        $obfPHP = $str;
        $phpcode = base64_decode($this->getTextInsideQuotes($this->getEvalCode($obfPHP)));
        $hexvalues = $this->getHexValues($phpcode);
        $tmpPoint = $this->getHexValues($obfPHP);
        $pointer1 = hexdec($tmpPoint[0]);
        $pointer2 = hexdec($hexvalues[0]);
        $pointer3 = hexdec($hexvalues[1]);
        $needles = $this->getNeedles($phpcode);
        $needle = $needles[count($needles) - 2];
        $beforeNeedle = end($needles);

        $phpcode = base64_decode(strtr(substr($obfPHP, $pointer2 + $pointer3, $pointer1), $needle, $beforeNeedle));

        return "<?php {$phpcode} ?>";
    }

    /**
     * Get needles.
     *
     * @param $string
     *
     * @return array
     */
    private function getNeedles($string)
    {
        preg_match_all("/'(.*?)'/", $string, $matches);

        return (empty($matches)) ? [] : $matches[1];
    }

    /**
     * Get hex values.
     *
     * @param $string
     *
     * @return array
     */
    private function getHexValues($string)
    {
        preg_match_all('/0x[a-fA-F0-9]{1,8}/', $string, $matches);

        return (empty($matches)) ? [] : $matches[0];
    }

    /**
     * Deobfuscate als.
     *
     * @param $str
     *
     * @return string
     */
    private function deobfuscateAls($str)
    {
        preg_match('~__FILE__;\$[O0]+=[0-9a-fx]+;eval\(\$[O0]+\(\'([^\']+)\'\)\);return;~mix', $str, $layer1);
        preg_match('~\$[O0]+=(\$[O0]+\()+\$[O0]+,[0-9a-fx]+\),\'([^\']+)\',\'([^\']+)\'\)\);eval\(~mix', base64_decode($layer1[1]), $layer2);
        $res = explode('?>', $str);
        if (end($res) !== '') {
            $res = substr(end($res), 380);
            $res = base64_decode(strtr($res, $layer2[2], $layer2[3]));
        }

        return "<?php {$res} ?>";
    }

    /**
     * Deobfuscate byterun.
     *
     * @param $str
     *
     * @return string
     */
    private function deobfuscateByterun($str)
    {
        $fullCode = $this->fullCode;
        preg_match('~\$_F=__FILE__;\$_X=\'([^\']+)\';\s*eval\s*\(\s*\$?\w{1,60}\s*\(\s*[\'"][^\'"]+[\'"]\s*\)\s*\)\s*;~mix', $str, $matches);
        if (!empty($matches)) {
            $res = base64_decode($matches[1]);
            $res = strtr($res, '123456aouie', 'aouie123456');
            $fullCode = str_replace($matches[0], $res, $this->fullCode);
        }

        return '<?php ' . $fullCode . ' ?>';
    }

    /**
     * Deobfuscate urldecode.
     *
     * @param $str
     *
     * @return mixed
     */
    private function deobfuscateUrldecode($str)
    {
        preg_match('~(\$[O0_]+)=urldecode\("([%0-9a-f]+)"\);((\$[O0_]+=(\1\{\d+\}\.?)+;)+)~mix', $str, $matches);
        $alph = urldecode($matches[2]);
        $funcs = $matches[3];
        for ($i = 0, $iMax = strlen($alph); $i < $iMax; $i++) {
            $funcs = str_replace([$matches[1] . '{' . $i . '}.', $matches[1] . '{' . $i . '}'], [$alph[$i], $alph[$i]], $funcs);
        }

        $str = str_replace($matches[3], $funcs, $str);
        $funcs = explode(';', $funcs);
        foreach ($funcs as $func) {
            $funcArr = explode('=', $func);
            if (count($funcArr) === 2) {
                $funcArr[0] = str_replace('$', '', $funcArr[0]);
                $str = str_replace('${"GLOBALS"}["' . $funcArr[0] . '"]', $funcArr[1], $str);
            }
        }

        return $str;
    }

    /**
     * Format PHP.
     *
     * @param $string
     *
     * @return mixed
     */
    private function formatPHP($string)
    {
        return str_replace(['<?php', '?>', PHP_EOL, ';'], ['', '', '', ";\n"], $string);
    }

    /**
     * Deobfuscate fopo.
     *
     * @param $str
     *
     * @return bool|string
     */
    private function deobfuscateFopo($str)
    {
        $phpcode = $this->formatPHP($str);
        $phpcode = base64_decode($this->getTextInsideQuotes($this->getEvalCode($phpcode)));
        $array = explode(':', $phpcode);
        @$phpcode = gzinflate(base64_decode(str_rot13($this->getTextInsideQuotes(end($array)))));
        $old = '';
        while (($old !== $phpcode) && (strlen(strstr($phpcode, '@eval($')) > 0)) {
            $old = $phpcode;
            $funcs = explode(';', $phpcode);
            if (count($funcs) === 5) {
                $phpcode = gzinflate(base64_decode(str_rot13($this->getTextInsideQuotes($this->getEvalCode($phpcode)))));
            } elseif (count($funcs) === 4) {
                $phpcode = gzinflate(base64_decode($this->getTextInsideQuotes($this->getEvalCode($phpcode))));
            }
        }

        return substr($phpcode, 2);
    }

    /**
     * Get obfuscation type.
     *
     * @param $str
     *
     * @return array
     */
    private function getObfuscateType($str)
    {
        $str = str_replace([".''", "''.", '.""', '"".'], '', $str);

        $types = [];

        if (preg_match('~\$(?:\{(?:"|\'))?GLOBALS(?:(?:"|\')\})?\[\s*[\'"]_+\w{1,60}[\'"]\s*\]\s*=\s*\s*(?:array\s*\(|\[)\s*base64_decode\s*\(~mix', $str)) {
            $types[] = 'globals';
        }
        if (preg_match('~function\s*_+\d+\s*\(\s*\$i\s*\)\s*{\s*\$a\s*=\s*(?:Array|\[)~mix', $str)) {
            $types[] = 'globals';
        }
        if (preg_match('~__FILE__;\$[O0]+=[0-9a-fx]+;eval\(\$[O0]+\(\'([^\']+)\'\)\);return;~mix', $str)) {
            $types[] = 'als';
        }
        if (preg_match('~\$[O0]*=urldecode\(\'%66%67%36%73%62%65%68%70%72%61%34%63%6f%5f%74%6e%64\'\);\s*\$(?:(?:"|\')\})?GLOBALS(?:(?:"|\')\})?\[\'[O0]*\'\]=\$[O0]*~mix', $str)) {
            $types[] = 'lockit';
        }
        if (preg_match('~\$\w+="(\\\x?[0-9a-f]+){13}";@eval\(\$\w+\(~mix', $str)) {
            $types[] = 'fopo';
        }
        if (preg_match('~\$_F=__FILE__;\$_X=\'([^\']+\');eval\(~mx', $str)) {
            $types[] = 'byterun';
        }
        if (preg_match('~(\$[O0_]+)=urldecode\("([%0-9a-f]+)"\);((\$[O0_]+=(\1\{\d+\}\.?)+;)+)~mix', $str)) {
            $types[] = 'urldecode_globals';
        }
        if (preg_match('~eval\((base64_decode|gzinflate|strrev|str_rot13|gzuncompress)~mix', $str)) {
            $types[] = 'eval';
        }

        return $types;
    }
}
