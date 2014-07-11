<?php
require('src/lightncandy.php');
$template = '{{{test @root.foo.bar}}} should be happy!';

$php = LightnCandy::compile($template, array(
    'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_ERROR_EXCEPTION,
    'helpers' => array(
        'test'
    )
));

function test ($input) {
   return join('.', $input[0]);
}

echo "Rendered PHP code is:\n$php\n\n";
$renderer = LightnCandy::prepare($php);

echo "Render result:\n";
echo $renderer(Array('foo' => Array('A', 'B', 'bar' => Array('C', 'D'))));
echo "\n";

?>
