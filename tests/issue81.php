<?php
require('src/lightncandy.php');
$template = <<<VAREND
{{#with ../person}}
  {{^name}}
  Unknown
  {{/name}}
{{/with}}
VAREND
;

$php = LightnCandy::compile($template, array(
    'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_ERROR_EXCEPTION,
));

echo "Rendered PHP code is:\n$php\n\n";
$renderer = LightnCandy::prepare($php);

echo "Render result:\n";
echo $renderer(Array('parent?!' => Array('A', 'B', 'bar' => Array('C', 'D', 'E'))));
echo "\n";

?>
