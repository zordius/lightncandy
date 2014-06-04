<?php
require('src/lightncandy.php');

$template = '<div class="terms-text"> {{render "artists-terms"}} </div>';

$compileOptions = [
    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
    'helpers' => [
        'url',
        'render' => function($view,$data = array()) {
            return View::make($view,$data);
        }
    ]
];

$php = LightnCandy::compile($template, $compileOptions);

echo "Rendered PHP code is:\n$php\n\n";

$renderer = LightnCandy::prepare($php);

echo "Render esult:\n";
echo $renderer(Array('id' => 'bla bla bla', 'a' => Array('id' => 'OK!')));
echo "\n";

?>
