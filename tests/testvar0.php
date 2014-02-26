<?php
require('src/lightncandy.php');
$template = <<<TEMP
{{#each list}}
Name {{0}} , Age {{1}} ...;
{{/each}}
TEMP;

$php = LightnCandy::compile($template, Array('flags' => LightnCandy::FLAG_HANDLEBARSJS));

$renderer = LightnCandy::prepare($php);

$data = array();
$data[] = array("John", "12");
$data[] = array("Marry", "22");

echo $renderer(Array('list' => $data));
