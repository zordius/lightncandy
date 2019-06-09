<?php

use LightnCandy\LightnCandy;
use LightnCandy\Runtime;
use PHPUnit\Framework\TestCase;

require_once('tests/helpers_for_test.php');

$tmpdir = sys_get_temp_dir();

class regressionTest extends TestCase
{
    /**
     * @dataProvider issueProvider
     */
    public function testIssues($issue)
    {
        global $tmpdir;

        $php = LightnCandy::compile($issue['template'], isset($issue['options']) ? $issue['options'] : null);
        $context = LightnCandy::getContext();
        $parsed = print_r(LightnCandy::$lastParsed, true);
        if (count($context['error'])) {
            $this->fail('Compile failed due to:' . print_r($context['error'], true) . "\nPARSED: $parsed");
        }
        $renderer = LightnCandy::prepare($php);

        $this->assertEquals($issue['expected'], $renderer(isset($issue['data']) ? $issue['data'] : null, array('debug' => $issue['debug'])), "PHP CODE:\n$php\n$parsed");
    }

    public function issueProvider()
    {
        $test_helpers = array('ouch' =>function() {
            return 'ok';
        });

        $test_helpers2 = array('ouch' =>function() {return 'wa!';});

        $test_helpers3 = array('ouch' =>function() {return 'wa!';}, 'god' => function () {return 'yo';});

        $issues = Array(
            Array(
                'id' => 39,
                'template' => '{{{tt}}}',
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
                    'partials' => Array(
                        'test1' => "123\n",
                        'recursive' => "{{#if foo}}{{bar}} -> {{#with foo}}{{>recursive}}{{/with}}{{else}}END!{{/if}}\n",
                    ),
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
                    'helpers' => Array(
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
                    'partials' => Array(
                        'tests/test1' => "123\n",
                    ),
                ),
                'expected' => "123\n"
            ),

            Array(
                'id' => 85,
                'template' => '{{helper 1 foo bar="q"}}',
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                    'helpers' => Array(
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
                    'partials' => Array(
                        'test2' => "a{{> test1}}b\n",
                        'test1' => "123\n",
                    ),
                ),
                'expected' => "a123\nb\n",
            ),

            Array(
                'id' => 89,
                'template' => '{{#with}}SHOW:{{.}} {{/with}}',
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_NOHBHELPERS,
                ),
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
                'id' => 109,
                'template' => '{{#if "OK"}}it\'s great!{{/if}}',
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_NOESCAPE,
                ),
                'expected' => 'it\'s great!',
            ),

