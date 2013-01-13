<?php
require('src/lightncandy.inc');
$template = "Welcome {{name}} , You win \${{value}} dollars!!\n";
$php = LightnCandy::compile($template);

// Usage 1: One time compile then runtime execute
// Do not suggested this way, because it require php setting allow_url_fopen=1 , not secure.
echo "Template is:\n$template\n\n";
echo "Rendered PHP code is:\n$php\n\n";
$renderer = LightnCandy::prepare($php);

echo $renderer(Array('name' => 'John', 'value' => 10000));
echo $renderer(Array('name' => 'Peter', 'value' => 1000));

?>
