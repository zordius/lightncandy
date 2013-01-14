<?php
class LightnCandy {
    public static $flagErrorlog = false;
    public static $flagThrowException = false;
    public static $flagStandalone = false;
    private static $lastContext;

    public static function compile($template) {
        $context = Array(
            'level' => 0,
            'stack' => Array(),
            'error' => false,
            'obj' => self::$flagStandalone ? '$' : 'LightnCandy::',
            'usedFeature' => Array(
                'enc' => 0,
                'raw' => 0,
                'sec' => 0,
                'isec' => 0,
                'if' => 0,
                'else' => 0,
                'each' => 0,
            )
        );

        $code = preg_replace_callback('/(\\{{2,3})(.+?)(\\}{2,3})/', function ($matches) use (&$context) {
            return '\'' . LightnCandy::tokens($matches, $context) . '\'';
        }, $template);

        self::$lastContext = $context;

        if ($context['error']) {
            if (self::$flagErrorlog) {
                error_log($context['error']);
            }
            if (self::$flagThrowException) {
                throw new Exception($context['error']);
            }
            return false;
        }

        return "<?php return function (\$in) {
    \$cx = Array(
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

    public static function tokens($token, &$context) {
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
            $context['usedFeature']['isec'] ++;
            $context['stack'][] = $act;
            $context['stack'][] = '^';
            return ".({$context['obj']}isec('$act') ? (";
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
                $context['usedFeature']['if'] ++;
                return ".({$context['obj']}ifvar('{$token[2]}') ? (";
            case 'else':
                $context['usedFeature']['else'] ++;
                $context['stack'][] = ':';
                return ') : (';
            case 'each':
                $context['usedFeature']['each'] ++;
                $act = substr($act, 5);
            default:
                $each = ($act == 'each') ? 'true' : 'false';
                $context['usedFeature']['sec'] ++;
                $context['level']++;
                $context['stack'][] = $act;
                $context['stack'][] = '#';
                return ".{$context['obj']}sec('$act', \$cx, \$in, $each, function(\$cx, \$in) {return ";
            }
        default:
            $fn = $raw ? 'raw' : 'enc';
            $context['usedFeature'][$fn] ++;
            return ".{$context['obj']}{$fn}('{$token[2]}', \$cx, \$in).";
        }
    }

    public static function ifvar($var, $cx, $in) {
        return $in[$var];
    }

    public static function raw($var, $cx, $in) {
        return ($in[$var] === true) ? 'true' : $in[$var];
    }

    public static function enc($var, $cx, $in) {
        return htmlentities(($in[$var] === true) ? 'true' : $in[$var], ENT_QUOTES);
    }

    public static function sec($var, &$cx, $in, $each, $cb) {
        if (!$each && is_array($var)) {
            if (count(array_diff_key($var, array_keys(array_keys($var)))) == 0) {
                $each = true;
            }
        }
        if ($each) {
            return join('', array_map(function($v, $k) use ($in, $cx) {
                $cb($cx, $v);
            }, $in[$var]));
        }
        if (is_array($in[$var])) {
            return $cb($cx, $in[$var]);
        }
        if ($in[$var]) {
            return $cb($cx, $in);
        }
        return '';
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
