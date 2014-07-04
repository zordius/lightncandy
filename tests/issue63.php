<?php
require('src/lightncandy.php');
$template = '{{test}} Test! {{this}} {{/test}}';
VAREND
;

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
echo $renderer(Array('foo' => Array('A', 'B', 'bar' => Array('C', 'D', 'E'))));
echo "\n";

?>
