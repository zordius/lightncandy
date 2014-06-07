<?php
require('src/lightncandy.php');

$template = '{{{a.b.c}}}, {{a.b.bar}}, {{a.b.prop}}';

class foo {
    public $prop = 'Yes!';

    function bar() {
        return 'OK!'; 
    }
}

$php = LightnCandy::compile($template, Array(
    'flags' => LightnCandy::FLAG_ERROR_LOG | LightnCandy::FLAG_INSTANCE | LightnCandy::FLAG_HANDLEBARSJS,
    )
);

echo $php;

$renderer = LightnCandy::prepare($php);

echo "Render result:\n";
echo $renderer(Array('a' => Array('b' => new foo)));
echo "\n";

?>
