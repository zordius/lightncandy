<?php

use LightnCandy\LightnCandy;
use PHPUnit\Framework\TestCase;

$tmpdir = sys_get_temp_dir();

function getFunctionCode($func) {
    eval("\$v = $func;");
    return $v;
}

class MustacheSpecTest extends TestCase
{
    /**
     * @dataProvider jsonSpecProvider
     */
    public function testSpecs($spec)
    {
        global $tmpdir;

        $flag = LightnCandy::FLAG_MUSTACHE | LightnCandy::FLAG_ERROR_EXCEPTION;
        if (
            ($spec['name'] == 'Interpolation - Expansion') ||
            ($spec['name'] == 'Interpolation - Alternate Delimiters') ||
            ($spec['desc'] == 'Lambdas used for sections should receive the raw section string.') ||
            ($spec['name'] == 'Section - Expansion') ||
            ($spec['name'] == 'Section - Alternate Delimiters') ||
            ($spec['name'] == 'Section - Multiple Calls') ||
            ($spec['name'] == 'Inverted Section')
           ) {
            $this->markTestIncomplete('Not supported case: complex mustache lambdas');
        }

        if (isset($spec['data']['lambda']['php'])) {
            $spec['data']['lambda'] = getFunctionCode('function ($text = null) {' . $spec['data']['lambda']['php'] . '}');
        }

        foreach (array($flag, $flag | LightnCandy::FLAG_STANDALONEPHP) as $f) {
            global $calls;
            $calls = 0;
            $php = LightnCandy::compile($spec['template'], array(
                'flags' => $f,
                'partials' => isset($spec['partials']) ? $spec['partials'] : null,
                'basedir' => $tmpdir,
            ));
            $parsed = print_r(LightnCandy::$lastParsed, true);
            $renderer = LightnCandy::prepare($php);
            $this->assertEquals($spec['expected'], $renderer($spec['data'], array('debug' => 0)), "SPEC:\n" . print_r($spec, true) . "\nPHP CODE: $php\nPARSED: $parsed");
        }
    }

    public function jsonSpecProvider()
    {
        $ret = array();

        foreach (glob('specs/mustache/specs/*.json') as $file) {
            $i=0;
            $json = json_decode(file_get_contents($file), true);
            $ret = array_merge($ret, array_map(function ($d) use ($file, &$i) {
                $d['file'] = $file;
                $d['no'] = ++$i;
                return array($d);
            }, $json['tests']));
        }

        return $ret;
    }
}

