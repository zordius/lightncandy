<?php

require_once('src/lightncandy.php');

class MustacheSpecTest extends PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider yamlProvider
     */
    public function testSpecs($spec)
    {
//$name, $desc, $data, $template, $expected)
        $this->assertEquals(0, 0);
    }

    public function yamlProvider()
    {
        $ret = Array();

        foreach (glob('spec/specs/*.json') as $file) {
           $json = json_decode(file_get_contents($file), true);
           $ret = array_merge($ret, $json['tests']);
        }

        return $ret;
    }
}


?>
