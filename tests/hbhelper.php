<?php
require('src/lightncandy.php');
$template = <<<VAREND
<ul>
 {{#myeach people}}
 <li>{{name}}: \${{salary}} (Second one is: {{../people.1.name}})</li>
 {{/myeach}}
</ul>
VAREND
;
$php = LightnCandy::compile($template, Array(
    'flags' => LightnCandy::FLAG_ERROR_LOG | 0 * LightnCandy::FLAG_STANDALONE | LightnCandy::FLAG_HANDLEBARSJS,
    'hbhelpers' => Array(
        'myeach' => function ($context, $options) {
            $ret = '';
            foreach ($context as $ppl) {
                $ret .= $options['fn']($ppl);
            }
            return $ret;
        }
    )

));

echo "Template is:\n$template\n\n";
echo "Rendered PHP code is:\n$php\n\n";
//echo 'LightnCandy Context:';
//print_r(LightnCandy::getContext());

$renderer = LightnCandy::prepare($php);

echo $renderer(Array('people' => Array(
    Array('name' => 'Peter', 'salary' => 100),
    Array('name' => 'John', 'salary' => 1000),
    Array('name' => 'Merry', 'salary' => 10000),
)));

?>
