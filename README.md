lightncandy
===========

A PHP library to support almost all features of handlebars ( http://handlebarsjs.com/ ) , target to run as fast as pure php.

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
   * NOTE: Detail performance test reports can be found here: https://github.com/zordius/HandlebarsTest
* Context generation
   * Analyze used feature from your template
   * generate <B>Json Schema</B>
      * Do LightnCandy::getJsonSchema() right after LightnCandy::compile() to get jsonSchema
      * Know required data structure from your templates
      * Verify input data, or find out missing variables with any jsonSchema validator
* Standalone Template
   * The compiled php template can run without any php library.

Sample
------
<pre>
// require the lib, compile template string
require('src/lightncandy.inc');
$template = "Welcome {{name}} , You win \${{value}} dollars!!\n";
$phpStr = LightnCandy::compile($template);

// Usage 1: One time compile then runtime execute
// Do not suggested this way, because it require php setting allow_url_fopen=1 and and allow_url_fopen=1, not secure.
// Or, prepare() will create tmp file then include it, you will need to add your tmp directory into open_basedir.
echo "Template is:\n$template\n\n";
echo "Rendered PHP code is:\n$php\n\n";
$renderer = LightnCandy::prepare($phpStr);

echo $renderer(Array('name' => 'John', 'value' => 10000));
echo $renderer(Array('name' => 'Peter', 'value' => 1000));


// Usage 2: One time save compiled php, later run with include
file_put_contents($php_inc, $phpStr)

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

You can apply more flags by running LightnCandy::compile($php, $options)
for example:

LightnCandy::compile($template, Array('flags' => LightnCandy::FLAG_ERROR_LOG | LightnCandy::FLAG_STANDALONE));

Default is to compile the template as php which can be run as fast as possible, all flags are off.

* FLAG_ERROR_LOG : output error_log when found any template error
* FLAG_ERROR_EXCEPTION : throw exception when found any template error
* FLAG_STANDALONE : generate stand alone php codes which can be execute without include LightnCandy. The compiled php code will contain scopped user function, somehow larger.
* FLAG_JSTRUE: generate 'true' when value is true (handlebars.js behavior). Otherwise, true will generate ''.
* FLAG_JSOBJECT: generate '[object Object]' for associated array, generate ',' seperated values for array (handlebars.js behavior). Otherwise, all php array will generate ''.
* FLAG_THIS: support {{this}} or {{.}} in template. Otherwise, {{this}} and {{.}} will cause template error.
* FLAG_WITH: support {{#with var}} . Otherwise, {{#with var}} will cause template error.
* FLAG_PARENT: support {{../var}} . Otherwise, {{../var}} will cause template error.
* FLAG_HANDLEBARSJS: align with handlebars.js behaviors, same as FLAG_JSTRUE + FLAG_JSOBJECT + FLAG_THIS + FLAG_WITH + FLAG_PARENT.

Partial Support
---------------

LightnCandy supports partial when compile time. When compile(), LightnCandy will search template file in current directory by default. You can define more then 1 template directories with 'basedir' option. Default template file name is *.tmpl, you can change or add more template file extensions with 'fileext' option. 

for example:
<pre>
LightnCandy::compile($template, Array(
    'flags' => LightnCandy::FLAG_STANDALONE,
    'basedir' => Array(
        '/usr/local/share/handlebars/templates',
        '/usr/local/share/my_project/templates',
        '/usr/local/share/my_project/partials',
    ),
    'fileext' => Array(
        '.tmpl',
        '.mustache',
        '.handlebars',
    )
));
</pre>

LightnCandy supports parent context access in partial {{../vars}}, so far no other php/javascript library can handle this correctly.

Unsupported Feature (so far)
----------------------------

* [Plan to support] set delimiter (change delimiter from {{ }} to custom string, for example <% then %>)
* [Possible] input as Object and methods (now only accept associated array data structure)
* [Never] register a helper function (We wish you to not use custom helper to keep your template generic, then you can reuse these templates in different languages.)

Lightncandy Design Concept
--------------------------

* Do not OO everywhere. Single inc file, keep it simple and fast.
* Simulate all handlebars/javascript behavior, including true, false, Object, Array output behavior.
* Make almost everything happened in compile time, including 'partial' support.

Suggested Handlebars Template Practices
---------------------------------------

* Prevent to use {{#with}} . I think {{path.to.val}} is more readable then {{#with path.to}}{{val}}{{/with}}, when using {{#with}} you will confusing on scope changing. {{#with}} only save you very little time when you access many variables under same path, but cost you a lot time when you need to understand then maintain a template.
* use {{{val}}} when you do not require urlencode. It is better performance, too.
* Prevent to use custom helper if you want to reuse your template in different language. Or, you may need to implement different versions of helper in different languages.

Detail Feature list
-------------------

Go http://handlebarsjs.com/ to see more feature description about handlebars.js. All features align with it.

* Exact same CR/LF behavior with handlebars.js
* Exact same 'true' output with handlebars.js (require FLAG_JSTRUE)
* Exact same '[object Object]' output or join(',' array) output with handlebars.js (require FLAG_JSOBJECT)
* Can place heading/tailing space, tab, CR/LF inside {{ var }} or {{{ var }}}
* {{{value}}} : raw variable
   * true as 'true' (require FLAG_JSTRUE)
   * false as ''
* {{value}} : html encoded variable
   * true as 'true' (require FLAG_JSTRUE)
   * false as ''
* {{{path.to.value}}} : dot notation, raw
* {{path.to.value}} : dot notation, html encoded
* {{.}} : current context, html encoded
* {{this}} : current context, html encoded (require FLAG_THIS)
* {{{.}}} : current context, raw (require FLAG_THIS)
* {{{this}}} : current context, raw (require FLAG_THIS)
* {{#value}} : section
   * false, undefined and null will skip the section
   * true will run the section with original scope
   * All others will run the section with new scope (include 0, 1, -1, '', '1', '0', '-1', 'false', Array, ...)
* {{/value}} : end section
* {{^value}} : inverted section
   * false, undefined and null will run the section with original scope
   * All others will skip the section (include 0, 1, -1, '', '1', '0', '-1', 'false', Array, ...)
* {{! comment}} : comment
* {{#each var}} : each loop
* {{/each}} : end loop
* {{#if var}} : run if logic with original scope
* {{/if}} : end if
* {{else}} : run else logic, should between {{#if var}} and {{/if}} , or between {{#unless var}} and {{/unless}}
* {{#unless var}} : run unless logic with original scope
* {{#with var}} : change context scope. If the var is false, skip included section. (require FLAG_WITH)
* {{../var}}: parent template scope. (require FLAG_PARENT)
* {{> file}}: partial; include another template inside a template.
* {{@index}}: reference to current index in a {{#each}} loop on an array.
* {{@key}}: reference to current key in a {{#each}} loop on an object.
