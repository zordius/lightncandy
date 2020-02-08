<?php

use LightnCandy\LightnCandy;
use LightnCandy\Runtime;
use PHPUnit\Framework\TestCase;

require_once('tests/helpers_for_test.php');

$tmpdir = sys_get_temp_dir();
$errlog_fn = tempnam($tmpdir, 'terr_');

function start_catch_error_log() {
    global $errlog_fn;
    date_default_timezone_set('GMT');
    if (file_exists($errlog_fn)) {
        unlink($errlog_fn);
    }
    return ini_set('error_log', $errlog_fn);
}

function stop_catch_error_log() {
    global $errlog_fn;
    ini_restore('error_log');
    if (!file_exists($errlog_fn)) {
        return null;
    }
    return array_map(function ($l) {
        $l = rtrim($l);
        preg_match('/GMT\] (.+)/', $l, $m);
        return isset($m[1]) ? $m[1] : $l;
    }, file($errlog_fn));
}

class errorTest extends TestCase
{
    public function testException()
    {
        try {
          $php = LightnCandy::compile('{{{foo}}', array('flags' => LightnCandy::FLAG_ERROR_EXCEPTION));
        } catch (\Exception $E) {
            $this->assertEquals('Bad token {{{foo}} ! Do you mean {{foo}} or {{{foo}}}?', $E->getMessage());
        }
    }

    public function testErrorLog()
    {
        start_catch_error_log();
        $php = LightnCandy::compile('{{{foo}}', array('flags' => LightnCandy::FLAG_ERROR_LOG));
        $e = stop_catch_error_log();
        if ($e) {
            $this->assertEquals(array('Bad token {{{foo}} ! Do you mean {{foo}} or {{{foo}}}?'), $e);
        } else {
            $this->markTestIncomplete('skip HHVM');
        }
    }

    public function testLog()
    {
        $php = LightnCandy::compile('{{log foo}}');
        $renderer = LightnCandy::prepare($php);
        start_catch_error_log();
        $renderer(array('foo' => 'OK!'));
        $e = stop_catch_error_log();
        if ($e) {
            $this->assertEquals(array('array (', "  0 => 'OK!',", ')'), $e);
        } else {
            $this->markTestIncomplete('skip HHVM');
        }
    }

    /**
     * @dataProvider renderErrorProvider
     */
    public function testRenderingException($test)
    {
        $php = LightnCandy::compile($test['template'], $test['options']);
        $renderer = LightnCandy::prepare($php);
        try {
            $input = isset($test['data']) ? $test['data'] : null;
            $renderer($input, array('debug' => Runtime::DEBUG_ERROR_EXCEPTION));
        } catch (\Exception $E) {
            $this->assertEquals($test['expected'], $E->getMessage());
            return;
        }
        $this->fail("Expected to throw exception: {$test['expected']} . CODE: $php");
    }

    /**
     * @dataProvider renderErrorProvider
     */
    public function testRenderingErrorLog($test)
    {
        start_catch_error_log();
        $php = LightnCandy::compile($test['template'], $test['options']);
        $renderer = LightnCandy::prepare($php);
        try {
            $in = array('dummy' => 'reference');
            $renderer($in, array('debug' => Runtime::DEBUG_ERROR_LOG));
        } catch (\Exception $E) {
            $this->fail("Unexpected render exception: " . $E->getMessage() . ", CODE: $php");
        }
        $e = stop_catch_error_log();
        if ($e) {
            $this->assertEquals(array($test['expected']), $e);
        } else {
            $this->markTestIncomplete('skip HHVM');
        }
    }

