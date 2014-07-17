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
            return;
        }

        // This case should be compiled without error
        if (isset($test['pass'])) {
            return;
        }

        $this->fail('This should be failed.'); // Context:' . print_r(LightnCandy::getContext(), true));
    }

    public function errorProvider()
    {
        $errorCases = Array(
             '{{testerr1}}}',
             '{{{testerr2}}',
             '{{{#testerr3}}}',
             '{{{!testerr4}}}',
             '{{{^testerr5}}}',
             '{{{/testerr6}}}',
             Array(
                 'template' => '{{win[ner.test1}}',
                 'options' => Array('flags' => LightnCandy::FLAG_ADVARNAME),
             ),
             Array(
                 'template' => '{{win]ner.test2}}',
                 'options' => Array('flags' => LightnCandy::FLAG_ADVARNAME),
             ),
             Array(
                 'template' => '{{wi[n]ner.test3}}',
                 'options' => Array('flags' => LightnCandy::FLAG_ADVARNAME),
             ),
             Array(
                 'template' => '{{winner].[test4]}}',
                 'options' => Array('flags' => LightnCandy::FLAG_ADVARNAME),
             ),
             Array(
                 'template' => '{{winner[.test5]}}',
                 'options' => Array('flags' => LightnCandy::FLAG_ADVARNAME),
             ),
             Array(
                 'template' => '{{winner.[.test6]}}',
                 'options' => Array('flags' => LightnCandy::FLAG_ADVARNAME),
                 'pass' => true,
             ),
             Array(
                 'template' => '{{winner.[#te.st7]}}',
                 'options' => Array('flags' => LightnCandy::FLAG_ADVARNAME),
                 'pass' => true,
             ),
             Array(
                 'template' => '{{test8}}',
                 'options' => Array('flags' => LightnCandy::FLAG_ADVARNAME),
                 'pass' => true,
             ),
             Array(
                 'template' => '{{test9]}}',
                 'options' => Array('flags' => LightnCandy::FLAG_ADVARNAME),
             ),
             Array(
                 'template' => '{{testA[}}',
                 'options' => Array('flags' => LightnCandy::FLAG_ADVARNAME),
             ),
             Array(
                 'template' => '{{[testB}}',
                 'options' => Array('flags' => LightnCandy::FLAG_ADVARNAME),
             ),
             Array(
                 'template' => '{{]testC}}',
                 'options' => Array('flags' => LightnCandy::FLAG_ADVARNAME),
             ),
             Array(
                 'template' => '{{[testD]}}',
                 'options' => Array('flags' => LightnCandy::FLAG_ADVARNAME),
                 'pass' => true,
             ),
             Array(
                 'template' => '{{te]stE}}',
                 'options' => Array('flags' => LightnCandy::FLAG_ADVARNAME),
             ),
             Array(
                 'template' => '{{tee[stF}}',
                 'options' => Array('flags' => LightnCandy::FLAG_ADVARNAME),
             ),
             Array(
                 'template' => '{{te.e[stG}}',
                 'options' => Array('flags' => LightnCandy::FLAG_ADVARNAME),
             ),
             Array(
                 'template' => '{{te.e]stH}}',
                 'options' => Array('flags' => LightnCandy::FLAG_ADVARNAME),
             ),
             Array(
                 'template' => '{{te.e[st.endI}}',
                 'options' => Array('flags' => LightnCandy::FLAG_ADVARNAME),
             ),
             Array(
                 'template' => '{{te.e]st.endJ}}',
                 'options' => Array('flags' => LightnCandy::FLAG_ADVARNAME),
             ),
             Array(
                 'template' => '{{te.[est].endK}}',
                 'options' => Array('flags' => LightnCandy::FLAG_ADVARNAME),
                 'pass' => true,
             ),
             Array(
                 'template' => '{{te.t[est].endL}}',
                 'options' => Array('flags' => LightnCandy::FLAG_ADVARNAME),
             ),
             Array(
                 'template' => '{{te.t[est]o.endM}}',
                 'options' => Array('flags' => LightnCandy::FLAG_ADVARNAME),
             ),
             Array(
                 'template' => '{{te.[est]o.endN}}',
                 'options' => Array('flags' => LightnCandy::FLAG_ADVARNAME),
             ),
             Array(
                 'template' => '{{te.[e.st].endO}}',
                 'options' => Array('flags' => LightnCandy::FLAG_ADVARNAME),
                 'pass' => true,
             ),
             Array(
                 'template' => '{{te.[e.s[t].endP}}',
                 'options' => Array('flags' => LightnCandy::FLAG_ADVARNAME),
                 'pass' => true,
             ),
             Array(
                 'template' => '{{te.[e[s.t].endQ}}',
                 'options' => Array('flags' => LightnCandy::FLAG_ADVARNAME),
                 'pass' => true,
             ),
             Array(
                 'template' => '{{helper}}',
                 'options' => Array('helpers' => Array(
                     'helper' => Array('bad input'),
                 )),
             ),
             '<ul>{{#each item}}<li>{{name}}</li>',
             'issue63: {{test_join}} Test! {{this}} {{/test_join}}',
             '{{../foo}}',
        );

        return array_map(function($i) {
            if (!is_array($i)) {
                $i = Array('template' => $i);
            }
            if (!isset($i['options'])) {
                $i['options'] = Array('flags' => 0);
            }
            $i['options']['flags'] = $i['options']['flags'] | LightnCandy::FLAG_ERROR_EXCEPTION;
            return Array($i);
        }, $errorCases);
    }
}


?>
