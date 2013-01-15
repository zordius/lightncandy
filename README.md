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
<pre>
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
file_put_contents($php_inc, $php)

$renderer = include($php_inc);
echo $renderer(Array('name' => 'John', 'value' => 10000));
echo $renderer(Array('name' => 'Peter', 'value' => 1000));
</pre>

Sample output
-------------
<pre>
Template is:
Welcome {{name}} , You win ${{value}} dollars!!


Rendered PHP code is:
&lt;?php return function ($in) {
    return 'Welcome ' . $in['name'] . ' , You win $' . $in['value'] . ' dollars!!
';
}
?&gt;

Welcome John , You win $10000 dollars!!
Welcome Peter , You win $1000 dollars!!
</pre>

CONSTANTS
---------

You can apply more flags by running LightnCandy::prepare($php, $flags)
for example:

LightnCandy::prepare($php, LightnCandy::FLAG_ERROR_LOG | FLAG_STANDALONE);

* FLAG_ERROR_LOG : output error_log when found template any error
* FLAG_ERROR_EXCEPTION : throw exception when found any template error
* FLAG_STANDALONE : generate stand alone php codes which can be execute without include LightnCandy. It will contain scopped user function, somehow larger.

Detail Feature list
-------------------

* Exact same CR/LF behavior with handlebars.js
* {{{value}}} : raw variable
   * true as 'true'
   * false as ''
* {{value}} : html encoded variable
   * true as 'true'
   * false as ''
* {{#value}} : section
   * false, undefined and null will skip the section
   * true will run the section with original scope
   * All others will run the section with new scope (include 0, 1, -1, '', '1', '0', '-1', 'false', Array, ...)
* {{/value}} : end section
* {{^value}} : inverted section
   * false, undefined and null will run the section with original scope
   * All others will skip the section (include 0, 1, -1, '', '1', '0', '-1', 'false', ...)
* {{! comment}} : comment
