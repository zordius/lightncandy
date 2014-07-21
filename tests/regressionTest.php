<?php

require_once('src/lightncandy.php');
require_once('tests/helpers_for_test.php');

$tmpdir = sys_get_temp_dir();

class regressionTest extends PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider issueProvider
     */
    public function testIssues($issue)
    {
        global $tmpdir;

        $php = LightnCandy::compile($issue['template'], isset($issue['options']) ? $issue['options'] : null);
        $context = LightnCandy::getContext();
        if (count($context['error'])) {
            $this->fail('Compile failed due to:' . print_r($context['error'], true));
        }
        $renderer = LightnCandy::prepare($php);

        $this->assertEquals($issue['expected'], $renderer($issue['data'], $issue['debug']), "PHP CODE:\n$php");
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
                'options' => Array(
                    'flags' => LightnCandy::FLAG_THIS,
                ),
                'data' => Array('id' => 'bla bla bla', 'a' => Array('id' => 'OK!')),
                'expected' => 'bla bla bla, OK!'
            ),

            Array(
                'id' => 49,
                'template' => '{{date_format}} 1, {{date_format2}} 2, {{date_format3}} 3, {{date_format4}} 4',
                'options' => Array(
                    'helpers' => Array(
                        'date_format' => 'meetup_date_format',
                        'date_format2' => 'meetup_date_format2',
                        'date_format3' => 'meetup_date_format3',
                        'date_format4' => 'meetup_date_format4'
                    )
                ),
                'data' => null,
                'expected' => 'OKOK~1 1, OKOK~2 2, OKOK~3 3, OKOK~4 4'
            ),

            Array(
                'id' => 52,
                'template' => '{{{test_array tmp}}} should be happy!',
                'options' => Array(
                    'helpers' => Array(
                        'test_array',
                    )
                ),
                'data' => Array('tmp' => Array('A', 'B', 'C')),
                'expected' => 'IS_ARRAY should be happy!'
            ),

            Array(
                'id' => 62,
                'template' => '{{{test_join @root.foo.bar}}} should be happy!',
                'options' => Array(
                     'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_ERROR_EXCEPTION,
                     'helpers' => array('test_join')
                ),
                'data' => Array('foo' => Array('A', 'B', 'bar' => Array('C', 'D'))),
                'expected' => 'C.D should be happy!',
            ),

            Array(
                'id' => 64,
                'template' => '{{#each foo}} Test! {{this}} {{/each}}{{> test1}} ! >>> {{>recursive}}',
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_RUNTIMEPARTIAL,                      
                    'basedir' => 'tests',
                ),
                'data' => Array(
                 'bar' => 1,
                 'foo' => Array(
                  'bar' => 3,
                  'foo' => Array(
                   'bar' => 5,
                   'foo' => Array(
                    'bar' => 7,
                    'foo' => Array(
                     'bar' => 11,
                     'foo' => Array(
                      'no foo here'
                     )
                    )
                   )
                  )
                 )
                ),
                'expected' => " Test! 3  Test! [object Object] 123\n ! >>> 1 -> 3 -> 5 -> 7 -> 11 -> END!\n\n\n\n\n\n",
            ),

            Array(
                'id' => 66,
                'template' => '{{&foo}} , {{foo}}, {{{foo}}}',
                'options' => Array(
                     'flags' => LightnCandy::FLAG_HANDLEBARSJS
                ),
                'data' => Array('foo' => 'Test & " \' :)'),
                'expected' => 'Test & " \' :) , Test &amp; &quot; &#x27; :), Test & " \' :)',
            ),

            Array(
                'id' => 68,
                'template' => '{{#myeach foo}} Test! {{this}} {{/myeach}}',
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                    'hbhelpers' => Array(
                        'myeach' => function ($context, $options) {
                            $ret = '';
                            foreach ($context as $cx) {
                                $ret .= $options['fn']($cx);
                            }
                            return $ret;
                        }
                    )
                ),
                'data' => Array('foo' => Array('A', 'B', 'bar' => Array('C', 'D', 'E'))),
                'expected' => ' Test! A  Test! B  Test! C,D,E ',
            ),

            Array(
                'id' => 81,
                'template' => '{{#with ../person}} {{^name}} Unknown {{/name}} {{/with}}?!',
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_ERROR_EXCEPTION,
                ),
                'data' => Array('parent?!' => Array('A', 'B', 'bar' => Array('C', 'D', 'E'))),
                'expected' => '?!'
            ),

            Array(
                'id' => 83,
                'template' => '{{> tests/test1}}',
                'options' => Array(
                    'basedir' => '.',
                ),
                'data' => null,
                'expected' => "123\n"
            ),

            Array(
                'id' => 85,
                'template' => '{{helper 1 foo bar="q"}}',
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                    'hbhelpers' => Array(
                        'helper' => function ($arg1, $arg2, $options) {
                            return "ARG1:$arg1, ARG2:$arg2, HASH:{$options['hash']['bar']}";
                        }
                    )
                ),
                'data' => Array('foo' => 'BAR'),
                'expected' => 'ARG1:1, ARG2:BAR, HASH:q',
            ),

            Array(
                'id' => 88,
                'template' => '{{>test2}}',
                'options' => Array(
                    'flags' => 0,
                    'basedir' => 'tests',
                ),
                'data' => null,
                'expected' => "a123\nb\n",
            ),

            Array(
                'id' => 89,
                'template' => '{{#with}}SHOW:{{.}} {{/with}}',
                'data' => Array('with' => Array(1, 3, 7), 'a' => Array(2, 4, 9)),
                'expected' => 'SHOW:1 SHOW:3 SHOW:7 ',
            ),

            Array(
                'id' => 90,
                'template' => '{{#items}}{{#value}}{{.}}{{/value}}{{/items}}',
                'data' => Array('items' => Array(Array('value'=>'123'))),
                'expected' => '123',
            ),

            Array(
                'template' => '{{#each .}}{{..}}>{{/each}}',
                'data' => Array('a', 'b', 'c'),
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                ),
                'expected' => 'a,b,c>a,b,c>a,b,c>',
            ),

            Array(
                'template' => '{{#each .}}->{{>tests/test3}}{{/each}}',
                'data' => Array('a', 'b', 'c'),
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                    'basedir' => '.',
                ),
                'expected' => "->New context:a\n->New context:b\n->New context:c\n",
            ),

            Array(
                'template' => '{{#each .}}->{{>tests/test3 ../foo}}{{/each}}',
                'data' => Array('a', 'foo' => Array('d', 'e', 'f')),
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_RUNTIMEPARTIAL,
                    'basedir' => '.',
                ),
                'expected' => "->New context:d,e,f\n->New context:d,e,f\n",
            ),

            Array(
                'template' => '{{{"{{"}}}',
                'data' => null,
                'expected' => '{{',
            ),

            Array(
                'template' => '{{good_helper}}',
                'data' => null,
                'options' => Array(
                    'helpers' => Array('good_helper' => 'foo::bar'),
                ),
                'expected' => 'OK!',
            ),

            Array(
                'template' => '-{{.}}-',
                'options' => Array('flags' => LightnCandy::FLAG_THIS),
                'data' => 'abc',
                'expected' => '-abc-',
            ),

            Array(
                'template' => '-{{this}}-',
                'options' => Array('flags' => LightnCandy::FLAG_THIS),
                'data' => 123,
                'expected' => '-123-',
            ),

            Array(
                'template' => '{{#if .}}YES{{else}}NO{{/if}}',
                'options' => Array('flags' => LightnCandy::FLAG_ELSE),
                'data' => true,
                'expected' => 'YES',
            ),

            Array(
                'template' => '{{foo}}',
                'options' => Array('flags' => LightnCandy::FLAG_RENDER_DEBUG),
                'data' => Array('foo' => 'OK'),
                'expected' => 'OK',
            ),

            Array(
                'template' => '{{foo}}',
                'options' => Array('flags' => LightnCandy::FLAG_RENDER_DEBUG),
                'debug' => LCRun3::DEBUG_TAGS_ANSI,
                'data' => Array('foo' => 'OK'),
                'expected' => pack('H*', '1b5b303b33326d7b7b5b666f6f5d7d7d1b5b306d'),
            ),

            Array(
                'template' => '{{foo}}',
                'options' => Array('flags' => LightnCandy::FLAG_RENDER_DEBUG),
                'debug' => LCRun3::DEBUG_TAGS_HTML,
                'data' => null,
                'expected' => '<!--MISSED((-->{{[foo]}}<!--))-->',
            ),

            Array(
                'template' => '{{#myif foo}}YES{{else}}NO{{/myif}}',
                'data' => null,
                'options' => Array(
                    'hbhelpers' => Array('myif'),
                ),
                'expected' => 'NO',
            ),

            Array(
                'template' => '{{#mywith foo}}YA: {{name}}{{/mywith}}',
                'data' => Array('name' => 'OK?', 'foo' => Array('name' => 'OK!')),
                'options' => Array(
                    'hbhelpers' => Array('mywith'),
                ),
                'expected' => 'YA: OK!',
            ),
        );

        return array_map(function($i) {
            if (!isset($i['debug'])) {
                $i['debug'] = 0;
            }
            return Array($i);
        }, $issues);
    }
}


?>
