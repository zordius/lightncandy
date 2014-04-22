<?php
require('src/lightncandy.php');
$template = <<<VAREND
Hello! {{name}} is {{gender}}.
VAREND
;
$php = LightnCandy::compile($template, Array(
    'flags' => LightnCandy::FLAG_RENDER_DEBUG
));

echo "Rendered PHP code is:\n$php\n\n";

$renderer = LightnCandy::prepare($php);

echo $renderer(Array('name' => 'John'));

?>
