HISTORY
=======

v0.10 current trunk
   * align with handlebars.js 2.0.0-alpha.1
   * 4c9f681080 file name changed: lightncandy.inc => lightncandy.php
   * e3de01081c some minor fix for json schema
   * 1feec458c7 new variable handling logic, save variable name parsing time when render() . rendering performance improved 10~30%!
   * 3fa897c98c rename LCRun to LCRun2 for interface changed, old none standalone templates will error with newer version.
   * 43a6d33717 fix for {{../}} php warning message
   * 9189ebc1e4 now auto push documents from Travis CI
   * e077d0b631 support named arguments for custom helpers {{helper name=value}}
   * 2331b6fe55 support block custom helpers
   * 4fedaa25f7 support number value as named arguments

v0.9 https://github.com/zordius/lightncandy/tree/v0.9
   * align with handlebars.js 1.3
   * a55f2dd067 support both {{@index}} and {{@key}} when {{#each an_object}}
   * e59f931ea7 add FLAG_JSQUOTE support
   * 92b3cf58af report more than 1 error when compile()
   * 93cc121bcf test for wrong variable name format in test/error.php
   * 41c1b431b4 support advanced variable naming {{foo.[bar].10}} now
   * 15ce1a00a8 add FLAG_EXTHELPER option
   * f51337bde2 support space control {{~sometag}} or {{sometag~}}
   * fe3d67802e add FLAG_SPACECTL option
   * 920fbb3039 support custom helper
   * 07ae71a1bf migrate into Travis CI
   * ddd3335ff6 support "some string" argument
   * 20f6c888d7 html encode after custom helper executed
   * 10a2f45fdc add test generator
   * ccd1d3ddc2 migrate to Scrutinizer , change file name LightnCandy.inc to LightnCandy.php
   * 5ac8ad8d04 now is a Composer package

v0.8 https://github.com/zordius/lightncandy/tree/v0.8
   * align with handlebars.js 1.0.12
   * aaec049 fix partial in partial not works bug
   * 52706cc fix for {{#var}} and {{^var}} , now when var === 0 means true
   * 4f7f816 support {{@key}} and {{@index}} in {{#each var}}
   * 63aef2a prevent array_diff_key() PHP warning when {{#each}} on none array value
   * 10f3d73 add more is_array() check when {{#each}} and {{#var}}
   * 367247b fix {{#if}} and {{#unless}} when value is an empty array
   * c76c0bb returns null if var is not exist in a template [contributed by dtelyukh@github.com]
   * d18bb6d add FLAG_ECHO support
   * aec2b2b fix {{#if}} and {{#unless}} when value is an empty string
   * 8924604 fix variable output when false in an array
   * e82c324 fix for ifv and ifvar logic difference
   * 1e38e47 better logic on var name checking. now support {{0}} in the loop, but it is not handlebars.js standard

v0.7 https://github.com/zordius/lightncandy/tree/v0.7
   * add HISTORY.md
   * 777304c change compile format to include in val, isec, ifvar
   * 55de127 support {{../}} in {{#each}}
   * 57e90af fix parent levels detection bug
   * 96bb4d7 fix bugs for {{#.}} and {{#this}}
   * f4217d1 add ifv and unl 2 new methods for LCRun
   * 3f1014c fix {{#this}} and {{#.}} bug when used with {{../var}}
   * cbf0b28 fix {{#if}} logic error when using {{../}}
   * 2b20ef8 fix {{#with}} + {{../}} bug
   * 540cd44 now support FLAG_STANDALONE
   * 67ac5ff support {{>partial}}
   * 98c7bb1 detect unclosed token now

v0.6 https://github.com/zordius/lightncandy/tree/v0.6
   * align with handlebarsjs 1.0.11
   * 45ac3b6 fix #with bug when var is false
   * 1a46c2c minor #with logic fix. update document
   * fdc753b fix #each and section logic for 018-hb-withwith-006
   * e6cc95a add FLAG_PARENT, detect template error when scan()
   * 1980691 make new LCRun::val() method to normal path.val logic
   * 110d24f {{#if path.var}} bug fixed
   * d6ae2e6 fix {{#with path.val}} when input value is null
   * 71cf074 fix for 020-hb-doteach testcase

v0.5 https://github.com/zordius/lightncandy/tree/v0.5
   * 955aadf fix #each bug when input is a hash
   * final version for following handlebarsjs 1.0.7
