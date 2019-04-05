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
          $php = LightnCandy::compile('{{{foo}}', Array('flags' => LightnCandy::FLAG_ERROR_EXCEPTION));
        } catch (\Exception $E) {
            $this->assertEquals('Bad token {{{foo}} ! Do you mean {{foo}} or {{{foo}}}?', $E->getMessage());
        }
    }

    public function testErrorLog()
    {
        start_catch_error_log();
        $php = LightnCandy::compile('{{{foo}}', Array('flags' => LightnCandy::FLAG_ERROR_LOG));
        $e = stop_catch_error_log();
        if ($e) {
            $this->assertEquals(Array('Bad token {{{foo}} ! Do you mean {{foo}} or {{{foo}}}?'), $e);
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
            $this->assertEquals(Array('array (', "  0 => 'OK!',", ')'), $e);
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
            $this->assertEquals(Array($test['expected']), $e);
        } else {
            $this->markTestIncomplete('skip HHVM');
        }
    }

    public function renderErrorProvider()
    {
        $errorCases = Array(
             Array(
                 'template' => "{{#> testPartial}}\n  {{#> innerPartial}}\n   {{> @partial-block}}\n  {{/innerPartial}}\n{{/testPartial}}",
                 'options' => Array(
                   'flags' => LightnCandy::FLAG_HANDLEBARS | LightnCandy::FLAG_RUNTIMEPARTIAL | LightnCandy::FLAG_ERROR_SKIPPARTIAL,
                   'partials' => Array(
                     'testPartial' => 'testPartial => {{> @partial-block}} <=',
                     'innerPartial' => 'innerPartial -> {{> @partial-block}} <-',
                   ),
                 ),
                 'expected' => "Can not find partial named as '@partial-block' !!",
             ),
             Array(
                 'template' => '{{> abc}}',
                 'options' => Array(
                   'flags' => LightnCandy::FLAG_HANDLEBARS | LightnCandy::FLAG_RUNTIMEPARTIAL | LightnCandy::FLAG_ERROR_SKIPPARTIAL,
                 ),
                 'expected' => "Can not find partial named as 'abc' !!",
             ),
             Array(
                 'template' => '{{> @partial-block}}',
                 'options' => Array(
                   'flags' => LightnCandy::FLAG_HANDLEBARS | LightnCandy::FLAG_RUNTIMEPARTIAL,
                 ),
                 'expected' => "Can not find partial named as '@partial-block' !!",
             ),
             Array(
                 'template' => '{{{foo}}}',
                 'expected' => 'Runtime: [foo] does not exist',
             ),
             Array(
                 'template' => '{{foo}}',
                 'options' => Array(
                     'helpers' => Array(
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
                $i['options'] = Array('flags' => LightnCandy::FLAG_RENDER_DEBUG);
            }
            if (!isset($i['options']['flags'])) {
                $i['options']['flags'] = LightnCandy::FLAG_RENDER_DEBUG;
            }
            return Array($i);
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
        $errorCases = Array(
            Array(
                'template' => '{{testerr1}}}',
                'expected' => 'Bad token {{testerr1}}} ! Do you mean {{testerr1}} or {{{testerr1}}}?',
            ),
            Array(
                'template' => '{{{testerr2}}',
                'expected' => 'Bad token {{{testerr2}} ! Do you mean {{testerr2}} or {{{testerr2}}}?',
            ),
            Array(
                'template' => '{{{#testerr3}}}',
                'expected' => 'Bad token {{{#testerr3}}} ! Do you mean {{#testerr3}} ?',
            ),
            Array(
                'template' => '{{{!testerr4}}}',
                'expected' => 'Bad token {{{!testerr4}}} ! Do you mean {{!testerr4}} ?',
            ),
            Array(
                'template' => '{{{^testerr5}}}',
                'expected' => 'Bad token {{{^testerr5}}} ! Do you mean {{^testerr5}} ?',
            ),
            Array(
                'template' => '{{{/testerr6}}}',
                'expected' => 'Bad token {{{/testerr6}}} ! Do you mean {{/testerr6}} ?',
            ),
            Array(
                'template' => '{{win[ner.test1}}',
                'options' => Array('flags' => LightnCandy::FLAG_ADVARNAME),
                'expected' => Array(
                    "Error in 'win[ner.test1': expect ']' but the token ended!!",
                    'Wrong variable naming in {{win[ner.test1}}',
                ),
            ),
            Array(
                'template' => '{{win]ner.test2}}',
                'options' => Array('flags' => LightnCandy::FLAG_ADVARNAME),
                'expected' => 'Wrong variable naming as \'win]ner.test2\' in {{win]ner.test2}} !',
            ),
            Array(
                'template' => '{{wi[n]ner.test3}}',
                'options' => Array('flags' => LightnCandy::FLAG_ADVARNAME),
                'expected' => Array(
                    'Wrong variable naming as \'wi[n]ner.test3\' in {{wi[n]ner.test3}} !',
                    "Unexpected charactor in 'wi[n]ner.test3' ! (should it be 'wi.[n].ner.test3' ?)",
                ),
            ),
            Array(
                'template' => '{{winner].[test4]}}',
                'options' => Array('flags' => LightnCandy::FLAG_ADVARNAME),
                'expected' => Array(
                    'Wrong variable naming as \'winner].[test4]\' in {{winner].[test4]}} !',
                    "Unexpected charactor in 'winner].[test4]' ! (should it be 'winner.[test4]' ?)",
                ),
            ),
            Array(
                'template' => '{{winner[.test5]}}',
                'options' => Array('flags' => LightnCandy::FLAG_ADVARNAME),
                'expected' => Array(
                    'Wrong variable naming as \'winner[.test5]\' in {{winner[.test5]}} !',
                    "Unexpected charactor in 'winner[.test5]' ! (should it be 'winner.[.test5]' ?)",
                ),
            ),
            Array(
                'template' => '{{winner.[.test6]}}',
                'options' => Array('flags' => LightnCandy::FLAG_ADVARNAME),
            ),
            Array(
                'template' => '{{winner.[#te.st7]}}',
                'options' => Array('flags' => LightnCandy::FLAG_ADVARNAME),
            ),
            Array(
                'template' => '{{test8}}',
                'options' => Array('flags' => LightnCandy::FLAG_ADVARNAME),
            ),
            Array(
                'template' => '{{test9]}}',
                'options' => Array('flags' => LightnCandy::FLAG_ADVARNAME),
                'expected' => Array(
                    'Wrong variable naming as \'test9]\' in {{test9]}} !',
                    "Unexpected charactor in 'test9]' ! (should it be 'test9' ?)",
                ),
            ),
            Array(
                'template' => '{{testA[}}',
                'options' => Array('flags' => LightnCandy::FLAG_ADVARNAME),
                'expected' => Array(
                    "Error in 'testA[': expect ']' but the token ended!!",
                    'Wrong variable naming in {{testA[}}',
                ),
            ),
            Array(
                'template' => '{{[testB}}',
                'options' => Array('flags' => LightnCandy::FLAG_ADVARNAME),
                'expected' => Array(
                    "Error in '[testB': expect ']' but the token ended!!",
                    'Wrong variable naming in {{[testB}}',
                ),
            ),
            Array(
                'template' => '{{]testC}}',
                'options' => Array('flags' => LightnCandy::FLAG_ADVARNAME),
                'expected' => Array(
                    'Wrong variable naming as \']testC\' in {{]testC}} !',
                    "Unexpected charactor in ']testC' ! (should it be 'testC' ?)",
                )
            ),
            Array(
                'template' => '{{[testD]}}',
                'options' => Array('flags' => LightnCandy::FLAG_ADVARNAME),
            ),
            Array(
                'template' => '{{te]stE}}',
                'options' => Array('flags' => LightnCandy::FLAG_ADVARNAME),
                'expected' => 'Wrong variable naming as \'te]stE\' in {{te]stE}} !',
            ),
            Array(
                'template' => '{{tee[stF}}',
                'options' => Array('flags' => LightnCandy::FLAG_ADVARNAME),
                'expected' => Array(
                    "Error in 'tee[stF': expect ']' but the token ended!!",
                    'Wrong variable naming in {{tee[stF}}',
                )
            ),
            Array(
                'template' => '{{te.e[stG}}',
                'options' => Array('flags' => LightnCandy::FLAG_ADVARNAME),
                'expected' => Array(
                    "Error in 'te.e[stG': expect ']' but the token ended!!",
                    'Wrong variable naming in {{te.e[stG}}',
                ),
            ),
            Array(
                'template' => '{{te.e]stH}}',
                'options' => Array('flags' => LightnCandy::FLAG_ADVARNAME),
                'expected' => 'Wrong variable naming as \'te.e]stH\' in {{te.e]stH}} !',
            ),
            Array(
                'template' => '{{te.e[st.endI}}',
                'options' => Array('flags' => LightnCandy::FLAG_ADVARNAME),
                'expected' => Array(
                    "Error in 'te.e[st.endI': expect ']' but the token ended!!",
                    'Wrong variable naming in {{te.e[st.endI}}',
                ),
            ),
            Array(
                'template' => '{{te.e]st.endJ}}',
                'options' => Array('flags' => LightnCandy::FLAG_ADVARNAME),
                'expected' => 'Wrong variable naming as \'te.e]st.endJ\' in {{te.e]st.endJ}} !',
            ),
            Array(
                'template' => '{{te.[est].endK}}',
                'options' => Array('flags' => LightnCandy::FLAG_ADVARNAME),
            ),
            Array(
                'template' => '{{te.t[est].endL}}',
                'options' => Array('flags' => LightnCandy::FLAG_ADVARNAME),
                'expected' => Array(
                    'Wrong variable naming as \'te.t[est].endL\' in {{te.t[est].endL}} !',
                    "Unexpected charactor in 'te.t[est].endL' ! (should it be 'te.t.[est].endL' ?)",
                ),
            ),
            Array(
                'template' => '{{te.t[est]o.endM}}',
                'options' => Array('flags' => LightnCandy::FLAG_ADVARNAME),
                'expected' => Array(
                    'Wrong variable naming as \'te.t[est]o.endM\' in {{te.t[est]o.endM}} !',
                    "Unexpected charactor in 'te.t[est]o.endM' ! (should it be 'te.t.[est].o.endM' ?)"
                ),
            ),
            Array(
                'template' => '{{te.[est]o.endN}}',
                'options' => Array('flags' => LightnCandy::FLAG_ADVARNAME),
                'expected' => Array(
                    'Wrong variable naming as \'te.[est]o.endN\' in {{te.[est]o.endN}} !',
                    "Unexpected charactor in 'te.[est]o.endN' ! (should it be 'te.[est].o.endN' ?)",
                ),
            ),
            Array(
                'template' => '{{te.[e.st].endO}}',
                'options' => Array('flags' => LightnCandy::FLAG_ADVARNAME),
            ),
            Array(
                'template' => '{{te.[e.s[t].endP}}',
                'options' => Array('flags' => LightnCandy::FLAG_ADVARNAME),
            ),
            Array(
                'template' => '{{te.[e[s.t].endQ}}',
                'options' => Array('flags' => LightnCandy::FLAG_ADVARNAME),
            ),
            Array(
                'template' => '{{helper}}',
                'options' => Array('helpers' => Array(
                    'helper' => Array('bad input'),
                )),
                'expected' => 'I found an array in helpers with key as helper, please fix it.',
            ),
            Array(
                'template' => '<ul>{{#each item}}<li>{{name}}</li>',
                'expected' => 'Unclosed token {{#each item}} !!',
            ),
            Array(
                'template' => 'issue63: {{test_join}} Test! {{this}} {{/test_join}}',
                'expected' => 'Unexpect token: {{/test_join}} !',
            ),
            Array(
                'template' => '{{#if a}}TEST{{/with}}',
                'expected' => 'Unexpect token: {{/with}} !',
            ),
            Array(
                'template' => '{{#foo}}error{{/bar}}',
                'expected' => 'Unexpect token {{/bar}} ! Previous token {{#[foo]}} is not closed',
            ),
            Array(
                'template' => '{{../foo}}',
                'expected' => 'Do not support {{../var}}, you should do compile with LightnCandy::FLAG_PARENT flag',
            ),
            Array(
                'template' => '{{..}}',
                'expected' => 'Do not support {{../var}}, you should do compile with LightnCandy::FLAG_PARENT flag',
            ),
            Array(
                'template' => '{{test_join [a]=b}}',
                'options' => Array(
                    'flags' => LightnCandy::FLAG_NAMEDARG,
                    'helpers' => Array('test_join')
                ),
                'expected' => "Wrong argument name as '[a]' in {{test_join [a]=b}} ! You should fix your template or compile with LightnCandy::FLAG_ADVARNAME flag.",
            ),
            Array(
                'template' => '{{a=b}}',
                'options' => Array('flags' => LightnCandy::FLAG_NAMEDARG),
                'expected' => 'Do not support name=value in {{a=b}}, you should use it after a custom helper.',
            ),
            Array(
                'template' => '{{#foo}}1{{^}}2{{/foo}}',
                'expected' => 'Do not support {{^}}, you should do compile with LightnCandy::FLAG_ELSE flag',
            ),
            Array(
                'template' => '{{#with a}OK!{{/with}}',
                'expected' => 'Unclosed token {{#with a}OK!{{/with}} !!',
            ),
            Array(
                'template' => '{{#each a}OK!{{/each}}',
                'expected' => 'Unclosed token {{#each a}OK!{{/each}} !!',
            ),
            Array(
                'template' => '{{#with items}}OK!{{/with}}',
            ),
            Array(
                'template' => '{{#with}}OK!{{/with}}',
                'expected' => 'No argument after {{#with}} !',
            ),
            Array(
                'template' => '{{#if}}OK!{{/if}}',
                'expected' => 'No argument after {{#if}} !',
            ),
            Array(
                'template' => '{{#unless}}OK!{{/unless}}',
                'expected' => 'No argument after {{#unless}} !',
            ),
            Array(
                'template' => '{{#each}}OK!{{/each}}',
                'expected' => 'No argument after {{#each}} !',
            ),
            Array(
                'template' => '{{lookup}}',
                'expected' => 'No argument after {{lookup}} !',
            ),
            Array(
                'template' => '{{lookup foo}}',
                'expected' => '{{lookup}} requires 2 arguments !',
            ),
            Array(
                'template' => '{{#test foo}}{{/test}}',
                'expected' => 'Custom helper not found: test in {{#test foo}} !',
            ),
            Array(
                'template' => '{{>not_found}}',
                'expected' => "Can not find partial for 'not_found', you should provide partials or partialresolver in options",
            ),
            Array(
                'template' => '{{>tests/test1 foo}}',
                'options' => Array('partials' => Array('tests/test1' => '')),
                'expected' => 'Do not support {{>tests/test1 foo}}, you should do compile with LightnCandy::FLAG_RUNTIMEPARTIAL flag',
            ),
            Array(
                'template' => '{{#with foo}}ABC{{/with}}',
                'options' => Array('flags' => LightnCandy::FLAG_NOHBHELPERS),
                'expected' => 'Do not support {{#with var}} because you compile with LightnCandy::FLAG_NOHBHELPERS flag',
            ),
            Array(
                'template' => '{{#if foo}}ABC{{/if}}',
                'options' => Array('flags' => LightnCandy::FLAG_NOHBHELPERS),
                'expected' => 'Do not support {{#if var}} because you compile with LightnCandy::FLAG_NOHBHELPERS flag',
            ),
            Array(
                'template' => '{{#unless foo}}ABC{{/unless}}',
                'options' => Array('flags' => LightnCandy::FLAG_NOHBHELPERS),
                'expected' => 'Do not support {{#unless var}} because you compile with LightnCandy::FLAG_NOHBHELPERS flag',
            ),
            Array(
                'template' => '{{#each foo}}ABC{{/each}}',
                'options' => Array('flags' => LightnCandy::FLAG_NOHBHELPERS),
                'expected' => 'Do not support {{#each var}} because you compile with LightnCandy::FLAG_NOHBHELPERS flag',
            ),
            Array(
                'template' => '{{abc}}',
                'options' => Array('helpers' => Array('abc')),
                'expected' => "You provide a custom helper named as 'abc' in options['helpers'], but the function abc() is not defined!",
            ),
            Array(
                'template' => '{{=~= =~=}}',
                'expected' => "Can not set delimiter contains '=' , you try to set delimiter as '~=' and '=~'.",
            ),
            Array(
                'template' => '{{>recursive}}',
                'options' => Array('partials' => Array('recursive' => '{{>recursive}}')),
                'expected' => Array(
                    'I found recursive partial includes as the path: recursive -> recursive! You should fix your template or compile with LightnCandy::FLAG_RUNTIMEPARTIAL flag.',
                )
            ),
            Array(
                'template' => '{{test_join (foo bar)}}',
                'options' => Array(
                    'flags' => LightnCandy::FLAG_ADVARNAME,
                    'helpers' => Array('test_join'),
                ),
                'expected' => "Can not find custom helper function defination foo() !",
            ),
            Array(
                'template' => '{{1 + 2}}',
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                    'helpers' => Array('test_join'),
                ),
                'expected' => "Wrong variable naming as '+' in {{1 + 2}} ! You should wrap ! \" # % & ' * + , ; < = > { | } ~ into [ ]",
            ),
            Array(
                'template' => '{{> (foo) bar}}',
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                ),
                'expected' => Array(
                    "Can not find custom helper function defination foo() !",
                    "You use dynamic partial name as '(foo)', this only works with option FLAG_RUNTIMEPARTIAL enabled",
                )
            ),
            Array(
                'template' => '{{{{#foo}}}',
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                ),
                'expected' => Array(
                    'Bad token {{{{#foo}}} ! Do you mean {{{{#foo}}}} ?',
                    'Wrong raw block begin with {{{{#foo}}} ! Remove "#" to fix this issue.',
                    'Unclosed token {{{{foo}}}} !!',
                )
            ),
            Array(
                'template' => '{{{{foo}}}} {{ {{{{/bar}}}}',
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                ),
                'expected' => Array(
                    'Unclosed token {{{{foo}}}} !!',
                )
            ),
            Array(
                'template' => '{{foo (foo (foo 1 2) 3))}}',
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                     'helpers' => Array(
                         'foo' => function () {
                             return;
                         }
                     )
                ),
                'expected' => Array(
                    'Unexcepted \')\' in expression \'foo (foo (foo 1 2) 3))\' !!',
                )
            ),
            Array(
                'template' => '{{{{foo}}}} {{ {{{{#foo}}}}',
                'options' => Array(
                    'flags' => LightnCandy::FLAG_HANDLEBARSJS,
                ),
                'expected' => Array(
                    'Unclosed token {{{{foo}}}} !!',
                )
            ),
            Array(
                'template' => '{{else}}',
                'options' => Array(
                    'flags' => LightnCandy::FLAG_ELSE,
                ),
                'expected' => Array(
                    '{{else}} only valid in if, unless, each, and #section context',
                )
            ),
            Array(
                'template' => '{{log}}',
                'expected' => Array(
                    'No argument after {{log}} !',
                )
            ),
            Array(
                'template' => '{{#*inline test}}{{/inline}}',
                'expected' => Array(
                    'Do not support {{#*inline test}}, you should do compile with LightnCandy::FLAG_RUNTIMEPARTIAL flag',
                )
            ),
            Array(
                'template' => '{{#*help me}}{{/help}}',
                'options' => Array(
                    'flags' => LightnCandy::FLAG_RUNTIMEPARTIAL,
                ),
                'expected' => Array(
                    'Do not support {{#*help me}}, now we only support {{#*inline "partialName"}}template...{{/inline}}'
                )
            ),
            Array(
                'template' => '{{#*inline}}{{/inline}}',
                'options' => Array(
                    'flags' => LightnCandy::FLAG_RUNTIMEPARTIAL,
                ),
                'expected' => Array(
                    'Error in {{#*inline}}: inline require 1 argument for partial name!',
                )
            ),
            Array(
                'template' => '{{#>foo}}bar',
                'options' => Array(
                    'flags' => LightnCandy::FLAG_RUNTIMEPARTIAL,
                ),
                'expected' => Array(
                    'Unclosed token {{#>foo}} !!',
                )
            ),
            Array(
                'template' => '{{ #2 }}',
                'options' => Array(
                    'flags' => LightnCandy::FLAG_BESTPERFORMANCE,
                ),
                'expected' => Array(
                    'Unclosed token {{#2}} !!',
                )
            ),
            Array(
                'template' => '{{foo a=b}}',
                'options' => Array(
                    'flags' => LightnCandy::FLAG_ADVARNAME,
                ),
                'expected' => Array(
                    "Wrong variable naming as 'a=b' in {{foo a=b}} ! If you try to use foo=bar param, you should enable LightnCandy::FLAG_NAMEDARG !",
                )
            ),
        );

        return array_map(function($i) {
            if (!isset($i['options'])) {
                $i['options'] = Array('flags' => 0);
            }
            if (!isset($i['options']['flags'])) {
                $i['options']['flags'] = 0;
            }
            if (isset($i['expected']) && !is_array($i['expected'])) {
                $i['expected'] = Array($i['expected']);
            }
            return Array($i);
        }, $errorCases);
    }
}


?>