    public function renderErrorProvider()
    {
        $errorCases = array(
             array(
                 'template' => "{{#> testPartial}}\n  {{#> innerPartial}}\n   {{> @partial-block}}\n  {{/innerPartial}}\n{{/testPartial}}",
                 'options' => array(
                   'flags' => LightnCandy::FLAG_HANDLEBARS | LightnCandy::FLAG_RUNTIMEPARTIAL | LightnCandy::FLAG_ERROR_SKIPPARTIAL,
                   'partials' => array(
                     'testPartial' => 'testPartial => {{> @partial-block}} <=',
                     'innerPartial' => 'innerPartial -> {{> @partial-block}} <-',
                   ),
                 ),
                 'expected' => "Can not find partial named as '@partial-block' !!",
             ),
             array(
                 'template' => '{{> abc}}',
                 'options' => array(
                   'flags' => LightnCandy::FLAG_HANDLEBARS | LightnCandy::FLAG_RUNTIMEPARTIAL | LightnCandy::FLAG_ERROR_SKIPPARTIAL,
                 ),
                 'expected' => "Can not find partial named as 'abc' !!",
             ),
             array(
                 'template' => '{{> @partial-block}}',
                 'options' => array(
                   'flags' => LightnCandy::FLAG_HANDLEBARS | LightnCandy::FLAG_RUNTIMEPARTIAL,
                 ),
                 'expected' => "Can not find partial named as '@partial-block' !!",
             ),
             array(
                 'template' => '{{{foo}}}',
                 'expected' => 'Runtime: [foo] does not exist',
             ),
             array(
                 'template' => '{{foo}}',
                 'options' => array(
                     'helpers' => array(
                         'foo' => function () {
                             return 1/0;
                         }
                     ),
                 ),
                 'expected' => 'Runtime: call custom helper \'foo\' error: Division by zero',
             ),
        );

        return array_map(function($i) {
            if (!isset($i['options'])) {
                $i['options'] = array('flags' => LightnCandy::FLAG_RENDER_DEBUG);
            }
            if (!isset($i['options']['flags'])) {
                $i['options']['flags'] = LightnCandy::FLAG_RENDER_DEBUG;
            }
            return array($i);
        }, $errorCases);
    }

    /**
     * @dataProvider errorProvider
     */
    public function testErrors($test)
    {
        global $tmpdir;

        $php = LightnCandy::compile($test['template'], $test['options']);
        $context = LightnCandy::getContext();

        // This case should be compiled without error
        if (!isset($test['expected'])) {
            $this->assertEquals(true, true);
            return;
        }

        $this->assertEquals($test['expected'], $context['error'], "Code: $php");
    }

