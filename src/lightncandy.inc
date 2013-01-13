<?php
class LightnCandy {
    public static $flagErrorlog = false;
    public static $flagThrowException = false;

    public static function compile($template) {
        $context = Array(
            'level' => 0,
            'scopes' => Array(),
            'scope' => '',
            'stack' => Array(),
            'error' => false
        );

        $code = preg_replace_callback('/\\{\\{(.+?)\\}\\}/', function ($matches) use (&$context) {
            return '\'' . LightnCandy::tokens($matches, $context) . '\'';
        }, $template);

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
        $act = substr($token[1], 1);

        switch (substr($token[1], 0, 1)) {
        case '^':
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
                        $context['error'] = "Unexpect token $token[1] ! Previous token $pop$pop2 is not closed";
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
                    $context['error'] = "Unexpect token: $token[1] !";
                    return;
                }
            }
        case '#':
            switch ($act) {
            case 'if':
                $vn = self::_vn($act);
                return "(\$in{$context['scope']}$vn ? (";
            case 'else':
                $context['stack'][] = ':';
                return ') : (';
            case 'each':
                $act = substr($act, 5);
            default:
                $context['level']++;
                $context['scopes'][] = "'$act'";
                $context['scopes'][] = "\$k{$context['level']}";
                $context['stack'][] = $act;
                $context['stack'][] = '#';
                $context['scope'] = self::_scope($context['scopes']);
                return ".implode('', array_map(function(\$v, \$k{$context['level']}) use (\$in) {return ";
            }
        default:
            $vn = self::_scope(explode('.', $token[1]));
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
