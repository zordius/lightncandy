<?php
require('src/lightncandy.php');
$template = '{{date_format date}} is a good day!';
$php = LightnCandy::compile($template, array(
    'flags' => LightnCandy::FLAG_ERROR_EXCEPTION,
    'helpers' => array(
        'date_format' => 'meetup_date_format'
    )
));

 function meetup_date_format($date) {
    return "OKOK~";
}

echo "Rendered PHP code is:\n$php\n\n";
$renderer = LightnCandy::prepare($php);

echo "Render result:\n";
echo $renderer();
echo "\n";

?>
