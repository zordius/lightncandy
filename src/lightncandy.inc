<?php
class LightnCandy {
    public static $flagErrorlog = false;
    public static $flagThrowException = false;
    public static $flagStandalone = false;
    private static $lastContext;

    public static function compile($template) {
        $context = Array(
            'level' => 0,
            'scopes' => Array(),
            'scope' => '',
            'stack' => Array(),
            'error' => false,
            'usedFeature' => Array(
                'var' => 0,
                'raw' => 0,
                'section' => 0,
                'invertsection' => 0,
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
            if (self::flagErrorlog) {
                error_log($context['error']);
            }
            if (self::flagThrowException) {
                throw new Exception($context['error']);
            }
            return false;
        }

        return "<?php return function (\$in) {
    return '$code';
}
?>";
    }

    public static function getContext() {
        return self::$lastContext;
    }

    protected static function _scope($scopes) {
        return count($scopes) ? '[' . implode('][', $scopes) . ']' : '';
    }

    protected static function _qscope($list) {
        return self::_scope(array_map(function ($v) {return "'$v'";}, $list));
    }

    protected static function _vn($vn) {
        return self::_qscope(explode('.', $vn));
    }

    public static function tokens($token, &$context) {
        $act = substr($token[2], 1);

        switch (substr($token[2], 0, 1)) {
        case '^':
            $context['usedFeature']['invertsection'] ++;
            $vn = self::_vn($act);
            $context['stack'][] = $act;
            $context['stack'][] = '^';
            return ".(!\$in{$context['scope']}$vn ? (";
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
                        $context['error'] = "Unexpect token $token[2] ! Previous token $pop$pop2 is not closed";
                        return;
                    }
                    if ($pop == '^') {
                        return ") : '').";
                    }
                    array_pop($context['scopes']);
                    array_pop($context['scopes']);
                    $vn = self::_vn($act);
                    $context['scope'] = self::_scope($context['scopes']);
                    return ";}, \$in{$context['scope']}$vn)).";
                default:
                    $context['error'] = "Unexpect token: $token[2] !";
                    return;
                }
            }
        case '#':
            switch ($act) {
            case 'if':
                $context['usedFeature']['if'] ++;
                $vn = self::_vn($act);
                return "(\$in{$context['scope']}$vn ? (";
            case 'else':
                $context['usedFeature']['else'] ++;
                $context['stack'][] = ':';
                return ') : (';
            case 'each':
                $context['usedFeature']['each'] ++;
                $act = substr($act, 5);
            default:
                $context['usedFeature']['section'] ++;
                $context['level']++;
                $context['scopes'][] = "'$act'";
                $context['scopes'][] = "\$k{$context['level']}";
                $context['stack'][] = $act;
                $context['stack'][] = '#';
                $context['scope'] = self::_scope($context['scopes']);
                return ".implode('', array_map(function(\$v, \$k{$context['level']}) use (\$in) {return ";
            }
        default:
            $context['usedFeature']['var'] ++;
            $vn = self::_vn($token[2]);
            return ".\$in{$context['scope']}$vn.";
        }
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
