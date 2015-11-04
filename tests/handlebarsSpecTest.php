<?php

require_once('src/lightncandy.php');

$tmpdir = sys_get_temp_dir();
$hb_test_flag = LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_ERROR_EXCEPTION | LightnCandy::FLAG_RUNTIMEPARTIAL | LightnCandy::FLAG_EXTHELPER | LightnCandy::FLAG_ERROR_SKIPPARTIAL | LightnCandy::FLAG_MUSTACHELOOKUP;

class HandlebarsSpecTest extends PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider jsonSpecProvider
     */
    public function testSpecs($spec)
    {
        global $tmpdir;
        global $hb_test_flag;

print_r($spec);

        //// Skip bad specs
        // 1. No expected or exception in spec
        if (!isset($spec['expected']) && !isset($spec['exception'])) {
            $this->markTestIncomplete("Skip [{$spec['file']}#{$spec['description']}]#{$spec['no']} , no expected result in spec, skip.");
        }

        // 2. Not supported case: lambdas
        $lambdas = false;
        array_walk_recursive($spec['data'], function ($v, $k) use (&$lambdas) {
            if (($v === true) && ($k === '!code')) {
                $lambdas = true;
            }
        });

        if ($lambdas) {
            $this->markTestIncomplete('Not supported case: lambdas');
        }

        // 3. Not supported case: foo/bar path
        if (
               ($spec['it'] === 'literal paths' && $spec['no'] === 58) ||
               ($spec['it'] === 'literal paths' && $spec['no'] === 59) ||
               ($spec['it'] === 'this keyword nested inside path')
           ) {
            $this->markTestIncomplete('Not supported case: foo/bar path');
        }

        // 4. Not supported case: optional data
        if (isset($spec['options']['data'])) {
            $this->markTestIncomplete('Not supported case: optional data');
        }

        // TODO: require fix
        if (
               ($spec['it'] === 'chained inverted sections') ||
               ($spec['it'] === 'chained inverted sections with mismatch') ||
               ($spec['it'] === 'block standalone else sections can be disabled') ||

               // Decorators: https://github.com/wycats/handlebars.js/blob/master/docs/decorators-api.md
               ($spec['description'] === 'decorators') ||

               // block parameters, https://github.com/zordius/lightncandy/issues/170
               ($spec['it'] === 'with provides block parameter') ||
               ($spec['it'] === 'works when data is disabled') ||
               ($spec['it'] === 'each with block params') ||
               ($spec['description'] === 'block params') ||

               // internal helper: lookup
               ($spec['description'] === '#lookup') ||

               // handlebars.js API: createFrame()
               ($spec['it'] === 'deep @foo triggers automatic top-level data') ||

               // helperMissing and blockHelperMissing
               ($spec['it'] === 'if a context is not found, custom helperMissing is used') ||
               ($spec['it'] === 'if a value is not found, custom helperMissing is used') ||
               ($spec['it'] === 'should include in simple block calls') ||
               ($spec['it'] === 'should include full id') ||
               ($spec['it'] === 'should include full id if a hash is passed') ||

               // helper for raw block
               ($spec['it'] === 'helper for raw block gets parameters') ||

               // scoped variable lookup
               ($spec['it'] === 'Scoped names take precedence over helpers') ||
               ($spec['it'] === 'Scoped names take precedence over block helpers') ||

               // partial no context
               ($spec['it'] === 'partials with no context') ||

               // partial in vm mode
               ($spec['it'] === 'rendering function partial in vm mode') ||

               // partial with string
               ($spec['it'] === 'Partials with string') ||

               // partial blocks
               ($spec['description'] === 'partial blocks') ||

               // inline partials
               ($spec['description'] === 'inline partials') ||

               // partial indent
               ($spec['it'] === 'prevent nested indented partials') ||

               // compat mode
               ($spec['description'] === 'compat mode')
           ) {
            $this->fail('TODO: require fix');
        }

        // PENDING: bad spec
        if (
               // Wait for https://github.com/jbboehr/handlebars-spec/issues/4
               ($spec['it'] === 'helpers can take an optional hash with booleans') ||
               ($spec['it'] === 'helpers take precedence over same-named context properties')
           ) {
            $this->fail('Bad spec: wait their fix');
        }

        // setup helpers
        $helpers = Array();
        foreach (array_merge(isset($spec['globalHelpers']) ? $spec['globalHelpers'] : array(), isset($spec['helpers']) ? $spec['helpers'] : array()) as $name => $func) {
            if (!isset($func['php'])) {
                $this->markTestIncomplete("Skip [{$spec['file']}#{$spec['description']}]#{$spec['no']} , no PHP helper code provided for this case.");
            }

            $hname = "custom_helper_{$spec['no']}_$name";
            $helpers[$name] = $hname;

            $helper = preg_replace('/\\$options->(\\w+)/', '$options[\'$1\']',
                    preg_replace('/\\$options->scope/', '$options[\'_this\']',
                        preg_replace('/\\$block\\/\\*\\[\'(.+?)\'\\]\\*\\/->(.+?)\\(/', '$block[\'$2\'](',
                            preg_replace('/new \\\\Handlebars\\\\SafeString\((.+?)\);/', 'array($1, "raw")',
                                preg_replace('/function/', "function $hname", $func['php'], 1)
                            )
                        )
                    )
                );
            echo "INIT HELPER: $helper\n";
            eval($helper);
        }

        foreach (Array($hb_test_flag, $hb_test_flag | LightnCandy::FLAG_STANDALONE) as $f) {
            try {
                $partials = isset($spec['globalPartials']) ? $spec['globalPartials'] : array();

                // Do not use array_merge() here because it destories numeric key
                if (isset($spec['partials'])) {
                    foreach ($spec['partials'] as $k => $v) {
                        $partials[$k] = $v;
                    }
                };

                $php = LightnCandy::compile($spec['template'], Array(
                    'flags' => $f,
                    'hbhelpers' => $helpers,
                    'basedir' => $tmpdir,
                    'partials' => $partials,
                ));
            } catch (Exception $e) {
                // Exception as expected, pass!
                if (isset($spec['exception'])) {
                    continue;
                }

                // Failed this case
                print_r(LightnCandy::getContext());
                print "#################### SPEC DUMP ####################\n";
                var_dump($spec);
                die;
                $this->fail('Exception:' . $e->getMessage());
            }
            $renderer = LightnCandy::prepare($php);
            if ($spec['description'] === 'Tokenizer') {
                // no compile error means passed
                continue;
            }

            $output = $renderer($spec['data']);

            if ($spec['expected'] !== $output) {
                print_r(LightnCandy::getContext());
                print "#################### SPEC DUMP ####################\n";
                var_dump($spec);
                echo "OUTPUT:\n";
                var_dump($output);
                echo "\nCODE: $php";
                die;
            }

            $this->assertEquals($spec['expected'], $renderer($spec['data']), "[{$spec['file']}#{$spec['description']}]#{$spec['no']}:{$spec['it']} PHP CODE: $php");
        }
    }

    public function jsonSpecProvider()
    {
        $ret = Array();

        foreach (glob('specs/handlebars/spec/*.json') as $file) {
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
