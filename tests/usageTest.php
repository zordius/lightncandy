<?php

use LightnCandy\LightnCandy;
use PHPUnit\Framework\TestCase;

require_once('tests/helpers_for_test.php');

class usageTest extends TestCase
{
    /**
     * @dataProvider compileProvider
     */
    public function testUsedFeature($test)
    {
        LightnCandy::compile($test['template'], $test['options']);
        $context = LightnCandy::getContext();
        $this->assertEquals($test['expected'], $context['usedFeature']);
    }

    public function compileProvider()
    {
        $default = array(
            'rootthis' => 0,
            'enc' => 0,
            'raw' => 0,
            'sec' => 0,
            'isec' => 0,
            'if' => 0,
            'else' => 0,
            'unless' => 0,
            'each' => 0,
            'this' => 0,
            'parent' => 0,
            'with' => 0,
            'comment' => 0,
            'partial' => 0,
            'dynpartial' => 0,
            'inlpartial' => 0,
            'helper' => 0,
            'delimiter' => 0,
            'subexp' => 0,
            'rawblock' => 0,
            'pblock' => 0,
            'lookup' => 0,
            'log' => 0,
        );

        $compileCases = array(
             array(
                 'template' => 'abc',
             ),

             array(
                 'template' => 'abc{{def',
             ),

             array(
                 'template' => 'abc{{def}}',
                 'expected' => array(
                     'enc' => 1
                 ),
             ),

             array(
                 'template' => 'abc{{{def}}}',
                 'expected' => array(
                     'raw' => 1
                 ),
             ),

             array(
                 'template' => 'abc{{&def}}',
                 'expected' => array(
                     'raw' => 1
                 ),
             ),

             array(
                 'template' => 'abc{{this}}',
                 'expected' => array(
                     'enc' => 1
                 ),
             ),

             array(
                 'template' => 'abc{{this}}',
                 'options' => array('flags' => LightnCandy::FLAG_THIS),
                 'expected' => array(
                     'enc' => 1,
                     'this' => 1,
                     'rootthis' => 1,
                 ),
             ),

             array(
                 'template' => '{{#if abc}}OK!{{/if}}',
                 'expected' => array(
                     'if' => 1
                 ),
             ),

             array(
                 'template' => '{{#unless abc}}OK!{{/unless}}',
                 'expected' => array(
                     'unless' => 1
                 ),
             ),

             array(
                 'template' => '{{#with abc}}OK!{{/with}}',
                 'expected' => array(
                     'with' => 1
                 ),
             ),

             array(
                 'template' => '{{#abc}}OK!{{/abc}}',
                 'expected' => array(
                     'sec' => 1
                 ),
             ),

             array(
                 'template' => '{{^abc}}OK!{{/abc}}',
                 'expected' => array(
                     'isec' => 1
                 ),
             ),

             array(
                 'template' => '{{#each abc}}OK!{{/each}}',
                 'expected' => array(
                     'each' => 1
                 ),
             ),

             array(
                 'template' => '{{! test}}OK!{{! done}}',
                 'expected' => array(
                     'comment' => 2
                 ),
             ),

             array(
                 'template' => '{{../OK}}',
                 'expected' => array(
                     'parent' => 1,
                     'enc' => 1,
                 ),
             ),

             array(
                 'template' => '{{&../../OK}}',
                 'expected' => array(
                     'parent' => 1,
                     'raw' => 1,
                 ),
             ),

             array(
                 'template' => '{{&../../../OK}} {{../OK}}',
                 'options' => array(
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                    'helpers' => array(
                        'mytest' => function ($context) {
                            return $context;
                        }
                    )
                ),
                 'expected' => array(
                     'parent' => 2,
                     'enc' => 1,
                     'raw' => 1,
                 ),
             ),

             array(
                 'template' => '{{mytest ../../../OK}} {{../OK}}',
                 'options' => array(
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                    'helpers' => array(
                        'mytest' => function ($context) {
                            return $context;
                        }
                    )
                ),
                 'expected' => array(
                     'parent' => 2,
                     'enc' => 2,
                     'helper' => 1,
                 ),
             ),

             array(
                 'template' => '{{mytest . .}}',
                 'options' => array(
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                    'helpers' => array(
                        'mytest' => function ($a, $b) {
                            return '';
                        }
                    )
                ),
                 'expected' => array(
                     'rootthis' => 2,
                     'this' => 2,
                     'enc' => 1,
                     'helper' => 1,
                 ),
             ),

             array(
                 'template' => '{{mytest (mytest ..)}}',
                 'options' => array(
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                    'helpers' => array(
                        'mytest' => function ($context) {
                            return $context;
                        }
                    )
                ),
                 'expected' => array(
                     'parent' => 1,
                     'enc' => 1,
                     'helper' => 2,
                     'subexp' => 1,
                 ),
             ),

             array(
                 'template' => '{{mytest (mytest ..) .}}',
                 'options' => array(
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                    'helpers' => array(
                        'mytest' => function ($context) {
                            return $context;
                        }
                    )
                ),
                 'expected' => array(
                     'parent' => 1,
                     'rootthis' => 1,
                     'this' => 1,
                     'enc' => 1,
                     'helper' => 2,
                     'subexp' => 1,
                 ),
             ),

             array(
                 'template' => '{{mytest (mytest (mytest ..)) .}}',
                 'options' => array(
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                    'helpers' => array(
                        'mytest' => function ($context) {
                            return $context;
                        }
                    )
                ),
                 'expected' => array(
                     'parent' => 1,
                     'rootthis' => 1,
                     'this' => 1,
                     'enc' => 1,
                     'helper' => 3,
                     'subexp' => 2,
                 ),
             ),

             array(
                 'id' => '134',
                 'template' => '{{#if 1}}{{keys (keys ../names)}}{{/if}}',
                 'options' => array(
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                    'helpers' => array(
                        'keys' => function ($context) {
                            return $context;
                        }
                    )
                ),
                 'expected' => array(
                     'parent' => 1,
                     'enc' => 1,
                     'if' => 1,
                     'helper' => 2,
                     'subexp' => 1,
                 ),
             ),

             array(
                 'id' => '196',
                 'template' => '{{log "this is a test"}}',
                 'options' => array(
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                ),
                 'expected' => array(
                     'log' => 1,
                     'enc' => 1,
                 ),
             ),
        );

        return array_map(function($i) use ($default) {
            if (!isset($i['options'])) {
                $i['options'] = array('flags' => 0);
            }
            if (!isset($i['options']['flags'])) {
                $i['options']['flags'] = 0;
            }
            $i['expected'] = array_merge($default, isset($i['expected']) ? $i['expected'] : array());
            return array($i);
        }, $compileCases);
    }
}

