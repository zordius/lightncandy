<?php
require('src/lightncandy.php');
$template = '{{#each foo}} Test! {{this}} {{/each}}{{> test/test1}} ! >>> {{>recursive}}';
VAREND
;

$php = LightnCandy::compile($template, array(
    'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_ERROR_EXCEPTION | LightnCandy::FLAG_RUNTIMEPARTIAL,                      
    'basedir' => Array('.')
));

echo "Rendered PHP code is:\n$php\n\n";
$renderer = LightnCandy::prepare($php);

echo "Render result 1:\n";
echo $renderer(Array('foo' => Array('A', 'B', 'bar' => Array('C', 'D', 'E'))));
echo "Render result 2:\n";
echo $renderer(Array(
 'bar' => 1,
 'foo' => Array(
  'bar' => 3,
  'foo' => Array(
   'bar' => 5,
   'foo' => Array(
    'bar' => 7,
    'foo' => Array(
     'bar' => 11,
     'foo' => Array(
      'no foo here'
     )
    )
   )
  )
 )
));
echo "\n";

?>
