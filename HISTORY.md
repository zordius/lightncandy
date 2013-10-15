HISTORY
=======

v0.8 curent trunk
   * align with handlebars 1.0.12
   * aaec049 fix partial in partial not works bug
   * 52706cc fix for {{#var}} and {{^var}} , now when var === 0 means true
   * 4f7f816 support {{@key}} and {{@index}} in {{#each var}}
   * 63aef2a prevent array_diff_key() PHP warning when {{#each}} on none array value
   * 10f3d73 add more is_array() check when {{#each}} and {{#var}}
   * 367247b fix {{#if}} and {{#unless}} when value is an empty array
   * c76c0bb returns null if var is not exist in a template [contributed by dtelyukh@github.com]
   * d18bb6d add FLAG_ECHO support
   * aec2b2b fix {{#if}} and {{#unless}} when value is an empty string
   * fix variable output when false in an array

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
