<?php
require('src/lightncandy.php');
$template = <<<VAREND
Hello! {{name}} is {{gender}}.
Test1: {{@root.name}}
Test2: {{@root.gender}}
Test3: {{../test3}}
Test4: {{../../test4}}
Test5: {{../../.}}
Test6: {{../../[test'6]}}
{{#each .}}
each Value: {{.}}
{{/each}}
{{#.}}
section Value: {{.}}
{{/.}}
{{#if .}}IF OK!{{/if}}
{{#unless .}}Unless not OK!{{/unless}}
VAREND
;
$php = LightnCandy::compile($template, Array(
    'flags' => LightnCandy::FLAG_RENDER_DEBUG | LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_ERROR_LOG //| LightnCandy::FLAG_STANDALONE
));

echo "Rendered PHP code is:\n$php\n\n";

$renderer = LightnCandy::prepare($php);

echo $renderer(Array('name' => 'John'), LCRun3::DEBUG_ERROR_LOG | LCRun3::DEBUG_TAGS_ANSI);

?>
