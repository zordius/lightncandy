LightnCandy
===========

⚡🍭 An extremely fast PHP implementation of handlebars ( http://handlebarsjs.com/ ) and mustache ( http://mustache.github.io/ ).

Travis CI status: [![Unit testing](https://travis-ci.org/zordius/lightncandy.svg?branch=master)](https://travis-ci.org/zordius/lightncandy) [![Regression testing](https://travis-ci.org/zordius/HandlebarsTest.svg?branch=master)](https://travis-ci.org/zordius/HandlebarsTest)

Scrutinizer CI status: [![Code Coverage](https://img.shields.io/scrutinizer/coverage/g/zordius/lightncandy.svg)](https://scrutinizer-ci.com/g/zordius/lightncandy/)

Package on packagist: [![Latest Stable Version](https://poser.pugx.org/zordius/lightncandy/v/stable.svg)](https://packagist.org/packages/zordius/lightncandy) [![License](https://poser.pugx.org/zordius/lightncandy/license.svg)](https://github.com/zordius/lightncandy/blob/master/LICENSE.md) [![Total Downloads](https://poser.pugx.org/zordius/lightncandy/downloads)](https://packagist.org/packages/zordius/lightncandy)

Features
--------

* Logicless template: mustache ( http://mustache.github.com/ ) or handlebars ( http://handlebarsjs.com/ ) .
* Compile template to **pure PHP** code. Examples:
   * <a href="https://github.com/zordius/HandlebarsTest/blob/master/fixture/001-simple-vars.tmpl">Template A</a> generated <a href="https://github.com/zordius/HandlebarsTest/blob/master/fixture/001-simple-vars.php">PHP A</a>
   * <a href="https://github.com/zordius/HandlebarsTest/blob/master/fixture/016-hb-eachthis.tmpl">Template B</a> generated <a href="https://github.com/zordius/HandlebarsTest/blob/master/fixture/016-hb-eachthis.php">PHP B</a>
* **FAST!**
   * Runs 2~7 times faster than <a href="https://github.com/bobthecow/mustache.php">mustache.php</a> (Justin Hileman/bobthecow implementation).
   * Runs 2~7 times faster than <a href="https://github.com/dingram/mustache-php">mustache-php</a> (Dave Ingram implementation).
   * Runs 10~50 times faster than <a href="https://github.com/XaminProject/handlebars.php">handlebars.php</a>.
   * Detail performance test reports can be found <a href="https://github.com/zordius/HandlebarsTest">here</a>, go http://zordius.github.io/HandlebarsTest/ to see charts.
* **SMALL!** all PHP files in 188K
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

Documents
---------

* <a href="https://zordius.github.io/HandlebarsCookbook/9000-quickstart.html">Quick Start</a>

Compile Options
---------------

You can apply more options by running `LightnCandy::compile($template, $options)`:

```php
LightnCandy::compile($template, Array(
    'flags' => LightnCandy::FLAG_ERROR_LOG | LightnCandy::FLAG_STANDALONEPHP
));
```

Default is to compile the template as PHP, which can be run as fast as possible (flags = <a href="https://zordius.github.io/HandlebarsCookbook/LC-FLAG_BESTPERFORMANCE.html">FLAG_BESTPERFORMANCE</a>).

**Error Handling**
* <a href="https://zordius.github.io/HandlebarsCookbook/LC-FLAG_ERROR_LOG.html">FLAG_ERROR_LOG</a>
* <a href="https://zordius.github.io/HandlebarsCookbook/LC-FLAG_ERROR_EXCEPTION.html">FLAG_ERROR_EXCEPTION</a>
* <a href="https://zordius.github.io/HandlebarsCookbook/LC-FLAG_ERROR_SKIPPARTIAL.html">FLAG_ERROR_SKIPPARTIAL</a>
* <a href="https://zordius.github.io/HandlebarsCookbook/LC-FLAG_RENDER_DEBUG.html">FLAG_RENDER_DEBUG</a>

**JavaScript Compatibility**
* <a href="https://zordius.github.io/HandlebarsCookbook/LC-FLAG_JSTRUE.html">FLAG_JSTRUE</a>
* <a href="https://zordius.github.io/HandlebarsCookbook/LC-FLAG_JSOBJECT.html">FLAG_JSOBJECT</a>
* <a href="https://zordius.github.io/HandlebarsCookbook/LC-FLAG_JSLENGTH.html">FLAG_JSLENGTH</a>
* <a href="https://zordius.github.io/HandlebarsCookbook/LC-FLAG_JS.html">FLAG_JS</a>

**Mustache Compatibility**
* `FLAG_MUSTACHELOOKUP` : align recursive lookup up behaviors with mustache specification. And, the rendering performance will be worse.
* `FLAG_MUSTACHELAMBDA` : support simple lambda logic as mustache specification. And, the rendering performance will be worse.
* `FLAG_NOHBHELPERS` : Do not compile handlebars.js builtin helpers. With this option, `{{#with}}`, `{{#if}}`, `{{#unless}}`, `{{#each}}` means normal section, and `{{#with foo}}`, `{{#if foo}}`, `{{#unless foo}}`, `{{#each foo}}` will cause compile error.
* `FLAG_MUSTACHESECTION` : align section context behaviors with mustache.js.
* `FLAG_MUSTACHE` : support all mustache specification but performance drop, same with `FLAG_ERROR_SKIPPARTIAL` + `FLAG_MUSTACHELOOKUP` + `FLAG_MUSTACHELAMBDA` + `FLAG_NOHBHELPERS` + `FLAG_RUNTIMEPARTIAL` + `FLAG_JSTRUE` + `FLAG_JSOBJECT`.

**Handlebars Compatibility**
* <a href="https://zordius.github.io/HandlebarsCookbook/LC-FLAG_THIS.html">FLAG_THIS</a>
* <a href="https://zordius.github.io/HandlebarsCookbook/LC-FLAG_PARENT.html">FLAG_PARENT</a>
* <a href="https://zordius.github.io/HandlebarsCookbook/LC-FLAG_HBESCAPE.html">FLAG_HBESCAPE</a>
* <a href="https://zordius.github.io/HandlebarsCookbook/LC-FLAG_ADVARNAME.html">FLAG_ADVARNAME</a>
* <a href="https://zordius.github.io/HandlebarsCookbook/LC-FLAG_NAMEDARG.html">FLAG_NAMEDARG</a>
* <a href="https://zordius.github.io/HandlebarsCookbook/LC-FLAG_SLASH.html">FLAG_SLASH</a>
* <a href="https://zordius.github.io/HandlebarsCookbook/LC-FLAG_ELSE.html">FLAG_ELSE</a>
* `FLAG_RAWBLOCK`: support `{{{{raw_block}}}}any_char_or_{{foo}}_as_raw_string{{{{/raw_block}}}}`.
* `FLAG_HANDLEBARSLAMBDA` : support lambda logic as handlebars.js specification. And, the rendering performance will be worse.
* `FLAG_SPVARS` : support special variables include @root, @index, @key, @first, @last. Otherwise, compile these variable names with default parsing logic.
* `FLAG_HANDLEBARS` : support most handlebars extensions and also keep performance good, same with `FLAG_THIS` + `FLAG_PARENT` + `FLAG_HBESCAPE` + `FLAG_ADVARNAME` + `FLAG_NAMEDARG` + `FLAG_SPVARS` + `FLAG_SLASH` + `FLAG_ELSE` + `FLAG_RAWBLOCK`.
* `FLAG_HANDLEBARSJS` : support most handlebars.js + javascript behaviors and also keep performance good, same with `FLAG_JS` + `FLAG_HANDLEBARS`.
* `FLAG_HANDLEBARSJS_FULL` : enable all supported handlebars.js behaviors but performance drop, same with `FLAG_HANDLEBARSJS` + `FLAG_INSTANCE` + `FLAG_RUNTIMEPARTIAL` + `FLAG_MUSTACHELOOKUP` + `FLAG_HANDLEBARSLAMBDA`.

**Handlebars Options**
* <a href="https://zordius.github.io/HandlebarsCookbook/LC-FLAG_NOESCAPE.html">FLAG_NOESCAPE</a>
* <a href="https://zordius.github.io/HandlebarsCookbook/LC-FLAG_PARTIALNEWCONTEXT.html">FLAG_PARTIALNEWCONTEXT</a>
* `FLAG_IGNORESTANDALONE` : prevent standalone detection on `{{#foo}}`, `{{/foo}}` or `{{^}}`, the behavior is same with handlebars.js ignoreStandalone compile time option.
* `FLAG_STRINGPARAMS` : pass variable name as string to helpers, the behavior is same with handlebars.js stringParams compile time option.
* `FLAG_KNOWNHELPERSONLY`: Only pass current context to lambda, the behavior is same with handlebars.js knownHelpersOnly compile time option.
* `FLAG_PREVENTINDENT` : align partial indent behavior with mustache specification. This is same with handlebars.js preventIndent copmile time option.

**PHP**
* <a href="https://zordius.github.io/HandlebarsCookbook/LC-FLAG_STANDALONEPHP.html">FLAG_STANDALONEPHP</a>
* <a href="https://zordius.github.io/HandlebarsCookbook/LC-FLAG_EXTHELPER.html">FLAG_EXTHELPER</a>
* <a href="https://zordius.github.io/HandlebarsCookbook/LC-FLAG_RUNTIMEPARTIAL.html">FLAG_RUNTIMEPARTIAL</a>
* <a href="https://zordius.github.io/HandlebarsCookbook/LC-FLAG_PROPERTY.html">FLAG_PROPERTY</a>
* <a href="https://zordius.github.io/HandlebarsCookbook/LC-FLAG_METHOD.html">FLAG_METHOD</a>
* <a href="https://zordius.github.io/HandlebarsCookbook/LC-FLAG_INSTANCE.html">FLAG_INSTANCE</a>
* <a href="https://zordius.github.io/HandlebarsCookbook/LC-FLAG_ECHO.html">FLAG_ECHO</a>
* <a href="https://zordius.github.io/HandlebarsCookbook/LC-FLAG_BESTPERFORMANCE.html">FLAG_BESTPERFORMANCE</a>

Partial Support
---------------

* <a href="https://zordius.github.io/HandlebarsCookbook/0011-partial.html">Example of compile time partial</a>
* <a href="https://zordius.github.io/HandlebarsCookbook/0024-partialcontext.html">Example of partial context changing</a>
* <a href="https://zordius.github.io/HandlebarsCookbook/0028-dynamicpartial.html">use dynamic partial</a>
* <a href="https://zordius.github.io/HandlebarsCookbook/9902-lcop-partialresolver.html">The partialresolver option</a>

Custom Helper
-------------

* <a href="https://zordius.github.io/HandlebarsCookbook/9001-customhelper.html">Custom Helpers in LighnCandy</a>
* <a href="https://zordius.github.io/HandlebarsCookbook/9002-helperoptions.html">The $options Object</a>
* <a href="https://zordius.github.io/HandlebarsCookbook/9003-helperescaping.html">Use SafeString</a>
* <a href="https://zordius.github.io/HandlebarsCookbook/9901-lcop-helperresolver.html">The helperresolver option</a>

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

Unsupported Feature
-------------------

* `{{foo/bar}}` style variable name, it is deprecated in official handlebars.js document, please use this style: `{{foo.bar}}`.

Suggested Handlebars Template Practices
---------------------------------------

* Prevent to use `{{#with}}` . I think `{{path.to.val}}` is more readable then `{{#with path.to}}{{val}}{{/with}}`; when using `{{#with}}` you will confusing on scope changing. `{{#with}}` only save you very little time when you access many variables under same path, but cost you a lot time when you need to understand then maintain a template.
* use `{{{val}}}` when you do not require HTML escaped output on the value. It is better performance, too.
* Prevent to use custom helper if you want to reuse your template in different language. Or, you may need to implement different versions of helper in different languages.
* For best performance, you should only use 'compile on demand' pattern when you are in development stage. Before you go to production, you can `LightnCandy::compile()` on all your templates, save all generated PHP codes, and deploy these generated files (You may need to maintain a build process for this) . **DO NOT COMPILE ON PRODUCTION** , it also a best practice for security. Adding cache for 'compile on demand' is not the best solution. If you want to build some library or framework based on LightnCandy, think about this scenario.
* Recompile your templates when you upgrade LightnCandy every time.
* Persistant ESCAPING practice of `{` or `}` for both handlebars and lightncandy:
  * If you want to display atomic `}}` , you can just use it without any trick.  EX: `{{foo}}   }}`
  * If you want to display `}` just after any handlebars token, you can use this: `{{#with "}"}}{{.}}{{/with}}` .  EX: `{{foo}}{{#with "}"}}{{.}}{{/with}}`
  * If you want to display atomic `{` , you can just use it without any trick. EX: `{ and {{foo}}`.
  * If you want to display `{{` , you can use `{{#with "{{"}}{{.}}{{/with}}`. EX: `{{#with "{{"}}{{.}}{{/with}}{{foo}}`

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
* `{{#if foo includeZero=true}}` : result as true when foo === 0 (require `FLAG_NAMEDARG`)
* `{{/if}}` : end if
* `{{else}}` or `{{^}}` : run else logic, should between `{{#if var}}` and `{{/if}}` ; or between `{{#unless var}}` and `{{/unless}}`; or between `{{#foo}}` and `{{/foo}}`; or between `{{#each var}}` and `{{/each}}`; or between `{{#with var}}` and `{{/with}}`. (require `FLAG_ELSE`)
* `{{#if foo}} ... {{else if bar}} ... {{/if}}` : chained if else blocks
* `{{#unless var}}` : run unless logic with original scope (null, false, empty Array and '' will render this block)
* `{{#unless foo}} ... {{else if bar}} ... {{/unless}}` : chained unless else blocks
* `{{#unless foo}} ... {{else unless bar}} ... {{/unless}}` : chained unless else blocks
* `{{#foo}} ... {{else bar}} ... {{/foo}}` : custom helper chained else blocks
* `{{#with var}}` : change context scope. If the var is false or an empty array, skip included section.
* `{{#with bar as |foo|}}` : change context to bar and set the value as foo. (require `FLAG_ADVARNAME`)
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
* `{{~any_valid_tag}}` : Space control, remove all previous spacing (includes CR/LF, tab, space; stop on any none spacing character)
* `{{any_valid_tag~}}` : Space control, remove all next spacing (includes CR/LF, tab, space; stop on any none spacing character)
* `{{{helper var}}}` : Execute custom helper then render the result
* `{{helper var}}` : Execute custom helper then render the HTML escaped result
* `{{helper "str"}}` or `{{helper 'str'}}` : Execute custom helper with string arguments (require `FLAG_ADVARNAME`)
* `{{helper 123 null true false undefined}}` : Pass number, true, false, null or undefined into helper
* `{{helper name1=var name2=var2}}` : Execute custom helper with named arguments (require `FLAG_NAMEDARG`)
* `{{#helper ...}}...{{/helper}}` : Execute block custom helper
* `{{helper (helper2 foo) bar}}` : Execute custom helpers as subexpression (require `FLAG_ADVARNAME`)
* `{{{{raw_block}}}} {{will_not_parsed}} {{{{/raw_block}}}}` : Raw block (require `FLAG_RAWBLOCK`)
* `{{#> foo}}block{{/foo}}` : Partial block, provide `foo` partial default content (require `FLAG_RUNTIMEPARTIAL`)
* `{{#> @partial-block}}` : access partial block content inside a partial
* `{{#*inline "partial_name"}}...{{/inline}}` : Inline partial, provide a partial and overwrite the original one.
* `{{log foo}}` : output value to stderr for debug.

Developer Notes
---------------

Please read <a href=".github/CONTRIBUTING.md">CONTRIBUTING.md</a> for develop environment setup.

Framework Integration
---------------------

- [Slim 3.0.x](https://github.com/endel/slim-lightncandy-view)
- [Laravel 4](https://github.com/samwalshnz/lightncandy-l4)
- [Laravel 5](https://github.com/ProAI/laravel-handlebars)
- [yii2](https://github.com/kfreiman/yii2-lightncandy)
- [Symfony3](https://packagist.org/packages/jays-de/handlebars-bundle)

Tools
-----

- CLI: https://github.com/PXLbros/LightnCandy-CLI
