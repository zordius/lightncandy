lightncandy
===========

A PHP library to support subset feature of handlebars, target to run as fast as pure php.

Features
--------

* Logicless template: subset of mustache ( http://mustache.github.com/ ) or handlebars ( http://handlebarsjs.com/ ) .
* Compile template to pure php code.
* Fast!

Sample
------
<code>
require('src/lightncandy.inc');
$template = "Welcome {{name}} , You win \${{value}} dollars!!\n";
$php = LightnCandy::compile($template);

// Usage 1: One time compile then runtime execute
// Do not suggested this way, because it require php setting allow_url_fopen=1 , not secure.
echo "Template is:\n$template\n\n";
echo "Rendered PHP code is:\n$php\n\n";
$renderer = LightnCandy::prepare($php);

echo $renderer(Array('name' => 'John', 'value' => 10000));
echo $renderer(Array('name' => 'Peter', 'value' => 1000));

// Usage 2: One time save compiled php, later run with include
file_put_contents($php_inc, $compiled)

$renderer = include($php_inc);
echo $renderer(Array('name' => 'John', 'value' => 10000));
echo $renderer(Array('name' => 'Peter', 'value' => 1000));
</code>

Sample output
-------------
<code>
Template is:
Welcome {{name}} , You win ${{value}} dollars!!


Rendered PHP code is:
<?php return function ($in) {
    return 'Welcome ' . $in['name'] . ' , You win $' . $in['value'] . ' dollars!!
';
}
?>

Welcome John , You win $10000 dollars!!
Welcome Peter , You win $1000 dollars!!
</code>
