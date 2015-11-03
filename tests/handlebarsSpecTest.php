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

        // TODO: require fix
        if (
            0
           ) {
            $this->fail('TODO: require fix');
        }

        // setup helpers
        $helpers = Array();
        if (isset($spec['helpers'])) {
            foreach ($spec['helpers'] as $name => $func) {
                if (!isset($func['php'])) {
                    $this->markTestIncomplete("Skip [{$spec['file']}#{$spec['description']}]#{$spec['no']} , no PHP helper code provided for this case.");
                }

                $hname = "custom_helper_{$spec['no']}_$name";
                $helpers[$name] = $hname;

                $helper = preg_replace('/\\$options->(\\w+)/', '$options[\'$1\']',
                        preg_replace('/\\$options->scope/', '$options[\'_this\']',
                            preg_replace('/function/', "function $hname", $func['php'], 1)
                        )
                    );
                echo "INIT HELPER: $helper\n";
                eval($helper);
            }

        }

        if (($spec['it'] === 'tokenizes hash arguments') || ($spec['it'] === 'tokenizes special @ identifiers')) {
            $helpers['foo'] = function () {return 'ABC';};
        }

        foreach (Array($hb_test_flag, $hb_test_flag | LightnCandy::FLAG_STANDALONE) as $f) {
            try {
                $php = LightnCandy::compile($spec['template'], Array(
                    'flags' => $f,
                    'hbhelpers' => $helpers,
                    'basedir' => $tmpdir,
                    'partials' => isset($spec['partials']) ? $spec['partials'] : null,
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
