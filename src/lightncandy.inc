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

        $code = preg_replace_callback('/\\{\\{(.+?)\\}\\}/', function ($matches) use ($context) {
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
        return count($scopes) ? '[\'' . join('\'][\'', $scopes) . '\']' : '';
    }

    public static function tokens($token, &$context) {
        $act = substr($token[1], 1);
        $ret = '';

        switch (substr($token[1], 0, 1)) {
        case '^':
            $vn = self::_scope(explode('.', $act));
            $context['stack'][] = $act;
            $context['stack'][] = '^';
            return "(!\$in{$context['scope']}$vn ? (''";
        case '/':
            switch ($act) {
            case 'if':
                return '';
            case 'each':
            default:
                $context['level']--;
                $pop = array_pop($context['stack']);
                if ($pop == '^') {
                    $pop = array_pop($context['stack']);
                    if ($pop !== $act) {
                        $context['error'] = "Except token name: /$pop !";
                        return;
                    }
                    return "'') : '')";
                } else {
                    array_pop($context['scopes']);
                    $context['scope'] = self::_scope($context['scopes']);
                }

                return "";
            }
        case '#':
            switch ($act) {
            case 'if':
                return '';
            case 'else':
                return '';
            case 'each':
                $act = substr($act, 1);
            default:
                $context['level']++;
                $context['scopes'][] = $act;
                $context['stack'][] = $act;
                $context['scope'] = self::_scope($context['scopes']);
                return '.array_map(function($in';
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
