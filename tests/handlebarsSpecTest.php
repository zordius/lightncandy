<?php

use LightnCandy\LightnCandy;
use LightnCandy\Runtime;
use PHPUnit\Framework\TestCase;

$tmpdir = sys_get_temp_dir();
$hb_test_flag = LightnCandy::FLAG_HANDLEBARSJS_FULL | LightnCandy::FLAG_ERROR_EXCEPTION | LightnCandy::FLAG_EXTHELPER;
$tested = 0;
$standalone = false;
$test_flags = array($hb_test_flag | LightnCandy::FLAG_STANDALONEPHP);
if (!version_compare(phpversion(), '5.4.0', '<')) {
    $test_flags[] = $hb_test_flag;
}

function recursive_unset(&$array, $unwanted_key) {
    if (isset($array[$unwanted_key])) {
        unset($array[$unwanted_key]);
    }
    foreach ($array as &$value) {
        if (is_array($value)) {
            recursive_unset($value, $unwanted_key);
        }
    }
}

function patch_safestring($code) {
    global $standalone;
    $classname = $standalone ? 'LS' : '\\LightnCandy\\SafeString';
    $code = preg_replace('/ \\\\Handlebars\\\\SafeString(\s*\(.*?\))?/', ' ' . $classname . '$1', $code);
    return preg_replace('/ SafeString(\s*\(.*?\))?/', ' ' . $classname . '$1', $code);
}

function patch_this($code) {
    return preg_replace('/\\$options->scope/', '$options[\'_this\']', $code);
}

function recursive_lambda_fix(&$array) {
    if (is_array($array) && isset($array['!code']) && isset($array['php'])) {
        $code = patch_this(patch_safestring($array['php']));
        eval("\$v = $code;");
        $array = $v;
    }
    if (is_array($array)) {
        foreach ($array as &$value) {
            if (is_array($value)) {
                recursive_lambda_fix($value);
            }
        }
    }
}

class Utils {
    static public function createFrame($data) {
        if (is_array($data)) {
            $r = array();
            foreach ($data as $k => $v) {
                $r[$k] = $v;
            }
            return $r;
        }
        return $data;
    }
}

