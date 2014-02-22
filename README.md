lightncandy
===========

A PHP library to support almost all features of handlebars ( http://handlebarsjs.com/ ) , target to run as fast as pure PHP.

Features
--------

* Logicless template: mustache ( http://mustache.github.com/ ) or handlebars ( http://handlebarsjs.com/ ) .
* Compile template to **pure PHP** code.
   * Examples:
      * templateA: https://github.com/zordius/HandlebarsTest/blob/master/fixture/001-simple-vars.tmpl
      * compile as phpA: https://github.com/zordius/HandlebarsTest/blob/master/fixture/001-simple-vars.php
      * templateB: https://github.com/zordius/HandlebarsTest/blob/master/fixture/016-hb-eachthis.tmpl
      * compile as phpB: https://github.com/zordius/HandlebarsTest/blob/master/fixture/016-hb-eachthis.php
* **FAST!**
   * runs 4~6 times faster than https://github.com/bobthecow/mustache.php
   * runs 4~10 times faster than https://github.com/dingram/mustache-php
   * runs 10~30 times faster than https://github.com/XaminProject/handlebars.php
   * NOTE: Detail performance test reports can be found here: https://github.com/zordius/HandlebarsTest
* Context generation
   * Analyze used feature from your template
   * generate **Json Schema** [BUGGY NOW]
      * Do `LightnCandy::getJsonSchema()` right after `LightnCandy::compile()` to get jsonSchema
      * Know required data structure from your templates
      * Verify input data, or find out missing variables with any jsonSchema validator
* Standalone Template
   * The compiled php template can run without any php library.

Sample
------
```php
// THREE STEPS TO USE LIGHTNCANDY
// Step 1. require the lib, compile template, get the php code as string
require('src/lightncandy.inc');

$template = "Welcome {{name}} , You win \${{value}} dollars!!\n";
$phpStr = LightnCandy::compile($template);

echo "Template is:\n$template\n\n";
echo "Rendered PHP code is:\n$phpStr\n\n";


// Step 2A. (Usage 1) use LightnCandy::prepare to get render function
//   Do not suggested this way, because it may require php setting allow_url_fopen=1 ,
//   and allow_url_fopen=1 is not secure .
//   When allow_url_fopen = 0, prepare() will create tmp file then include it, 
//   you will need to add your tmp directory into open_basedir.
//   YOU MAY NEED TO CHANGE PHP SETTING BY THIS WAY
$renderer = LightnCandy::prepare($phpStr);


// Step 2B. (Usage 2) Store your render function in a file 
//   You decide your compiled template file path and name
//   You can load your render function by include()
//   RECOMMENDED WAY
file_put_contents($php_inc, $phpStr);
$renderer = include($php_inc);


// Step 3. run native php render function any time
echo $renderer(Array('name' => 'John', 'value' => 10000));
echo $renderer(Array('name' => 'Peter', 'value' => 1000));
```

Sample output
-------------
```
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
```

CONSTANTS
---------

You can apply more flags by running `LightnCandy::compile($php, $options)`
for example:

```php
LightnCandy::compile($template, Array(
    'flags' => LightnCandy::FLAG_ERROR_LOG | LightnCandy::FLAG_STANDALONE
));
```

Default is to compile the template as php which can be run as fast as possible, all flags are off.

* `FLAG_ERROR_LOG` : output error_log when found any template error
* `FLAG_ERROR_EXCEPTION` : throw exception when found any template error
* `FLAG_STANDALONE` : generate stand alone php codes which can be execute without including LightnCandy. The compiled php code will contain scopped user function, somehow larger. And, the performance of the template will slow 1 ~ 10%.
* `FLAG_JSTRUE` : generate 'true' when value is true (handlebars.js behavior). Otherwise, true will generate ''.
* `FLAG_JSOBJECT` : generate '[object Object]' for associated array, generate ',' seperated values for array (handlebars.js behavior). Otherwise, all php array will generate ''.
* `FLAG_THIS` : support `{{this}}` or `{{.}}` in template. Otherwise, `{{this}}` and `{{.}}` will cause template error.
* `FLAG_WITH` : support `{{#with var}}` . Otherwise, `{{#with var}}` will cause template error.
* `FLAG_PARENT` : support `{{../var}}` . Otherwise, `{{../var}}` will cause template error.
* `FLAG_JSQUOTE` : encode `'` to `&#x27;` . Otherwise, `'` will encoded as `&#039;` .
* `FLAG_ADVARNAME` : support `{{foo.[0].[#te#st].bar}}` style advanced variable naming.
* `FLAG_EXTHELPER` : do not include custom helper codes in compiled php codes. This reduce the code size, but you need to take care of your helper functions when rendering. If you forget to include required functions, `undefined function` runtime error will be triggered. **Note: Anonymouse functions will always be placed in generated codes**
* `FLAG_HANDLEBARSJS` : align with handlebars.js behaviors, same as `FLAG_JSTRUE + FLAG_JSOBJECT + FLAG_THIS + FLAG_WITH + FLAG_PARENT + FLAG_JSQUOTE + FLAG_ADVARNAME`.
* `FLAG_ECHO` (experimental): compile to `echo 'a', $b, 'c';` to improve performance. This will slow down rendering when the template and data are simple, but will improve 1% ~ 7% when the data is big and looping in the template.
* `FLAG_BESTPERFORMANCE` : same as `FLAG_ECHO` now. This flag may be changed base on performance testing result in the future.

Partial Support
---------------

LightnCandy supports partial when compile time. When `compile()`, LightnCandy will search template file in current directory by default. You can define more then 1 template directories with `basedir` option. Default template file name is `*.tmpl`, you can change or add more template file extensions with `fileext` option. 

for example:
```php
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
```

LightnCandy supports parent context access in partial (access `{{../vars}}` inside the template), so far no other php/javascript library can handle this correctly.

Custom Helper
-------------

LightnCandy supports custom helper when compile time. When `compile()`, LightnCandy will lookup helpers from custom helper table. You can regist custom helpers with `helpers` option.

for exmample:
```php
LightnCandy::compile($template, Array(
    'helpers' => Array(
        // 1. You may pass your function name
        //    When the function is not exist, you get compile time error
        //    In this case, the helper name is same with function name
        'my_helper_function',

        // 2. You may also provide a static call from a class
        //    In this case, the helper name is same with provided full name
        //    It is not valid in handlebars.js
        'myClass::myStaticMethod',

        // 3. You may also provide an alias for helper name
        //    This help you to mapping different function to a prefered helper name
        'helper_name' => 'my_other_helper',

        // 4. Alias also works well for static call from a class
        //    This help you to mapping different function to a prefered helper name
        'helper_name' => 'myClass::func',

        // 5. Anonymouse function should be provided with helper name
        //    The function will be included in generaed code always
        'helper_name' => function ($arg1, $arg2) {
            return "<a href="{$arg1}">{$arg2}</a>";
        }
    )
));
```

Unsupported Feature (so far)
----------------------------

* [NEVER] `{{foo/bar}}` style variable name, it is deprecated in offical handlebars.js document.
* [Plan to support] set delimiter (change delimiter from `{{ }}` to custom string, for example `<% then %>`)
* [Plan to support] register a helper function (We wish you to not use custom helper to keep your template generic, then you can reuse these templates in different languages. This feature will default off, you can turn it on with enable the option flag)
* [Possible] input as Object and methods (now only accept associated array data structure)

Lightncandy Design Concept
--------------------------

* Do not OO everywhere. Single inc file, keep it simple and fast.
* Simulate all handlebars/javascript behavior, including true, false, Object, Array output behavior.
* Make almost everything happened in compile time, including partial support.

Suggested Handlebars Template Practices
---------------------------------------

* Prevent to use `{{#with}}` . I think `{{path.to.val}}` is more readable then `{{#with path.to}}{{val}}{{/with}}`; when using `{{#with}}` you will confusing on scope changing. `{{#with}}` only save you very little time when you access many variables under same path, but cost you a lot time when you need to understand then maintain a template.
* use `{{{val}}}` when you do not require urlencode. It is better performance, too.
* Prevent to use custom helper if you want to reuse your template in different language. Or, you may need to implement different versions of helper in different languages.

Detail Feature list
-------------------

Go http://handlebarsjs.com/ to see more feature description about handlebars.js. All features align with it.

* Exact same CR/LF behavior with handlebars.js
* Exact same 'true' output with handlebars.js (require `FLAG_JSTRUE`)
* Exact same '[object Object]' output or join(',' array) output with handlebars.js (require `FLAG_JSOBJECT`)
* Can place heading/tailing space, tab, CR/LF inside `{{ var }}` or `{{{ var }}}`
* `{{{value}}}` : raw variable
   * true as 'true' (require `FLAG_JSTRUE`)
   * false as ''
* `{{value}}` : html encoded variable
   * true as 'true' (require `FLAG_JSTRUE`)
   * false as ''
* `{{{path.to.value}}}` : dot notation, raw
* `{{path.to.value}}` : dot notation, html encoded
* `{{.}}` : current context, html encoded (require `FLAG_THIS`)
* `{{this}}` : current context, html encoded (require `FLAG_THIS`)
* `{{{.}}}` : current context, raw (require `FLAG_THIS`)
* `{{{this}}}` : current context, raw (require `FLAG_THIS`)
* `{{#value}}` : section
   * false, undefined and null will skip the section
   * true will run the section with original scope
   * All others will run the section with new scope (include 0, 1, -1, '', '1', '0', '-1', 'false', Array, ...)
* `{{/value}}` : end section
* `{{^value}}` : inverted section
   * false, undefined and null will run the section with original scope
   * All others will skip the section (include 0, 1, -1, '', '1', '0', '-1', 'false', Array, ...)
* `{{! comment}}` : comment
* `{{#each var}}` : each loop
* `{{/each}}` : end loop
* `{{#if var}}` : run if logic with original scope (null, false, empty Array and '' will skip this block)
* `{{/if}}` : end if
* `{{else}}` : run else logic, should between `{{#if var}}` and `{{/if}}` , or between `{{#unless var}}` and `{{/unless}}`
* `{{#unless var}}` : run unless logic with original scope (null, false, empty Array and '' will render this block)
* `{{#with var}}` : change context scope. If the var is false, skip included section. (require `FLAG_WITH`)
* `{{../var}}` : parent template scope. (require `FLAG_PARENT`)
* `{{> file}}` : partial; include another template inside a template.
* `{{@index}}` : reference to current index in a `{{#each}}` loop on an array.
* `{{@key}}` : reference to current key in a `{{#each}}` loop on an object.
* `{{foo.[ba.r].[#spec].0.ok}}` : reference to $CurrentConext['foo']['ba.r']['#spec'][0]['ok']
