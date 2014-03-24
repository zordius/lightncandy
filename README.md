LightnCandy
===========

A PHP library to support almost all features of handlebars ( http://handlebarsjs.com/ ) , target to run as fast as pure PHP.

Travis CI status: [![Unit testing](https://travis-ci.org/zordius/lightncandy.png?branch=master)](https://travis-ci.org/zordius/lightncandy) [![Regression testing](https://travis-ci.org/zordius/HandlebarsTest.png?branch=master)](https://travis-ci.org/zordius/HandlebarsTest)

Scrutinizer CI status: [![Code Coverage](https://scrutinizer-ci.com/g/zordius/lightncandy/badges/coverage.png?s=57aaeed149696b16380a79615df2ff83a1c25afa)](https://scrutinizer-ci.com/g/zordius/lightncandy/)

Features
--------

* Logicless template: mustache ( http://mustache.github.com/ ) or handlebars ( http://handlebarsjs.com/ ) .
* Compile template to **pure PHP** code. Examples:
   * <a href="https://github.com/zordius/HandlebarsTest/blob/master/fixture/001-simple-vars.tmpl">Template A</a> generated <a href="https://github.com/zordius/HandlebarsTest/blob/master/fixture/001-simple-vars.php">PHP A</a>
   * <a href="https://github.com/zordius/HandlebarsTest/blob/master/fixture/016-hb-eachthis.tmpl">Template B</a> generated <a href="https://github.com/zordius/HandlebarsTest/blob/master/fixture/016-hb-eachthis.php">PHP B</a>
* **FAST!**
   * runs 4~6 times faster than <a href="https://github.com/bobthecow/mustache.php">mustache.php</a>
   * runs 4~10 times faster than <a href="https://github.com/dingram/mustache-php">mustache-php</a>
   * runs 10~30 times faster than <a href="https://github.com/XaminProject/handlebars.php">handlebars.php</a>
   * Detail performance test reports can be found <a href="https://github.com/zordius/HandlebarsTest">here</a>, go http://zordius.github.io/HandlebarsTest/ to see charts.
* **SMALL** single PHP file, only 70K!
* Context generation
   * Analyze used feature from your template
   * generate **Json Schema** [BUGGY NOW]
      * Do `LightnCandy::getJsonSchema()` right after `LightnCandy::compile()` to get jsonSchema
      * Know required data structure from your templates
      * Verify input data, or find out missing variables with any jsonSchema validator
* Standalone Template
   * The compiled PHP code can run without any PHP library. You do not need to include LightnCandy when execute rendering function.

Installation
------------

Use Composer ( https://getcomposer.org/ ) to install LightnCandy:

```
composer require zordius/lightncandy:dev-master
```

Or, download LightnCandy from github:

```
wget https://raw.github.com/zordius/lightncandy/master/src/lightncandy.php --no-check-certificate
```

LightnCandy requirement: PHP 5.3.0+ , json_encode() (optional for `LightnCandy::getJsonSchema()`)

**UPGRADE NOTICE**

* Due to big change of variable name handling, the rendering support class `LCRun` is renamed to `LCRun2`. If you compile templates as none standalone PHP code by LightnCandy v0.9 or before, you should compile these templates again. Or, you may run into `Class 'LCRun' not found` error when you execute these old rendering functions.

* Standalone templates compiled by older LightnCandy can be executed safe when you upgrade to any new version of LightnCandy.

Usage
-----
```php
// THREE STEPS TO USE LIGHTNCANDY
// Step 1. require the lib, compile template, get the PHP code as string
require('src/lightncandy.php');

$template = "Welcome {{name}} , You win \${{value}} dollars!!\n";
$phpStr = LightnCandy::compile($template);  // compiled PHP code in $phpStr

// Step 2A. (Usage 1) use LightnCandy::prepare to get render function
//   DEPRECATED , it may require PHP setting allow_url_fopen=1 ,
//   and allow_url_fopen=1 is not secure .
//   When allow_url_fopen = 0, prepare() will create tmp file then include it, 
//   you will need to add your tmp directory into open_basedir.
//   YOU MAY NEED TO CHANGE PHP SETTING BY THIS WAY
$renderer = LightnCandy::prepare($phpStr);


// Step 2B. (Usage 2) Store your render function in a file 
//   You decide your compiled template file path and name, save it.
//   You can load your render function by include() later.
//   RECOMMENDED WAY
file_put_contents($php_inc, $phpStr);
$renderer = include($php_inc);


// Step 3. run native PHP render function any time
echo "Template is:\n$template\n\n";
echo $renderer(Array('name' => 'John', 'value' => 10000));
echo $renderer(Array('name' => 'Peter', 'value' => 1000));
```

The output will be:

```
Template is:
Welcome {{name}} , You win ${{value}} dollars!!


Welcome John , You win $10000 dollars!!
Welcome Peter , You win $1000 dollars!!
```

CONSTANTS
---------

You can apply more flags by running `LightnCandy::compile($php, $options)` , for example:

```php
LightnCandy::compile($template, Array(
    'flags' => LightnCandy::FLAG_ERROR_LOG | LightnCandy::FLAG_STANDALONE
));
```

Default is to compile the template as PHP which can be run as fast as possible (flags = `FLAG_BESTPERFORMANCE`).

* `FLAG_ERROR_LOG` : output error_log when found any template error
* `FLAG_ERROR_EXCEPTION` : throw exception when found any template error
* `FLAG_STANDALONE` : generate stand alone PHP codes which can be execute without including LightnCandy. The compiled PHP code will contain scoped user function, somehow larger. And, the performance of the template will slow 1 ~ 10%.
* `FLAG_JSTRUE` : generate 'true' when value is true (handlebars.js behavior). Otherwise, true will generate ''.
* `FLAG_JSOBJECT` : generate '[object Object]' for associated array, generate ',' separated values for array (handlebars.js behavior). Otherwise, all PHP array will generate ''.
* `FLAG_THIS` : support `{{this}}` or `{{.}}` in template. Otherwise, `{{this}}` and `{{.}}` will cause template error.
* `FLAG_WITH` : support `{{#with var}}` in template. Otherwise, `{{#with var}}` will cause template error.
* `FLAG_PARENT` : support `{{../var}}` in template. Otherwise, `{{../var}}` will cause template error.
* `FLAG_JSQUOTE` : encode `'` to `&#x27;` . Otherwise, `'` will encoded as `&#039;` .
* `FLAG_ADVARNAME` : support `{{foo.[0].[#te#st].bar}}` style advanced variable naming in template.
* `FLAG_NAMEDARG` : support named arguments for custom helper `{{helper name1=val1 nam2=val2 ...}}.
* `FLAG_EXTHELPER` : do not including custom helper codes into compiled PHP codes. This reduces the code size, but you need to take care of your helper functions when rendering. If you forget to include required functions when execute rendering function, `undefined function` runtime error will be triggered. NOTE: Anonymous functions will always be placed into generated codes.
* `FLAG_SPACECTL` : support space control `{{~ }}` or `{{ ~}}` in template. Otherwise, `{{~ }}` or `{{ ~}}` will cause template error. 
* `FLAG_JS` : simulate all JavaScript string conversion behavior, same with `FLAG_JSTRUE` + `FLAG_JSOBJECT`.
* `FLAG_HANDLEBARS` : support all handlebars extensions (which mustache do not supports) , same with `FLAG_THIS` + `FLAG_WITH` + `FLAG_PARENT` + `FLAG_JSQUOTE` + `FLAG_ADVARNAME` + `FLAG_NAMEDARG`.
* `FLAG_HANDLEBARSJS` : align with handlebars.js behaviors, same with `FLAG_JS` + `FLAG_HANDLEBARS`.
* `FLAG_ECHO` : compile to `echo 'a', $b, 'c';` to improve performance. This will slow down rendering when the template and data are simple, but will improve 1% ~ 7% when the data is big and looping in the template.
* `FLAG_BESTPERFORMANCE` : same with `FLAG_ECHO` now. This flag may be changed base on performance testing result in the future.

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

LightnCandy supports parent context access in partial (access `{{../vars}}` inside the template), so far no other PHP/JavaScript library can handle this correctly.

Custom Helper
-------------

Custom helper can help you deal with common template tasks, for example: provide URL and text then generate a link. To know more about custom helper, you can read original handlebars.js document here: http://handlebarsjs.com/expressions.html . 

**NOTICE**: custom helpers to handle single tag `{{xxx}}` or a section `{{#yyy}} ... {{/yyy}}` are absolutely different in LightnCandy. Too know more about creating custom helpers to handle `{{#yyy}} ... {{/yyy}}`, please refer to <a href="#block-custom-helper">Block Custom Helper</a>.

When `compile()`, LightnCandy will lookup helpers from generated custom helper name table. You can register custom helpers with `helpers` option.  For example:

```php
LightnCandy::compile($template, Array(
    'helpers' => Array(
        // 1. You may pass your function name
        //    When the function is not exist, you get compile time error
        //    In this case, the helper name is same with function name
        //    Tempalte: {{my_helper_functoin ....}}
        'my_helper_function',

        // 2. You may also provide a static call from a class
        //    In this case, the helper name is same with provided full name
        //    **DEPRECATED** It is not valid in handlebars.js 
        //    Tempalte: {{myClass::myStaticMethod ....}}
        'myClass::myStaticMethod',

        // 3. You may also provide an alias name for helper function
        //    This help you to mapping different function to a preferred helper name
        //    Tempalte: {{helper_name ....}}
        'helper_name' => 'my_other_helper',

        // 4. Alias also works well for static call of a class
        //    This help you to mapping different function to a preferred helper name
        //    Tempalte: {{helper_name2 ....}}
        'helper_name2' => 'myClass::func',

        // 5. Anonymous function should be provided with alias
        //    The function will be included in generaed code always
        //    Tempalte: {{helper_name3 ....}}
        'helper_name3' => function ($arg1, $arg2) {
            return "<a href=\"{$arg1}\">{$arg2}</a>";
        }
    )
));
```

Custom Helper Interface
-----------------------

The input arguments are processed by LightnCandy automatically, you do not need to worry about variable name processing or current context. You can also use double quoted string as input, for example:

```
{{{helper name}}}           // This send processed {{{name}}} into the helper
{{{helper ../name}}}        // This send processed {{{../name}}} into the helper
{{{helper "Test"}}}         // This send the string "Test" into the helper
{{helper "Test"}}           // This send the string "Test" into the helper and HTML encode the helper result
{{{helper "Test" ../name}}} // This send string "Test" as first parameter,
                            // and processed {{{../name}}} as second parameter into the helper
```

The return value of your custom helper should be a string. When your custom helper be executed from {{ }} , the return value will be HTML encoded. You may execute your helper by {{{ }}} , then the original helper return value will be outputted directly.

When you pass arguments as `name=value` pairs, the input to your custom helper will turn into only one associative array. For example, when your custom helper is `function ($input) {...}`:

```
{{{helper name=value}}        // This send processed {{{value}}} into $input['name']
{{{helper name="value"}}      // This send the string "value" into $input['name']
{{{helper [na me]="value"}}   // You can still protect the name with [ ]
                              // so you get $input['na me'] as the string 'value'
{{{helper url name="value"}}  // This send processed {{{url}}}  into $input[0]
                              // and the string "value" into $input['name']
```

Block Custom Helper
-------------------

Block custom helper must be used as a section, the section is started with `{{#helper_name ...}}` and ended with `{{/helper_name}}`.

You may use block custom helper to:

1. Provide advanced condition logic which is different from `{{#if ...}}` ... `{{/if}}` .
2. Modify current context for the inner block.
3. Provide different context to the inner block.

You can register block custom helpers with `blockhelpers` option.  For example:

```php
LightnCandy::compile($template, Array(
    'blockhelpers' => Array(    // The usage of blockhelpers option is similar with helpers option.
        'my_helper_function',   // You can use function name, class name with static method,
        ...                     // and choose preferred helper name by providing key name.
    )
));
```

Block Custom Helper Interface
-----------------------------

LightnCandy handled all input arguments for you, you will receive current context and parsed arguments. The return value of helper function will become new context then be passed into inner block. If you do not return any value, or return null, the inner block will not be rendered. For example:

```php
// Only render inner block when input > 5
// {{#helper_iffivemore people.length}}More then 5 people, discount!{{/helper_iffivemore}}
function helper_iffivemore($cx, $args) {
    return $args[0] > 5 ? $cx : null;
}

// You can use named arguments, too
// {{#helper_if value=people logic="more" tovalue=5}}Yes the logic is true{{/helper_if}}
function helper_if($cx, $args) {
    switch ($args['logic']) {
    case 'more':
        return $args['value'] > $args['tovalue'] ? $cx : null;
    case 'less':
        return $args['value'] < $args['tovalue'] ? $cx : null;
    case 'eq':
        return $args['value'] == $args['tovalue'] ? $cx : null;
    }
}

// Provide default values for name and salary
// {{#helper_defaultpeople}}Hello, {{name}} ....Your salary will be {{salary}}{{/helper_defaultpeople}}
function helper_defaultpeople($cx, $args) {
    if (!isset($cx['name'])) {
        $cx['name'] = 'Sir';
    }
    $cx['salary'] = isset($cx['salary']) ? '$' . $cx['salary'] : 'unknown';
    return $cx;
}

// Provide specific context to innerblock
// {{#helper_sample}}Preview Name:{{name}} , Salary:{{salary}}.{{/helper_sample}}
function helper_sample($cx, $args) {
    return Array('name' => 'Sample Name', 'salary' => 'Sample Salary');
}
```

You cannot provide new rendered result, nor handle loop in your block custom helper. To provide different rendering result, you should use <a href="#custom-helper">custom helper</a>. To handle loop, you should use `{{#each}}` . For example:

```php
// Provide specific context to innerblock, then loop on it.
// {{#helper_categories}}{{#each .}}<li><a href="?id={{id}}">{{name}}</a></li>{{/each}}{{/helper_categories}}
function helper_categories($cx, $args) {
    return getMyCategories(); // Array('category1', 'category2', ...)
}
```

The mission of a block custom helper is only focus on providing different context or logic to inner block, nothing else.

Unsupported Feature (so far)
----------------------------

* [NEVER] `{{foo/bar}}` style variable name, it is deprecated in official handlebars.js document.
* [Plan to support] set delimiter (change delimiter from `{{ }}` to custom string, for example `<% then %>`)
* [Possible] input as Object and methods (now only accept associative array data structure)

Suggested Handlebars Template Practices
---------------------------------------

* Prevent to use `{{#with}}` . I think `{{path.to.val}}` is more readable then `{{#with path.to}}{{val}}{{/with}}`; when using `{{#with}}` you will confusing on scope changing. `{{#with}}` only save you very little time when you access many variables under same path, but cost you a lot time when you need to understand then maintain a template.
* use `{{{val}}}` when you do not require HTML encoded output on the value. It is better performance, too.
* Prevent to use custom helper if you want to reuse your template in different language. Or, you may need to implement different versions of helper in different languages.
* For best performance, you should only use 'compile on demand' pattern when you are in development stage. Before you go to production, you can `LightnCandy::compile()` on all your templates, save all generated PHP codes, and deploy these generated files (You may need to maintain a build process for this) . **DO NOT COMPILE ON PRODUCTION** , it also a best practice for security. Adding cache for 'compile on demand' is not the best solution. If you want to build some library or framework based on LightnCandy, think about this scenario.
* Recompile your templates when you upgrade LightnCandy every time.

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
* `{{value}}` : HTML encoded variable
   * true as 'true' (require `FLAG_JSTRUE`)
   * false as ''
* `{{{path.to.value}}}` : dot notation, raw
* `{{path.to.value}}` : dot notation, HTML encoded
* `{{.}}` : current context, HTML encoded (require `FLAG_THIS`)
* `{{this}}` : current context, HTML encoded (require `FLAG_THIS`)
* `{{{.}}}` : current context, raw (require `FLAG_THIS`)
* `{{{this}}}` : current context, raw (require `FLAG_THIS`)
* `{{#value}}` : section
   * false, undefined and null will skip the section
   * true will run the section with original scope
   * All others will run the section with new scope (includes 0, 1, -1, '', '1', '0', '-1', 'false', Array, ...)
* `{{/value}}` : end section
* `{{^value}}` : inverted section
   * false, undefined and null will run the section with original scope
   * All others will skip the section (includes 0, 1, -1, '', '1', '0', '-1', 'false', Array, ...)
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
* `{{foo.[ba.r].[#spec].0.ok}}` : reference to $CurrentConext['foo']['ba.r']['#spec'][0]['ok'] (require `FLAG_ADVARNAME`)
* `{{~any_valid_tag}}` : Space control, remove all previous spacing (includes CR/LF, tab, space; stop on any none spacing character) (require `FLAG_SPACECTL`)
* `{{any_valid_tag~}}` : Space control, remove all next spacing (includes CR/LF, tab, space; stop on any none spacing character) (require `FLAG_SPACECTL`)
* `{{{helper var}}}` : Execute custom helper then render the result
* `{{helper var}}` : Execute custom helper then render the HTML encoded result
* `{{helper name1=var name2="str"}}` : Execute custom helper with named arguments
* `{{#helper ...}}...{{/helper}}` : Execute block custom helper
