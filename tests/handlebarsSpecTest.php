<?php

use LightnCandy\LightnCandy;
use LightnCandy\Runtime;

$tmpdir = sys_get_temp_dir();
$hb_test_flag = LightnCandy::FLAG_HANDLEBARSJS_FULL | LightnCandy::FLAG_ERROR_EXCEPTION | LightnCandy::FLAG_EXTHELPER;
$tested = 0;
$test_flags = array($hb_test_flag);
if (!version_compare(phpversion(), '5.4.0', '<')) {
    $test_flags[] = $hb_test_flag | LightnCandy::FLAG_STANDALONEPHP;
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
    $code = preg_replace('/new \\\\Handlebars\\\\SafeString\((.+?)\);/', 'array($1, "raw");', $code);
    return preg_replace('/new SafeString\((.+?)\);/', 'array($1, "raw");', $code);
}

function recursive_lambda_fix(&$array) {
    if (is_array($array) && isset($array['!code']) && isset($array['php'])) {
        $code = patch_safestring($array['php']);
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

class HandlebarsSpecTest extends PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider jsonSpecProvider
     */
    public function testSpecs($spec)
    {
        global $tmpdir;
        global $tested;
        global $test_flags;

        recursive_unset($spec, '!sparsearray');
        recursive_lambda_fix($spec['data']);
        if (isset($spec['options']['data'])) {
            recursive_lambda_fix($spec['options']['data']);
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

        // 5. Different API, no need to test
        if (
               ($spec['it'] === 'registering undefined partial throws an exception')
           ) {
            $this->markTestIncomplete('Not supported case: just skip it');
        }

        // TODO: require fix
        if (
               ($spec['it'] === 'chained inverted sections') ||

               // Decorators: https://github.com/wycats/handlebars.js/blob/master/docs/decorators-api.md
               ($spec['description'] === 'decorators') ||

               // block parameters, https://github.com/zordius/lightncandy/issues/170
               ($spec['it'] === 'should allow block params on chained helpers') ||
               ($spec['it'] === 'should take presedence over helper values') ||
               ($spec['it'] === 'should not take presedence over pathed values') ||

               // helperMissing and blockHelperMissing
               ($spec['it'] === 'if a context is not found, helperMissing is used') ||
               ($spec['it'] === 'if a context is not found, custom helperMissing is used') ||
               ($spec['it'] === 'if a value is not found, custom helperMissing is used') ||
               ($spec['it'] === 'should include in simple block calls') ||
               ($spec['it'] === 'should include full id') ||
               ($spec['it'] === 'should include full id if a hash is passed') ||
               ($spec['it'] === 'lambdas resolved by blockHelperMissing are bound to the context') ||


               // helper for raw block
               ($spec['it'] === 'helper for raw block gets parameters') ||

               // scoped variable lookup
               ($spec['it'] === 'Scoped names take precedence over helpers') ||
               ($spec['it'] === 'Scoped names take precedence over block helpers') ||

               // partial in vm mode
               ($spec['it'] === 'rendering function partial in vm mode') ||
               ($spec['it'] === 'rendering template partial in vm mode throws an exception') ||

               // partial with string
               ($spec['it'] === 'Partials with string') ||

               // partial blocks
               ($spec['description'] === 'partial blocks') ||

               // inline partials
               ($spec['description'] === 'inline partials') ||
               ($spec['it'] === 'should support multiple levels of inline partials') ||
               ($spec['it'] === 'GH-1089: should support failover content in multiple levels of inline partials') ||
               ($spec['it'] === 'GH-1099: should support greater than 3 nested levels of inline partials') ||

               // compat mode
               ($spec['description'] === 'compat mode') ||

               // knownHelpers and knownHelpersOnly
               ($spec['description'] === 'knownHelpers') ||

               // string params mode
               ($spec['description'] === 'string params mode') ||

               // directives
               ($spec['description'] === 'directives') ||

               // track ids
               ($spec['file'] === 'specs/handlebars/spec/track-ids.json') ||

               // Error report: position
               ($spec['it'] === 'knows how to report the correct line number in errors') ||
               ($spec['it'] === 'knows how to report the correct line number in errors when the first character is a newline') ||

               // !!!! Never support
               ($spec['template'] === '{{foo}') ||

               // need confirm
               ($spec['it'] === 'provides each nested helper invocation its own options hash') ||
               ($spec['template'] === "{{blog (equal (equal true true) true fun='yes')}}") ||
               ($spec['it'] === 'multiple subexpressions in a hash') ||
               ($spec['it'] === 'multiple subexpressions in a hash with context') ||
               ($spec['it'] === 'in string params mode,') ||
               ($spec['it'] === "subexpressions can't just be property lookups") ||
               ($spec['it'] === 'fails with multiple and args') ||
               ($spec['it'] === 'each with function argument') ||
               ($spec['it'] === 'if with function argument') ||
               ($spec['it'] === 'pass number literals') ||
               ($spec['it'] === 'functions returning safestrings shouldn\'t be escaped') ||
               ($spec['it'] === 'should handle undefined and null') ||
               ($spec['it'] === 'with with function argument') ||
               ($spec['it'] === 'depthed block functions without context argument') ||
               ($spec['template'] === '{{echo (header)}}') ||
               ($spec['it'] === 'pathed block functions without context argument') ||
               ($spec['it'] === 'block functions without context argument') ||
               ($spec['it'] === 'depthed block functions with context argument') ||
               ($spec['it'] === 'block functions with context argument') ||
               ($spec['it'] === 'depthed functions with context argument') ||
               ($spec['it'] === 'pathed functions with context argument') ||
               ($spec['it'] === 'functions with context argument') ||
               (($spec['template'] === '{{awesome}}') && ($spec['it'] === 'functions'))
           ) {
            $this->markTestIncomplete('TODO: require fix');
        }

        // FIX SPEC
        if ($spec['it'] === 'should take presednece over parent block params') {
            $spec['helpers']['goodbyes']['php'] = 'function($options) { static $value; if($value === null) { $value = 1; } return $options->fn(array("value" => "bar"), array("blockParams" => ($options["fn.blockParams"] === 1) ? array($value++, $value++) : null));}';
        }

        foreach ($test_flags as $f) {
            // setup helpers
            $tested++;
            $helpers = Array();
            $helpersList = '';
            foreach (array_merge((isset($spec['globalHelpers']) && is_array($spec['globalHelpers'])) ? $spec['globalHelpers'] : array(), (isset($spec['helpers']) && is_array($spec['helpers'])) ? $spec['helpers'] : array()) as $name => $func) {
                if (!isset($func['php'])) {
                    $this->markTestIncomplete("Skip [{$spec['file']}#{$spec['description']}]#{$spec['no']} , no PHP helper code provided for this case.");
                }
                $hname = preg_replace('/\\.|\\//', '_', "custom_helper_{$spec['no']}_{$tested}_$name");
                $helpers[$name] = $hname;
                $helper = preg_replace('/\\$options->(\\w+)/', '$options[\'$1\']',
                        preg_replace('/\\$options->scope/', '$options[\'_this\']',
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

                $php = LightnCandy::compile($spec['template'], Array(
                    'flags' => $f,
                    'hbhelpers' => $helpers,
                    'basedir' => $tmpdir,
                    'partials' => $partials,
                ));

                $parsed = print_r(LightnCandy::$lastParsed, true);
            } catch (Exception $e) {
                // Exception as expected, pass!
                if (isset($spec['exception'])) {
                    continue;
                }

                // Failed this case
                $this->fail('Exception:' . $e->getMessage());
            }
            $renderer = LightnCandy::prepare($php);
            if ($spec['description'] === 'Tokenizer') {
                // no compile error means passed
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
                    continue;
                }
                $this->fail("Rendering Error in {$spec['file']}#{$spec['description']}]#{$spec['no']}:{$spec['it']} PHP CODE: $php\nPARSED: $parsed\n" . $e->getMessage());
            }

            if (!isset($spec['expected'])) {
                $this->fail('Should Fail:' . print_r($spec, true));
            }

            $this->assertEquals($spec['expected'], $result, "[{$spec['file']}#{$spec['description']}]#{$spec['no']}:{$spec['it']} PHP CODE: $php\nPARSED: $parsed\nHELPERS:$helpersList");
        }
    }

    public function jsonSpecProvider()
    {
        $ret = Array();

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
               return Array($d);
           }, $json));
        }

        return $ret;
    }
}
?>
