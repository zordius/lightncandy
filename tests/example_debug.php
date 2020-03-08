<?php
require dirname(__DIR__, 1) . '/vendor/autoload.php';

use LightnCandy\LightnCandy;
use LightnCandy\Runtime;

$template = "Hello! {{name}} is {{gender}}.
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
";

$php = LightnCandy::compile($template, array(
    'flags' => LightnCandy::FLAG_RENDER_DEBUG | LightnCandy::FLAG_HANDLEBARSJS
));

$renderer = LightnCandy::prepare($php);
error_reporting(0);
echo $renderer(array('name' => 'John'), array('debug' => Runtime::DEBUG_TAGS_ANSI));
