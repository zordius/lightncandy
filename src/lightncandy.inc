<?php
class LightnCandy {
    const FLAG_ERROR_LOG = 1;
    const FLAG_ERROR_EXCEPTION = 2;
    const FLAG_STANDALONE = 4;
    const FLAG_JSTRUE = 8;
    const FLAG_JSOBJECT = 16;
    const FLAG_THIS = 32;

    const FLAG_BESTPERFORMANCE = 0;
    const FLAG_HANDLEBARSJS = 56; // FLAG_JSTRUE + FLAG_JSOBJECT + FLAG_THIS 

    const TOKEN_SEARCH = '/(\\{{2,3})(.+?)(\\}{2,3})/';

    private static $lastContext;

    public static function compile($template, $flags = self::FLAG_HANDLEBARSJS) {
        $context = Array(
            'flags' => Array(
                'errorlog' => $flags & self::FLAG_ERROR_LOG,
                'exception' => $flags & self::FLAG_ERROR_EXCEPTION,
                'standalong' => $flags & self::FLAG_STANDALONE,
                'jstrue' => $flags & self::FLAG_JSTRUE,
                'jsobj' => $flags & self::FLAG_JSOBJECT,
                'this' => $flags & self::FLAG_THIS
            ),
            'level' => 0,
            'stack' => Array(),
            'error' => false,
            'useVar' => false,
            'obj' => ($flags & self::FLAG_STANDALONE) ? '$' : 'LightnCandy::',
            'usedFeature' => Array(
                'enc' => 0,
                'raw' => 0,
                'sec' => 0,
                'isec' => 0,
                'if' => 0,
                'else' => 0,
                'each' => 0,
                'comment' => 0
            )
        );

        if (preg_match_all(self::TOKEN_SEARCH, $template, $tokens, PREG_SET_ORDER) > 0) {
            foreach ($tokens as $token) {
                self::scan($token, $context);
            }
        }
        self::$lastContext = $context;

        if ($context['error']) {
            if ($context['flags']['errorlog']) {
                error_log($context['error']);
            }
            if ($context['flags']['exception']) {
                throw new Exception($context['error']);
            }
            return false;
        }

        if (($context['usedFeature']['sec'] < 1) || !$context['flags']['jsobj']) {
            $context['useVar'] = Array('$in');
        }

        $code = preg_replace_callback(self::TOKEN_SEARCH, function ($matches) use (&$context) {
            return '\'' . LightnCandy::tokens($matches, $context) . '\'';
        }, $template);

        $flagJStrue = self::_true($context['flags']['jstrue']);
        $flagJSObj = self::_true($context['flags']['jsobj']);

        return "<?php return function (\$in) {
    \$cx = Array(
        'flags' => Array(
            'jstrue' => $flagJStrue,
            'jsobj' => $flagJSObj
        ),
        'path' => Array(),
        'parents' => Array()
    );
    return '$code';
}
?>";
    }

    public static function getContext() {
        return self::$lastContext;
    }

    protected static function _true($v) {
        return ($v === true) ? 'true' : $v;
    }

    protected static function _scope($scopes) {
        return count($scopes) ? '[' . implode('][', $scopes) . ']' : '';
    }

    protected static function _qscope($list) {
        return self::_scope(array_map(function ($v) {return "'$v'";}, $list));
    }

    protected static function _vn($vn) {
        return $vn ? self::_qscope(explode('.', $vn)) : '';
    }

    protected static function scan($token, &$context) {
        $head = substr($token[2], 0, 1);
        $act = substr($token[2], 1);
        $raw = ($token[1] === '{{{');

        if (count($token[1]) !== count($token[3])) {
            $context['error'] = "Bad token {$token[1]}{$token[2]}{$token[3]} ! Do you mean {{}} or {{{}}}?";
            return;
        }

        if ($raw) {
            if (preg_match('/\\^|\\/|#/', $head)) {
                $context['error'] = "Bad token {$token[1]}{$token[2]}{$token[3]} ! Do you mean \{\{{$token[2]}\}\}?";
                return;
            }
        }
        switch ($head) {
        case '^':
            return $context['usedFeature']['isec'] ++;
        case '#':
            switch ($act) {
            case 'if':
                return $context['usedFeature']['if'] ++;
            case 'else':
                return $context['usedFeature']['else'] ++;
            case 'each':
                return $context['usedFeature']['each'] ++;
            default:
                return $context['usedFeature']['sec'] ++;
            }
        case '!':
            return $context['usedFeature']['comment'] ++;
        default:
            $fn = $raw ? 'raw' : 'enc';
            return $context['usedFeature'][$fn] ++;
        }
    }

    public static function tokens($token, &$context) {
        $head = substr($token[2], 0, 1);
        $act = substr($token[2], 1);
        $raw = ($token[1] === '{{{');

        switch ($head) {
        case '^':
            $context['stack'][] = $act;
            $context['stack'][] = '^';
            if ($context['useVar']) {
                $v = end($context['useVar']) . "['{$act}']";
                return ".((is_null($v) && ($v !== false)) ? ("; 
            } else {
                return ".({$context['obj']}isec('$act', \$in) ? (";
            }
        case '/':
            switch ($act) {
            case 'if':
                $pop = array_pop($context['stack']);
                if ($pop == ':') {
                    $pop = array_pop($context['stack']);
                    return ')).';
                }
                return ') : \'\').';
            case 'each':
                $act = substr($act, 5);
            default:
                $context['level']--;
                $pop = array_pop($context['stack']);
                switch($pop) {
                case '#':
                case '^':
                    $pop2 = array_pop($context['stack']);
                    if ($pop2 !== $act) {
                        $context['error'] = "Unexpect token {$token[2]} ! Previous token $pop$pop2 is not closed";
                        return;
                    }
                    if ($pop == '^') {
                        return ") : '').";
                    }
                    return ";}).";
                default:
                    $context['error'] = "Unexpect token: {$token[2]} !";
                    return;
                }
            }
        case '#':
            switch ($act) {
            case 'if':
                $act = substr($act, 3);
                return ".({$context['obj']}ifvar('{$act}', \$in) ? (";
            case 'else':
                $context['stack'][] = ':';
                return ') : (';
            case 'each':
                $act = substr($act, 5);
            default:
                $each = ($act == 'each') ? 'true' : 'false';
                $context['level']++;
                $context['stack'][] = $act;
                $context['stack'][] = '#';
                return ".{$context['obj']}sec('$act', \$cx, \$in, $each, function(\$cx, \$in) {return ";
            }
        case '!':
            return '.';
        default:
            if (($token[2] == 'this') || $token[2] == '.') {
                if ($context['flags']['this']) {
                    $token[2] = null;
                } else {
                }
            }
            $fn = $raw ? 'raw' : 'enc';
            if ($context['useVar']) {
                $v = end($context['useVar']) . self::_vn($token[2]);
                if ($context['flags']['jstrue']) {
                    return $raw ? ".(($v === true) ? 'true' : $v)." : ".(($v === true) ? 'true' : htmlentities($v, ENT_QUOTES)).";
                } else {
                    return $raw ? ".$v." : ".htmlentities($v, ENT_QUOTES).";
                }
            } else {
                return ".{$context['obj']}{$fn}('{$token[2]}', \$cx, \$in).";
            }
        }
    }

    public static function ifvar($var, $in) {
        return (!is_null($in[$var]) && ($in[$var] !== false));
    }

    public static function raw($var, $cx, $in) {
        $v = ($var === '') ? $in : $in[$var];
        if ($v === true) {
            if ($cx['flags']['jstrue']) {
                return 'true';
            }
        } elseif (is_array($v)) {
            if ($cx['flags']['jsobj']) {
                if (count(array_diff_key($v, array_keys(array_keys($v)))) > 0) {
                    return '[object Object]';
                } else {
                    $ret = Array();
                    foreach ($v as $k => $vv) {
                        $ret[] = self::enc($k, $cx, $v);
                    }
                    return join(',', $ret);
                }
            }
        }
        return $v;
    }

    public static function enc($var, $cx, $in) {
        $v = ($var === '') ? $in : $in[$var];

        if ($v === true) {
            if ($cx['flags']['jstrue']) {
                return 'true';
            }
        } elseif (is_array($v)) {
            if ($cx['flags']['jsobj']) {
                if (count(array_diff_key($v, array_keys(array_keys($v)))) > 0) {
                    return '[object Object]';
                } else {
                    $ret = Array();
                    foreach ($v as $k => $vv) {
                        $ret[] = self::enc($k, $cx, $v);
                    }
                    return join(',', $ret);
                }
            }
        }
        return htmlentities($v, ENT_QUOTES);
    }

    public static function sec($var, &$cx, $in, $each, $cb) {
        if (!$each && is_array($in[$var])) {
            if (count(array_diff_key($in[$var], array_keys(array_keys($in[$var])))) == 0) {
                $each = true;
            }
        }
        if ($each) {
            $ret = Array();
            foreach ($in[$var] as $raw) {
                $ret[] = $cb($cx, $raw);
            }
            return join('', $ret);
        }
        if (is_array($in[$var])) {
            return $cb($cx, $in[$var]);
        }
        if ($in[$var] === true) {
            return $cb($cx, $in);
        }
        if (is_string($in[$var])) {
            return $cb($cx, Array());
        }
        if (!is_null($in[$var]) && ($in[$var] !== false)) {
            return $cb($cx, $in[$var]);
        }
        return '';
    }

    public static function isec($var, $in) {
        return !self::ifvar($var, $in);
    }

    public static function prepare($php) {
        return include('data://text/plain,' . urlencode($php));
    }

    public static function render($prepared, $data) {
        $func = include($prepared);
        return $func($data);
    }
}
?>
