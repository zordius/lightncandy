LightnCandy
===========

⚡🍭 An extremely fast PHP implementation of handlebars ( http://handlebarsjs.com/ ) and mustache ( http://mustache.github.io/ ).

Travis CI status: [![Unit testing](https://travis-ci.org/zordius/lightncandy.svg?branch=master)](https://travis-ci.org/zordius/lightncandy) [![Regression testing](https://travis-ci.org/zordius/HandlebarsTest.svg?branch=master)](https://travis-ci.org/zordius/HandlebarsTest)

Scrutinizer CI status: [![Code Coverage](https://img.shields.io/scrutinizer/coverage/g/zordius/lightncandy.svg)](https://scrutinizer-ci.com/g/zordius/lightncandy/)

Package on packagist: [![Latest Stable Version](https://poser.pugx.org/zordius/lightncandy/v/stable.svg)](https://packagist.org/packages/zordius/lightncandy) [![License](https://poser.pugx.org/zordius/lightncandy/license.svg)](https://github.com/zordius/lightncandy/blob/master/LICENSE.txt) [![Total Downloads](https://poser.pugx.org/zordius/lightncandy/downloads)](https://packagist.org/packages/zordius/lightncandy) [![HHVM Status](http://hhvm.h4cc.de/badge/zordius/lightncandy.svg)](http://hhvm.h4cc.de/package/zordius/lightncandy)

Features
--------

* Logicless template: mustache ( http://mustache.github.com/ ) or handlebars ( http://handlebarsjs.com/ ) .
* Compile template to **pure PHP** code. Examples:
   * <a href="https://github.com/zordius/HandlebarsTest/blob/master/fixture/001-simple-vars.tmpl">Template A</a> generated <a href="https://github.com/zordius/HandlebarsTest/blob/master/fixture/001-simple-vars.php">PHP A</a>
   * <a href="https://github.com/zordius/HandlebarsTest/blob/master/fixture/016-hb-eachthis.tmpl">Template B</a> generated <a href="https://github.com/zordius/HandlebarsTest/blob/master/fixture/016-hb-eachthis.php">PHP B</a>
* **FAST!**
   * Runs 3~6 times faster than <a href="https://github.com/bobthecow/mustache.php">mustache.php</a>.
   * Runs 2~7 times faster than <a href="https://github.com/dingram/mustache-php">mustache-php</a>.
   * Runs 15~50 times faster than <a href="https://github.com/XaminProject/handlebars.php">handlebars.php</a>.
   * Detail performance test reports can be found <a href="https://github.com/zordius/HandlebarsTest">here</a>, go http://zordius.github.io/HandlebarsTest/ to see charts.
* **SMALL!** all PHP files in 196K
* **ROBUST!**
   * 100% supports <a href="https://github.com/mustache/spec">mustache spec v1.1.3</a>. For the optional lambda module, supports 4 of 10 specs.
   * Supports almost all <a href="https://github.com/jbboehr/handlebars-spec">handlebars.js spec</a>
   * Output <a href="https://github.com/zordius/HandlebarsTest/blob/master/FEATURES.md">SAME</a> with <a href="https://github.com/wycats/handlebars.js">handlebars.js</a>
* **FLEXIBLE!**
   * Lot of <a href="#compile-options">options</a> to change features and behaviors.
* Context generation
   * Analyze used features from your template (execute `LightnCandy::getContext()` to get it) .
* Debug
   * <a href="#template-debugging">Generate debug version template</a>
      * Find out missing data when rendering template.
      * Generate visually debug template.
* Standalone Template
   * The compiled PHP code can run without any PHP library. You do not need to include LightnCandy when execute rendering function.

Installation
------------

Use Composer ( https://getcomposer.org/ ) to install LightnCandy:

```
composer require zordius/lightncandy:dev-master
```

**UPGRADE NOTICE**

* Please check <a href="HISTORY.md">HISTORY.md</a> for versions history.
* Please check <a href="UPGRADE.md">UPGRADE.md</a> for upgrade notice.

Usage
-----
```php
// THREE STEPS TO USE LIGHTNCANDY
// Step 1. use LightnCandy
use LightnCandy\LightnCandy;

$template = "Welcome {{name}} , You win \${{value}} dollars!!\n";
$phpStr = LightnCandy::compile($template);  // compiled PHP code in $phpStr

// Step 2A. (Usage 1) use LightnCandy::prepare to get rendering function
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
file_put_contents($php_inc, '<?php ' . $phpStr . '?>');
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

Compile Options
---------------

You can apply more options by running `LightnCandy::compile($template, $options)`:

```php
LightnCandy::compile($template, Array(
    'flags' => LightnCandy::FLAG_ERROR_LOG | LightnCandy::FLAG_STANDALONEPHP
));
```

Default is to compile the template as PHP, which can be run as fast as possible (flags = `FLAG_BESTPERFORMANCE`).

* <a href="https://zordius.github.io/HandlebarsCookbook/LC-FLAG_ERROR_LOG.html">FLAG_ERROR_LOG</a>
* <a href="https://zordius.github.io/HandlebarsCookbook/LC-FLAG_ERROR_EXCEPTION.html">FLAG_ERROR_EXCEPTION</a>
* <a href="https://zordius.github.io/HandlebarsCookbook/LC-FLAG_ERROR_SKIPPARTIAL.html">FLAG_ERROR_SKIPPARTIAL</a>
* <a href="https://zordius.github.io/HandlebarsCookbook/LC-FLAG_NOESCAPE.html">FLAG_NOESCAPE</a>
* <a href="https://zordius.github.io/HandlebarsCookbook/LC-FLAG_STANDALONEPHP.html">FLAG_STANDALONEPHP</a>
* <a href="https://zordius.github.io/HandlebarsCookbook/LC-FLAG_JSTRUE.html">FLAG_JSTRUE</a>
* <a href="https://zordius.github.io/HandlebarsCookbook/LC-FLAG_JSOBJECT.html">FLAG_JSOBJECT</a>
* <a href="https://zordius.github.io/HandlebarsCookbook/LC-FLAG_THIS.html">FLAG_THIS</a>
* <a href="https://zordius.github.io/HandlebarsCookbook/LC-FLAG_PARENT.html">FLAG_PARENT</a>
* <a href="https://zordius.github.io/HandlebarsCookbook/LC-FLAG_HBESCAPE.html">FLAG_HBESCAPE</a>
* <a href="https://zordius.github.io/HandlebarsCookbook/LC-FLAG_ADVARNAME.html">FLAG_ADVARNAME</a>
* <a href="https://zordius.github.io/HandlebarsCookbook/LC-FLAG_NAMEDARG.html">FLAG_NAMEDARG</a>
* `FLAG_EXTHELPER` : do not including custom helper codes into compiled PHP codes. This reduces the code size, but you need to take care of your helper functions when rendering. If you forget to include required functions when execute rendering function, `undefined function` runtime error will be triggered. NOTE: Anonymous functions will always be placed into generated codes.
* <a href="https://zordius.github.io/HandlebarsCookbook/LC-FLAG_RUNTIMEPARTIAL.html">FLAG_RUNTIMEPARTIAL</a>
* `FLAG_PARTIALNEWCONTEXT` : create a new context for the partial, the behavior is same with handlebars.js explicitPartialContext compile time option.
* `FLAG_SLASH` : Skip a delimiter when it behind `\` .
* `FLAG_ELSE` : support `{{else}}` or `{{^}}` as handlebars specification. Otherwise, `{{else}}` will be resolved as normal variable , and {{^}} will cause template error.
* `FLAG_RAWBLOCK`: support `{{{{raw_block}}}} any char or {{foo}} as none parsed raw string {{{{/raw_block}}}}`.
* `FLAG_PROPERTY` : support object instance attribute access. You MUST apply this if your data contains object. And, the rendering performance will be worse.
* `FLAG_METHOD` : support object instance method access. You MUST apply this if your data contains object. And, the rendering performance will be worse.
* `FLAG_INSTANCE` : same with `FLAG_PROPERTY` + `FLAG_METHOD`
* `FLAG_SPACECTL` : support space control `{{~ }}` or `{{ ~}}` in template. Otherwise, `{{~ }}` or `{{ ~}}` will cause template error.
* `FLAG_IGNORESTANDALONE` : prevent standalone detection on `{{#foo}}`, `{{/foo}}` or `{{^}}`, the behavior is same with handlebars.js ignoreStandalone compile time option.
* `FLAG_STRINGPARAMS` : pass variable name as string to helpers, the behavior is same with handlebars.js stringParams compile time option.
* `FLAG_KNOWNHELPERSONLY`: Only pass current context to lambda, the behavior is same with handlebars.js knownHelpersOnly compile time option.
* `FLAG_SPVARS` : support special variables include @root, @index, @key, @first, @last. Otherwise, compile these variable names with default parsing logic.
* `FLAG_HANDLEBARSLAMBDA` : support lambda logic as handlebars.js specification. And, the rendering performance will be worse.
* `FLAG_JS` : simulate all JavaScript string conversion behavior, same with `FLAG_JSTRUE` + `FLAG_JSOBJECT`.
* `FLAG_HANDLEBARS` : support most handlebars extensions and also keep performance good, same with `FLAG_THIS` + `FLAG_WITH` + `FLAG_PARENT` + `FLAG_HBESCAPE` + `FLAG_ADVARNAME` + `FLAG_NAMEDARG` + `FLAG_SLASH` + `FLAG_ELSE` + `FLAG_RAWBLOCK`.
* `FLAG_HANDLEBARSJS` : support most handlebars.js + javascript behaviors and also keep performance good, same with `FLAG_JS` + `FLAG_HANDLEBARS`.
* `FLAG_HANDLEBARSJS_FULL` : enable all supported handlebars.js behaviors but performance drop, same with `FLAG_HANDLEBARSJS` + `FLAG_INSTANCE` + `FLAG_RUNTIMEPARTIAL` + `FLAG_MUSTACHELOOKUP` + `FLAG_HANDLEBARSLAMBDA`.
* `FLAG_MUSTACHELOOKUP` : align recursive lookup up behaviors with mustache specification. And, the rendering performance will be worse.
* `FLAG_MUSTACHELAMBDA` : support simple lambda logic as mustache specification. And, the rendering performance will be worse.
* `FLAG_PREVENTINDENT` : align partial indent behavior with mustache specification. This is same with handlebars.js preventIndent copmile time option.
* `FLAG_NOHBHELPERS` : Do not compile handlebars.js builtin helpers. With this option, `{{#with}}`, `{{#if}}`, `{{#unless}}`, `{{#each}}` means normal section, and `{{#with foo}}`, `{{#if foo}}`, `{{#unless foo}}`, `{{#each foo}}` will cause compile error.
* `FLAG_MUSTACHE` : support all mustache specification but performance drop, same with `FLAG_ERROR_SKIPPARTIAL` + `FLAG_MUSTACHELOOKUP` + `FLAG_MUSTACHELAMBDA` + `FLAG_NOHBHELPERS` + `FLAG_RUNTIMEPARTIAL` + `FLAG_JS`.
* `FLAG_ECHO` : compile to `echo 'a', $b, 'c';` to improve performance. This will slow down rendering when the template and data are simple, but will improve 5% ~ 10% when the data is big and looping in the template.
* `FLAG_BESTPERFORMANCE` : same with `FLAG_ECHO` + `FLAG_STANDALONEPHP` now. This flag may be changed base on performance testing result in the future.
* `FLAG_RENDER_DEBUG` : generate debug template to show error when rendering. With this flag, the performance of rendering may be slowed.

Partial Support
---------------

* <a href="https://zordius.github.io/HandlebarsCookbook/0011-partial.html">Example of compile time partial</a>
* <a href="https://zordius.github.io/HandlebarsCookbook/0024-partialcontext.html">Example of partial context changing</a>

You can use `partialresolver` option to create your own partial loader:

```php
LightnCandy::compile($template, Array(
    'partialsresolver' => function ($context, $name) {
        return MyPartialLoader($name); // Return partial content
    }
));
```

Dynamic Partial
---------------

You can use dynamic partial name by passing a custom helper as subexpression syntax, for example: `{{> (foo)}}` . the return value of custom helper `foo` will be the partial name.

```php
$php = LightnCandy::compile('{{> (partial_name_helper obj_type)}}', Array(
    'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_RUNTIMEPARTIAL,
    'helpers' => Array(
        'partial_name_helper' => function ($args) {
            switch ($args[0]) {
                ....
            }
        }
    ),
    'partials' => Array(
        'people' => 'This is {{name}}, he is {{age}} years old.',
        'animal' => 'This is {{name}}, it is {{age}} years old.',
    )
));

$renderer = LightnCandy::prepare($php);

// Will use people partial and output: 'This is John, he is 15 years old.'
echo $renderer(Array(
   'obj_type' => 'people',
   'name' => 'John',
   'age' => '15',
));
```

When you using dynamic partial, LightnCandy will compile all partials inside the `partials` option into template. This makes the generated code larger, but this can make sure all partials are included for rendering. (TODO: add an example to show how to provide partials across templates to reduce size)

Custom Helper
-------------

Custom helper can help you deal with common template tasks, for example: provide URL and text then generate a link. To know more about custom helper, you can read original handlebars.js document here: http://handlebarsjs.com/expressions.html . 

When `compile()`, LightnCandy will lookup helpers from generated custom helper name table. You can register custom helpers with `helpers` option (**NOTICE**: `FLAG_NAMEDARG` is required for named arguments, `FLAG_ADVARNAME` is required for string or subexpression arguments):

```php
LightnCandy::compile($template, Array(
    // FLAG_NAMEDARG is required if you want to use named arguments
    'flags' => LightnCandy::FLAG_HANDLEBARS
    'helpers' => Array(
        // 1. You may pass your function name
        //    When the function is not exist, you get compile time error
        //    In this case, the helper name is same with function name
        //    Template: {{my_helper_functoin ....}}
        'my_helper_function',

        // 2. You may also provide a static call from a class
        //    In this case, the helper name is same with provided full name
        //    **DEPRECATED** It is not valid in handlebars.js 
        //    Template: {{myClass::myStaticMethod ....}}
        'myClass::myStaticMethod',

        // 3. You may also provide an alias name for helper function
        //    This help you to mapping different function to a preferred helper name
        //    Template: {{helper_name ....}}
        'helper_name' => 'my_other_helper',

        // 4. Alias also works well for static call of a class
        //    This help you to mapping different function to a preferred helper name
        //    Template: {{helper_name2 ....}}
        'helper_name2' => 'myClass::func',

        // 5. Anonymous function should be provided with alias
        //    The function will be included in generaed code always
        //    Template: {{helper_name3 ....}}
        'helper_name3' => function ($arg1, $arg2) {
            return "<a href=\"{$arg1}\">{$arg2}</a>";
        }
    )
));
```

Custom Helper Interface
-----------------------

The input arguments are processed by LightnCandy automatically, you do not need to worry about variable name processing or current context. You can also use double quoted string as input:

```
{{{helper name}}}           // This send processed {{{name}}} into the helper
{{{helper ../name}}}        // This send processed {{{../name}}} into the helper
{{{helper "Test"}}}         // This send the string "Test" into the helper (FLAG_ADVARNAME is required)
{{helper "Test"}}           // This send the string "Test" into the helper and escape the helper result
{{{helper "Test" ../name}}} // This send string "Test" as first parameter,
                            // and processed {{{../name}}} as second parameter into the helper
```

In your template:

```
{{{helper name=value}}}        // This send processed {{{value}}} into $options['hash']['name']
{{{helper name="value"}}}      // This send the string "value" into $options['hash']['name']
{{{helper [na me]="value"}}}   // You can still protect the name with [ ]
                               // so you get $options['hash']['na me'] as the string 'value'
{{{helper url name="value"}}}  // This send processed {{{url}}} into first argument
                               // and the string "value" into $options['hash']['name']
```

Custom Helper Escaping
----------------------

The return value of your custom helper should be a string. When your custom helper be executed from {{ }} , the return value will be HTML escaped. You may execute your helper by {{{ }}} , then the original helper return value will be outputted directly.

If you return a LightnCandy\SafeString object, it will not be html escaped.

```php
// escaping is handled by lightncandy and decided by template
// if the helper is in {{ }} , you get 'The U&amp;ME Helper is ececuted!'
// if the helper is in {{{ }}} , you get 'The U&ME Helper is executed!'
return 'The U&ME Helper is executed!';

// Do not escape anything.
// No matter in {{ }} or {{{ }}} , you get 'Exact&Same output \' \" Ya!'
return new LightnCandy\SafeString('Exact&Same output \' " Ya!');

// Force to escape the result.
// No matter in {{ }} or {{{ }}} , you get 'Not&amp;Same output &#039; &quot; Ya!'
return new LightnCandy\SafeString('Not&Same output \' " Ya!', true);

// Force to escape the result in handlebars.js way
// No matter in {{ }} or {{{ }}} , you get 'Not&amp;Same output &#x27; &quot; Ya!'
return new LightnCandy\SafeString('Not&Same output \' " Ya!', 'encq');
```

Custom Helper Examples
----------------------

**#mywith (context change)**
* LightnCandy
```php
// LightnCandy sample, #mywith works same with #with
$php = LightnCandy::compile($template, Array(
    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
    'helpers' => Array(
        'mywith' => function ($context, $options) {
            return $options['fn']($context);
        }
    )
));
```

* Handlebars.js
```javascript
// Handlebars.js sample, #mywith works same with #with
Handlebars.registerHelper('mywith', function(context, options) {
    return options.fn(context);
});
```

**#myeach (context change)**
* LightnCandy
```php
// LightnCandy sample, #myeach works same with #each
$php = LightnCandy::compile($template, Array(
    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
    'helpers' => Array(
        'myeach' => function ($context, $options) {
            $ret = '';
            foreach ($context as $cx) {
                $ret .= $options['fn']($cx);
            }
            return $ret;
        }
    )
));
```

* Handlebars.js
```javascript
// Handlebars.js sample, #myeach works same with #each
Handlebars.registerHelper('myeach', function(context, options) {
    var ret = '', i, j = context.length;
    for (i = 0; i < j; i++) {
        ret = ret + options.fn(context[i]);
    }
    return ret;
});
```

**#myif (no context change)**
* LightnCandy
```php
// LightnCandy sample, #myif works same with #if
$php = LightnCandy::compile($template, Array(
    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
    'helpers' => Array(
        'myif' => function ($conditional, $options) {
            if ($conditional) {
                return $options['fn']();
            } else {
                return $options['inverse']();
            }
        }
    )
));
```

* Handlebars.js
```javascript
// Handlebars.js sample, #myif works same with #if
Handlebars.registerHelper('myif', function(conditional, options) {
    if (conditional) {
        return options.fn(this);
    } else {
        return options.inverse(this);
    }
});
```

You can use `isset($options['fn'])` to detect your custom helper is a block or not; you can also use `isset($options['inverse'])` to detect the existence of `{{else}}`.

**Hashed arguments**
* LightnCandy
```php
$php = LightnCandy::compile($template, Array(
    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
    'helpers' => Array(
        'sample' => function ($arg1, $arg2, $options) {
            // All hashed arguments are in $options['hash']
        }
    )
));
```

* Handlebars.js
```javascript
Handlebars.registerHelper('sample', function(arg1, arg2, options) {
    // All hashed arguments are in options.hash
});
```

**Data variables and context**

You can get special data variables from `$options['data']`. Using `$options['_this']` to receive current context.

```php
$php = LightnCandy::compile($template, Array(
    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
    'helpers' => Array(
        'getRoot' => function ($options) {
            print_r($options['_this']); // dump current context
            return $options['data']['root']; // same as {{@root}}
        }
    )
));
```

* Handlebars.js
```javascript
Handlebars.registerHelper('getRoot', function(options) {
    console.log(this); // dump current context
    return options.data.root; // same as {{@root}}
});
```

**Private variables**

You can inject private variables into inner block when you execute child block with second parameter. The example code showed similar behavior with `{{#each}}` which sets index for child block and can be accessed with `{{@index}}`.

* LightnCandy
```php
$php = LightnCandy::compile($template, Array(
    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
    'helpers' => Array(
        'list' => function ($context, $options) {
            $out = '';
            $data = $options['data'];

            foreach ($context as $idx => $cx) {
                $data['index'] = $idx;
                $out .= $options['fn']($cx, Array('data' => $data));
            }

            return $out;
        }
    )
));
```

* Handlebars.js
```javascript
Handlebars.registerHelper('list', function(context, options) {
  var out = '';
  var data = options.data ? Handlebars.createFrame(options.data) : undefined;

  for (var i=0; i<context.length; i++) {
    if (data) {
      data.index = i;
    }
    out += options.fn(context[i], {data: data});
  }
  return out;
});
```

**Escaping**

When a Handlebars.js style custom helper be used as block tags, LightnCandy will not escape the result. When it is a single {{...}} tag, LightnCandy will escape the result. To change the escape behavior, you can return extended information by Array(), please read <a href="#custom-helper-escaping">Custom Helper Escaping</a> for more.

Change Delimiters
-----------------

You may change delimiters from `{{` and `}}` to other strings. In the template, you can use `{{=<% %>=}}` to change delimiters to `<%` and `%>` , but the change will not affect included partials.

If you want to change default delimiters for a template and all included partials, you may `compile()` it with `delimiters` option:

```php
LightnCandy::compile('I wanna use <% foo %> as delimiters!', Array(
    'delimiters' => array('<%', '%>')
));
```

Template Debugging
------------------

When template error happened, LightnCandy::compile() will return false. You may compile with `FLAG_ERROR_LOG` to see more error message, or compile with `FLAG_ERROR_EXCEPTION` to catch the exception.

You may generate debug version of templates with `FLAG_RENDER_DEBUG` when compile() . The debug template contained more debug information and slower (TBD: performance result) , you may pass extra LightnCandy\Runtime options into render function to know more rendering error (missing data). For example:

```php
$template = "Hello! {{name}} is {{gender}}.
Test1: {{@root.name}}
Test2: {{@root.gender}}
Test3: {{../test3}}
Test4: {{../../test4}}
Test5: {{../../.}}
Test6: {{../../[test'6]}}
{{#each .}}
each Value: {{.}}
{{/each}}
{{#.}}
section Value: {{.}}
{{/.}}
{{#if .}}IF OK!{{/if}}
{{#unless .}}Unless not OK!{{/unless}}
";

// compile to debug version
$php = LightnCandy::compile($template, Array(
    'flags' => LightnCandy::FLAG_RENDER_DEBUG | LightnCandy::FLAG_HANDLEBARSJS
));

// Get the render function
$renderer = LightnCandy::prepare($php);

// error_log() when missing data:
//   LightnCandy\Runtime: [gender] is not exist
//   LightnCandy\Runtime: ../[test] is not exist
$renderer(Array('name' => 'John'), array('debug' => LightnCandy\Runtime::DEBUG_ERROR_LOG));

// Output visual debug template with ANSI color:
echo $renderer(Array('name' => 'John'), array('debug' => LightnCandy\Runtime::DEBUG_TAGS_ANSI));

// Output debug template with HTML comments:
echo $renderer(Array('name' => 'John'), array('debug' => LightnCandy\Runtime::DEBUG_TAGS_HTML));
```

The ANSI output will be: 

<a href="tests/example_debug.php"><img src="https://github.com/zordius/lightncandy/raw/master/example_debug.png"/></a>

Here are the list of LightnCandy\Runtime debug options for render function:

* `DEBUG_ERROR_LOG` : error_log() when missing required data
* `DEBUG_ERROR_EXCEPTION` : throw exception when missing required data
* `DEBUG_TAGS` : turn the return value of render function into normalized mustache tags
* `DEBUG_TAGS_ANSI` : turn the return value of render function into normalized mustache tags with ANSI color
* `DEBUG_TAGS_HTML` : turn the return value of render function into normalized mustache tags with HTML comments

Preprocess Partials
-------------------

If you want to do extra process before the partial be compiled, you may use `prepartial` when `compile()`. For example, this sample adds HTML comments to identify the partial by the name:

```php
$php = LightnCandy::compile($template, Array(
    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
    'prepartial' => function ($context, $template, $name) {
        return "<!-- partial start: $name -->$template<!-- partial end: $name -->";
    }
));
```

You may also extend <a href="https://zordius.github.io/lightncandy/class-LightnCandy.Partial.html">LightnCandy\Partial</a> by override the <a href="https://zordius.github.io/lightncandy/class-LightnCandy.Partial.html#_prePartial">prePartial()</a> static method to turn your preprocess into a built-in feature.

Customize Render Function
-------------------------

If you want to do extra tasks inside render function or add more comment, you may use `renderex` when `compile()` . For example, this sample embed the compile time comment into the template:

```php
$php = LightnCandy::compile($template, Array(
    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
    'renderex' => '// Compiled at ' . date('Y-m-d h:i:s')
));
```

Your render function will be:

```php
function ($in) {
    $cx = array(...);
    // compiled at 1999-12-31 00:00:00
    return .....
}
```

Please make sure the passed in `renderex` is valid PHP, LightnCandy will not check it.

Customize Rendering Runtime Class
---------------------------------

If you want to extend `LightnCandy\Runtime` class and replace the default runtime library, you may use `runtime` when `compile()` . For example, this sample will generate render function based on your extended `MyRunTime`:

```php
// Customized runtime library to debug {{{foo}}}
class MyRunTime extends LightnCandy\Runtime {
    public static function raw($cx, $v) {
        return '[[DEBUG:raw()=>' . var_export($v, true) . ']]';
    }
}

// Use MyRunTime as runtime library
$php = LightnCandy::compile($template, Array(
    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
    'runtime' => 'MyRunTime'
));
```

Please make sure `MyRunTime` exists when compile() or rendering based on your `FLAG_STANDALONEPHP` .

Unsupported Feature (so far)
----------------------------

* [NEVER] `{{foo/bar}}` style variable name, it is deprecated in official handlebars.js document, please use this style: `{{foo.bar}}`.

Suggested Handlebars Template Practices
---------------------------------------

* Prevent to use `{{#with}}` . I think `{{path.to.val}}` is more readable then `{{#with path.to}}{{val}}{{/with}}`; when using `{{#with}}` you will confusing on scope changing. `{{#with}}` only save you very little time when you access many variables under same path, but cost you a lot time when you need to understand then maintain a template.
* use `{{{val}}}` when you do not require HTML escaped output on the value. It is better performance, too.
* If you wanna display `{{`, use this: `{{#with "{{"}}{{.}}{{/with}}`.
* Prevent to use custom helper if you want to reuse your template in different language. Or, you may need to implement different versions of helper in different languages.
* For best performance, you should only use 'compile on demand' pattern when you are in development stage. Before you go to production, you can `LightnCandy::compile()` on all your templates, save all generated PHP codes, and deploy these generated files (You may need to maintain a build process for this) . **DO NOT COMPILE ON PRODUCTION** , it also a best practice for security. Adding cache for 'compile on demand' is not the best solution. If you want to build some library or framework based on LightnCandy, think about this scenario.
* Recompile your templates when you upgrade LightnCandy every time.

Detail Feature list
-------------------

Go http://handlebarsjs.com/ to see more feature description about handlebars.js. All features align with it.

* Exact same CR/LF behavior with handlebars.js
* Exact same CR/LF bahavior with mustache spec
* Exact same 'true' or 'false' output with handlebars.js (require `FLAG_JSTRUE`)
* Exact same '[object Object]' output or join(',' array) output with handlebars.js (require `FLAG_JSOBJECT`)
* Can place heading/tailing space, tab, CR/LF inside `{{ var }}` or `{{{ var }}}`
* Indent behavior of the partial same with mustache spec
* Recursive variable lookup to parent context behavior same with mustache spec (require `FLAG_MUSTACHELOOKUP`)
* `{{{value}}}` or `{{&value}}` : raw variable
   * true as 'true' (require `FLAG_JSTRUE`)
   * false as 'false' (require `FLAG_TRUE`)
* `{{value}}` : HTML escaped variable
   * true as 'true' (require `FLAG_JSTRUE`)
   * false as 'false' (require `FLAG_JSTRUE`)
* `{{{path.to.value}}}` : dot notation, raw
* `{{path.to.value}}` : dot notation, HTML escaped 
* `{{.}}` : current context, HTML escaped
* `{{{.}}}` : current context, raw
* `{{this}}` : current context, HTML escaped (require `FLAG_THIS`)
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
* `{{!-- comment or {{ or }} --}}` : extended comment that can contain }} or {{ .
* `{{=<% %>=}}` : set delimiter to custom string , the custom string can not contain `=` . Check http://mustache.github.io/mustache.5.html for more example.
* `{{#each var}}` : each loop
* `{{#each}}` : each loop on {{.}}
* `{{/each}}` : end loop
* `{{#if var}}` : run if logic with original scope (null, false, empty Array and '' will skip this block)
* `{{/if}}` : end if
* `{{else}}` or `{{^}}` : run else logic, should between `{{#if var}}` and `{{/if}}` ; or between `{{#unless var}}` and `{{/unless}}`; or between `{{#foo}}` and `{{/foo}}`; or between `{{#each var}}` and `{{/each}}`; or between `{{#with var}}` and `{{/with}}`. (require `FLAG_ELSE`)
* `{{#unless var}}` : run unless logic with original scope (null, false, empty Array and '' will render this block)
* `{{#with var}}` : change context scope. If the var is false, skip included section. (require `FLAG_WITH`)
* `{{lookup foo bar}}` : lookup foo by value of bar as key.
* `{{../var}}` : parent template scope. (require `FLAG_PARENT`)
* `{{>file}}` : partial; include another template inside a template.
* `{{>file foo}}` : partial with new context (require `FLAG_RUNTIMEPARTIAL`)
* `{{>file foo bar=another}}` : partial with new context which mixed with followed key value (require `FLAG_RUNTIMEPARTIAL`)
* `{{>(helper) foo}}` : include dynamic partial by name provided from a helper (require `FLAG_RUNTIMEPARTIAL`)
* `{{@index}}` : references to current index in a `{{#each}}` loop on an array. (require `FLAG_SPVARS`)
* `{{@key}}` : references to current key in a `{{#each}}` loop on an object. (require `FLAG_SPVARS`)
* `{{@root}}` : references to root context. (require `FLAG_SPVARS`)
* `{{@first}}` : true when looping at first item. (require `FLAG_SPVARS`)
* `{{@last}}` : true when looping at last item. (require `FLAG_SPVARS`)
* `{{@root.path.to.value}}` : references to root context then follow the path. (require `FLAG_SPVARS`)
* `{{@../index}}` : access to parent loop index. (require `FLAG_SPVARS` and `FLAG_PARENT`)
* `{{@../key}}` : access to parent loop key. (require `FLAG_SPVARS` and `FLAG_PARENT`)
* `{{foo.[ba.r].[#spec].0.ok}}` : references to $CurrentConext['foo']['ba.r']['#spec'][0]['ok'] . (require `FLAG_ADVARNAME`)
* `{{~any_valid_tag}}` : Space control, remove all previous spacing (includes CR/LF, tab, space; stop on any none spacing character) (require `FLAG_SPACECTL`)
* `{{any_valid_tag~}}` : Space control, remove all next spacing (includes CR/LF, tab, space; stop on any none spacing character) (require `FLAG_SPACECTL`)
* `{{{helper var}}}` : Execute custom helper then render the result
* `{{helper var}}` : Execute custom helper then render the HTML escaped result
* `{{helper "str"}}` or `{{helper 'str'}}` : Execute custom helper with string arguments (require `FLAG_ADVARNAME`)
* `{{helper 123 null true false undefined}}` : Pass number, true, false, null or undefined into helper
* `{{helper name1=var name2=var2}}` : Execute custom helper with named arguments (require `FLAG_NAMEDARG`)
* `{{#helper ...}}...{{/helper}}` : Execute block custom helper
* `{{helper (helper2 foo) bar}}` : Execute custom helpers as subexpression (require `FLAG_ADVARNAME`)
* `{{{{raw_block}}}} {{will_not_parsed}} {{{{/raw_block}}}}` : Raw block (require FLAG_RAWBLOCK`)

*TODO*

* https://github.com/wycats/handlebars.js/issues/1092

Framework Integration
---------------------

- [Slim 3.0.x](https://github.com/endel/slim-lightncandy-view)
- [Laravel 4](https://github.com/samwalshnz/lightncandy-l4)
- [Laravel 5](https://github.com/ProAI/laravel-handlebars)
- [yii2](https://github.com/kfreiman/yii2-lightncandy)

Tools
-----

- CLI: https://github.com/PXLbros/LightnCandy-CLI
