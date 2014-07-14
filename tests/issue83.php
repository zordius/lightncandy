<?php
require('src/lightncandy.php');
$template = '{{#each foo}} Test! {{this}} {{/each}}{{> tests/test1}} !';
VAREND
;

$php = LightnCandy::compile($template, array(
    'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_ERROR_EXCEPTION | LightnCandy::FLAG_RUNTIMEPARTIAL,                      
    'basedir' => Array('')
));

echo "Rendered PHP code is:\n$php\n\n";
$renderer = LightnCandy::prepare($php);

echo "Render result 1:\n";
echo $renderer(Array('foo' => Array('A', 'B', 'bar' => Array('C', 'D', 'E'))));

?>
