<?php
require('src/lightncandy.php');
$template = '{{{tt}}}';
$php = LightnCandy::compile($template, Array(
    'flags' => LightnCandy::FLAG_STANDALONE || LightnCandy::FLAG_HANDLEBARSJS
));

echo "Rendered PHP code is:\n$php\n\n";

$renderer = LightnCandy::prepare($php);

echo $renderer(Array('tt' => 'bla bla bla'), LCRun3::DEBUG_ERROR_LOG | LCRun3::DEBUG_TAGS_ANSI);

?>
