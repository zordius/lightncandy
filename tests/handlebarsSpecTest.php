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

        //// Skip unsupported features
        // can not get any hint of 'function' from handlebars-spec , maybe it is a conversion error.
        if (($spec['description'] === 'basic context') && preg_match('/functions/', $spec['it'])) {
            $this->markTestIncomplete("Skip [{$spec['file']}#{$spec['description']}]#{$spec['no']} , lightncandy do not support this now.");
        }

        // Lightncandy will not support old path style as foo/bar , now only support foo.bar .
        if ($spec['it'] === 'literal paths') {
            $this->markTestIncomplete("Skip [{$spec['file']}#{$spec['description']}]#{$spec['no']} , lightncandy do not support this now.");
        }

        // clean up old partials
        foreach (glob("$tmpdir/*.tmpl") as $file) {
            unlink($file);
        }

        // setup partials
        if (isset($spec['partials'])) {
            foreach ($spec['partials'] as $name => $cnt) {
                file_put_contents("$tmpdir/$name.tmpl", $cnt);
            }
        }

        // setup helpers
        $helpers = Array();
        if (isset($spec['helpers'])) {
            foreach ($spec['helpers'] as $name => $func) {
                $hname = "custom_helper_{$spec['no']}_$name";
                $helpers[$name] = $hname;
                eval(preg_replace('/function/', "function $hname", $func['php'], 1));
            }

        }

        $php = LightnCandy::compile($spec['template'], Array(
            'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_ERROR_EXCEPTION | LightnCandy::FLAG_RUNTIMEPARTIAL | LightnCandy::FLAG_EXTHELPER,
            'hbhelpers' => $helpers,
            'basedir' => $tmpdir,
        ));
        $renderer = LightnCandy::prepare($php);

        $this->assertEquals($spec['expected'], $renderer($spec['data']), "[{$spec['file']}#{$spec['description']}]#{$spec['no']}:{$spec['it']} PHP CODE: $php");
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

        return array_slice($ret, 0, 70);
    }
}


?>
