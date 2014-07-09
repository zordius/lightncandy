<?php

require_once('src/lightncandy.php');

class MustacheSpecTest extends PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider jsonSpecProvider
     */
    public function testSpecs($spec)
    {
        if (preg_match('/(partials|lambdas)\\.json/', $spec['file'])) {
            $this->markTestIncomplete("Skip [{$spec['file']}.{$spec['name']}]#{$spec['no']} , lightncandy do not support this now.");
        }

        $php = LightnCandy::compile($spec['template'], Array(
            'flags' => LightnCandy::FLAG_MUSTACHELOOKUP | LightnCandy::FLAG_MUSTACHESP | LightnCandy::FLAG_ERROR_EXCEPTION,
                'helpers' => array(
                )
            )
        );
        $renderer = LightnCandy::prepare($php);

        $this->assertEquals($spec['expected'], $renderer($spec['data']), "[{$spec['file']}.{$spec['name']}]#{$spec['no']}:{$spec['desc']} PHP CODE: $php");
    }

    public function jsonSpecProvider()
    {
        $ret = Array();

        foreach (glob('spec/specs/*.json') as $file) {
           $i=0;
           $json = json_decode(file_get_contents($file), true);
           $ret = array_merge($ret, array_map(function ($d) use ($file, &$i) {
               $d['file'] = $file;
               $d['no'] = ++$i;
               return Array($d);
           }, $json['tests']));
        }

        return $ret;
    }
}


?>
