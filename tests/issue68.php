<?php
require('src/lightncandy.php');
$template = '{{#myeach foo}} Test! {{this}} {{/myeach}}';

$php = LightnCandy::compile($template, array(
    'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_ERROR_EXCEPTION,
    'hbhelpers' => array(
        'myeach'
    )
));

function myeach($context, $options) {
    $ret = '';
    foreach ($context as $cx) {
        $ret .= $options['fn']($cx);
    }
    return $ret;
}

echo "Rendered PHP code is:\n$php\n\n";
$renderer = LightnCandy::prepare($php);

echo "Render result:\n";
echo $renderer(Array('foo' => Array('A', 'B', 'bar' => Array('C', 'D', 'E'))));
echo "\n";

?>
