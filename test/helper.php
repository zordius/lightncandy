<?php
require('src/lightncandy.inc');
$template = <<<VAREND
<ul>
 <li>1. {{helper1 name}}</li>
 <li>2. {{helper1 value}}</li>
 <li>3. {{myClass::helper2 name}}</li>
 <li>4. {{myClass::helper2 value}}</li>
 <li>5. {{he name}}</li>
 <li>6. {{he value}}</li>
 <li>7. {{h2 name}}</li>
 <li>8. {{h2 value}}</li>
 <li>9. {{link name}}</li>
 <li>10. {{link value}}</li>
</ul>
VAREND
;
$php = LightnCandy::compile($template, Array(
    'flags' => LightnCandy::FLAG_ERROR_LOG | 0 * LightnCandy::FLAG_STANDALONE | LightnCandy::FLAG_HANDLEBARSJS,
    'helpers' => Array(
        'helper1',
        'myClass::helper2',
        'he' => 'helper1',
        'h2' => 'myClass::helper2',
        'link' => function ($arg) {
            return "<a href=\"{$arg}\">click here</a>";
        },
    )

));

echo "Template is:\n$template\n\n";
echo "Rendered PHP code is:\n$php\n\n";
echo 'LightnCandy Context:';
print_r(LightnCandy::getContext());

$renderer = LightnCandy::prepare($php);

function helper1($arg) {
    return "-$arg-";
}

class myClass {
    function helper2($arg) {
        return "=$arg=";
    }
}

echo $renderer(Array('name' => 'John', 'value' => 10000));

?>
