<?php
require('src/lightncandy.php');
$template = "{{#if name}}Welcome {{name}} , You win \${{value}} dollars!!{{else}}No name!{{/if}}\n";
$php = LightnCandy::compile($template, Array('flags' => LightnCandy::FLAG_STANDALONE));

// Usage 1: One time compile then runtime execute
// Do not suggested this way, because it require php setting allow_url_fopen=1 , not secure.
echo "Template is:\n$template\n\n";
echo "Rendered PHP code is:\n$php\n\n";
echo 'LightnCandy Context:';
print_r(LightnCandy::getContext());
$renderer = LightnCandy::prepare($php);

echo $renderer(Array('name' => 'John', 'value' => 10000));
echo $renderer(Array('noname' => 'Peter', 'value' => 1000));

?>