            Array(
                'id' => 110,
                'template' => 'ABC{{#block "YES!"}}DEF{{foo}}GHI{{/block}}JKL',
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_BESTPERFORMANCE,
                    'helpers' => Array(
                        'block' => function ($name, $options) {
                            return "1-$name-2-" . $options['fn']() . '-3';
                        }
                    ),
                ),
                'data' => Array('foo' => 'bar'),
                'expected' => 'ABC1-YES!-2-DEFbarGHI-3JKL',
            ),

            Array(
                'id' => 109,
                'template' => '{{foo}} {{> test}}',
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_NOESCAPE,
                    'partials' => Array('test' => '{{foo}}'),
                ),
                'data' => Array('foo' => '<'),
                'expected' => '< <',
            ),

            Array(
                'id' => 114,
                'template' => '{{^myeach .}}OK:{{.}},{{else}}NOT GOOD{{/myeach}}',
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_BESTPERFORMANCE,
                    'helpers' => Array(
                        'myeach' => function ($context, $options) {
                            $ret = '';
                            foreach ($context as $cx) {
                                $ret .= $options['fn']($cx);
                            }
                            return $ret;
                        }
                    ),
                ),
                'data' => Array(1, 'foo', 3, 'bar'),
                'expected' => 'NOT GOODNOT GOODNOT GOODNOT GOOD',
            ),

            Array(
                'id' => 124,
                'template' => '{{list foo bar abc=(lt 10 3) def=(lt 3 10)}}',
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                    'helpers' => Array(
                        'lt' => function ($a, $b) {
                            return ($a > $b) ? new SafeString("$a>$b") : '';
                        },
                        'list' => function () {
                            $out = 'List:';
                            $args = func_get_args();
                            $opts = array_pop($args);

                            foreach ($args as $v) {
                                if ($v) {
                                    $out .= ")$v , ";
                                }
                            }

                            foreach ($opts['hash'] as $k => $v) {
                                if ($v) {
                                    $out .= "]$k=$v , ";
                                }
                            }
                            return new SafeString($out);
                        }
                    ),
                ),
                'data' => Array('foo' => 'OK!', 'bar' => 'OK2', 'abc' => false, 'def' => 123),
                'expected' => 'List:)OK! , )OK2 , ]abc=10>3 , ',
            ),

            Array(
                'id' => 124,
                'template' => '{{#if (equal \'OK\' cde)}}YES!{{/if}}',
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                    'helpers' => Array(
                        'equal' => function ($a, $b) {
                            return $a === $b;
                        }
                    ),
                ),
                'data' => Array('cde' => 'OK'),
                'expected' => 'YES!'
            ),

            Array(
                'id' => 124,
                'template' => '{{#if (equal true (equal \'OK\' cde))}}YES!{{/if}}',
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                    'helpers' => Array(
                        'equal' => function ($a, $b) {
                            return $a === $b;
                        }
                    ),
                ),
                'data' => Array('cde' => 'OK'),
                'expected' => 'YES!'
            ),

            Array(
                'id' => 125,
                'template' => '{{#if (equal true ( equal \'OK\' cde))}}YES!{{/if}}',
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                    'helpers' => Array(
                        'equal' => function ($a, $b) {
                            return $a === $b;
                        }
                    ),
                ),
                'data' => Array('cde' => 'OK'),
                'expected' => 'YES!'
            ),

            Array(
                'id' => 125,
                'template' => '{{#if (equal true (equal \' OK\' cde))}}YES!{{/if}}',
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                    'helpers' => Array(
                        'equal' => function ($a, $b) {
                            return $a === $b;
                        }
                    ),
                ),
                'data' => Array('cde' => ' OK'),
                'expected' => 'YES!'
            ),

            Array(
                'id' => 125,
                'template' => '{{#if (equal true (equal \' ==\' cde))}}YES!{{/if}}',
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                    'helpers' => Array(
                        'equal' => function ($a, $b) {
                            return $a === $b;
                        }
                    ),
                ),
                'data' => Array('cde' => ' =='),
                'expected' => 'YES!'
            ),

            Array(
                'id' => 125,
                'template' => '{{#if (equal true (equal " ==" cde))}}YES!{{/if}}',
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                    'helpers' => Array(
                        'equal' => function ($a, $b) {
                            return $a === $b;
                        }
                    ),
                ),
                'data' => Array('cde' => ' =='),
                'expected' => 'YES!'
            ),

            Array(
                'id' => 125,
                'template' => '{{[ abc]}}',
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                    'helpers' => Array(
                        'equal' => function ($a, $b) {
                            return $a === $b;
                        }
                    ),
                ),
                'data' => Array(' abc' => 'YES!'),
                'expected' => 'YES!'
            ),

            Array(
                'id' => 125,
                'template' => '{{list [ abc] " xyz" \' def\' "==" \'==\' "OK"}}',
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                    'helpers' => Array(
                        'list' => function ($a, $b) {
                            $out = 'List:';
                            $args = func_get_args();
                            $opts = array_pop($args);
                            foreach ($args as $v) {
                                if ($v) {
                                    $out .= ")$v , ";
                                }
                            }
                            return $out;
                        }
                    ),
                ),
                'data' => Array(' abc' => 'YES!'),
                'expected' => 'List:)YES! , ) xyz , ) def , )&#x3D;&#x3D; , )&#x3D;&#x3D; , )OK , ',
            ),

            Array(
                'id' => 127,
                'template' => '{{#each array}}#{{#if true}}{{name}}-{{../name}}-{{../../name}}-{{../../../name}}{{/if}}##{{#myif true}}{{name}}={{../name}}={{../../name}}={{../../../name}}{{/myif}}###{{#mywith true}}{{name}}~{{../name}}~{{../../name}}~{{../../../name}}{{/mywith}}{{/each}}',
                'data' => Array('name' => 'john', 'array' => Array(1,2,3)),
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                    'helpers' => Array('myif', 'mywith'),
                ),
                // PENDING ISSUE, check for https://github.com/wycats/handlebars.js/issues/1135
                // 'expected' => '#--john-##==john=###~~john~#--john-##==john=###~~john~#--john-##==john=###~~john~',
                'expected' => '#-john--##=john==###~~john~#-john--##=john==###~~john~#-john--##=john==###~~john~',
            ),

            Array(
                'id' => 128,
                'template' => 'foo: {{foo}} , parent foo: {{../foo}}',
                'data' => Array('foo' => 'OK'),
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                ),
                'expected' => 'foo: OK , parent foo: ',
            ),

            Array(
                'id' => 132,
                'template' => '{{list (keys .)}}',
                'data' => Array('foo' => 'bar', 'test' => 'ok'),
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                    'helpers' => Array(
                        'keys' => function($arg) {
                            return array_keys($arg);
                         },
                        'list' => function($arg) {
                            return join(',', $arg);
                         }
                    ),
                ),
                'expected' => 'foo,test',
            ),

            Array(
                'id' => 133,
                'template' => "{{list (keys\n .\n ) \n}}",
                'data' => Array('foo' => 'bar', 'test' => 'ok'),
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                    'helpers' => Array(
                        'keys' => function($arg) {
                            return array_keys($arg);
                         },
                        'list' => function($arg) {
                            return join(',', $arg);
                         }
                    ),
                ),
                'expected' => 'foo,test',
            ),

            Array(
                'id' => 133,
                'template' => "{{list\n .\n \n \n}}",
                'data' => Array('foo', 'bar', 'test'),
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                    'helpers' => Array(
                        'list' => function($arg) {
                            return join(',', $arg);
                         }
                    ),
                ),
                'expected' => 'foo,bar,test',
            ),

            Array(
                'id' => 134,
                'template' => "{{#if 1}}{{list (keys names)}}{{/if}}",
                'data' => Array('names' => Array('foo' => 'bar', 'test' => 'ok')),
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                    'helpers' => Array(
                        'keys' => function($arg) {
                            return array_keys($arg);
                         },
                        'list' => function($arg) {
                            return join(',', $arg);
                         }
                    ),
                ),
                'expected' => 'foo,test',
            ),

            Array(
                'id' => 138,
                'template' => "{{#each (keys .)}}={{.}}{{/each}}",
                'data' => Array('foo' => 'bar', 'test' => 'ok', 'Haha'),
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                    'helpers' => Array(
                        'keys' => function($arg) {
                            return array_keys($arg);
                         }
                    ),
                ),
                'expected' => '=foo=test=0',
            ),

            Array(
                'id' => 140,
                'template' => "{{[a.good.helper] .}}",
                'data' => Array('ha', 'hey', 'ho'),
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                    'helpers' => Array(
                        'a.good.helper' => function($arg) {
                            return join(',', $arg);
                         }
                    ),
                ),
                'expected' => 'ha,hey,ho',
            ),

            Array(
                'id' => 141,
                'template' => "{{#with foo}}{{#getThis bar}}{{/getThis}}{{/with}}",
                'data' => Array('foo' => Array('bar' => 'Good!')),
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                    'helpers' => Array(
                        'getThis' => function($input, $options) {
                            return $input . '-' . $options['_this']['bar'];
                         }
                    ),
                ),
                'expected' => 'Good!-Good!',
            ),

            Array(
                'id' => 141,
                'template' => "{{#with foo}}{{getThis bar}}{{/with}}",
                'data' => Array('foo' => Array('bar' => 'Good!')),
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                    'helpers' => Array(
                        'getThis' => function($input, $options) {
                            return $input . '-' . $options['_this']['bar'];
                         }
                    ),
                ),
                'expected' => 'Good!-Good!',
            ),

            Array(
                'id' => 143,
                'template' => "{{testString foo bar=\" \"}}",
                'data' => Array('foo' => 'good!'),
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                    'helpers' => Array(
                        'testString' => function($arg, $options) {
                            return $arg . '-' . $options['hash']['bar'];
                         }
                    ),
                ),
                'expected' => 'good!- ',
            ),

            Array(
                'id' => 143,
                'template' => "{{testString foo bar=\"\"}}",
                'data' => Array('foo' => 'good!'),
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                    'helpers' => Array(
                        'testString' => function($arg, $options) {
                            return $arg . '-' . $options['hash']['bar'];
                         }
                    ),
                ),
                'expected' => 'good!-',
            ),

            Array(
                'id' => 143,
                'template' => "{{testString foo bar=' '}}",
                'data' => Array('foo' => 'good!'),
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                    'helpers' => Array(
                        'testString' => function($arg, $options) {
                            return $arg . '-' . $options['hash']['bar'];
                         }
                    ),
                ),
                'expected' => 'good!- ',
            ),

            Array(
                'id' => 143,
                'template' => "{{testString foo bar=''}}",
                'data' => Array('foo' => 'good!'),
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                    'helpers' => Array(
                        'testString' => function($arg, $options) {
                            return $arg . '-' . $options['hash']['bar'];
                         }
                    ),
                ),
                'expected' => 'good!-',
            ),

            Array(
                'id' => 143,
                'template' => "{{testString foo bar=\" \"}}",
                'data' => Array('foo' => 'good!'),
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                    'helpers' => Array(
                        'testString' => function($arg1, $options) {
                            return $arg1 . '-' . $options['hash']['bar'];
                         }
                    ),
                ),
                'expected' => 'good!- ',
            ),

            Array(
                'id' => 147,
                'template' => '{{> test/test3 foo="bar"}}',
                'data' => Array('test' => 'OK!', 'foo' => 'error'),
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_RUNTIMEPARTIAL,
                    'partials' => Array('test/test3' => '{{test}}, {{foo}}'),
                ),
                'expected' => 'OK!, bar'
            ),

            Array(
                'id' => 147,
                'template' => '{{> test/test3 foo="bar"}}',
                'data' => new foo(),
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_RUNTIMEPARTIAL | LightnCandy::FLAG_INSTANCE,
                    'partials' => Array('test/test3' => '{{bar}}, {{foo}}'),
                ),
                'expected' => 'OK!, bar'
            ),

            Array(
                'id' => 150,
                'template' => '{{{.}}}',
                'data' => Array('hello' => 'world'),
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                    'runtime' => 'MyLCRunClass',
                ),
                'expected' => "[[DEBUG:raw()=>array (\n  'hello' => 'world',\n)]]",
            ),

            Array(
                'id' => 153,
                'template' => '{{echo "test[]"}}',
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                    'helpers' => Array(
                        'echo' => function ($in) {
                            return "-$in-";
                        }
                    )
                ),
                'expected' => "-test[]-",
            ),

            Array(
                'id' => 153,
                'template' => '{{echo \'test[]\'}}',
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                    'helpers' => Array(
                        'echo' => function ($in) {
                            return "-$in-";
                        }
                    )
                ),
                'expected' => "-test[]-",
            ),

            Array(
                'id' => 154,
                'template' => 'O{{! this is comment ! ... }}K!',
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                ),
                'expected' => "OK!"
            ),

            Array(
                'id' => 157,
                'template' => '{{{du_mp text=(du_mp "123")}}}',
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                    'helpers' => Array(
                        'du_mp' => function ($a) {
                            return '>' . print_r(isset($a['hash']) ? $a['hash'] : $a, true);
                        }
                    )
                ),
                'expected' => <<<VAREND
