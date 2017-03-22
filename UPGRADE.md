Upgrade Notice
==============

* Standalone templates compiled by older LightnCandy can be executed safe when you upgrade to any new version of LightnCandy.

* Recompile your none standalone templates when you upgrade LightnCandy.

Version v1.0.0
--------------
* Support PHP 7.1

Version v0.91
-------------
* Option basedir removed. Please use the new partialresolver option to handle partial files.
* Option fileext removed. Please use the new partialresolver option to handle partial files.

Version v0.90
-------------
* Option FLAG_BARE removed.
* When you save your compiled PHP code into a file, you need to add `<?php ` and `?>` by yourself.
* Remove $option['helpers'] and $option['blockhelpers']
* Rename $option['hbhelpers'] into $option['helpers']

Version v0.89
-------------
* Option FLAG_MUSTACHESP removed.
* Option FLAG_MUSTACHEPAIN removed.
* Option FLAG_WITH removed.
* Option FLAG_MUSTACHE includes FLAG_RUNTIMEPARTIAL now.
* Option FLAG_MUSTACHE includes FLAG_NOHBHELPERS now.
* Option FLAG_JSQUOTE is changed to FLAG_HBESCAPE
* Option FLAG_STANDALONE is changed to FLAG_STANDALONEPHP
* Option `lcrun` is changed to `runtime`
* generated render function interface changed, aligned with handlebars.js now
* LightnCandy be refactored into many sub classes, you can not just use curl to install it now.
* Due to big change of rendering function: sec() and inv(), the rendering supporting class `LCRun3` is renamed to `LightnCandy\Runtime`. If you compile templates as none standalone PHP code by LightnCandy v0.23 or before, you should compile these templates again. Or, you may run into `Class 'LCRun3' not found` error when you execute these old rendering functions.

Version v0.19
-------------
* Option FLAG_MUSTACHESEC removed, no need to use this flag anymore.

Version v0.13
-------------
* The interface of custom helpers was changed from v0.13 . if you use this feature you may need to modify your custom helper functions.

Version v0.12
-------------
* LightnCandy::getJsonSchema() removed
* jsonSchema generation feature removed
* Due to big change of render() debugging, the rendering supporting class `LCRun2` is renamed to `LCRun3`. If you compile templates as none standalone PHP code by LightnCandy v0.11 or before, you should compile these templates again. Or, you may run into `Class 'LCRun2' not found` error when you execute these old rendering functions.

Version v0.10
------------
* Due to big change of variable name handling, the rendering supporting class `LCRun` is renamed to `LCRun2`. If you compile templates as none standalone PHP code by LightnCandy v0.9 or before, you should compile these templates again. Or, you may run into `Class 'LCRun' not found` error when you execute these old rendering functions.
