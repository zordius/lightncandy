<?php
require('src/lightncandy.php');
$template = '{{&foo}} , {{foo}}, {{{foo}}}';

$php = LightnCandy::compile($template, array(
    'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_ERROR_EXCEPTION
));

echo "Rendered PHP code is:\n$php\n\n";
$renderer = LightnCandy::prepare($php);

echo "Render result:\n";
echo $renderer(Array('foo' => 'Test & " \' :)'));
echo "\n";

?>
