<?php
require('src/lightncandy.php');
$template = <<<VAREND
{{date_format}} is a good day!
{{date_format2}} is a good day!
{{date_format3}} is a good day!
VAREND
;

$php = LightnCandy::compile($template, array(
    'flags' => LightnCandy::FLAG_ERROR_EXCEPTION,
    'helpers' => array(
        'date_format' => 'meetup_date_format',
        'date_format2' => 'meetup_date_format2',
        'date_format3' => 'meetup_date_format3'
    )
));

 function meetup_date_format() {
    return "OKOK~1";
}

function meetup_date_format2() {
    return "OKOK~2";
}

function meetup_date_format3 () {
    return "OKOK~3";
}

echo "Rendered PHP code is:\n$php\n\n";
$renderer = LightnCandy::prepare($php);

echo "Render result:\n";
echo $renderer(null);
echo "\n";

?>
