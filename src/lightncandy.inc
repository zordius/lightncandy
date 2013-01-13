<?php
class LightnCandy {
    public static function compile($template) {
        $context = Array(
            'level' => 0,
            'scopes' => Array(),
            'scope' => ''
        );

        return '<?php return function ($in) {
    return \'' . preg_replace_callback('/\\{\\{(.+?)\\}\\}/', function ($matches) use ($context) {
            return '\' . ' . LightnCandy::tokens($matches, $context) . ' . \'';
        }, $template) . '\';
}
?>';
    }

    protected static function _scope($scopes) {
        return count($scope) ? '[\'' . join('\'][\'', $scopes) . '\']' : '';
    }

    public static function tokens($token, &$context) {
        $act = substr($token[1], 1);
        $ret = '';

        switch (substr($token[1], 0, 1)) {
        case '/':
            switch ($act) {
            case 'if':
                return '';
            case 'each':
                return '';
            default:
                $context['level']--;
                array_pop($context['scopes']);
                $context['scope'] = self::_scope($context['scopes']);
            }
        case '#':
            switch ($act) {
            case 'if':
                return '';
            case 'each':
                return '';
            default:
                $context['level']++;
                $context['scopes'][] = $act;
                $context['scope'] = self::_scope($context['scopes']);
                return '';
            }
        default:
            $vn = '[\'' . preg_replace('/\./', '\'][\'', $token[1]) . '\']';
            return "\$in{$context['scope']}$vn";
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
