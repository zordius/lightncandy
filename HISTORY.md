HISTORY
=======

1.2.3 current master, not released
   * align with handlebars.js 4.0.11

1.2.2 current master, not released
   * align with handlebars.js 4.0.11
   * 6ef2efd8d9 fix LightnCandy::compilePartial() error when there is `'` in the partial
   * 658244f864 better error message when a=b found without FLAG_NAMEDARG option
   * a752abd855 **BREAK CHANGE** remove FLAG_SPACECTL option because it always enabled and useless
   * 9615b44920 fix partial block stand alone detection issue

1.2.1 https://github.com/zordius/lightncandy/tree/v1.2.1
   * align with handlebars.js 4.0.10
   * 15201d7300 fix `{{foo (bar "moo (1 2)")}}` parsing issue

1.2.0 https://github.com/zordius/lightncandy/tree/v1.2.0
   * align with handlebars.js 4.0.10
   * 046de72460 reduce is_array() check when context is not changed
   * d217900d5b **BREAK CHANGE**
      * fix {{#sec}} context switch behavior for mustache.js compatibility
      * new `FLAG_MUSTACHESECTION` to align {{#sec}} context switch behavior with mustache.js
      * now `FLAG_MUSTACHE` includes `FLAG_MUSTACHESECTION`

1.1.0 https://github.com/zordius/lightncandy/tree/v1.1.0
   * align with handlebars.js 4.0.6
   * 0557429c07 fix {{lookup . "foo"}} parsing issue
   * 6939eebef8 fix {{foo a=(foo a=(bar))}} parsing issue
   * 59eba28d8a fix error message when compile {{each foo as |bar|}}
   * 3164c82047 **BREAK CHANGE** block custom helper is compiled as `hbbch()` now

1.0.3 https://github.com/zordius/lightncandy/tree/v1.0.3
   * align with handlebars.js 4.0.6
   * b25ea6b1de support FLAG_JSLENGTH when FLAG_METHOD

1.0.2 https://github.com/zordius/lightncandy/tree/v1.0.2
   * align with handlebars.js 4.0.6
   * e9108cdb52 {{#if}}{{else if}}{{/if}} will not cause next {{else}} validation error now

1.0.1 https://github.com/zordius/lightncandy/tree/v1.0.1
   * align with handlebars.js 4.0.6
   * cb94f98149 fix helpers in partial are not collected bug
   * bcbadd785d lookup inside subexpression returns untouched value now
   * 18e114a683 {{foo.bar}} will never be resolved as custom helper now

1.0.0 https://github.com/zordius/lightncandy/tree/v1.0.0
   * align with handlebars.js 4.0.6
   * b2d9eab03a fix generated PHP code error when FLAG_RUNTIMEPARTIAL is on
   * 510b4611bb fix `{{lookup . foo}}` issue
   * e629941f8f fix `Cannot use lexical variable $sp as a parameter name` issue in PHP 7.1
   * 84c94665d8 export SafeString static properties when FLAG_STANDALONEPHP is enabled
   * 5e9f6d0bc7 fix FLAG_STANDALONEPHP mode LS Class bug
   * 7ec3dac944 refine safestring compile option
   * 1f10501c01 fix {{#with .}} context behavior
   * 426291d7ad support recursive {{@partial-block}}

v0.95 https://github.com/zordius/lightncandy/tree/v0.95
   * align with handlebars.js 4.0.5
   * 71abc9853e fix `{{> (lookup foo bar)}}` issue (allow builtin helpers in subexpression)
   * c321e477b2 fix comment inside partial block issue
   * 6e98a13a55 fix `@partial-block` id generation issue

v0.94 https://github.com/zordius/lightncandy/tree/v0.94
   * align with handlebars.js 4.0.5
   * 7082bd3276 refine helper function exporter
   * dcda1202cf now can compile custom helpers without implementation when FLAG_EXTHELPER is on
   * 2eb2ae8e8d supports `{{else with foo}}` , `{{else each bar}}`, `{{else myHelper}}` now
   * cd8d96befa fix {{foo.bar}} lookup PHP warning
   * 0effc46896 fix compile bug when override built-in helpers

v0.93 https://github.com/zordius/lightncandy/tree/v0.93
   * align with handlebars.js 4.0.5
   * a9fb08c3e8 speed up `{{@var}}` lookup, check parent type when `{{../var}}`
   * c7040ecc76 **BREAK CHANGE**
      * new FLAG_JSLENGTH to support {{array.length}} lookup
      * now FLAG_JS includes FLAG_JSLENGTH
      * now FLAG_HANDLEBARSJS includes FLAG_JSLENGTH
   * 8f88c409c9 fix compiler bug for none FLAG_ELSE cases
   * a7617d9ff1 now default options.inverse provided even when no `{{else}}` in template
   * 45e0b600f7 refine unclosed partial block error message
   * 73ac95e882 allow spacing between `{{` and `#` now

v0.92 https://github.com/zordius/lightncandy/tree/v0.92
   * align with handlebars.js 4.0.5
   * c9811d9c3a Detect `{{..foo}}` syntax error
   * 2e77514841 fix `[fo o]="1 2 3"` parsing bug
   * 52b072e246 fix compiler error when a block customhelper inside `{{else if}}`
   * e30edc4302 fix `{{#with .}}` logic when input is an empty array

v0.91 https://github.com/zordius/lightncandy/tree/v0.91
   * align with handlebars.js 4.0.5
   * 93323508ce now FLAG_BESTPERFORMANCE also includes FLAG_STANDALONEPHP.
   * 4deffaf89d prevent warning message when FLAG_ERROR_SKIPPARTIAL used.
   * fbc04f065b **BREAK CHANGE** remove `basedir` and `fileext` option.
   * 5c7c651985 new `partialresolver` option.
   * 771eedfda4 fix compile error when `{{#if}}..{{else if}}..no_else_here..{{/if}}`
   * 68134c627c do not use SafeString when no Runtime::enc() included
   * 6cda56c552 Now FLAG_STANDALONEPHP do not require \LightnCandy\SafeString class
   * 2dd671592e fix the [fo o]=123 parsing bug
   * 993e65ef8e fix @partial-block duplication bug
   * 2c9044e1d7 new `helperresolver` option.

v0.90 https://github.com/zordius/lightncandy/tree/v0.90
   * align with handlebars.js 4.0.4
   * 47e1b28847 performance improvement: now `{{ ... }}` escaping only takes 70% time.
   * ff776f1dff add new options `delimiters` to change default delimiters.
   * 8942155d20 **BREAK CHANGE** remove FLAG_BARE , the generated PHP code will not includes `<?php` and `?>` now.
   * 9dab19e658 support `{{else if ...}}` and `{{else unless ...}}` now.
   * 58a23f3ef6 support `{{log ...}}` now.
   * d5c32c4028 **BREAK CHANGE** remove option: helpers, blockhelpers.
   * dab79506cc support SafeString now.
   * dab79506cc **BREAK CHANGE** rename option: hbhelpers into helpers.
   * a1f699efa7 support render time partial function.

v0.89 https://github.com/zordius/lightncandy/tree/v0.89
   * align with handlebars.js 4.0.4
   * use newer handlebars spec: https://github.com/jbboehr/handlebars-spec
   * 50028d36a7 **BREAK CHANGE** remove FLAG_MUSTACHESP
   * e32079fe08 fix standalone detection on single `{{.}}` or `{{this}}`
   * 83caaec2c0 support `{{#if foo includeZero=true}}`
   * 6c636a8857 support literal references
   * 3d7a7d81a7 **BREAK CHANGE** remove FLAG_MUSTACHEPAIN
   * 3d7a7d81a7 new flag FLAG_PREVENTINDENT to stop auto indent on partial.
   * 0d3a92a52e new flag FLAG_HANDLEBARSJS_FULL to enable all handlebars features with performance drop
   * c76b9c6fc0 **BREAK CHANGE** now FLAG_MUSTACHE also includes FLAG_RUNTIMEPARTIAL
   * 28be0377ea new flag FLAG_MUSTACHELAMBDA to support simple case of mustache lambda
   * 37ba20c234 **BREAK CHANGE** rename LCRun3 to LCRun4 for interface changed, old none standalone templates will error with newer version
   * 8f062e4ef1 fix for nested subexpression parsing bug
   * b3704e78c4 new flag FLAG_HANDLEBARSLAMBDA to support handlebars lambda
   * 4d4f4d5b57 **BREAK CHANGE** start to use namespace, support psr-4 autoloader by composer
      * rename LightnCandy to LightnCandy\LightnCandy
      * rename LCRun4 to LightnCandy\Runtime
      * rename `lcrun` option to `runtime`
   * b818c2411e split LightnCandy methods into different classes
   * 67a4518460 Refactoring Validator and Compiler done
   * e1362e7779 Usage counting feature fixed
   * 4e21ff3f11 **BREAK CHANGE** remove FLAG_WITH
   * fde6859ae7 new flag FLAG_NOHBHELPERS to remove all handlebars.js builtin helpers
   * 82e4221919 **BREAK CHANGE** now FLAG_MUSTACHE also includes FLAG_NOHBHELPERS
   * b71afbead3 **BREAK CHANGE** rename FLAG_JSQUOTE to FLAG_HBESCAPE
   * 90c2bbf16d **BREAK CHANGE** now {{#if}} and {{#unless}} context behavior align with handlebars.js 4.0.4
   * c65e380877 **BREAK CHANGE** now flag FLAG_PREVENTINDENT behavior align with handlebars.js options.preventIndent
   * f85001d225 fix standalone detection when space control {{~}} used
   * 6d1e390c5d now hbhelpers context change behavior align with handlebars.js 4.0.4
   * 0d1f00196f **BREAK CHANGE** now render function interface align with handlebars.js 4.0.4
   * 44741ac197 supports {{lookup foo bar}} now
   * 50eae060c5 new flag FLAG_PARTIALNEWCONTEXT to create new empty context for every partial
   * e118a08e6b **BREAK CHANGE** rename FLAG_STANDALONE to FLAG_STANDALONEPHP
   * 3994014ca4 support {{#with bar as |foo|}}
   * fc2f9643c7 support {{#each foo as |value index|}}
   * 83a173e575 support block params for hbhelpers
   * 2a262f671b new flag FLAG_STRINGPARAMS to support handlebars.js options.stringParams
   * dcc84c3118 support lambda arguments
   * edef496b25 maintain options.contexts for custom helpers now
   * 8e94d5d6ba support partial block: {{#> foo}}block{{/foo}}
   * 4347a78b3e support partial block: {{> @partial-block}}
   * f4df6d722f support inline partial: {{#*inline "partial_name"}}...{{/inline}}
   * 71130e69e0 fix partial block + inline partial parsing bugs

v0.23 https://github.com/zordius/lightncandy/tree/v0.23
   * align with handlebars.js 3.0.3
   * b194f37430 support `{{{{rawblock}}}} ... {{{{/rawblock}}}}` when FLAG_RAWBLOCK enabled
   * 927741a07c add `prePartial()` static method and `prepartial` compile option for extendibility
   * f9f41277d7 support private variable injection from handlebars custom block helpers
   * 850dcd7082 fix `{{!-- --}}` bug when it inside a partial
   * edb486ac87 fix support for nested raw block

v0.22 https://github.com/zordius/lightncandy/tree/v0.22
   * align with handlebars.js 3.0.3
   * 1d1e8829cb fix `{{foo bar=(tee_taa "hoo")}}` parsing issue
   * 9bd994ee94 fix JavaScript function in runtime partial be changed bug
   * a514e4652e fix `{{#foo}}` issue when foo is an empty ArrayObject

v0.21 https://github.com/zordius/lightncandy/tree/v0.21
   * align with handlebars.js 3.0.3
   * 9f24268d57 support FLAG_BARE to remove PHP start/end tags
   * 60d5a46c55 handle object/propery merge when deal with partial
   * d0130bf7e5 support undefined `{{helper undefined}}`
   * 8d228606f7 support `lcrun` to use customized render library when compile()
   * d0bad115f0 remove tmp PHP file when prepare() now
   * d84bbb4519 support keeping tmp PHP file when prepare()
   * ee833ae2f8 fix syntax validator bug on `{{helper "foo[]"}}`
   * 30b891ab28 fix syntax validator bug on `{{helper 'foo[]'}}`
   * 1867f1cc37 now count subexpression usage correctly
   * 78ef9b8a89 new syntax validator on handlebars variable name

v0.20 https://github.com/zordius/lightncandy/tree/v0.20
   * align with handlebars.js 3.0.0
   * 3d9a557af9 fix `{{foo (bar ../abc)}}` compile bug
   * 7dc16ac255 refine custom helper error detection logic
   * 72d32dc299 fix subexpression parsing bug inside `{{#each}}`
   * d1f1b93130 support context access inside a hbhelper by `$options['_this']`

v0.19 https://github.com/zordius/lightncandy/tree/v0.19
   * align with handlebars.js 3.0.0
   * 5703851e49 fix `{{foo bar=['abc=123']}}` parsing bug
   * 7b4e36a1e3 fix `{{foo bar=["abc=123"]}}` parsing bug
   * c710c8349b fix `{{foo bar=(helper a b c)}}` parsing bug
   * 4bda1c6f41 fix subexpression+builtin block helper (EX: `{{#if (foo bar)}}`) parsing bug
   * 6fdba10fc6 fix `{{foo ( bar) or " car" or ' cat' or [ cage]}}` pasing bug
   * 0cd5f2d5e2 fix indent issue when custom helper inside a partial
   * 296ea89267 support dynamic partial `{{> (foo)}}`
   * f491d04bd5 fix `{{../foo}}` look up inside root scope issue
   * 38fba8a5a5 fix scope issue for hbhelpers
   * a24a0473e2 change internal variable structure and fix for `{{number}}`
   * 7ae8289b7e fix escape in double quoted string bug
   * 90adb5531b fix `{{#if 0.0}}` logic to behave as false
   * 004a6ddffe fix `{{../foo}}` double usage count bug
   * 9d55f12c5a fix subexpression parsing bug when line change inside it
   * fe1cb4987a **BREAK CHANGE** remove FLAG_MUSTACHESEC

v0.18 https://github.com/zordius/lightncandy/tree/v0.18
   * align with handlebars.js 2.0.0
   * 7bcce4c1a7 support `{{@last}}` for `{{#each}}` on both object and array
   * b0c44c3b40 remove ending \n in lightncandy.php
   * e130875d5a support single quoted string input: `{{foo 'bar'}}`
   * c603aa39d8 support `renderex` to extend anything in render function
   * f063e5302c now render function debug constants works well in standalone mode
   * 53f6a6816d fix parsing bug when there is a `=` inside single quoted string
   * 2f16c0c393 now really autoload when installed with composer
   * c4da1f576c supports `{{^myHelper}}`

v0.17 https://github.com/zordius/lightncandy/tree/v0.17
   * align with handlebars.js 2.0.0
   * 3b48a0acf7 fix parsing bug when FLAG_NOESCAPE enabled
   * 5c774b1b08 fix hbhelpers response error with options['fn'] when FLAG_BESTPERFORMANCE enabled
   * c60fe70bdb fix hbhelpers response error with options['inverse'] when FLAG_BESTPERFORMANCE enabled
   * e19b3e3426 provide options['root'] and options['_parent'] to hbhelpers
   * d8a288e83b refine variable parsing logic to support `{{@../index}}`, `{{@../key}}`, etc.

v0.16 https://github.com/zordius/lightncandy/tree/v0.16
   * align with handlebars.js 2.0.0
   * 4f036aff62 better error message for named arguments
   * 0b462a387b support `{{#with var}}` ... `{{else}}` ... `{{/with}}`
   * 4ca624f651 fix 1 ANSI code error
   * 01ea3e9f42 support instances with PHP __call magic funciton
   * 38059036a7 support `{{#foo}}` or `{{#each foo}}` on PHP Traversable instance
   * 366f5ec0ac add FLAG_MUSTACHESP and FLAG_MUSTACHEPAIN into FLAG_HANDLEBARS and FLAG_HANDLEBARSJS now
   * b61d7b4a81 align with handlebars.js standalone tags behavior
   * b211e1742e now render false as 'false'
   * 655a2485be fix bug for `{{helper "==="}}`
   * bb58669162 support FLAG_NOESCAPE

v0.15 https://github.com/zordius/lightncandy/tree/v0.15
   * align with handlebars.js 2.0.0
   * 4c750806e8 fix for `\` in template
   * 12ab6626d6 support escape. `\{{foo}}` will be rendered as is. ( handlebars spec , require FLAG_SLASH )
   * 876bd44d9c escape &#x60; to &amp;#x60; ( require FLAG_JSQUOTE )
   * f1f388ed79 support `{{^}}` as `{{else}}` ( require FLAG_ELSE )
   * d5e17204b6 support `{{#each}}` == `{{#each .}}` now
   * 742126b440 fix `{{>foo/bar}}` partial not found bug
   * d62c261ff9 support numbers as helper input `{{helper 0.1 -1.2}}`
   * d40c76b84f support escape in string arguments `{{helper "test \" double quote"}}`
   * ecb57a2348 fix for missing partial in partial bug
   * 1adad5dbfa fix `{{#with}}` error when FLAG_WITH not used
   * ffd5e35c2d fix error when rendering array value as `{{.}}` without FLAG_JSOBJECT
   * bd4987adbd support changing context on partial `{{>foo bar}}` ( require FLAG_RUNTIMEPARTIAL )
   * f5decaa7e3 support name sarguments on partial `{{>foo bar name=tee}}` . fix `{{..}}` bug
   * c20bb36457 support `partials` in options
   * e8779dbe8c change default `basedir` hehavior, stop partial files lookup when do not prodive `basedir` in options
   * c4e3401fe4 fix `{{>"test"}}` or `{{>[test]}}` or `{{>1234}}` bug
   * e59f62ea9b fix seciton behavior when input is object, and add one new flag: FLAG_MUSTACHESEC
   * 80eaf8e007 use static::method not self::method for subclass
   * 0bad5c8f20 fix usedFeature generation bugs

v0.14 https://github.com/zordius/lightncandy/tree/v0.14
   * align with handlebars.js 2.0.0-alpha.4
   * fa6225f278 support boolen value in named arguments for cusotm helper
   * 160743e1c8 better error message when unmatch `{{/foo}}` tag detected
   * d9a9416907 support `{{&foo}}`
   * 8797485cfa fix `{{^foo}}` logic when foo is empty list
   * 523b1373c4 fix handlebars custom helper interface
   * a744a2d522 fix bad syntax when FLAG_RENDER_DEBUG + helpers
   * 0044f7bd10 change FLAG_THIS behavoir
   * b5b0739b68 support recursive context lookup now ( mustache spec , require FLAG_MUSTACHELOOKUP )
   * 096c241fce support standalone tag detection now ( mustache spec , require FLAG_MUSTACHESP )
   * cea46c9a67 support `{{=<% %>=}}` to set delimiter
   * 131696af11 support subexpression `{{helper (helper2 foo) bar}}`
   * 5184d41be6 support runtime/recursive partial ( require FLAG_RUNTIMEPARTIAL )
   * 6408917f76 support partial indent ( mustache spec , require FLAG_MUSTACHEPAIN )

v0.13 https://github.com/zordius/lightncandy/tree/v0.13
   * align with handlebars.js 2.0.0-alpha.4
   * e5a8fe3833 fix issue #46 ( error with `{{this.foo.bar}}` )
   * ea131512f9 fix issue #44 ( error with some helper inline function PHP code syntax )
   * 522591a0c6 fix issue #49 ( error with some helper user function PHP code syntax )
   * c4f7e1eaac support `{{foo.bar}} lookup on instance foo then property/method bar ( flagd FLAG_PROPERTY or FLAG_METHOD required )
   * 0f4c0daa4b stop simulate Javascript output for array when pass input to custom helpers
   * 22d07e5f0f **BREAK CHANGE** BIG CHANGE of custom helper interface

v0.12 https://github.com/zordius/lightncandy/tree/v0.12
   * align with handlebars.js 2.0.0-alpha.2
   * 64db34cf65 support `{{@first}}` and `{{@last}}`
   * bfa1fbef97 add new flag FLAG_SPVARS
   * 10a4623dc1 **BREAK CHANGE** remove json schema support
   * 240d9fa290 only export used LCRun2 functions when compile() with FLAG_STANDALONE now
   * 3fa897c98c **BREAK CHANGE** rename LCRun2 to LCRun3 for interface changed, old none standalone templates will error with newer version
   * e0838c7418 now can output debug template map with ANSI color
   * 80dbeab63d fix php warning when compile with custom helper or block custom helper
   * 8ce6268b64 support Handlebars.js style custom helper

v0.11 https://github.com/zordius/lightncandy/tree/v0.11
   * align with handlebars.js 2.0.0-alpha.2
   * a275d52c97 use php array, remove val()
   * 8834914c2a only export used custom helper into render function now
   * eb6d82d871 refine option flag consts
   * fc437295ed refine comments for phpdoc
   * fbf116c3e2 fix for tailing ; after helper functions
   * f47a2d5014 fix for wrong param when new Exception
   * 94e71ebcbd add isset() check for input value
   * a826b8a1ab support `{{else}}` in `{{#each}}` now
   * 25dac11bb7 support `{{!-- comments --}}` now (this handlebars.js extension allow `}}` to be placed inside a comment)
   * e142b6e116 support `{{@root}}` or `{{@root.foo.bar}}` now
   * 58c8d84aa2 custom helper can return extra flag to change html encoded behavior now

v0.10 https://github.com/zordius/lightncandy/tree/v0.10
   * align with handlebars.js 2.0.0-alpha.1
   * 4c9f681080 file name changed: lightncandy.inc => lightncandy.php
   * e3de01081c some minor fix for json schema
   * 1feec458c7 new variable handling logic, save variable name parsing time when render() . rendering performance improved 10~30%!
   * 3fa897c98c **BREAK CHANGE** rename LCRun to LCRun2 for interface changed, old none standalone templates will error with newer version
   * 43a6d33717 fix for `{{../}}` php warning message
   * 9189ebc1e4 now auto push documents from Travis CI
   * e077d0b631 support named arguments for custom helpers `{{helper name=value}}`
   * 2331b6fe55 support block custom helpers
   * 4fedaa25f7 support number value as named arguments
   * 6a91ab93d2 fix for default options and php warnings
   * fc157fde62 fix for doblue quoted arguments (issue #15)

v0.9 https://github.com/zordius/lightncandy/tree/v0.9
   * align with handlebars.js 1.3
   * **STOP PHP 5.3.x testing and support**
   * a55f2dd067 support both `{{@index}}` and `{{@key}}` when `{{#each an_object}}`
   * e59f931ea7 add FLAG_JSQUOTE support
   * 92b3cf58af report more than 1 error when compile()
   * 93cc121bcf test for wrong variable name format in test/error.php
   * 41c1b431b4 support advanced variable naming `{{foo.[bar].10}}` now
   * 15ce1a00a8 add FLAG_EXTHELPER option
   * f51337bde2 support space control `{{~sometag}}` or `{{sometag~}}`
   * fe3d67802e add FLAG_SPACECTL option
   * 920fbb3039 support custom helper
   * 07ae71a1bf migrate into Travis CI
   * ddd3335ff6 support "some string" argument
   * 20f6c888d7 html encode after custom helper executed
   * 10a2f45fdc add test generator
   * ccd1d3ddc2 **BREAK CHANGE** migrate to Scrutinizer, change file name LightnCandy.inc to LightnCandy.php
   * 5ac8ad8d04 now is a Composer package

v0.8 https://github.com/zordius/lightncandy/tree/v0.8
   * align with handlebars.js 1.0.12
   * aaec049 fix partial in partial not works bug
   * 52706cc fix for `{{#var}}` and `{{^var}}` , now when var === 0 means true
   * 4f7f816 support `{{@key}}` and `{{@index}}` in `{{#each var}}`
   * 63aef2a prevent array_diff_key() PHP warning when `{{#each}}` on none array value
   * 10f3d73 add more is_array() check when `{{#each}}` and `{{#var}}`
   * 367247b fix `{{#if}}` and `{{#unless}}` when value is an empty array
   * c76c0bb returns null if var is not exist in a template [contributed by dtelyukh@github.com]
   * d18bb6d add FLAG_ECHO support
   * aec2b2b fix `{{#if}}` and `{{#unless}}` when value is an empty string
   * 8924604 fix variable output when false in an array
   * e82c324 fix for ifv and ifvar logic difference
   * 1e38e47 better logic on var name checking. now support `{{0}}` in the loop, but it is not handlebars.js standard

v0.7 https://github.com/zordius/lightncandy/tree/v0.7
   * align with handlebarsjs 1.0.11
   * add HISTORY.md
   * 777304c change compile format to include in val, isec, ifvar
   * 55de127 support `{{../}}` in `{{#each}}`
   * 57e90af fix parent levels detection bug
   * 96bb4d7 fix bugs for `{{#.}}` and `{{#this}}`
   * f4217d1 add ifv and unl 2 new methods for LCRun
   * 3f1014c fix `{{#this}}` and `{{#.}}` bug when used with `{{../var}}`
   * cbf0b28 fix `{{#if}}` logic error when using `{{../}}`
   * 2b20ef8 fix `{{#with}}` + `{{../}}` bug
   * 540cd44 now support FLAG_STANDALONE
   * 67ac5ff support `{{>partial}}`
   * 98c7bb1 detect unclosed token now

v0.6 https://github.com/zordius/lightncandy/tree/v0.6
   * align with handlebarsjs 1.0.11
   * 45ac3b6 fix #with bug when var is false
   * 1a46c2c minor #with logic fix. update document
   * fdc753b fix #each and section logic for 018-hb-withwith-006
   * e6cc95a add FLAG_PARENT, detect template error when scan()
   * 1980691 make new LCRun::val() method to normal path.val logic
   * 110d24f `{{#if path.var}}` bug fixed
   * d6ae2e6 fix `{{#with path.val}}` when input value is null
   * 71cf074 fix for 020-hb-doteach testcase

v0.5 https://github.com/zordius/lightncandy/tree/v0.5
   * align with handlebarsjs 1.0.7
   * 955aadf fix #each bug when input is a hash
