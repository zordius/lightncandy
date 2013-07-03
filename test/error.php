<?php
require('src/lightncandy.inc');
$template = <<<VAREND
<ul>
{{#each item}}<li>{{name}}</li>
</ul>
VAREND
;
$php = LightnCandy::compile($template, Array('flags' => LightnCandy::FLAG_ERROR_LOG | LightnCandy::FLAG_STANDALONE));

echo "Template is:\n$template\n\n";
echo "Rendered PHP code is:\n$php\n\n";
echo 'LightnCandy Context:';
print_r(LightnCandy::getContext());

?>
