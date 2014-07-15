<?php

require_once('src/lightncandy.php');
require_once('tests/helpers_for_test.php');

$tmpdir = sys_get_temp_dir();

class regressionTest extends PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider issueProvider
     */
    public function testSpecs($issue)
    {
        global $tmpdir;

        $php = LightnCandy::compile($issue['template'], $issue['options']);
        $renderer = LightnCandy::prepare($php);

        $this->assertEquals($issue['expected'], $renderer($issue['data']), "PHP CODE:\n$php");
    }

    public function issueProvider()
    {
        $issues = Array(
            Array(
                'id' => 39,
                'template' => '{{{tt}}}',
                'options' => null,
                'data' => Array('tt' => 'bla bla bla'),
                'expected' => 'bla bla bla'
            ),

            Array(
                'id' => 44,
                'template' => '<div class="terms-text"> {{render "artists-terms"}} </div>',
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_ERROR_LOG | LightnCandy::FLAG_EXTHELPER,
                    'helpers' => Array(
                        'url',
                        'render' => function($view,$data = array()) {
                            return 'OK!';
                         }
                    )
                ),
                'data' => Array('tt' => 'bla bla bla'),
                'expected' => '<div class="terms-text"> OK! </div>'
            ),

            Array(
                'id' => 45,
                'template' => '{{{a.b.c}}}, {{a.b.bar}}, {{a.b.prop}}',
                'options' => Array(
                    'flags' => LightnCandy::FLAG_ERROR_LOG | LightnCandy::FLAG_INSTANCE | LightnCandy::FLAG_HANDLEBARSJS,
                ),
                'data' => Array('a' => Array('b' => new foo)),
                'expected' => ', OK!, Yes!'
            ),

            Array(
                'id' => 46,
                'template' => '{{{this.id}}}, {{a.id}}',
                'options' => null,
                'data' => Array('id' => 'bla bla bla', 'a' => Array('id' => 'OK!')),
                'expected' => 'bla bla bla, OK!',
            ),
        );

        return $issues;
    }
}


?>
