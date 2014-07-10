<?php
require('src/lightncandy.php');
$template = '{{#each foo}} Test! {{this}} {{/each}}{{> test1}} !';
VAREND
;

$php = LightnCandy::compile($template, array(
    'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_ERROR_EXCEPTION | LightnCandy::FLAG_RUNTIMEPARTIAL,
    'basedir' => Array('tests')
));

echo "Rendered PHP code is:\n$php\n\n";
$renderer = LightnCandy::prepare($php);

echo "Render result:\n";
echo $renderer(Array('foo' => Array('A', 'B', 'bar' => Array('C', 'D', 'E'))));
echo "\n";

?>
