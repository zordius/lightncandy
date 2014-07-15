<?php

require_once('src/lightncandy.php');

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
        );

        return $issues;
    }
}


?>
