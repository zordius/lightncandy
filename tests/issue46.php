<?php
require('src/lightncandy.php');
$template = '{{{this.id}}}, {{a.id}}';
$php = LightnCandy::compile($template, Array(
    'flags' => LightnCandy::FLAG_ERROR_LOG | 0 * LightnCandy::FLAG_STANDALONE | LightnCandy::FLAG_HANDLEBARSJS,
    )
);

echo "Rendered PHP code is:\n$php\n\n";

$renderer = LightnCandy::prepare($php);

echo "Render esult:\n";
echo $renderer(Array('id' => 'bla bla bla', 'a' => Array('id' => 'OK!')));
echo "\n";

?>