    public function errorProvider()
    {
        $errorCases = array(
            array(
                'template' => '{{testerr1}}}',
                'expected' => 'Bad token {{testerr1}}} ! Do you mean {{testerr1}} or {{{testerr1}}}?',
            ),
            array(
                'template' => '{{{testerr2}}',
                'expected' => 'Bad token {{{testerr2}} ! Do you mean {{testerr2}} or {{{testerr2}}}?',
            ),
            array(
                'template' => '{{{#testerr3}}}',
                'expected' => 'Bad token {{{#testerr3}}} ! Do you mean {{#testerr3}} ?',
            ),
            array(
                'template' => '{{{!testerr4}}}',
                'expected' => 'Bad token {{{!testerr4}}} ! Do you mean {{!testerr4}} ?',
            ),
            array(
                'template' => '{{{^testerr5}}}',
                'expected' => 'Bad token {{{^testerr5}}} ! Do you mean {{^testerr5}} ?',
            ),
            array(
                'template' => '{{{/testerr6}}}',
                'expected' => 'Bad token {{{/testerr6}}} ! Do you mean {{/testerr6}} ?',
            ),
            array(
                'template' => '{{win[ner.test1}}',
                'options' => array('flags' => LightnCandy::FLAG_ADVARNAME),
                'expected' => array(
                    "Error in 'win[ner.test1': expect ']' but the token ended!!",
                    'Wrong variable naming in {{win[ner.test1}}',
                ),
            ),
            array(
                'template' => '{{win]ner.test2}}',
                'options' => array('flags' => LightnCandy::FLAG_ADVARNAME),
                'expected' => 'Wrong variable naming as \'win]ner.test2\' in {{win]ner.test2}} !',
            ),
            array(
                'template' => '{{wi[n]ner.test3}}',
                'options' => array('flags' => LightnCandy::FLAG_ADVARNAME),
                'expected' => array(
                    'Wrong variable naming as \'wi[n]ner.test3\' in {{wi[n]ner.test3}} !',
                    "Unexpected charactor in 'wi[n]ner.test3' ! (should it be 'wi.[n].ner.test3' ?)",
                ),
            ),
            array(
                'template' => '{{winner].[test4]}}',
                'options' => array('flags' => LightnCandy::FLAG_ADVARNAME),
                'expected' => array(
                    'Wrong variable naming as \'winner].[test4]\' in {{winner].[test4]}} !',
                    "Unexpected charactor in 'winner].[test4]' ! (should it be 'winner.[test4]' ?)",
                ),
            ),
            array(
                'template' => '{{winner[.test5]}}',
                'options' => array('flags' => LightnCandy::FLAG_ADVARNAME),
                'expected' => array(
                    'Wrong variable naming as \'winner[.test5]\' in {{winner[.test5]}} !',
                    "Unexpected charactor in 'winner[.test5]' ! (should it be 'winner.[.test5]' ?)",
                ),
            ),
            array(
                'template' => '{{winner.[.test6]}}',
                'options' => array('flags' => LightnCandy::FLAG_ADVARNAME),
            ),
            array(
                'template' => '{{winner.[#te.st7]}}',
                'options' => array('flags' => LightnCandy::FLAG_ADVARNAME),
            ),
            array(
                'template' => '{{test8}}',
                'options' => array('flags' => LightnCandy::FLAG_ADVARNAME),
            ),
            array(
                'template' => '{{test9]}}',
                'options' => array('flags' => LightnCandy::FLAG_ADVARNAME),
                'expected' => array(
                    'Wrong variable naming as \'test9]\' in {{test9]}} !',
                    "Unexpected charactor in 'test9]' ! (should it be 'test9' ?)",
                ),
            ),
            array(
                'template' => '{{testA[}}',
                'options' => array('flags' => LightnCandy::FLAG_ADVARNAME),
                'expected' => array(
                    "Error in 'testA[': expect ']' but the token ended!!",
                    'Wrong variable naming in {{testA[}}',
                ),
            ),
            array(
                'template' => '{{[testB}}',
                'options' => array('flags' => LightnCandy::FLAG_ADVARNAME),
                'expected' => array(
                    "Error in '[testB': expect ']' but the token ended!!",
                    'Wrong variable naming in {{[testB}}',
                ),
            ),
            array(
                'template' => '{{]testC}}',
                'options' => array('flags' => LightnCandy::FLAG_ADVARNAME),
                'expected' => array(
                    'Wrong variable naming as \']testC\' in {{]testC}} !',
                    "Unexpected charactor in ']testC' ! (should it be 'testC' ?)",
                )
            ),
            array(
                'template' => '{{[testD]}}',
                'options' => array('flags' => LightnCandy::FLAG_ADVARNAME),
            ),
            array(
                'template' => '{{te]stE}}',
                'options' => array('flags' => LightnCandy::FLAG_ADVARNAME),
                'expected' => 'Wrong variable naming as \'te]stE\' in {{te]stE}} !',
            ),
            array(
                'template' => '{{tee[stF}}',
                'options' => array('flags' => LightnCandy::FLAG_ADVARNAME),
                'expected' => array(
                    "Error in 'tee[stF': expect ']' but the token ended!!",
                    'Wrong variable naming in {{tee[stF}}',
                )
            ),
            array(
                'template' => '{{te.e[stG}}',
                'options' => array('flags' => LightnCandy::FLAG_ADVARNAME),
                'expected' => array(
                    "Error in 'te.e[stG': expect ']' but the token ended!!",
                    'Wrong variable naming in {{te.e[stG}}',
                ),
            ),
            array(
                'template' => '{{te.e]stH}}',
                'options' => array('flags' => LightnCandy::FLAG_ADVARNAME),
                'expected' => 'Wrong variable naming as \'te.e]stH\' in {{te.e]stH}} !',
            ),
            array(
                'template' => '{{te.e[st.endI}}',
                'options' => array('flags' => LightnCandy::FLAG_ADVARNAME),
                'expected' => array(
                    "Error in 'te.e[st.endI': expect ']' but the token ended!!",
                    'Wrong variable naming in {{te.e[st.endI}}',
                ),
            ),
            array(
                'template' => '{{te.e]st.endJ}}',
                'options' => array('flags' => LightnCandy::FLAG_ADVARNAME),
                'expected' => 'Wrong variable naming as \'te.e]st.endJ\' in {{te.e]st.endJ}} !',
            ),
            array(
                'template' => '{{te.[est].endK}}',
                'options' => array('flags' => LightnCandy::FLAG_ADVARNAME),
            ),
            array(
                'template' => '{{te.t[est].endL}}',
                'options' => array('flags' => LightnCandy::FLAG_ADVARNAME),
                'expected' => array(
                    'Wrong variable naming as \'te.t[est].endL\' in {{te.t[est].endL}} !',
                    "Unexpected charactor in 'te.t[est].endL' ! (should it be 'te.t.[est].endL' ?)",
                ),
            ),
            array(
                'template' => '{{te.t[est]o.endM}}',
                'options' => array('flags' => LightnCandy::FLAG_ADVARNAME),
                'expected' => array(
                    'Wrong variable naming as \'te.t[est]o.endM\' in {{te.t[est]o.endM}} !',
                    "Unexpected charactor in 'te.t[est]o.endM' ! (should it be 'te.t.[est].o.endM' ?)"
                ),
            ),
            array(
                'template' => '{{te.[est]o.endN}}',
                'options' => array('flags' => LightnCandy::FLAG_ADVARNAME),
                'expected' => array(
                    'Wrong variable naming as \'te.[est]o.endN\' in {{te.[est]o.endN}} !',
                    "Unexpected charactor in 'te.[est]o.endN' ! (should it be 'te.[est].o.endN' ?)",
                ),
            ),
            array(
                'template' => '{{te.[e.st].endO}}',
                'options' => array('flags' => LightnCandy::FLAG_ADVARNAME),
            ),
            array(
                'template' => '{{te.[e.s[t].endP}}',
                'options' => array('flags' => LightnCandy::FLAG_ADVARNAME),
            ),
            array(
                'template' => '{{te.[e[s.t].endQ}}',
                'options' => array('flags' => LightnCandy::FLAG_ADVARNAME),
            ),
            array(
                'template' => '{{helper}}',
                'options' => array('helpers' => array(
                    'helper' => array('bad input'),
                )),
                'expected' => 'I found an array in helpers with key as helper, please fix it.',
            ),
            array(
                'template' => '<ul>{{#each item}}<li>{{name}}</li>',
                'expected' => 'Unclosed token {{#each item}} !!',
            ),
            array(
                'template' => 'issue63: {{test_join}} Test! {{this}} {{/test_join}}',
                'expected' => 'Unexpect token: {{/test_join}} !',
            ),
            array(
                'template' => '{{#if a}}TEST{{/with}}',
                'expected' => 'Unexpect token: {{/with}} !',
            ),
            array(
                'template' => '{{#foo}}error{{/bar}}',
                'expected' => 'Unexpect token {{/bar}} ! Previous token {{#[foo]}} is not closed',
            ),
            array(
                'template' => '{{../foo}}',
                'expected' => 'Do not support {{../var}}, you should do compile with LightnCandy::FLAG_PARENT flag',
            ),
            array(
                'template' => '{{..}}',
                'expected' => 'Do not support {{../var}}, you should do compile with LightnCandy::FLAG_PARENT flag',
            ),
            array(
                'template' => '{{test_join [a]=b}}',
                'options' => array(
                    'flags' => LightnCandy::FLAG_NAMEDARG,
                    'helpers' => array('test_join')
                ),
                'expected' => "Wrong argument name as '[a]' in {{test_join [a]=b}} ! You should fix your template or compile with LightnCandy::FLAG_ADVARNAME flag.",
            ),
            array(
                'template' => '{{a=b}}',
                'options' => array('flags' => LightnCandy::FLAG_NAMEDARG),
                'expected' => 'Do not support name=value in {{a=b}}, you should use it after a custom helper.',
            ),
            array(
                'template' => '{{#foo}}1{{^}}2{{/foo}}',
                'expected' => 'Do not support {{^}}, you should do compile with LightnCandy::FLAG_ELSE flag',
            ),
            array(
                'template' => '{{#with a}OK!{{/with}}',
                'expected' => 'Unclosed token {{#with a}OK!{{/with}} !!',
            ),
            array(
                'template' => '{{#each a}OK!{{/each}}',
                'expected' => 'Unclosed token {{#each a}OK!{{/each}} !!',
            ),
            array(
                'template' => '{{#with items}}OK!{{/with}}',
            ),
            array(
                'template' => '{{#with}}OK!{{/with}}',
                'expected' => 'No argument after {{#with}} !',
            ),
            array(
                'template' => '{{#if}}OK!{{/if}}',
                'expected' => 'No argument after {{#if}} !',
            ),
            array(
                'template' => '{{#unless}}OK!{{/unless}}',
                'expected' => 'No argument after {{#unless}} !',
            ),
            array(
                'template' => '{{#each}}OK!{{/each}}',
                'expected' => 'No argument after {{#each}} !',
            ),
            array(
                'template' => '{{lookup}}',
                'expected' => 'No argument after {{lookup}} !',
            ),
            array(
                'template' => '{{lookup foo}}',
                'expected' => '{{lookup}} requires 2 arguments !',
            ),
            array(
                'template' => '{{#test foo}}{{/test}}',
                'expected' => 'Custom helper not found: test in {{#test foo}} !',
            ),
            array(
                'template' => '{{>not_found}}',
                'expected' => "Can not find partial for 'not_found', you should provide partials or partialresolver in options",
            ),
            array(
                'template' => '{{>tests/test1 foo}}',
                'options' => array('partials' => array('tests/test1' => '')),
                'expected' => 'Do not support {{>tests/test1 foo}}, you should do compile with LightnCandy::FLAG_RUNTIMEPARTIAL flag',
            ),
            array(
                'template' => '{{#with foo}}ABC{{/with}}',
                'options' => array('flags' => LightnCandy::FLAG_NOHBHELPERS),
                'expected' => 'Do not support {{#with var}} because you compile with LightnCandy::FLAG_NOHBHELPERS flag',
            ),
            array(
                'template' => '{{#if foo}}ABC{{/if}}',
                'options' => array('flags' => LightnCandy::FLAG_NOHBHELPERS),
                'expected' => 'Do not support {{#if var}} because you compile with LightnCandy::FLAG_NOHBHELPERS flag',
            ),
            array(
                'template' => '{{#unless foo}}ABC{{/unless}}',
                'options' => array('flags' => LightnCandy::FLAG_NOHBHELPERS),
                'expected' => 'Do not support {{#unless var}} because you compile with LightnCandy::FLAG_NOHBHELPERS flag',
            ),
            array(
                'template' => '{{#each foo}}ABC{{/each}}',
                'options' => array('flags' => LightnCandy::FLAG_NOHBHELPERS),
                'expected' => 'Do not support {{#each var}} because you compile with LightnCandy::FLAG_NOHBHELPERS flag',
            ),
            array(
                'template' => '{{abc}}',
                'options' => array('helpers' => array('abc')),
                'expected' => "You provide a custom helper named as 'abc' in options['helpers'], but the function abc() is not defined!",
            ),
            array(
                'template' => '{{=~= =~=}}',
                'expected' => "Can not set delimiter contains '=' , you try to set delimiter as '~=' and '=~'.",
            ),
            array(
                'template' => '{{>recursive}}',
                'options' => array('partials' => array('recursive' => '{{>recursive}}')),
                'expected' => array(
                    'I found recursive partial includes as the path: recursive -> recursive! You should fix your template or compile with LightnCandy::FLAG_RUNTIMEPARTIAL flag.',
                )
            ),
            array(
                'template' => '{{test_join (foo bar)}}',
                'options' => array(
                    'flags' => LightnCandy::FLAG_ADVARNAME,
                    'helpers' => array('test_join'),
                ),
                'expected' => "Can not find custom helper function defination foo() !",
            ),
            array(
                'template' => '{{1 + 2}}',
                'options' => array(
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                    'helpers' => array('test_join'),
                ),
                'expected' => "Wrong variable naming as '+' in {{1 + 2}} ! You should wrap ! \" # % & ' * + , ; < = > { | } ~ into [ ]",
            ),
            array(
                'template' => '{{> (foo) bar}}',
                'options' => array(
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                ),
                'expected' => array(
                    "Can not find custom helper function defination foo() !",
                    "You use dynamic partial name as '(foo)', this only works with option FLAG_RUNTIMEPARTIAL enabled",
                )
            ),
            array(
                'template' => '{{{{#foo}}}',
                'options' => array(
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                ),
                'expected' => array(
                    'Bad token {{{{#foo}}} ! Do you mean {{{{#foo}}}} ?',
                    'Wrong raw block begin with {{{{#foo}}} ! Remove "#" to fix this issue.',
                    'Unclosed token {{{{foo}}}} !!',
                )
            ),
            array(
                'template' => '{{{{foo}}}} {{ {{{{/bar}}}}',
                'options' => array(
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                ),
                'expected' => array(
                    'Unclosed token {{{{foo}}}} !!',
                )
            ),
            array(
                'template' => '{{foo (foo (foo 1 2) 3))}}',
                'options' => array(
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                     'helpers' => array(
                         'foo' => function () {
                             return;
                         }
                     )
                ),
                'expected' => array(
                    'Unexcepted \')\' in expression \'foo (foo (foo 1 2) 3))\' !!',
                )
            ),
            array(
                'template' => '{{{{foo}}}} {{ {{{{#foo}}}}',
                'options' => array(
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                ),
                'expected' => array(
                    'Unclosed token {{{{foo}}}} !!',
                )
            ),
            array(
                'template' => '{{else}}',
                'options' => array(
                    'flags' => LightnCandy::FLAG_ELSE,
                ),
                'expected' => array(
                    '{{else}} only valid in if, unless, each, and #section context',
                )
            ),
            array(
                'template' => '{{log}}',
                'expected' => array(
                    'No argument after {{log}} !',
                )
            ),
            array(
                'template' => '{{#*inline test}}{{/inline}}',
                'expected' => array(
                    'Do not support {{#*inline test}}, you should do compile with LightnCandy::FLAG_RUNTIMEPARTIAL flag',
                )
            ),
            array(
                'template' => '{{#*help me}}{{/help}}',
                'options' => array(
                    'flags' => LightnCandy::FLAG_RUNTIMEPARTIAL,
                ),
                'expected' => array(
                    'Do not support {{#*help me}}, now we only support {{#*inline "partialName"}}template...{{/inline}}'
                )
            ),
            array(
                'template' => '{{#*inline}}{{/inline}}',
                'options' => array(
                    'flags' => LightnCandy::FLAG_RUNTIMEPARTIAL,
                ),
                'expected' => array(
                    'Error in {{#*inline}}: inline require 1 argument for partial name!',
                )
            ),
            array(
                'template' => '{{#>foo}}bar',
                'options' => array(
                    'flags' => LightnCandy::FLAG_RUNTIMEPARTIAL,
                ),
                'expected' => array(
                    'Unclosed token {{#>foo}} !!',
                )
            ),
            array(
                'template' => '{{ #2 }}',
                'options' => array(
                    'flags' => LightnCandy::FLAG_BESTPERFORMANCE,
                ),
                'expected' => array(
                    'Unclosed token {{#2}} !!',
                )
            ),
            array(
                'template' => '{{foo a=b}}',
                'options' => array(
                    'flags' => LightnCandy::FLAG_ADVARNAME,
                ),
                'expected' => array(
                    "Wrong variable naming as 'a=b' in {{foo a=b}} ! If you try to use foo=bar param, you should enable LightnCandy::FLAG_NAMEDARG !",
                )
            ),
        );

        return array_map(function($i) {
            if (!isset($i['options'])) {
                $i['options'] = array('flags' => 0);
            }
            if (!isset($i['options']['flags'])) {
                $i['options']['flags'] = 0;
            }
            if (isset($i['expected']) && !is_array($i['expected'])) {
                $i['expected'] = array($i['expected']);
            }
            return array($i);
        }, $errorCases);
    }
}