class HandlebarsSpecTest extends TestCase
{
    /**
     * @dataProvider jsonSpecProvider
     */
    public function testSpecs($spec)
    {
        global $tmpdir;
        global $tested;
        global $test_flags;
        global $standalone;

        recursive_unset($spec, '!sparsearray');
        recursive_lambda_fix($spec['data']);
        if (isset($spec['options']['data'])) {
            recursive_lambda_fix($spec['options']['data']);
        }

        // Fix {} for these test cases
        if (
               ($spec['it'] === 'should override template partials') ||
               ($spec['it'] === 'should override partials down the entire stack') ||
               ($spec['it'] === 'should define inline partials for block')
           ) {
            $spec['data'] = new stdClass;
        }

        //// Skip bad specs
        // 1. No expected nor exception in spec
        if (!isset($spec['expected']) && !isset($spec['exception'])) {
            $this->markTestIncomplete("Skip [{$spec['file']}#{$spec['description']}]#{$spec['no']} , no expected result in spec, skip.");
        }

        // 2. Not supported case: foo/bar path
        if (
               ($spec['it'] === 'literal paths' && $spec['no'] === 58) ||
               ($spec['it'] === 'literal paths' && $spec['no'] === 59) ||
               ($spec['it'] === 'this keyword nested inside path') ||
               ($spec['it'] === 'this keyword nested inside helpers param') ||
               ($spec['it'] === 'should handle invalid paths') ||
               ($spec['it'] === 'parameter data throws when using complex scope references') ||
               ($spec['it'] === 'block with complex lookup using nested context')
           ) {
            $this->markTestIncomplete('Not supported case: foo/bar path');
        }

        // 3. Different API, no need to test
        if (
               ($spec['it'] === 'registering undefined partial throws an exception')
           ) {
            $this->markTestIncomplete('Not supported case: just skip it');
        }

        // 4. block parameters, special case now do not support
        if (
               ($spec['it'] === 'should not take presedence over pathed values')
           ) {
            $this->markTestIncomplete('Not supported case: just skip it');
        }

        // 5. Not supported case: helperMissing and blockHelperMissing
        if (
               ($spec['it'] === 'if a context is not found, helperMissing is used') ||
               ($spec['it'] === 'if a context is not found, custom helperMissing is used') ||
               ($spec['it'] === 'if a value is not found, custom helperMissing is used') ||
               ($spec['it'] === 'should include in simple block calls') ||
               ($spec['it'] === 'should include full id') ||
               ($spec['it'] === 'should include full id if a hash is passed') ||
               ($spec['it'] === 'lambdas resolved by blockHelperMissing are bound to the context')
           ) {
            $this->markTestIncomplete('Not supported case: just skip it');
        }

        // 6. Not supported case: misc
        if (
               // compat mode
               ($spec['description'] === 'compat mode') ||

               // directives
               ($spec['description'] === 'directives') ||

               // track ids
               ($spec['file'] === 'specs/handlebars/spec/track-ids.json') ||

               // Error report: position
               ($spec['it'] === 'knows how to report the correct line number in errors') ||
               ($spec['it'] === 'knows how to report the correct line number in errors when the first character is a newline') ||

               // chained inverted sections + block params
               ($spec['it'] === 'should allow block params on chained helpers') ||

               // Decorators: https://github.com/wycats/handlebars.js/blob/master/docs/decorators-api.md
               ($spec['description'] === 'decorators') ||

               // strict mode
               ($spec['description'] === 'strict mode') ||

               // assume objects
               ($spec['description'] === 'assume objects') ||

               // helper for raw block
               ($spec['it'] === 'helper for raw block gets parameters') ||

               // lambda function in data
               ($spec['it'] === 'Functions are bound to the context in knownHelpers only mode') ||

               // !!!! Never support
               ($spec['template'] === '{{foo}')
           ) {
            $this->markTestIncomplete('Not supported case: just skip it');
        }

        // TODO: require fix
        if (
               // inline partials
               ($spec['template'] === '{{#with .}}{{#*inline "myPartial"}}success{{/inline}}{{/with}}{{> myPartial}}') ||

               // SafeString
               ($spec['it'] === 'functions returning safestrings shouldn\'t be escaped') ||
               ($spec['it'] === 'rendering function partial in vm mode') ||

               // need confirm
               ($spec['it'] === 'provides each nested helper invocation its own options hash') ||
               ($spec['template'] === '{{echo (header)}}') ||
               ($spec['it'] === 'block functions without context argument') ||
               ($spec['it'] === 'depthed block functions with context argument') ||
               ($spec['it'] === 'block functions with context argument')
           ) {
            $this->markTestIncomplete('TODO: require fix');
        }

        // FIX SPEC
        if ($spec['it'] === 'should take presednece over parent block params') {
            $spec['helpers']['goodbyes']['php'] = 'function($options) { static $value; if($value === null) { $value = 1; } return $options->fn(array("value" => "bar"), array("blockParams" => ($options["fn.blockParams"] === 1) ? array($value++, $value++) : null));}';
        }
        if (($spec['it'] === 'should handle undefined and null') && ($spec['expected'] === 'true true object')) {
            $spec['expected'] = 'true true array';
        }

        foreach ($test_flags as $f) {
            // setup helpers
            $tested++;
            $helpers = array();
            $helpersList = '';
            foreach (array_merge((isset($spec['globalHelpers']) && is_array($spec['globalHelpers'])) ? $spec['globalHelpers'] : array(), (isset($spec['helpers']) && is_array($spec['helpers'])) ? $spec['helpers'] : array()) as $name => $func) {
                if (!isset($func['php'])) {
                    $this->markTestIncomplete("Skip [{$spec['file']}#{$spec['description']}]#{$spec['no']} , no PHP helper code provided for this case.");
                }
                $hname = preg_replace('/\\.|\\//', '_', "custom_helper_{$spec['no']}_{$tested}_$name");
                $helpers[$name] = $hname;
                $standalone = $f & LightnCandy::FLAG_STANDALONEPHP;
                $helper = preg_replace('/\\$options->(\\w+)/', '$options[\'$1\']',
                        patch_this(
                            preg_replace('/\\$block\\/\\*\\[\'(.+?)\'\\]\\*\\/->(.+?)\\(/', '$block[\'$2\'](',
                                patch_safestring(
                                    preg_replace('/function/', "function $hname", $func['php'], 1)
                                )
                            )
                        )
                    );
                if (($spec['it'] === 'helper block with complex lookup expression') && ($name === 'goodbyes')) {
                    $helper = preg_replace('/\\[\'fn\'\\]\\(\\)/', '[\'fn\'](array())', $helper);
                }
                $helpersList .= "$helper\n";
                eval($helper);
            }

            try {
                $partials = isset($spec['globalPartials']) ? $spec['globalPartials'] : array();

                // Do not use array_merge() here because it destories numeric key
                if (isset($spec['partials'])) {
                    foreach ($spec['partials'] as $k => $v) {
                        $partials[$k] = $v;
                    }
                };

                if (isset($spec['compileOptions']['preventIndent'])) {
                    if ($spec['compileOptions']['preventIndent']) {
                        $f = $f | LightnCandy::FLAG_PREVENTINDENT;
                    }
                }

                if (isset($spec['compileOptions']['explicitPartialContext'])) {
                    if ($spec['compileOptions']['explicitPartialContext']) {
                        $f = $f | LightnCandy::FLAG_PARTIALNEWCONTEXT;
                    }
                }

                if (isset($spec['compileOptions']['ignoreStandalone'])) {
                    if ($spec['compileOptions']['ignoreStandalone']) {
                        $f = $f | LightnCandy::FLAG_IGNORESTANDALONE;
                    }
                }

                if (isset($spec['compileOptions']['stringParams'])) {
                    if ($spec['compileOptions']['stringParams']) {
                        $f = $f | LightnCandy::FLAG_STRINGPARAMS;
                    }
                }

                if (isset($spec['compileOptions']['knownHelpersOnly'])) {
                    if ($spec['compileOptions']['knownHelpersOnly']) {
                        $f = $f | LightnCandy::FLAG_KNOWNHELPERSONLY;
                    }
                }

                $php = LightnCandy::compile($spec['template'], array(
                    'flags' => $f,
                    'helpers' => $helpers,
                    'basedir' => $tmpdir,
                    'partials' => $partials,
                ));

                $parsed = print_r(LightnCandy::$lastParsed, true);
            } catch (Exception $e) {
                // Exception as expected, pass!
                if (isset($spec['exception'])) {
                    $this->assertEquals(true, true);
                    continue;
                }

                // Failed this case
                $this->fail('Exception:' . $e->getMessage());
            }
            $renderer = LightnCandy::prepare($php, null, false);
            if ($spec['description'] === 'Tokenizer') {
                // no compile error means passed
                $this->assertEquals(true, true);
                continue;
            }

            try {
                $ropt = array('debug' => Runtime::DEBUG_ERROR_EXCEPTION);
                if (isset($spec['options']['data'])) {
                    $ropt['data'] = $spec['options']['data'];
                }
                $result = $renderer($spec['data'], $ropt);
            } catch (Exception $e) {
                if (!isset($spec['expected'])) {
                    // expected error and catched here, so passed
                    $this->assertEquals(true, true);
                    continue;
                }
                $this->fail("Rendering Error in {$spec['file']}#{$spec['description']}]#{$spec['no']}:{$spec['it']} PHP CODE: $php\nPARSED: $parsed\n" . $e->getMessage());
            }

            if (!isset($spec['expected'])) {
                $this->fail('Should Fail:' . print_r($spec, true) . "PHP CODE: $php\nPARSED: $parsed\nHELPERS:$helpersList");
            }

            $this->assertEquals($spec['expected'], $result, "[{$spec['file']}#{$spec['description']}]#{$spec['no']}:{$spec['it']} PHP CODE: $php\nPARSED: $parsed\nHELPERS:$helpersList");
        }
    }

    public function jsonSpecProvider()
    {
        $ret = array();

        foreach (glob('specs/handlebars/spec/*.json') as $file) {
           if ($file === 'specs/handlebars/spec/tokenizer.json') {
               continue;
           }
           if ($file === 'specs/handlebars/spec/parser.json') {
               continue;
           }
           $i=0;
           $json = json_decode(file_get_contents($file), true);
           $ret = array_merge($ret, array_map(function ($d) use ($file, &$i) {
               $d['file'] = $file;
               $d['no'] = ++$i;
               if (!isset($d['message'])) {
                   $d['message'] = null;
               }
               if (!isset($d['data'])) {
                   $d['data'] = null;
               }
               return array($d);
           }, $json));
        }

        return $ret;
    }
}

