<?php
require('src/lightncandy.php');

$template = '{{{a.b.c}}}, {{a.b.bar}}, {{a.b.prop}}';

class foo {
    $prop = 'Yes!';

    function bar() {
        return 'OK!'; 
    }
}

$php = LightnCandy::compile($template, Array(
    'flags' => LightnCandy::FLAG_ERROR_LOG | 0 * LightnCandy::FLAG_STANDALONE | LightnCandy::FLAG_HANDLEBARSJS,
    )
);

$renderer = LightnCandy::prepare($php);

echo "Render result:\n";
echo $renderer(Array('a' => Array('b' => new foo)));
echo "\n";

?>
