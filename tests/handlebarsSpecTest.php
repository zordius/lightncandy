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

        //// Skip bad specs
        // No expect in spec
        if (!isset($spec['expected'])) {
            $this->markTestIncomplete("Skip [{$spec['file']}#{$spec['description']}]#{$spec['no']} , no expected result in spec, skip.");
        }
        // This spec is bad , lightncandy result as '} hello }' and same with mustache.js
        if ($spec['template'] === '{{{{raw}}}} {{test}} {{{{/raw}}}}') {
            $this->markTestIncomplete("Skip [{$spec['file']}#{$spec['description']}]#{$spec['no']} , bad spec, skip.");
        }
        // missing partial in this spec
        if ($spec['it'] === 'rendering function partial in vm mode') {
            $this->markTestIncomplete("Skip [{$spec['file']}#{$spec['description']}]#{$spec['no']} , bad spec, skip.");
        }
        // Helper depend on an external class, skip it now.
        if ($spec['it'] === 'simple literals work') {
            $this->markTestIncomplete("Skip [{$spec['file']}#{$spec['description']}]#{$spec['no']} , external class not found, skip.");
        }

        //// Skip unsupported features
        // can not get any hint of 'function' from handlebars-spec , maybe it is a conversion error.
        if (($spec['description'] === 'basic context') && preg_match('/functions/', $spec['it'])) {
            $this->markTestIncomplete("Skip [{$spec['file']}#{$spec['description']}]#{$spec['no']} , undefined function in spec json, skip.");
        }
        if (preg_match('/(.+) with function argument/', $spec['it'])) {
            $this->markTestIncomplete("Skip [{$spec['file']}#{$spec['description']}]#{$spec['no']} , undefined function in spec json, skip.");
        }
        if ($spec['it'] === 'Functions are bound to the context in knownHelpers only mode') {
            $this->markTestIncomplete("Skip [{$spec['file']}#{$spec['description']}]#{$spec['no']} , undefined function in spec json, skip.");
        }
        if ($spec['it'] === 'lambdas are resolved by blockHelperMissing, not handlebars proper') {
            $this->markTestIncomplete("Skip [{$spec['file']}#{$spec['description']}]#{$spec['no']} , undefined function in spec json, skip.");
        }

        // Do not support includeZero now
        if (($spec['description'] === '#if') && preg_match('/includeZero=true/', $spec['template'])) {
            $this->markTestIncomplete("Skip [{$spec['file']}#{$spec['description']}]#{$spec['no']} , lightncandy do not support this now.");
        }

        // Do not support setting options.data now
        if ($spec['it'] === 'data passed to helpers') {
            $this->markTestIncomplete("Skip [{$spec['file']}#{$spec['description']}]#{$spec['no']} , lightncandy do not support this now.");
        }

        // Do not support buildin helper : lookup now
        if ($spec['description'] == '#lookup') {
            $this->markTestIncomplete("Skip [{$spec['file']}#{$spec['description']}]#{$spec['no']} , lightncandy do not support this now.");
        }

        // No do not support partials with named arguments
        if ($spec['it'] == 'partials with parameters') {
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
                if (!isset($func['php'])) {
                    $this->markTestIncomplete("Skip [{$spec['file']}#{$spec['description']}]#{$spec['no']} , no PHP helper code provided for this case.");
                }

                // Wrong PHP helper interface in spec, skip.
                preg_match('/function *\(.+?\)/', $func['javascript'], $js_args);
                preg_match('/function *\(.+?\)/', $func['php'], $php_args);
                $jsn = substr_count($js_args[0], ',');
                $phpn = substr_count($php_args[0], ',');
                if ($jsn !== $phpn) {
                    $this->markTestIncomplete("Skip [{$spec['file']}#{$spec['description']}]#{$spec['no']} , PHP helper interface is wrong.");
                }

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

        return array_slice($ret, 0, 190);
    }
}


?>