>Array
(
    [text] => >123
)

VAREND
            ),

            Array(
                'id' => 157,
                'template' => '{{>test_js_partial}}',
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_RUNTIMEPARTIAL,
                    'partials' => Array(
                        'test_js_partial' => <<<VAREND
Test GA....
<script>
(function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){console.log('works!')};})();
</script>
VAREND
                    )
                ),
                'expected' => <<<VAREND
Test GA....
<script>
(function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){console.log('works!')};})();
</script>
VAREND
            ),

            Array(
                'id' => 159,
                'template' => '{{#.}}true{{else}}false{{/.}}',
                'data' => new ArrayObject(),
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                ),
                'expected' => "false",
            ),

            Array(
                'id' => 169,
                'template' => '{{{{a}}}}true{{else}}false{{{{/a}}}}',
                'data' => Array('a' => true),
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                ),
                'expected' => "true{{else}}false",
            ),

            Array(
                'id' => 171,
                'template' => '{{#my_private_each .}}{{@index}}:{{.}},{{/my_private_each}}',
                'data' => Array('a', 'b', 'c'),
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_ERROR_EXCEPTION,
                    'helpers' => Array(
                        'my_private_each'
                    )
                ),
                'expected' => '0:a,1:b,2:c,',
            ),

            Array(
                'id' => 175,
                'template' => 'a{{!-- {{each}} haha {{/each}} --}}b',
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                ),
                'expected' => 'ab',
            ),

            Array(
                'id' => 175,
                'template' => 'c{{>test}}d',
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                    'partials' => Array(
                        'test' => 'a{{!-- {{each}} haha {{/each}} --}}b',
                    ),
                ),
                'expected' => 'cabd',
            ),

            Array(
                'id' => 177,
                'template' => '{{{{a}}}} {{{{b}}}} {{{{/b}}}} {{{{/a}}}}',
                'data' => Array('a' => true),
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                ),
                'expected' => ' {{{{b}}}} {{{{/b}}}} ',
            ),

            Array(
                'id' => 177,
                'template' => '{{{{a}}}} {{{{b}}}} {{{{/b}}}} {{{{/a}}}}',
                'data' => Array('a' => true),
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                    'helpers' => Array(
                        'a' => function($options) {
                            return $options['fn']();
                        }
                    )
                ),
                'expected' => ' {{{{b}}}} {{{{/b}}}} ',
            ),

            Array(
                'id' => 177,
                'template' => '{{{{a}}}} {{{{b}}}} {{{{/b}}}} {{{{/a}}}}',
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS
                ),
                'expected' => ''
            ),

            Array(
                'id' => 191,
                'template' => '<% foo %> is good <%> bar %>',
                'data' => Array('foo' => 'world'),
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                    'delimiters' => array('<%', '%>'),
                    'partials' => array(
                        'bar' => '<% @root.foo %>{{:D}}!',
                    )
                ),
                'expected' => 'world is good world{{:D}}!',
            ),

            Array(
                'id' => 199,
                'template' => '{{#if foo}}1{{else if bar}}2{{else}}3{{/if}}',
                'options' => Array(
                    'flags' => LightnCandy::FLAG_ELSE,
                ),
                'expected' => '3',
            ),

            Array(
                'id' => 199,
                'template' => '{{#if foo}}1{{else if bar}}2{{/if}}',
                'options' => Array(
                    'flags' => LightnCandy::FLAG_ELSE,
                ),
                'data' => Array('bar' => true),
                'expected' => '2',
            ),

            Array(
                'id' => 201,
                'template' => '{{foo "world"}}',
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARS,
                    'helperresolver' => function ($cx, $name) {
                        return function ($name, $option) {
                            return "Hello, $name";
                        };
                    }
                ),
                'expected' => 'Hello, world',
            ),

            Array(
                'id' => 201,
                'template' => '{{#foo "test"}}World{{/foo}}',
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARS,
                    'helperresolver' => function ($cx, $name) {
                        return function ($name, $option) {
                            return "$name = " . $option['fn']();
                        };
                    }
                ),
                'expected' => 'test = World',
            ),

            Array(
                'id' => 204,
                'template' => '{{#> test name="A"}}B{{/test}}{{#> test name="C"}}D{{/test}}',
                'data' => Array('bar' => true),
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARS | LightnCandy::FLAG_RUNTIMEPARTIAL,
                    'partials' => array(
                        'test' => '{{name}}:{{> @partial-block}},',
                    )
                ),
                'expected' => 'A:B,C:D,',
            ),

            Array(
                'id' => 206,
                'template' => '{{#with bar}}{{#../foo}}YES!{{/../foo}}{{/with}}',
                'data' => Array('foo' => 999, 'bar' => true),
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARS,
                ),
                'expected' => 'YES!',
            ),

            Array(
                'id' => 213,
                'template' => '{{#if foo}}foo{{else if bar}}{{#moo moo}}moo{{/moo}}{{/if}}',
                'data' => Array('foo' => true),
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARS,
                    'helpers' => Array(
                        'moo' => function($arg1) {
                            return ($arg1 === null);
                         }
                    )
                ),
                'expected' => 'foo',
            ),

            Array(
                'id' => 213,
                'template' => '{{#with .}}bad{{else}}Good!{{/with}}',
                'data' => Array(),
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARS,
                ),
                'expected' => 'Good!',
            ),

            Array(
                'id' => 216,
                'template' => '{{foo.length}}',
                'data' => Array('foo' => Array()),
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARS,
                ),
                'expected' => '',
            ),

            Array(
                'id' => 216,
                'template' => '{{foo.length}}',
                'data' => Array('foo' => Array()),
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                ),
                'expected' => '0',
            ),

            Array(
                'id' => 221,
                'template' => 'a{{ouch}}b',
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                    'helpers' => $test_helpers
                ),
                'expected' => 'aokb',
            ),

            Array(
                'id' => 221,
                'template' => 'a{{ouch}}b',
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                    'helpers' => $test_helpers2
                ),
                'expected' => 'awa!b',
            ),

            Array(
                'id' => 221,
                'template' => 'a{{ouch}}b',
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                    'helpers' => $test_helpers3
                ),
                'expected' => 'awa!b',
            ),

            Array(
                'id' => 224,
                'template' => '{{#> foo bar}}a,b,{{.}},{{!-- comment --}},d{{/foo}}',
                'data' => Array('bar' => 'BA!'),
                'options' => Array(
                    'flags' => LightnCandy::FLAG_RUNTIMEPARTIAL | LightnCandy::FLAG_THIS | LightnCandy::FLAG_SPVARS | LightnCandy::FLAG_ERROR_LOG | LightnCandy::FLAG_ERROR_EXCEPTION | LightnCandy::FLAG_ERROR_SKIPPARTIAL | LightnCandy::FLAG_PARENT,
                    'partials' => Array('foo' => 'hello, {{> @partial-block}}')
                ),
                'expected' => 'hello, a,b,BA!,,d',
            ),

            Array(
                'id' => 224,
                'template' => '{{#> foo bar}}{{#if .}}OK! {{.}}{{else}}no bar{{/if}}{{/foo}}',
                'data' => Array('bar' => 'BA!'),
                'options' => Array(
                    'flags' => LightnCandy::FLAG_RUNTIMEPARTIAL | LightnCandy::FLAG_THIS | LightnCandy::FLAG_SPVARS | LightnCandy::FLAG_ERROR_LOG | LightnCandy::FLAG_ERROR_EXCEPTION | LightnCandy::FLAG_ERROR_SKIPPARTIAL | LightnCandy::FLAG_PARENT,
                    'partials' => Array('foo' => 'hello, {{> @partial-block}}')
                ),
                'expected' => 'hello, OK! BA!no bar',
            ),

            Array(
                'id' => 224,
                'template' => '{{#> foo bar}}{{#if .}}OK! {{.}}{{else}}no bar{{/if}}{{/foo}}',
                'data' => Array('bar' => 'BA!'),
                'options' => Array(
                    'flags' => LightnCandy::FLAG_RUNTIMEPARTIAL | LightnCandy::FLAG_THIS | LightnCandy::FLAG_SPVARS | LightnCandy::FLAG_ERROR_LOG | LightnCandy::FLAG_ERROR_EXCEPTION | LightnCandy::FLAG_ERROR_SKIPPARTIAL | LightnCandy::FLAG_PARENT | LightnCandy::FLAG_ELSE,
                    'partials' => Array('foo' => 'hello, {{> @partial-block}}')
                ),
                'expected' => 'hello, OK! BA!',
            ),

            Array(
                'id' => 227,
                'template' => '{{#if moo}}A{{else if bar}}B{{else foo}}C{{/if}}',
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARS | LightnCandy::FLAG_ERROR_EXCEPTION,
                    'helpers' => Array(
                        'foo' => function($options) {
                            return $options['fn']();
                         }
                    )
                ),
                'expected' => 'C'
            ),

            Array(
                'id' => 227,
                'template' => '{{#if moo}}A{{else if bar}}B{{else with foo}}C{{.}}{{/if}}',
                'data' => array('foo' => 'D'),
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARS | LightnCandy::FLAG_ERROR_EXCEPTION
                ),
                'expected' => 'CD'
            ),

            Array(
                'id' => 227,
                'template' => '{{#if moo}}A{{else if bar}}B{{else each foo}}C{{.}}{{/if}}',
                'data' => array('foo' => array(1, 3, 5)),
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARS | LightnCandy::FLAG_ERROR_EXCEPTION
                ),
                'expected' => 'C1C3C5'
            ),

            Array(
                'id' => 229,
                'template' => '{{#if foo.bar.moo}}TRUE{{else}}FALSE{{/if}}',
                'data' => array(),
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARS | LightnCandy::FLAG_ERROR_EXCEPTION
                ),
                'expected' => 'FALSE'
            ),

            Array(
                'id' => 233,
                'template' => '{{#if foo}}FOO{{else}}BAR{{/if}}',
                'data' => array(),
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARS,
                    'helpers' => Array(
                        'if' => function($arg, $options) {
                            return $options['fn']();
                         }
                    )
                ),
                'expected' => 'FOO'
            ),

            Array(
                'id' => 234,
                'template' => '{{> (lookup foo 2)}}',
                'data' => array('foo' => array('a', 'b', 'c')),
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARS | LightnCandy::FLAG_RUNTIMEPARTIAL,
                    'partials' => Array(
                        'a' => '1st',
                        'b' => '2nd',
                        'c' => '3rd'
                    )
                ),
                'expected' => '3rd'
            ),

            Array(
                'id' => 235,
                'template' => '{{#> "myPartial"}}{{#> myOtherPartial}}{{ @root.foo}}{{/myOtherPartial}}{{/"myPartial"}}',
                'data' => Array('foo' => 'hello!'),
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARS | LightnCandy::FLAG_RUNTIMEPARTIAL,
                    'partials' => Array(
                        'myPartial' => '<div>outer {{> @partial-block}}</div>',
                        'myOtherPartial' => '<div>inner {{> @partial-block}}</div>'
                    )
                ),
                'expected' => '<div>outer <div>inner hello!</div></div>',
            ),

            Array(
                'id' => 236,
                'template' => 'A{{#> foo}}B{{#> bar}}C{{>moo}}D{{/bar}}E{{/foo}}F',
                'options' => Array(
                    'flags' => LightnCandy::FLAG_RUNTIMEPARTIAL | LightnCandy::FLAG_HANDLEBARS,
                    'partials' => Array(
                        'foo' => 'FOO>{{> @partial-block}}<FOO',
                        'bar' => 'bar>{{> @partial-block}}<bar',
                        'moo' => 'MOO!',
                    )
                ),
                'expected' => 'AFOO>Bbar>CMOO!D<barE<FOOF'
            ),

            Array(
                'id' => 241,
                'template' => '{{#>foo}}{{#*inline "bar"}}GOOD!{{#each .}}>{{.}}{{/each}}{{/inline}}{{/foo}}',
                'data' => Array('1', '3', '5'),
                'options' => Array(
                    'flags' => LightnCandy::FLAG_RUNTIMEPARTIAL,
                    'partials' => Array(
                        'foo' => 'A{{#>bar}}BAD{{/bar}}B',
                        'moo' => 'oh'
                    )
                ),
                'expected' => 'AGOOD!>1>3>5B'
            ),

            Array(
                'id' => 243,
                'template' => '{{lookup . 3}}',
                'data' => Array('3' => 'OK'),
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARS,
                ),
                'expected' => 'OK'
            ),

            Array(
                'id' => 243,
                'template' => '{{lookup . "test"}}',
                'data' => Array('test' => 'OK'),
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARS,
                ),
                'expected' => 'OK'
            ),

            Array(
                'id' => 244,
                'template' => '{{#>outer}}content{{/outer}}',
                'data' => Array('test' => 'OK'),
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARS | LightnCandy::FLAG_RUNTIMEPARTIAL,
                    'partials' => Array(
                        'outer' => 'outer+{{#>nested}}~{{>@partial-block}}~{{/nested}}+outer-end',
                        'nested' => 'nested={{>@partial-block}}=nested-end'
                    )
                ),
                'expected' => 'outer+nested=~content~=nested-end+outer-end'
            ),

            Array(
                'id' => 245,
                'template' => '{{#each foo}}{{#with .}}{{bar}}-{{../../name}}{{/with}}{{/each}}',
                'data' => Array('name' => 'bad', 'foo' => Array(
                    Array('bar' => 1),
                    Array('bar' => 2),
                )),
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARS,
                ),
                'expected' => '1-2-'
            ),

            Array(
                'id' => 251,
                'template' => '{{>foo}}',
                'data' => Array('bar' => 'BAD'),
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARS | LightnCandy::FLAG_RUNTIMEPARTIAL | LightnCandy::FLAG_EXTHELPER,
                    'partials' => Array('foo' => '{{bar}}'),
                    'helperresolver' => function ($cx, $name) {
                        return function () {
                            return "OK!";
                        };
                    }
                ),
                'expected' => 'OK!'
            ),

            Array(
                'id' => 252,
                'template' => '{{foo (lookup bar 1)}}',
                'data' => Array('bar' => Array(
                    'nil',
                    Array(3, 5)
                )),
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARS,
                    'helpers' => Array(
                        'foo' => function($arg1) {
                            return is_array($arg1) ? 'OK' : 'bad';
                         }
                    )
                ),
                'expected' => 'OK'
            ),

            Array(
                'id' => 253,
                'template' => '{{foo.bar}}',
                'data' => Array('foo' => Array('bar' => 'OK!')),
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARS,
                    'helpers' => Array(
                        'foo' => function() {
                            return 'bad';
                         }
                    )
                ),
                'expected' => 'OK!'
            ),

            Array(
                'id' => 254,
                'template' => '{{#if a}}a{{else if b}}b{{else}}c{{/if}}{{#if a}}a{{else if b}}b{{/if}}',
                'data' => Array('b' => 1),
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARS
                ),
                'expected' => 'bb'
            ),

            Array(
                'id' => 255,
                'template' => '{{foo.length}}',
                'data' => Array('foo' => Array(1, 2)),
                'options' => Array(
                    'flags' => LightnCandy::FLAG_JSLENGTH | LightnCandy::FLAG_METHOD
                ),
                'expected' => '2'
            ),

            Array(
                'id' => 256,
                'template' => '{{lookup . "foo"}}',
                'data' => Array('foo' => 'ok'),
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARSLAMBDA
                ),
                'expected' => 'ok'
            ),

            Array(
                'id' => 257,
                'template' => '{{foo a=(foo a=(foo a="ok"))}}',
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARS,
                    'helpers' => Array(
                        'foo' => function($opt) {
                            return $opt['hash']['a'];
                        }
                    )
                ),
                'expected' => 'ok'
            ),

            Array(
                'id' => 261,
                'template' => '{{#each foo as |bar|}}?{{bar.0}}{{/each}}',
                'data' => Array('foo' => Array(array('a'), array('b'))),
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARS
                ),
                'expected' => '?a?b'
            ),

            Array(
                'id' => 267,
                'template' => '{{#each . as |v k|}}#{{k}}>{{v}}|{{.}}{{/each}}',
                'data' => Array('a' => 'b', 'c' => 'd'),
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARS | LightnCandy::FLAG_PROPERTY,
                ),
                'expected' => '#a>b|b#c>d|d'
            ),

            Array(
                'id' => 268,
                'template' => '{{foo}}{{bar}}',
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARS,
                    'helpers' => Array(
                        'foo' => function($opt) {
                            $opt['_this']['change'] = true;
                        },
                        'bar' => function($opt) {
                            return $opt['_this']['change'] ? 'ok' : 'bad';
                        }
                    )
                ),
                'expected' => 'ok'
            ),

            Array(
                'id' => 278,
                'template' => '{{#foo}}-{{#bar}}={{moo}}{{/bar}}{{/foo}}',
                'data' => Array(
                    'foo' => Array(
                         Array('bar' => 0, 'moo' => 'A'),
                         Array('bar' => 1, 'moo' => 'B'),
                         Array('bar' => false, 'moo' => 'C'),
                         Array('bar' => true, 'moo' => 'D'),
                    )
                ),
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARS,
                ),
                'expected' => '-=-=--=D'
            ),

            Array(
                'id' => 278,
                'template' => '{{#foo}}-{{#bar}}={{moo}}{{/bar}}{{/foo}}',
                'data' => Array(
                    'foo' => Array(
                         Array('bar' => 0, 'moo' => 'A'),
                         Array('bar' => 1, 'moo' => 'B'),
                         Array('bar' => false, 'moo' => 'C'),
                         Array('bar' => true, 'moo' => 'D'),
                    )
                ),
                'options' => Array(
                    'flags' => LightnCandy::FLAG_MUSTACHE,
                ),
                'expected' => '--=B--=D'
            ),

            Array(
                'id' => 281,
                'template' => '{{echo (echo "foo bar (moo).")}}',
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARS,
                    'helpers' => Array(
                        'echo' => function($arg1) {
                            return "ECHO: $arg1";
                        }
                    )
                ),
                'expected' => 'ECHO: ECHO: foo bar (moo).'
            ),

            Array(
                'id' => 284,
                'template' => '{{> foo}}',
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_RUNTIMEPARTIAL,
                    'partials' => Array('foo' => "12'34")
                ),
                'expected' => "12'34"
            ),

            Array(
                'id' => 284,
                'template' => '{{> (lookup foo 2)}}',
                'data' => array('foo' => array('a', 'b', 'c')),
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARS | LightnCandy::FLAG_RUNTIMEPARTIAL,
                    'partials' => Array(
                        'a' => '1st',
                        'b' => '2nd',
                        'c' => "3'r'd"
                    )
                ),
                'expected' => "3'r'd"
            ),

            Array(
                'id' => 289,
                'template' => "1\n2\n{{~foo~}}\n3",
                'data' => array('foo' => 'OK'),
                'expected' => "1\n2OK3"
            ),

            Array(
                'id' => 289,
                'template' => "1\n2\n{{#test}}\n3TEST\n{{/test}}\n4",
                'data' => array('test' => 1),
                'options' => Array(
                    'flags' => LightnCandy::FLAG_RUNTIMEPARTIAL
                ),
                'expected' => "1\n2\n3TEST\n4"
            ),

            Array(
                'id' => 289,
                'template' => "1\n2\n{{~#test}}\n3TEST\n{{/test}}\n4",
                'data' => array('test' => 1),
                'options' => Array(
                    'flags' => LightnCandy::FLAG_RUNTIMEPARTIAL
                ),
                'expected' => "1\n23TEST\n4"
            ),

            Array(
                'id' => 289,
                'template' => "1\n2\n{{#>test}}\n3TEST\n{{/test}}\n4",
                'options' => Array(
                    'flags' => LightnCandy::FLAG_RUNTIMEPARTIAL
                ),
                'expected' => "1\n2\n3TEST\n4"
            ),

            Array(
                'id' => 289,
                'template' => "1\n2\n\n{{#>test}}\n3TEST\n{{/test}}\n4",
                'options' => Array(
                    'flags' => LightnCandy::FLAG_RUNTIMEPARTIAL
                ),
                'expected' => "1\n2\n\n3TEST\n4"
            ),

            Array(
                'id' => 289,
                'template' => "1\n2\n\n{{#>test~}}\n\n3TEST\n{{/test}}\n4",
                'options' => Array(
                    'flags' => LightnCandy::FLAG_RUNTIMEPARTIAL
                ),
                'expected' => "1\n2\n\n3TEST\n4"
            ),

            Array(
                'id' => 290,
                'template' => '{{foo}} }} OK',
                'data' => Array(
                  'foo' => 'YES',
                ),
                'expected' => 'YES }} OK'
            ),

            Array(
                'id' => 290,
                'template' => '{{foo}}{{#with "}"}}{{.}}{{/with}}OK',
                'data' => Array(
                  'foo' => 'YES',
                ),
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                ),
                'expected' => 'YES}OK'
            ),

            Array(
                'id' => 290,
                'template' => '{ {{foo}}',
                'data' => Array(
                  'foo' => 'YES',
                ),
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                ),
                'expected' => '{ YES'
            ),

            Array(
                'id' => 290,
                'template' => '{{#with "{{"}}{{.}}{{/with}}{{foo}}{{#with "{{"}}{{.}}{{/with}}',
                'data' => Array(
                  'foo' => 'YES',
                ),
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                ),
                'expected' => '{{YES{{'
            ),

            Array(
                'id' => 291,
                'template' => 'a{{> @partial-block}}b',
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                ),
                'expected' => 'ab'
            ),

            Array(
                'id' => 302,
                'template' => "{{#*inline \"t1\"}}{{#if imageUrl}}<span />{{else}}<div />{{/if}}{{/inline}}{{#*inline \"t2\"}}{{#if imageUrl}}<span />{{else}}<div />{{/if}}{{/inline}}{{#*inline \"t3\"}}{{#if imageUrl}}<span />{{else}}<div />{{/if}}{{/inline}}",
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARS | LightnCandy::FLAG_RUNTIMEPARTIAL,
                ),
                'expected' => '',
            ),

            Array(
                'id' => 303,
                'template' => '{{#*inline "t1"}} {{#if url}} <a /> {{else if imageUrl}} <img /> {{else}} <span /> {{/if}} {{/inline}}',
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARS | LightnCandy::FLAG_RUNTIMEPARTIAL,
                ),
                'expected' => ''
            ),

            Array(
                'template' => '{{#each . as |v k|}}#{{k}}{{/each}}',
                'data' => Array('a' => Array(), 'c' => Array()),
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARS
                ),
                'expected' => '#a#c'
            ),

            Array(
                'template' => '{{testNull null undefined 1}}',
                'data' => 'test',
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                    'helpers' => Array(
                        'testNull' => function($arg1, $arg2) {
                            return (($arg1 === null) && ($arg2 === null)) ? 'YES!' : 'no';
                        }
                    )
                ),
                'expected' => 'YES!'
            ),

            Array(
                'template' => '{{> (pname foo) bar}}',
                'data' => Array('bar' => 'OK! SUBEXP+PARTIAL!', 'foo' => 'test/test3'),
                'options' => Array(
                    'helpers' => Array(
                        'pname' => function($arg) {
                            return $arg;
                         }
                    ),
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_RUNTIMEPARTIAL,
                    'partials' => Array('test/test3' => '{{.}}'),
                ),
                'expected' => 'OK! SUBEXP+PARTIAL!'
            ),

            Array(
                'template' => '{{> testpartial newcontext mixed=foo}}',
                'data' => Array('foo' => 'OK!', 'newcontext' => Array('bar' => 'test')),
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_RUNTIMEPARTIAL,
                    'partials' => Array('testpartial' => '{{bar}}-{{mixed}}'),
                ),
                'expected' => 'test-OK!'
            ),

            Array(
                'template' => '{{[helper]}}',
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                    'helpers' => Array(
                        'helper' => function () {
                            return 'DEF';
                        }
                    )
                ),
                'data' => Array(),
                'expected' => 'DEF'
            ),

            Array(
                'template' => '{{#[helper3]}}ABC{{/[helper3]}}',
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                    'helpers' => Array(
                        'helper3' => function () {
                            return 'DEF';
                        }
                    )
                ),
                'data' => Array(),
                'expected' => 'DEF'
            ),

            Array(
                'template' => '{{hash abc=["def=123"]}}',
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_BESTPERFORMANCE,
                    'helpers' => Array(
                        'hash' => function ($options) {
                            $ret = '';
                            foreach ($options['hash'] as $k => $v) {
                                $ret .= "$k : $v,";
                            }
                            return $ret;
                        }
                    ),
                ),
                'data' => Array('"def=123"' => 'La!'),
                'expected' => 'abc : La!,',
            ),

            Array(
                'template' => '{{hash abc=[\'def=123\']}}',
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_BESTPERFORMANCE,
                    'helpers' => Array(
                        'hash' => function ($options) {
                            $ret = '';
                            foreach ($options['hash'] as $k => $v) {
                                $ret .= "$k : $v,";
                            }
                            return $ret;
                        }
                    ),
                ),
                'data' => Array("'def=123'" => 'La!'),
                'expected' => 'abc : La!,',
            ),

            Array(
                'template' => 'ABC{{#block "YES!"}}DEF{{foo}}GHI{{else}}NO~{{/block}}JKL',
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_BESTPERFORMANCE,
                    'helpers' => Array(
                        'block' => function ($name, $options) {
                            return "1-$name-2-" . $options['fn']() . '-3';
                        }
                    ),
                ),
                'data' => Array('foo' => 'bar'),
                'expected' => 'ABC1-YES!-2-DEFbarGHI-3JKL',
            ),

            Array(
                'template' => '-{{getroot}}=',
                'options' => Array(
                    'flags' => LightnCandy::FLAG_SPVARS,
                    'helpers' => Array('getroot'),
                ),
                'data' => 'ROOT!',
                'expected' => '-ROOT!=',
            ),

            Array(
                'template' => 'A{{#each .}}-{{#each .}}={{.}},{{@key}},{{@index}},{{@../index}}~{{/each}}%{{/each}}B',
                'data' => Array(Array('a' => 'b'), Array('c' => 'd'), Array('e' => 'f')),
                'options' => Array(
                    'flags' => LightnCandy::FLAG_PARENT | LightnCandy::FLAG_THIS | LightnCandy::FLAG_SPVARS,
                ),
                'expected' => 'A-=b,a,0,0~%-=d,c,0,1~%-=f,e,0,2~%B',
            ),

            Array(
                'template' => 'ABC{{#block "YES!"}}TRUE{{else}}DEF{{foo}}GHI{{/block}}JKL',
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_BESTPERFORMANCE,
                    'helpers' => Array(
                        'block' => function ($name, $options) {
                            return "1-$name-2-" . $options['inverse']() . '-3';
                        }
                    ),
                ),
                'data' => Array('foo' => 'bar'),
                'expected' => 'ABC1-YES!-2-DEFbarGHI-3JKL',
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
                    'partials' => Array(
                        'tests/test3' => 'New context:{{.}}'
                    ),
                ),
                'expected' => "->New context:a->New context:b->New context:c",
            ),

            Array(
                'template' => '{{#each .}}->{{>tests/test3 ../foo}}{{/each}}',
                'data' => Array('a', 'foo' => Array('d', 'e', 'f')),
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_RUNTIMEPARTIAL,
                    'partials' => Array(
                        'tests/test3' => 'New context:{{.}}'
                    ),
                ),
                'expected' => "->New context:d,e,f->New context:d,e,f",
            ),

            Array(
                'template' => '{{{"{{"}}}',
                'data' => Array('{{' => ':D'),
                'expected' => ':D',
            ),

            Array(
                'template' => '{{{\'{{\'}}}',
                'data' => Array('{{' => ':D'),
                'expected' => ':D',
            ),

            Array(
                'template' => '{{#with "{{"}}{{.}}{{/with}}',
                'expected' => '{{',
            ),

            Array(
                'template' => '{{good_helper}}',
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
                'debug' => Runtime::DEBUG_TAGS_ANSI,
                'data' => Array('foo' => 'OK'),
                'expected' => pack('H*', '1b5b303b33326d7b7b5b666f6f5d7d7d1b5b306d'),
            ),

            Array(
                'template' => '{{foo}}',
                'options' => Array('flags' => LightnCandy::FLAG_RENDER_DEBUG),
                'debug' => Runtime::DEBUG_TAGS_HTML,
                'expected' => '<!--MISSED((-->{{[foo]}}<!--))-->',
            ),

            Array(
                'template' => '{{#foo}}OK{{/foo}}',
                'options' => Array('flags' => LightnCandy::FLAG_RENDER_DEBUG),
                'debug' => Runtime::DEBUG_TAGS_HTML,
                'expected' => '<!--MISSED((-->{{#[foo]}}<!--))--><!--SKIPPED--><!--MISSED((-->{{/[foo]}}<!--))-->',
            ),

            Array(
                'template' => '{{#foo}}OK{{/foo}}',
                'options' => Array('flags' => LightnCandy::FLAG_RENDER_DEBUG),
                'debug' => Runtime::DEBUG_TAGS_ANSI,
                'expected' => pack('H*', '1b5b303b33316d7b7b235b666f6f5d7d7d1b5b306d1b5b303b33336d534b49505045441b5b306d1b5b303b33316d7b7b2f5b666f6f5d7d7d1b5b306d'),
            ),

            Array(
                'template' => '{{#myif foo}}YES{{else}}NO{{/myif}}',
                'options' => Array(
                    'flags' => LightnCandy::FLAG_ELSE,
                    'helpers' => Array('myif'),
                ),
                'expected' => 'NO',
            ),

            Array(
                'template' => '{{#myif foo}}YES{{else}}NO{{/myif}}',
                'data' => Array('foo' => 1),
                'options' => Array(
                    'flags' => LightnCandy::FLAG_ELSE,
                    'helpers' => Array('myif'),
                ),
                'expected' => 'YES',
            ),

            Array(
                'template' => '{{#mylogic 0 foo bar}}YES:{{.}}{{else}}NO:{{.}}{{/mylogic}}',
                'data' => Array('foo' => 'FOO', 'bar' => 'BAR'),
                'options' => Array(
                    'flags' => LightnCandy::FLAG_ELSE,
                    'helpers' => Array('mylogic'),
                ),
                'expected' => 'NO:BAR',
            ),

            Array(
                'template' => '{{#mylogic 0 foo bar}}YES:{{.}}{{else}}NO:{{.}}{{/mylogic}}',
                'data' => Array('foo' => 'FOO', 'bar' => 'BAR'),
                'options' => Array(
                    'helpers' => Array('mylogic'),
                ),
                'expected' => '',
            ),

            Array(
                'template' => '{{#mylogic true foo bar}}YES:{{.}}{{else}}NO:{{.}}{{/mylogic}}',
                'data' => Array('foo' => 'FOO', 'bar' => 'BAR'),
                'options' => Array(
                    'flags' => LightnCandy::FLAG_ELSE,
                    'helpers' => Array('mylogic'),
                ),
                'expected' => 'YES:FOO',
            ),

            Array(
                'template' => '{{#mywith foo}}YA: {{name}}{{/mywith}}',
                'data' => Array('name' => 'OK?', 'foo' => Array('name' => 'OK!')),
                'options' => Array(
                    'helpers' => Array('mywith'),
                ),
                'expected' => 'YA: OK!',
            ),

            Array(
                'template' => '{{mydash \'abc\' "dev"}}',
                'data' => Array('a' => 'a', 'b' => 'b', 'c' => Array('c' => 'c'), 'd' => 'd', 'e' => 'e'),
                'options' => Array(
                    'helpers' => Array('mydash'),
                ),
                'expected' => 'abc-dev',
            ),

            Array(
                'template' => '{{mydash \'a b c\' "d e f"}}',
                'data' => Array('a' => 'a', 'b' => 'b', 'c' => Array('c' => 'c'), 'd' => 'd', 'e' => 'e'),
                'options' => Array(
                    'flags' => LightnCandy::FLAG_ADVARNAME,
                    'helpers' => Array('mydash'),
                ),
                'expected' => 'a b c-d e f',
            ),

            Array(
                'template' => '{{mydash "abc" (test_array 1)}}',
                'data' => Array('a' => 'a', 'b' => 'b', 'c' => Array('c' => 'c'), 'd' => 'd', 'e' => 'e'),
                'options' => Array(
                    'flags' => LightnCandy::FLAG_ADVARNAME,
                    'helpers' => Array('mydash', 'test_array'),
                ),
                'expected' => 'abc-NOT_ARRAY',
            ),

            Array(
                'template' => '{{mydash "abc" (myjoin a b)}}',
                'data' => Array('a' => 'a', 'b' => 'b', 'c' => Array('c' => 'c'), 'd' => 'd', 'e' => 'e'),
                'options' => Array(
                    'flags' => LightnCandy::FLAG_ADVARNAME,
                    'helpers' => Array('mydash', 'myjoin'),
                ),
                'expected' => 'abc-ab',
            ),

            Array(
                'template' => '{{#with people}}Yes , {{name}}{{else}}No, {{name}}{{/with}}',
                'data' => Array('people' => Array('name' => 'Peter'), 'name' => 'NoOne'),
                'options' => Array(
                    'flags' => LightnCandy::FLAG_ELSE,
                ),
                'expected' => 'Yes , Peter',
            ),

            Array(
                'template' => '{{#with people}}Yes , {{name}}{{else}}No, {{name}}{{/with}}',
                'data' => Array('name' => 'NoOne'),
                'options' => Array(
                    'flags' => LightnCandy::FLAG_ELSE,
                ),
                'expected' => 'No, NoOne',
            ),

            Array(
                'template' => <<<VAREND
<ul>
 <li>1. {{helper1 name}}</li>
 <li>2. {{helper1 value}}</li>
 <li>3. {{myClass::helper2 name}}</li>
 <li>4. {{myClass::helper2 value}}</li>
 <li>5. {{he name}}</li>
 <li>6. {{he value}}</li>
 <li>7. {{h2 name}}</li>
 <li>8. {{h2 value}}</li>
 <li>9. {{link name}}</li>
 <li>10. {{link value}}</li>
 <li>11. {{alink url text}}</li>
 <li>12. {{{alink url text}}}</li>
</ul>
VAREND
                ,
                'data' => Array('name' => 'John', 'value' => 10000, 'url' => 'http://yahoo.com', 'text' => 'You&Me!'),
                'options' => Array(
                    'flags' => LightnCandy::FLAG_ERROR_LOG | LightnCandy::FLAG_HANDLEBARSJS,
                    'helpers' => Array(
                        'helper1',
                        'myClass::helper2',
                        'he' => 'helper1',
                        'h2' => 'myClass::helper2',
                        'link' => function ($arg) {
                            if (is_array($arg)) {
                                $arg = 'Array';
                            }
                            return "<a href=\"{$arg}\">click here</a>";
                        },
                        'alink',
                    )
                ),
                'expected' => <<<VAREND
<ul>
 <li>1. -John-</li>
 <li>2. -10000-</li>
 <li>3. &#x3D;John&#x3D;</li>
 <li>4. &#x3D;10000&#x3D;</li>
 <li>5. -John-</li>
 <li>6. -10000-</li>
 <li>7. &#x3D;John&#x3D;</li>
 <li>8. &#x3D;10000&#x3D;</li>
 <li>9. &lt;a href&#x3D;&quot;John&quot;&gt;click here&lt;/a&gt;</li>
 <li>10. &lt;a href&#x3D;&quot;10000&quot;&gt;click here&lt;/a&gt;</li>
 <li>11. &lt;a href&#x3D;&quot;http://yahoo.com&quot;&gt;You&amp;Me!&lt;/a&gt;</li>
 <li>12. <a href="http://yahoo.com">You&Me!</a></li>
</ul>
VAREND
            ),

            Array(
                'template' => '{{test.test}} == {{test.test3}}',
                'data' => Array('test' => new myClass()),
                'options' => Array('flags' => LightnCandy::FLAG_INSTANCE),
                'expected' => "testMethod OK! == -- test3:Array\n(\n)\n",
            ),

            Array(
                'template' => '{{test.test}} == {{test.bar}}',
                'data' => Array('test' => new foo()),
                'options' => Array('flags' => LightnCandy::FLAG_INSTANCE),
                'expected' => ' == OK!',
            ),

            Array(
                'template' => '{{#each foo}}{{@key}}: {{.}},{{/each}}',
                'data' => Array('foo' => Array(1,'a'=>'b',5)),
                'expected' => ': 1,: b,: 5,',
            ),

            Array(
                'template' => '{{#each foo}}{{@key}}: {{.}},{{/each}}',
                'data' => Array('foo' => Array(1,'a'=>'b',5)),
                'options' => Array('flags' => LightnCandy::FLAG_SPVARS),
                'expected' => '0: 1,a: b,1: 5,',
            ),

            Array(
                'template' => '{{#each foo}}{{@key}}: {{.}},{{/each}}',
                'data' => Array('foo' => new twoDimensionIterator(2, 3)),
                'options' => Array('flags' => LightnCandy::FLAG_SPVARS),
                'expected' => '0x0: 0,1x0: 0,0x1: 0,1x1: 1,0x2: 0,1x2: 2,',
            ),

            Array(
                'template' => "   {{#foo}}\n {{name}}\n{{/foo}}\n  ",
                'data' => Array('foo' => Array(Array('name' => 'A'),Array('name' => 'd'),Array('name' => 'E'))),
                'options' => Array('flags' => LightnCandy::FLAG_MUSTACHE),
                'expected' => " A\n d\n E\n  ",
            ),

            Array(
                'template' => "{{bar}}\n   {{#foo}}\n {{name}}\n{{/foo}}\n  ",
                'data' => Array('bar' => 'OK', 'foo' => Array(Array('name' => 'A'),Array('name' => 'd'),Array('name' => 'E'))),
                'options' => Array('flags' => LightnCandy::FLAG_MUSTACHE),
                'expected' => "OK\n A\n d\n E\n  ",
            ),

            Array(
                'template' => "   {{#if foo}}\nYES\n{{else}}\nNO\n{{/if}}\n",
                'options' => Array('flags' => LightnCandy::FLAG_HANDLEBARS),
                'expected' => "NO\n",
            ),

            Array(
                'template' => "  {{#each foo}}\n{{@key}}: {{.}}\n{{/each}}\nDONE",
                'data' => Array('foo' => Array('a' => 'A', 'b' => 'BOY!')),
                'options' => Array('flags' => LightnCandy::FLAG_HANDLEBARS),
                'expected' => "a: A\nb: BOY!\nDONE",
            ),

            Array(
                'template' => "{{>test1}}\n  {{>test1}}\nDONE\n",
                'options' => Array(
                    'flags' => LightnCandy::FLAG_MUSTACHE,
                    'partials' => Array('test1' => "1:A\n 2:B\n  3:C\n 4:D\n5:E\n"),
                ),
                'expected' => "1:A\n 2:B\n  3:C\n 4:D\n5:E\n  1:A\n   2:B\n    3:C\n   4:D\n  5:E\nDONE\n",
            ),

            Array(
                'template' => "{{>test1}}\n  {{>test1}}\nDONE\n",
                'options' => Array(
                    'flags' => LightnCandy::FLAG_MUSTACHE | LightnCandy::FLAG_PREVENTINDENT,
                    'partials' => Array('test1' => "1:A\n 2:B\n  3:C\n 4:D\n5:E\n"),
                ),
                'expected' => "1:A\n 2:B\n  3:C\n 4:D\n5:E\n  1:A\n 2:B\n  3:C\n 4:D\n5:E\nDONE\n",
            ),

            Array(
                'template' => "{{foo}}\n  {{bar}}\n",
                'data' => Array('foo' => 'ha', 'bar' => 'hey'),
                'options' => Array(
                    'flags' => LightnCandy::FLAG_MUSTACHE | LightnCandy::FLAG_PREVENTINDENT,
                ),
                'expected' => "ha\n  hey\n",
            ),

            Array(
                'template' => "{{>test}}\n",
                'data' => Array('foo' => 'ha', 'bar' => 'hey'),
                'options' => Array(
                    'flags' => LightnCandy::FLAG_MUSTACHE | LightnCandy::FLAG_PREVENTINDENT,
                    'partials' => Array('test' => "{{foo}}\n  {{bar}}\n"),
                ),
                'expected' => "ha\n  hey\n",
            ),

            Array(
                'template' => " {{>test}}\n",
                'data' => Array('foo' => 'ha', 'bar' => 'hey'),
                'options' => Array(
                    'flags' => LightnCandy::FLAG_MUSTACHE | LightnCandy::FLAG_PREVENTINDENT,
                    'partials' => Array('test' => "{{foo}}\n  {{bar}}\n"),
                ),
                'expected' => " ha\n  hey\n",
            ),

            Array(
                'template' => "\n {{>test}}\n",
                'data' => Array('foo' => 'ha', 'bar' => 'hey'),
                'options' => Array(
                    'flags' => LightnCandy::FLAG_MUSTACHE | LightnCandy::FLAG_PREVENTINDENT,
                    'partials' => Array('test' => "{{foo}}\n  {{bar}}\n"),
                ),
                'expected' => "\n ha\n  hey\n",
            ),

            Array(
                'template' => "\n{{#each foo~}}\n  <li>{{.}}</li>\n{{~/each}}\n\nOK",
                'data' => Array('foo' => array('ha', 'hu')),
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                ),
                'expected' => "\n<li>ha</li><li>hu</li>\nOK",
            ),

            Array(
                'template' => "ST:\n{{#foo}}\n {{>test1}}\n{{/foo}}\nOK\n",
                'data' => Array('foo' => Array(1, 2)),
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                    'partials' => Array('test1' => "1:A\n 2:B({{@index}})\n"),
                ),
                'expected' => "ST:\n 1:A\n  2:B(0)\n 1:A\n  2:B(1)\nOK\n",
            ),

            Array(
                'template' => ">{{helper1 \"===\"}}<",
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                    'helpers' => Array(
                        'helper1',
                    )
                ),
                'expected' => ">-&#x3D;&#x3D;&#x3D;-<",
            ),

            Array(
                'template' => "{{foo}}",
                'data' => Array('foo' => 'A&B " \''),
                'options' => Array('flags' => LightnCandy::FLAG_NOESCAPE),
                'expected' => "A&B \" '",
            ),

            Array(
                'template' => "{{foo}}",
                'data' => Array('foo' => 'A&B " \' ='),
                'expected' => "A&amp;B &quot; &#039; =",
            ),

            Array(
                'template' => "{{foo}}",
                'data' => Array('foo' => '<a href="#">\'</a>'),
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HBESCAPE,
                ),
                'expected' => '&lt;a href&#x3D;&quot;#&quot;&gt;&#x27;&lt;/a&gt;',
            ),

            Array(
                'template' => '{{#if}}SHOW:{{.}} {{/if}}',
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_NOHBHELPERS,
                ),
                'data' => Array('if' => Array(1, 3, 7), 'a' => Array(2, 4, 9)),
                'expected' => 'SHOW:1 SHOW:3 SHOW:7 ',
            ),

            Array(
                'template' => '{{#unless}}SHOW:{{.}} {{/unless}}',
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_NOHBHELPERS,
                ),
                'data' => Array('unless' => Array(1, 3, 7), 'a' => Array(2, 4, 9)),
                'expected' => 'SHOW:1 SHOW:3 SHOW:7 ',
            ),

            Array(
                'template' => '{{#each}}SHOW:{{.}} {{/each}}',
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_NOHBHELPERS,
                ),
                'data' => Array('each' => Array(1, 3, 7), 'a' => Array(2, 4, 9)),
                'expected' => 'SHOW:1 SHOW:3 SHOW:7 ',
            ),

            Array(
                'template' => '{{#>foo}}inline\'partial{{/foo}}',
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS_FULL,
                ),
                'expected' => 'inline\'partial',
            ),

            Array(
                'template' => '{{>foo}} and {{>bar}}',
                'options' => Array(
                    'partialresolver' => function ($context, $name) {
                        return "PARTIAL: $name";
                    }
                ),
                'expected' => 'PARTIAL: foo and PARTIAL: bar',
            ),

            Array(
                'template' => "{{#> testPartial}}\n ERROR: testPartial is not found!\n  {{#> innerPartial}}\n   ERROR: innerPartial is not found!\n   ERROR: innerPartial is not found!\n  {{/innerPartial}}\n ERROR: testPartial is not found!\n {{/testPartial}}",
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARS | LightnCandy::FLAG_RUNTIMEPARTIAL,
                ),
                'expected' => " ERROR: testPartial is not found!\n   ERROR: innerPartial is not found!\n   ERROR: innerPartial is not found!\n ERROR: testPartial is not found!\n",
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
