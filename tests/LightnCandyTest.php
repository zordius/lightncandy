<?php
/**
 * Generated by build/gen_test
 */
require_once('src/lightncandy.php');

class LightnCandyTest extends PHPUnit_Framework_TestCase
{
    /**
     * @covers LightnCandy::buildHelperTable
     */
    public function testOn_buildHelperTable() {
        $method = new ReflectionMethod('LightnCandy', 'buildHelperTable');
        $method->setAccessible(true);
        $this->assertEquals(array(), $method->invoke(null,
            array(), array()
        ));
        $this->assertEquals(array('flags' => array('exhlp' => 1)), $method->invoke(null,
            array('flags' => array('exhlp' => 1)), array('helpers' => array('abc'))
        ));
        $this->assertEquals(array('error' => array('Can not find custom helper function defination abc() !'), 'flags' => array('exhlp' => 0)), $method->invoke(null,
            array('error' => array(), 'flags' => array('exhlp' => 0)), array('helpers' => array('abc'))
        ));
        $this->assertEquals(array('flags' => array('exhlp' => 1), 'helpers' => array('LCRun3::raw' => 'LCRun3::raw')), $method->invoke(null,
            array('flags' => array('exhlp' => 1), 'helpers' => array()), array('helpers' => array('LCRun3::raw'))
        ));
        $this->assertEquals(array('flags' => array('exhlp' => 1), 'helpers' => array('test' => 'LCRun3::raw')), $method->invoke(null,
            array('flags' => array('exhlp' => 1), 'helpers' => array()), array('helpers' => array('test' => 'LCRun3::raw'))
        ));
    }
    /**
     * @covers LightnCandy::buildCXFileext
     */
    public function testOn_buildCXFileext() {
        $method = new ReflectionMethod('LightnCandy', 'buildCXFileext');
        $method->setAccessible(true);
        $this->assertEquals(array('.tmpl'), $method->invoke(null,
            array()
        ));
        $this->assertEquals(array('test'), $method->invoke(null,
            array('fileext' => 'test')
        ));
        $this->assertEquals(array('test1'), $method->invoke(null,
            array('fileext' => array('test1'))
        ));
        $this->assertEquals(array('test2', 'test3'), $method->invoke(null,
            array('fileext' => array('test2', 'test3'))
        ));
    }
    /**
     * @covers LightnCandy::buildCXBasedir
     */
    public function testOn_buildCXBasedir() {
        $method = new ReflectionMethod('LightnCandy', 'buildCXBasedir');
        $method->setAccessible(true);
        $this->assertEquals(array(), $method->invoke(null,
            array()
        ));
        $this->assertEquals(array(), $method->invoke(null,
            array('basedir' => array())
        ));
        $this->assertEquals(array('src'), $method->invoke(null,
            array('basedir' => array('src'))
        ));
        $this->assertEquals(array('src'), $method->invoke(null,
            array('basedir' => array('src', 'dir_not_found'))
        ));
        $this->assertEquals(array('src', 'tests'), $method->invoke(null,
            array('basedir' => array('src', 'tests'))
        ));
    }
    /**
     * @covers LightnCandy::getPHPCode
     */
    public function testOn_getPHPCode() {
        $method = new ReflectionMethod('LightnCandy', 'getPHPCode');
        $method->setAccessible(true);
        $this->assertEquals('function($a) {return;}', $method->invoke(null,
            function ($a) {return;}
        ));
        $this->assertEquals('function($a) {return;}', $method->invoke(null,
               function ($a) {return;}
        ));
    }
    /**
     * @covers LightnCandy::handleError
     */
    public function testOn_handleError() {
        $method = new ReflectionMethod('LightnCandy', 'handleError');
        $method->setAccessible(true);
        $this->assertEquals(true, $method->invoke(null,
            array('level' => 1, 'stack' => array('X'), 'flags' => array('errorlog' => 0, 'exception' => 0), 'error' => array())
        ));
        $this->assertEquals(false, $method->invoke(null,
            array('level' => 0, 'error' => array())
        ));
        $this->assertEquals(true, $method->invoke(null,
            array('level' => 0, 'error' => array('some error'), 'flags' => array('errorlog' => 0, 'exception' => 0))
        ));
    }
    /**
     * @covers LightnCandy::getBoolStr
     */
    public function testOn_getBoolStr() {
        $method = new ReflectionMethod('LightnCandy', 'getBoolStr');
        $method->setAccessible(true);
        $this->assertEquals('true', $method->invoke(null,
            1
        ));
        $this->assertEquals('true', $method->invoke(null,
            999
        ));
        $this->assertEquals('false', $method->invoke(null,
            0
        ));
        $this->assertEquals('false', $method->invoke(null,
            -1
        ));
    }
    /**
     * @covers LightnCandy::getFuncName
     */
    public function testOn_getFuncName() {
        $method = new ReflectionMethod('LightnCandy', 'getFuncName');
        $method->setAccessible(true);
        $this->assertEquals('LCRun3::test(', $method->invoke(null,
            array('flags' => array('standalone' => 0, 'debug' => 0)), 'test', ''
        ));
        $this->assertEquals('LCRun3::test2(', $method->invoke(null,
            array('flags' => array('standalone' => 0, 'debug' => 0)), 'test2', ''
        ));
        $this->assertEquals("\$cx['funcs']['test3'](", $method->invoke(null,
            array('flags' => array('standalone' => 1, 'debug' => 0)), 'test3', ''
        ));
        $this->assertEquals('LCRun3::debug(\'abc\', \'test\', ', $method->invoke(null,
            array('flags' => array('standalone' => 0, 'debug' => 1)), 'test', 'abc'
        ));
    }
    /**
     * @covers LightnCandy::getArrayStr
     */
    public function testOn_getArrayStr() {
        $method = new ReflectionMethod('LightnCandy', 'getArrayStr');
        $method->setAccessible(true);
        $this->assertEquals('', $method->invoke(null,
            array()
        ));
        $this->assertEquals('[a]', $method->invoke(null,
            array('a')
        ));
        $this->assertEquals('[a][b][c]', $method->invoke(null,
            array('a', 'b', 'c')
        ));
    }
    /**
     * @covers LightnCandy::getArrayCode
     */
    public function testOn_getArrayCode() {
        $method = new ReflectionMethod('LightnCandy', 'getArrayCode');
        $method->setAccessible(true);
        $this->assertEquals('', $method->invoke(null,
            array()
        ));
        $this->assertEquals("['a']", $method->invoke(null,
            array('a')
        ));
        $this->assertEquals("['a']['b']['c']", $method->invoke(null,
            array('a', 'b', 'c')
        ));
    }
    /**
     * @covers LightnCandy::getVariableNames
     */
    public function testOn_getVariableNames() {
        $method = new ReflectionMethod('LightnCandy', 'getVariableNames');
        $method->setAccessible(true);
        $this->assertEquals(array('array(array($in),array())', array('this')), $method->invoke(null,
            array(null), array('flags'=>array('spvar'=>true))
        ));
        $this->assertEquals(array('array(array($in,$in),array())', array('this', 'this')), $method->invoke(null,
            array(null, null), array('flags'=>array('spvar'=>true))
        ));
        $this->assertEquals(array('array(array(),array(\'a\'=>$in))', array('this')), $method->invoke(null,
            array('a' => null), array('flags'=>array('spvar'=>true))
        ));
    }
    /**
     * @covers LightnCandy::getVariableName
     */
    public function testOn_getVariableName() {
        $method = new ReflectionMethod('LightnCandy', 'getVariableName');
        $method->setAccessible(true);
        $this->assertEquals(array('$in', 'this'), $method->invoke(null,
            array(null), array('flags'=>array('spvar'=>true,'debug'=>0))
        ));
        $this->assertEquals(array('true', 'true'), $method->invoke(null,
            array('true'), array('flags'=>array('spvar'=>true,'debug'=>0)), true
        ));
        $this->assertEquals(array('false', 'false'), $method->invoke(null,
            array('false'), array('flags'=>array('spvar'=>true,'debug'=>0)), true
        ));
        $this->assertEquals(array(2, '2'), $method->invoke(null,
            array('2'), array('flags'=>array('spvar'=>true,'debug'=>0)), true
        ));
        $this->assertEquals(array('((isset($in[\'@index\']) && is_array($in)) ? $in[\'@index\'] : null)', '[@index]'), $method->invoke(null,
            array('@index'), array('flags'=>array('spvar'=>false,'debug'=>0,'prop'=>0,'method'=>0,'mustlok'=>0))
        ));
        $this->assertEquals(array("(isset(\$cx['sp_vars']['index'])?\$cx['sp_vars']['index']:'')", '@index'), $method->invoke(null,
            array('@index'), array('flags'=>array('spvar'=>true,'debug'=>0))
        ));
        $this->assertEquals(array("(isset(\$cx['sp_vars']['key'])?\$cx['sp_vars']['key']:'')", '@key'), $method->invoke(null,
            array('@key'), array('flags'=>array('spvar'=>true,'debug'=>0))
        ));
        $this->assertEquals(array("(isset(\$cx['sp_vars']['first'])?\$cx['sp_vars']['first']:'')", '@first'), $method->invoke(null,
            array('@first'), array('flags'=>array('spvar'=>true,'debug'=>0))
        ));
        $this->assertEquals(array("(isset(\$cx['sp_vars']['last'])?\$cx['sp_vars']['last']:'')", '@last'), $method->invoke(null,
            array('@last'), array('flags'=>array('spvar'=>true,'debug'=>0))
        ));
        $this->assertEquals(array('$cx[\'scopes\'][0]', '@root'), $method->invoke(null,
            array('@root'), array('flags'=>array('spvar'=>true,'debug'=>0))
        ));
        $this->assertEquals(array('\'a\'', '"a"'), $method->invoke(null,
            array('"a"'), array('flags'=>array('spvar'=>true,'debug'=>0))
        ));
        $this->assertEquals(array('((isset($in[\'a\']) && is_array($in)) ? $in[\'a\'] : null)', '[a]'), $method->invoke(null,
            array('a'), array('flags'=>array('spvar'=>true,'debug'=>0,'prop'=>0,'method'=>0,'mustlok'=>0))
        ));
        $this->assertEquals(array('((isset($cx[\'scopes\'][count($cx[\'scopes\'])-1][\'a\']) && is_array($cx[\'scopes\'][count($cx[\'scopes\'])-1])) ? $cx[\'scopes\'][count($cx[\'scopes\'])-1][\'a\'] : null)', '../[a]'), $method->invoke(null,
            array(1,'a'), array('flags'=>array('spvar'=>true,'debug'=>0,'prop'=>0,'method'=>0,'mustlok'=>0))
        ));
        $this->assertEquals(array('((isset($cx[\'scopes\'][count($cx[\'scopes\'])-3][\'a\']) && is_array($cx[\'scopes\'][count($cx[\'scopes\'])-3])) ? $cx[\'scopes\'][count($cx[\'scopes\'])-3][\'a\'] : null)', '../../../[a]'), $method->invoke(null,
            array(3,'a'), array('flags'=>array('spvar'=>true,'debug'=>0,'prop'=>0,'method'=>0,'mustlok'=>0))
        ));
        $this->assertEquals(array('((isset($in[\'id\']) && is_array($in)) ? $in[\'id\'] : null)', 'this.[id]'), $method->invoke(null,
            array(null, 'id'), array('flags'=>array('spvar'=>true,'debug'=>0,'prop'=>0,'method'=>0,'mustlok'=>0))
        ));
        $this->assertEquals(array('LCRun3::v($cx, $in, array(\'id\'))', 'this.[id]'), $method->invoke(null,
            array(null, 'id'), array('flags'=>array('prop'=>true,'spvar'=>true,'debug'=>0,'method'=>0,'mustlok'=>0,'standalone'=>0))
        ));
    }
    /**
     * @covers LightnCandy::getExpression
     */
    public function testOn_getExpression() {
        $method = new ReflectionMethod('LightnCandy', 'getExpression');
        $method->setAccessible(true);
        $this->assertEquals('[a].[b]', $method->invoke(null,
            0, false, array('a', 'b')
        ));
        $this->assertEquals('@root', $method->invoke(null,
            0, true, array()
        ));
        $this->assertEquals('this', $method->invoke(null,
            0, false, null
        ));
        $this->assertEquals('this.[id]', $method->invoke(null,
            0, false, array(null, 'id')
        ));
        $this->assertEquals('@root.[a].[b]', $method->invoke(null,
            0, true, array('a', 'b')
        ));
        $this->assertEquals('../../[a].[b]', $method->invoke(null,
            2, false, array('a', 'b')
        ));
        $this->assertEquals('../[a\'b]', $method->invoke(null,
            1, false, array('a\'b')
        ));
    }
    /**
     * @covers LightnCandy::fixVariable
     */
    public function testOn_fixVariable() {
        $method = new ReflectionMethod('LightnCandy', 'fixVariable');
        $method->setAccessible(true);
        $this->assertEquals(array('this'), $method->invoke(null,
            'this', array('flags' => array('advar' => 0, 'this' => 0))
        ));
        $this->assertEquals(array(null), $method->invoke(null,
            'this', array('flags' => array('advar' => 0, 'this' => 1))
        ));
        $this->assertEquals(array(1, null), $method->invoke(null,
            '../', array('flags' => array('advar' => 0, 'this' => 1, 'parent' => 1), 'usedFeature' => array('parent' => 0))
        ));
        $this->assertEquals(array(1, null), $method->invoke(null,
            '../.', array('flags' => array('advar' => 0, 'this' => 1, 'parent' => 1), 'usedFeature' => array('parent' => 0))
        ));
        $this->assertEquals(array(1, null), $method->invoke(null,
            '../this', array('flags' => array('advar' => 0, 'this' => 1, 'parent' => 1), 'usedFeature' => array('parent' => 0))
        ));
        $this->assertEquals(array(1, 'a'), $method->invoke(null,
            '../a', array('flags' => array('advar' => 0, 'this' => 1, 'parent' => 1), 'usedFeature' => array('parent' => 0))
        ));
        $this->assertEquals(array(2, 'a', 'b'), $method->invoke(null,
            '../../a.b', array('flags' => array('advar' => 0, 'this' => 0, 'parent' => 1), 'usedFeature' => array('parent' => 0))
        ));
        $this->assertEquals(array(2, '[a]', 'b'), $method->invoke(null,
            '../../[a].b', array('flags' => array('advar' => 0, 'this' => 0, 'parent' => 1), 'usedFeature' => array('parent' => 0))
        ));
        $this->assertEquals(array(2, 'a', 'b'), $method->invoke(null,
            '../../[a].b', array('flags' => array('advar' => 1, 'this' => 0, 'parent' => 1), 'usedFeature' => array('parent' => 0))
        ));
        $this->assertEquals(array('"a.b"'), $method->invoke(null,
            '"a.b"', array('flags' => array('advar' => 1, 'this' => 0, 'parent' => 1), 'usedFeature' => array('parent' => 0))
        ));
        $this->assertEquals(array(null, 'id'), $method->invoke(null,
            'this.id', array('flags' => array('advar' => 1, 'this' => 1, 'parent' => 1), 'usedFeature' => array('parent' => 0))
        ));
    }
    /**
     * @covers LightnCandy::parseTokenArgs
     */
    public function testOn_parseTokenArgs() {
        $method = new ReflectionMethod('LightnCandy', 'parseTokenArgs');
        $method->setAccessible(true);
        $this->assertEquals(array(false, array(array(null))), $method->invoke(null,
            array(0,0,0,0,0,0,''), array('flags' => array('advar' => 0, 'this' => 1, 'namev' => 0))
        ));
        $this->assertEquals(array(true, array(array(null))), $method->invoke(null,
            array(0,0,0,'{{{',0,0,''), array('flags' => array('advar' => 0, 'this' => 1, 'namev' => 0))
        ));
        $this->assertEquals(array(false, array(array('a'))), $method->invoke(null,
            array(0,0,0,0,0,0,'a'), array('flags' => array('advar' => 0, 'this' => 1, 'namev' => 0))
        ));
        $this->assertEquals(array(false, array(array('a'), array('b'))), $method->invoke(null,
            array(0,0,0,0,0,0,'a  b'), array('flags' => array('advar' => 0, 'this' => 1, 'namev' => 0))
        ));
        $this->assertEquals(array(false, array(array('a'), array('"b'), array('c"'))), $method->invoke(null,
            array(0,0,0,0,0,0,'a "b c"'), array('flags' => array('advar' => 0, 'this' => 1, 'namev' => 0))
        ));
        $this->assertEquals(array(false, array(array('a'), array('"b c"'))), $method->invoke(null,
            array(0,0,0,0,0,0,'a "b c"'), array('flags' => array('advar' => 1, 'this' => 1, 'namev' => 0))
        ));
        $this->assertEquals(array(false, array(array('a'), array('[b'), array('c]'))), $method->invoke(null,
            array(0,0,0,0,0,0,'a [b c]'), array('flags' => array('advar' => 0, 'this' => 1, 'namev' => 0))
        ));
        $this->assertEquals(array(false, array(array('a'), array('[b'), array('c]'))), $method->invoke(null,
            array(0,0,0,0,0,0,'a [b c]'), array('flags' => array('advar' => 0, 'this' => 1, 'namev' => 1))
        ));
        $this->assertEquals(array(false, array(array('a'), array('b c'))), $method->invoke(null,
            array(0,0,0,0,0,0,'a [b c]'), array('flags' => array('advar' => 1, 'this' => 1, 'namev' => 0))
        ));
        $this->assertEquals(array(false, array(array('a'), array('b c'))), $method->invoke(null,
            array(0,0,0,0,0,0,'a [b c]'), array('flags' => array('advar' => 1, 'this' => 1, 'namev' => 1))
        ));
        $this->assertEquals(array(false, array(array('a'), 'q' => array('b c'))), $method->invoke(null,
            array(0,0,0,0,0,0,'a q=[b c]'), array('flags' => array('advar' => 1, 'this' => 1, 'namev' => 1))
        ));
        $this->assertEquals(array(false, array(array('a'), array('q=[b c'))), $method->invoke(null,
            array(0,0,0,0,0,0,'a [q=[b c]'), array('flags' => array('advar' => 1, 'this' => 1, 'namev' => 1))
        ));
        $this->assertEquals(array(false, array(array('a'), 'q' => array('[b'), array('c]'))), $method->invoke(null,
            array(0,0,0,0,0,0,'a q=[b c]'), array('flags' => array('advar' => 0, 'this' => 1, 'namev' => 1))
        ));
        $this->assertEquals(array(false, array(array('a'), 'q' => array('b'), array('c'))), $method->invoke(null,
            array(0,0,0,0,0,0,'a [q]=b c'), array('flags' => array('advar' => 0, 'this' => 1, 'namev' => 1))
        ));
        $this->assertEquals(array(false, array(array('a'), 'q' => array('"b c"'))), $method->invoke(null,
            array(0,0,0,0,0,0,'a q="b c"'), array('flags' => array('advar' => 1, 'this' => 1, 'namev' => 1))
        ));
        $this->assertEquals(array(false, array(array('(foo bar)'))), $method->invoke(null,
            array(0,0,0,0,0,0,'(foo bar)'), array('flags' => array('advar' => 1, 'this' => 1, 'namev' => 1))
        ));
        $this->assertEquals(array(false, array(array('"!=="'))), $method->invoke(null,
            array(0,0,0,0,0,0,'"!=="'), array('flags' => array('namev' => 1))
        ));
    }
    /**
     * @covers LightnCandy::tokenString
     */
    public function testOn_tokenString() {
        $method = new ReflectionMethod('LightnCandy', 'tokenString');
        $method->setAccessible(true);
        $this->assertEquals('b', $method->invoke(null,
            array(0, 'a', 'b', 'c'), 1
        ));
        $this->assertEquals('c', $method->invoke(null,
            array(0, 'a', 'b', 'c', 'd', 'e')
        ));
    }
    /**
     * @covers LightnCandy::validateStartEnd
     */
    public function testOn_validateStartEnd() {
        $method = new ReflectionMethod('LightnCandy', 'validateStartEnd');
        $method->setAccessible(true);
        $this->assertEquals(null, $method->invoke(null,
            array_fill(0, 9, ''), array(), true
        ));
        $this->assertEquals(true, $method->invoke(null,
            range(0, 8), array(), true
        ));
    }
    /**
     * @covers LightnCandy::validateOperations
     */
    public function testOn_validateOperations() {
        $method = new ReflectionMethod('LightnCandy', 'validateOperations');
        $method->setAccessible(true);
        $this->assertEquals(null, $method->invoke(null,
            array(0, 0, 0, 0, 0, ''), array(), array()
        ));
        $this->assertEquals(2, $method->invoke(null,
            array(0, 0, 0, 0, 0, '^', '...'), array('usedFeature' => array('isec' => 1), 'level' => 0), array(array('foo'))
        ));
        $this->assertEquals(3, $method->invoke(null,
            array(0, 0, 0, 0, 0, '!', '...'), array('usedFeature' => array('comment' => 2)), array()
        ));
        $this->assertEquals(true, $method->invoke(null,
            array(0, 0, 0, 0, 0, '/'), array('stack' => array(1), 'level' => 1), array()
        ));
        $this->assertEquals(4, $method->invoke(null,
            array(0, 0, 0, 0, 0, '#', '...'), array('usedFeature' => array('sec' => 3), 'level' => 0), array(array('x'))
        ));
        $this->assertEquals(5, $method->invoke(null,
            array(0, 0, 0, 0, 0, '#', '...'), array('usedFeature' => array('if' => 4), 'level' => 0), array(array('if'))
        ));
        $this->assertEquals(6, $method->invoke(null,
            array(0, 0, 0, 0, 0, '#', '...'), array('usedFeature' => array('with' => 5), 'level' => 0, 'flags' => array('with' => 1)), array(array('with'))
        ));
        $this->assertEquals(7, $method->invoke(null,
            array(0, 0, 0, 0, 0, '#', '...'), array('usedFeature' => array('each' => 6), 'level' => 0), array(array('each'))
        ));
        $this->assertEquals(8, $method->invoke(null,
            array(0, 0, 0, 0, 0, '#', '...'), array('usedFeature' => array('unless' => 7), 'level' => 0), array(array('unless'))
        ));
        $this->assertEquals(9, $method->invoke(null,
            array(0, 0, 0, 0, 0, '#', '...'), array('blockhelpers' => array('abc' => ''), 'usedFeature' => array('bhelper' => 8), 'level' => 0), array(array('abc'))
        ));
        $this->assertEquals(10, $method->invoke(null,
            array(0, 0, 0, 0, 0, ' ', '...'), array('usedFeature' => array('delimiter' => 9), 'level' => 0), array()
        ));
        $this->assertEquals(11, $method->invoke(null,
            array(0, 0, 0, 0, 0, '#', '...'), array('hbhelpers' => array('abc' => ''), 'usedFeature' => array('hbhelper' => 10), 'level' => 0), array(array('abc'))
        ));
        $this->assertEquals(true, $method->invoke(null,
            array(0, 0, 0, 0, 0, '>', '...'), array('basedir' => array('.'), 'fileext' => array('.tmpl'), 'usedFeature' => array('unless' => 7, 'partial' => 7), 'level' => 0, 'flags' => array('skippartial' => 0)), array('test')
        ));
    }
    /**
     * @covers LightnCandy::addUsageCount
     */
    public function testOn_addUsageCount() {
        $method = new ReflectionMethod('LightnCandy', 'addUsageCount');
        $method->setAccessible(true);
        $this->assertEquals(1, $method->invoke(null,
            array('usedCount' => array('test' => array())), 'test', 'testname'
        ));
        $this->assertEquals(3, $method->invoke(null,
            array('usedCount' => array('test' => array('testname' => 2))), 'test', 'testname'
        ));
        $this->assertEquals(5, $method->invoke(null,
            array('usedCount' => array('test' => array('testname' => 2))), 'test', 'testname', 3
        ));
    }
}
?>