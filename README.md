lightncandy
===========

A PHP library to support subset feature of handlebars, target to run as fast as pure php.

Features
--------

* Logicless template: subset of mustache ( http://mustache.github.com/ ) or handlebars ( http://handlebarsjs.com/ ) .
* Compile template to <B>pure php</B> code.
   * Examples:
      * templateA: https://github.com/zordius/HandlebarsTest/blob/master/fixture/001-simple-vars.tmpl
      * compile as phpA: https://github.com/zordius/HandlebarsTest/blob/master/fixture/001-simple-vars.php
      * templateB: https://github.com/zordius/HandlebarsTest/blob/master/fixture/016-hb-eachthis.tmpl
      * compile as phpB: https://github.com/zordius/HandlebarsTest/blob/master/fixture/016-hb-eachthis.php

* <B>Fast</B>!
   * runs 4~6 times faster than https://github.com/bobthecow/mustache.php
   * runs 4~10 times faster than https://github.com/dingram/mustache-php
   * runs 10~30 times faster than https://github.com/XaminProject/handlebars.php

NOTE: Detail performance test reports can be found here: https://github.com/zordius/HandlebarsTest

Sample
------
<pre>
// require the lib, compile template string
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

You can apply more flags by running LightnCandy::compile($php, $flags)
for example:

LightnCandy::compile($php, LightnCandy::FLAG_ERROR_LOG | LightnCandy::FLAG_STANDALONE);

Default is to compile the template as php which can be run as fast as possible, all flags are off.

* FLAG_ERROR_LOG : output error_log when found template any error
* FLAG_ERROR_EXCEPTION : throw exception when found any template error
* FLAG_STANDALONE : generate stand alone php codes which can be execute without include LightnCandy. It will contain scopped user function, somehow larger.
* FLAG_JSTRUE: generate 'true' when value is true (handlebars.js behavior). Otherwise, true will generate ''.
* FLAG_JSOBJECT: generate '[object Object]' for associated array, generate ',' seperated values for array (handlebars.js behavior). Otherwise, all php array will generate ''.
* FLAG_THIS: support {{this}} or {{.}} in template. Otherwise, {{this}} and {{.}} will cause template error.
* FLAG_HANDLEBARSJS: align with handlebars.js behaviors, same as FLAG_JSTRUE + FLAG_JSOBJECT + FLAG_THIS.

Unsupported Feature (so far)
----------------------------

* partial (include another template inside a template)
* set delimiter (change delimiter from {{ }} to custom string, for example <% then %>)

Detail Feature list
-------------------

* Exact same CR/LF behavior with handlebars.js
* Exact same 'true' output with handlebars.js (require FLAG_JSTRUE)
* Exact same '[object Object]' output or join(',' array) output with handlebars.js (require FLAG_JSOBJECT)
* Can place heading/tailing space, tab, CR/LF inside {{ var }} or {{{ var }}}
* {{{value}}} : raw variable
   * true as 'true'
   * false as ''
* {{value}} : html encoded variable
   * true as 'true'
   * false as ''
* {{{path.to.value}}} : dot notation, raw
* {{path.to.value}} : dot notation, html encoded
* {{.}} : current context, html encoded
* {{this}} : current context, html encoded
* {{.}} : current context, raw
* {{this}} : current context, raw
* {{#value}} : section
   * false, undefined and null will skip the section
   * true will run the section with original scope
   * All others will run the section with new scope (include 0, 1, -1, '', '1', '0', '-1', 'false', Array, ...)
* {{/value}} : end section
* {{^value}} : inverted section
   * false, undefined and null will run the section with original scope
   * All others will skip the section (include 0, 1, -1, '', '1', '0', '-1', 'false', ...)
* {{! comment}} : comment
* {{#each var}} : each loop
* {{/each}} : end loop
* {{#if var}} : run if logic with original scope
* {{/if}} : end if
* {{else}} : run else logic, should between {{#if var}} and {{/if}}
* {{#unless var}} : run unless logic with original scope
