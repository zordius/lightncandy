<?php
class LightnCandy {
    public static function compile($template) {
        $context = Array(
            'level' => 0
        );

        return '<?php return function ($in0) {
    return \'' . preg_replace_callback('/\\{\\{(.+?)\\}\\}/', function ($matches) use ($context) {
            return '\' . ' . LightnCandy::tokens($matches, $context) . ' . \'';
        }, $template) . '\';
}
?>';
    }

    public static function tokens($token, $context) {
        $ret = '';
        switch (substr($token[1], 0, 1)) {
        case '#':
            $act = substr($token[1], 1);
            switch ($act) {
            default:
                return "";
            }
            break;
        default:
            $vn = '[\'' . preg_replace('/\./', '\'][\'', $token[1]) . '\']';
            return "\$in{$context['level']}$vn";
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
