<?php

require_once('src/lightncandy.php');
require_once('tests/helpers_for_test.php');

$tmpdir = sys_get_temp_dir();

class errorTest extends PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider errorProvider
     */
    public function testSpecs($test)
    {
        global $tmpdir;

        try {
            $php = LightnCandy::compile($test['template'], $test['options']);
        } catch (Exception $e) {
            $this->assertEquals($test['expected'], $e->getMessage());
            return;
        }

        // This case should be compiled without error
        if (!isset($test['expected'])) {
            return;
        }

        $this->fail("This should be failed as '{$test['expected']}' !");
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
                 'expected' => 'Wrong variable naming in {{win[ner.test1}}',
             ),
             Array(
                 'template' => '{{win]ner.test2}}',
                 'options' => Array('flags' => LightnCandy::FLAG_ADVARNAME),
                 'expected' => 'Wrong variable naming as \'win]ner.test2\' in {{win]ner.test2}} !',
             ),
             Array(
                 'template' => '{{wi[n]ner.test3}}',
                 'options' => Array('flags' => LightnCandy::FLAG_ADVARNAME),
                 'expected' => 'Wrong variable naming as \'wi[n]ner.test3\' in {{wi[n]ner.test3}} !',
             ),
             Array(
                 'template' => '{{winner].[test4]}}',
                 'options' => Array('flags' => LightnCandy::FLAG_ADVARNAME),
                 'expected' => 'Wrong variable naming as \'winner].[test4]\' in {{winner].[test4]}} !',
             ),
             Array(
                 'template' => '{{winner[.test5]}}',
                 'options' => Array('flags' => LightnCandy::FLAG_ADVARNAME),
                 'expected' => 'Wrong variable naming as \'winner[.test5]\' in {{winner[.test5]}} !',
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
                 'expected' => 'Wrong variable naming as \'test9]\' in {{test9]}} !',
             ),
             Array(
                 'template' => '{{testA[}}',
                 'options' => Array('flags' => LightnCandy::FLAG_ADVARNAME),
                 'expected' => 'Wrong variable naming as \'testA[\' in {{testA[}} !',
             ),
             Array(
                 'template' => '{{[testB}}',
                 'options' => Array('flags' => LightnCandy::FLAG_ADVARNAME),
                 'expected' => 'Wrong variable naming in {{[testB}}',
             ),
             Array(
                 'template' => '{{]testC}}',
                 'options' => Array('flags' => LightnCandy::FLAG_ADVARNAME),
                 'expected' => 'Wrong variable naming as \']testC\' in {{]testC}} !',
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
                 'expected' => 'Wrong variable naming in {{tee[stF}}',
             ),
             Array(
                 'template' => '{{te.e[stG}}',
                 'options' => Array('flags' => LightnCandy::FLAG_ADVARNAME),
                 'expected' => 'Wrong variable naming in {{te.e[stG}}',
             ),
             Array(
                 'template' => '{{te.e]stH}}',
                 'options' => Array('flags' => LightnCandy::FLAG_ADVARNAME),
                 'expected' => 'Wrong variable naming as \'te.e]stH\' in {{te.e]stH}} !',
             ),
             Array(
                 'template' => '{{te.e[st.endI}}',
                 'options' => Array('flags' => LightnCandy::FLAG_ADVARNAME),
                 'expected' => 'Wrong variable naming in {{te.e[st.endI}}',
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
                 'expected' => 'Wrong variable naming as \'te.t[est].endL\' in {{te.t[est].endL}} !',
             ),
             Array(
                 'template' => '{{te.t[est]o.endM}}',
                 'options' => Array('flags' => LightnCandy::FLAG_ADVARNAME),
                 'expected' => 'Wrong variable naming as \'te.t[est]o.endM\' in {{te.t[est]o.endM}} !',
             ),
             Array(
                 'template' => '{{te.[est]o.endN}}',
                 'options' => Array('flags' => LightnCandy::FLAG_ADVARNAME),
                 'expected' => 'Wrong variable naming as \'te.[est]o.endN\' in {{te.[est]o.endN}} !',
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
                 'expected' => 'Unclosed token {{{#each item}}} !!',
             ),
             Array(
                 'template' => 'issue63: {{test_join}} Test! {{this}} {{/test_join}}',
                 'expected' => 'Unexpect token: {{/test_join}} !',
             ),
             Array(
                 'template' => '{{../foo}}',
                 'expected' => 'do not support {{../var}}, you should do compile with LightnCandy::FLAG_PARENT flag',
             ),
             Array(
                 'template' => '{{..}}',
                 'expected' => 'do not support {{../var}}, you should do compile with LightnCandy::FLAG_PARENT flag',
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
                 'expected' => 'do not support name=value in {{a=b}}!',
             ),
             Array(
                 'template' => '{{#foo}}1{{^}}2{{/foo}}',
                 'expected' => 'do not support {{^}}, you should do compile with LightnCandy::FLAG_ELSE flag',
             ),
             Array(
                 'template' => '{{#with a}OK!{{/with}}',
                 'expected' => 'Unclosed token {{{#with a}OK!{{/with}}} !!',
             ),
             Array(
                 'template' => '{{#with items}}OK!{{/with}}',
             ),
             Array(
                 'template' => '{{>not_found}}',
                 'expected' => "can not find partial file for 'not_found', you should set correct basedir and fileext in options",
             ),
             Array(
                 'template' => '{{>tests/test1 foo}}',
                 'expected' => 'Do not support {{>tests/test1 [foo]}}, you should do compile with LightnCandy::FLAG_RUNTIMEPARTIAL flag',
             ),
        );

        return array_map(function($i) {
            if (!isset($i['options'])) {
                $i['options'] = Array('flags' => 0);
            }
            $i['options']['flags'] = $i['options']['flags'] | LightnCandy::FLAG_ERROR_EXCEPTION;
            return Array($i);
        }, $errorCases);
    }
}


?>
