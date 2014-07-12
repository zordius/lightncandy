<?php

require_once('src/lightncandy.php');

$tmpdir = sys_get_temp_dir();

class HandlebarsSpecTest extends PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider jsonSpecProvider
     */
    public function testSpecs($spec)
    {
        global $tmpdir;

        // clean up old partials
        foreach (glob("$tmpdir/*.tmpl") as $file) {
            unlink($file);
        }

        if (isset($spec['partials'])) {
            foreach ($spec['partials'] as $name => $cnt) {
                file_put_contents("$tmpdir/$name.tmpl", $cnt);
            }
        }

        $php = LightnCandy::compile($spec['template'], Array(
            'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_ERROR_EXCEPTION | LightnCandy::FLAG_RUNTIMEPARTIAL,
            'helpers' => array(
            ),
            'basedir' => $tmpdir,
        ));
        $renderer = LightnCandy::prepare($php);

        $this->assertEquals($spec['expected'], $renderer($spec['data']), "[{$spec['file']}.{$spec['description']}]#{$spec['no']}:{$spec['it']} PHP CODE: $php");
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
               return Array($d);
           }, $json));
        }

        return $ret;
    }
}


?>
