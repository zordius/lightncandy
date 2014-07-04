<?php
require('src/lightncandy.php');
$template = '{{{test tmp}}} should be happy!';
VAREND
;

$php = LightnCandy::compile($template, array(
    'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_ERROR_EXCEPTION,
    'helpers' => array(
        'test'
    )
));

function test ($input) {
   return is_array($input[0]) ? 'IS_ARRAY' : 'NOT_ARRAY';
}

echo "Rendered PHP code is:\n$php\n\n";
$renderer = LightnCandy::prepare($php);

echo "Render result:\n";
echo $renderer(Array('tmp' => Array('A', 'B', 'C')));
echo "\n";

?>
