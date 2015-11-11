<?php
/*

Copyrights for code authored by Yahoo! Inc. is licensed under the following terms:
MIT License
Copyright (c) 2013-2015 Yahoo! Inc. All Rights Reserved.
Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:
The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.
THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.

Origin: https://github.com/zordius/lightncandy
*/

/**
 * the major file of LightnCandy compiler
 *
 * @package    LightnCandy
 * @author     Zordius <zordius@yahoo-inc.com>
 */

namespace LightnCandy;
use \LightnCandy\Flags;
use \LightnCandy\Runtime;
use \LightnCandy\Context;
use \LightnCandy\Parser;
use \LightnCandy\Token;
use \LightnCandy\Validator;
use \LightnCandy\Partial;
use \LightnCandy\Exporter;

/**
 * LightnCandy major static class
 */
class LightnCandy extends Flags {
    // RegExps
    const EXTENDED_COMMENT_SEARCH = '/{{!--.*?--}}/s';
    const IS_SUBEXP_SEARCH = '/^\(.+\)$/s';

    // Positions of matched token
    const POS_LOTHER = 1;
    const POS_LSPACE = 2;
    const POS_BEGINTAG = 3;
    const POS_LSPACECTL = 4;
    const POS_OP = 5;
    const POS_INNERTAG = 6;
    const POS_RSPACECTL = 7;
    const POS_ENDTAG = 8;
    const POS_RSPACE = 9;
    const POS_ROTHER = 10;

    protected static $lastContext;

    /**
     * Compile handlebars template into PHP code.
     *
     * @param string $template handlebars template string
     * @param array<string,array|string|integer> $options LightnCandy compile time and run time options, default is array('flags' => LightnCandy::FLAG_BESTPERFORMANCE)
     *
     * @return string|false Compiled PHP code when successed. If error happened and compile failed, return false.
     */
    public static function compile($template, $options = array('flags' => self::FLAG_BESTPERFORMANCE)) {
        $context = Context::create($options);

        if (static::handleError($context)) {
            return false;
        }

        $template = static::stripExtendedComments($template);

        // Do first time scan to find out used feature, detect template error.
        Parser::setDelimiter($context);
        Validator::verify($context, $template);

        if (static::handleError($context)) {
            return false;
        }

        $context['scan'] = false;

        // Do PHP code generation.
        Parser::setDelimiter($context);

        // Handle dynamic partials
        Partial::handleDynamicPartial($context);

        $code = static::compileTemplate($context, static::escapeTemplate($template));

        // return false when fatal error
        if (static::handleError($context)) {
            return false;
        }

        // Or, return full PHP render codes as string
        return static::composePHPRender($context, $code);
    }

    /**
     * Strip extended comments {{!-- .... --}}
     *
     * @param string $template handlebars template string
     *
     * @return string Stripped template
     *
     * @expect 'abc' when input 'abc'
     * @expect 'abc{{!}}cde' when input 'abc{{!}}cde'
     * @expect 'abc{{! }}cde' when input 'abc{{!----}}cde'
     */
    protected static function stripExtendedComments($template) {
        return preg_replace(static::EXTENDED_COMMENT_SEARCH, '{{! }}', $template);
    }

    /**
     * Escape template
     *
     * @param string $template handlebars template string
     *
     * @return string Escaped template
     *
     * @expect 'abc' when input 'abc'
     * @expect 'a\\\\bc' when input 'a\bc'
     * @expect 'a\\\'bc' when input 'a\'bc'
     */
    protected static function escapeTemplate($template) {
        return addcslashes(addcslashes($template, '\\'), "'");
    }

    /**
     * Compile template into PHP code (internal method)
     *
     * @param array<string,array|string|integer> $context Current context
     * @param string $template handlebars template
     * @param string $partial partial name when $template is come from the template
     *
     * @return string generated PHP code
     */
    protected static function compileTemplate(&$context, $template, $partial = '') {
        // Check for recursive partial
        if ($partial && !$context['flags']['runpart']) {
            $context['partialStack'][] = $partial;
            $diff = count($context['partialStack']) - count(array_unique($context['partialStack']));
            if ($diff > 1) {
                $context['error'][] = "Skip rendering partial '$partial' again due to recursive detected";
                return '';
            }
            if ($diff) {
                $context['error'][] = 'I found recursive partial includes as the path: ' . implode(' -> ', $context['partialStack']) . '! You should fix your template or compile with LightnCandy::FLAG_RUNTIMEPARTIAL flag.';
            }
        }

        $code = '';
        while (preg_match($context['tokens']['search'], $template, $matches)) {
            // Skip a token when it is slash escaped
            if ($context['flags']['slash'] && ($matches[self::POS_LSPACE] === '') && preg_match('/^(.*?)(\\\\+)$/s', $matches[self::POS_LOTHER], $escmatch)) {
                if (strlen($escmatch[2]) % 4) {
                    $code .= substr($matches[self::POS_LOTHER], 0, -2) . $context['tokens']['startchar'];
                    $matches[self::POS_BEGINTAG] = substr($matches[self::POS_BEGINTAG], 1);
                    $template = implode('', array_slice($matches, self::POS_BEGINTAG));
                    continue;
                } else {
                    $matches[self::POS_LOTHER] = $escmatch[1] . str_repeat('\\', strlen($escmatch[2]) / 2);
                }
            }

            $context['tokens']['current']++;
            $tmpl = static::compileToken($matches, $context);
            if ($tmpl == $context['ops']['seperator']) {
                $tmpl = '';
            } else {
                $tmpl = "'$tmpl'";
            }
            $code .= "{$matches[self::POS_LOTHER]}{$matches[self::POS_LSPACE]}$tmpl";
            $template = "{$matches[self::POS_RSPACE]}{$matches[self::POS_ROTHER]}";
        }

        if ($partial && !$context['flags']['runpart']) {
            array_pop($context['partialStack']);
        }

        return "$code$template";
    }

    /**
     * Compose LightnCandy render codes for include()
     *
     * @param array<string,array|string|integer> $context Current context
     * @param string $code generated PHP code
     *
     * @return string Composed PHP code
     */
    protected static function composePHPRender($context, $code) {
        $flagJStrue = static::getBoolStr($context['flags']['jstrue']);
        $flagJSObj = static::getBoolStr($context['flags']['jsobj']);
        $flagSPVar = static::getBoolStr($context['flags']['spvar']);
        $flagProp = static::getBoolStr($context['flags']['prop']);
        $flagMethod = static::getBoolStr($context['flags']['method']);
        $flagLambda = static::getBoolStr($context['flags']['lambda']);
        $flagMustlok = static::getBoolStr($context['flags']['mustlok']);
        $flagMustlam = static::getBoolStr($context['flags']['mustlam']);
        $flagEcho = static::getBoolStr($context['flags']['echo']);

        $libstr = Exporter::runtime($context);
        $constants = Exporter::constants($context);
        $helpers = Exporter::helpers($context);
        $bhelpers = Exporter::helpers($context, 'blockhelpers');
        $hbhelpers = Exporter::helpers($context, 'hbhelpers');
        $debug = Runtime::DEBUG_ERROR_LOG;
        $phpstart = $context['flags']['bare'] ? '' : "<?php use {$context['runtime']} as LR;\n";
        $phpend = $context['flags']['bare'] ? ';' : "\n?>";

        // Return generated PHP code string.
        return "{$phpstart}return function (\$in, \$debugopt = $debug) {
    \$cx = array(
        'flags' => array(
            'jstrue' => $flagJStrue,
            'jsobj' => $flagJSObj,
            'spvar' => $flagSPVar,
            'prop' => $flagProp,
            'method' => $flagMethod,
            'lambda' => $flagLambda,
            'mustlok' => $flagMustlok,
            'mustlam' => $flagMustlam,
            'echo' => $flagEcho,
            'debug' => \$debugopt,
        ),
        'constants' => $constants,
        'helpers' => $helpers,
        'blockhelpers' => $bhelpers,
        'hbhelpers' => $hbhelpers,
        'partials' => array({$context['partialCode']}),
        'scopes' => array(),
        'sp_vars' => array('root' => \$in),
        'runtime' => '{$context['runtime']}',
$libstr
    );
    {$context['renderex']}
    {$context['ops']['op_start']}'$code'{$context['ops']['op_end']}
}$phpend";
    }

    /**
     * Internal method used by compile(). Handle exists error and return error status.
     *
     * @param array<string,array|string|integer> $context Current context of compiler progress.
     *
     * @throws \Exception
     * @return boolean True when error detected
     *
     * @expect true when input array('level' => 1, 'stack' => array('X'), 'flags' => array('errorlog' => 0, 'exception' => 0), 'error' => array(), 'rawblock' => 0)
     * @expect false when input array('level' => 0, 'error' => array())
     * @expect true when input array('level' => 0, 'error' => array('some error'), 'flags' => array('errorlog' => 0, 'exception' => 0))
     */
    protected static function handleError(&$context) {
        if ($context['level'] > 0) {
            $token = array_pop($context['stack']);
            $context['error'][] = 'Unclosed token ' . ($context['rawblock'] ? "{{{{{$token}}}}}" : "{{#{$token}}}") . ' !!';
        }

        static::$lastContext = $context;

        if (count($context['error'])) {
            if ($context['flags']['errorlog']) {
                error_log(implode("\n", $context['error']));
            }
            if ($context['flags']['exception']) {
                throw new \Exception(implode("\n", $context['error']));
            }
            return true;
        }
        return false;
    }

    /**
     * Internal method used by compile(). Return 'true' or 'false' string.
     *
     * @param integer $v value
     *
     * @return string 'true' when the value larger then 0
     *
     * @expect 'true' when input 1
     * @expect 'true' when input 999
     * @expect 'false' when input 0
     * @expect 'false' when input -1
     */
    protected static function getBoolStr($v) {
        return ($v > 0) ? 'true' : 'false';
    }

    /**
     * Get last compiler context.
     *
     * @return array<string,array|string|integer> Context data
     */
    public static function getContext() {
        return static::$lastContext;
    }

    /**
     * Get a working render function by a string of PHP code. This method may requires php setting allow_url_include=1 and allow_url_fopen=1 , or access right to tmp file system.
     *
     * @param string      $php PHP code
     * @param string|null $tmpDir Optional, change temp directory for php include file saved by prepare() when cannot include PHP code with data:// format.
     * @param boolean     $delete Optional, delete temp php file when set to tru. Default is true, set it to false for debug propose
     *
     * @return Closure|false result of include()
     *
     * @deprecated
     */
    public static function prepare($php, $tmpDir = null, $delete = true) {
        if (!ini_get('allow_url_include') || !ini_get('allow_url_fopen')) {
            if (!is_string($tmpDir) || !is_dir($tmpDir)) {
                $tmpDir = sys_get_temp_dir();
            }
        }

        if (is_dir($tmpDir)) {
            $fn = tempnam($tmpDir, 'lci_');
            if (!$fn) {
                error_log("Can not generate tmp file under $tmpDir!!\n");
                return false;
            }
            if (!file_put_contents($fn, $php)) {
                error_log("Can not include saved temp php code from $fn, you should add $tmpDir into open_basedir!!\n");
                return false;
            }

            $phpfunc = include($fn);

            if ($delete) {
                unlink($fn);
            }

            return $phpfunc;
        }

        return include('data://text/plain,' . urlencode($php));
    }

    /**
     * Internal method used by compile(). Get function name for standalone or none standalone template.
     *
     * @param array<string,array|string|integer> $context Current context of compiler progress.
     * @param string $name base function name
     * @param string $tag original handlabars tag for debug
     *
     * @return string compiled Function name
     *
     * @expect 'LR::test(' when input array('flags' => array('standalone' => 0, 'debug' => 0), 'runtime' => 'Runtime'), 'test', ''
     * @expect 'LR::test2(' when input array('flags' => array('standalone' => 0, 'debug' => 0), 'runtime' => 'Runtime'), 'test2', ''
     * @expect "\$cx['funcs']['test3'](" when input array('flags' => array('standalone' => 1, 'debug' => 0), 'runtime' => 'Runtime'), 'test3', ''
     * @expect 'LR::debug(\'abc\', \'test\', ' when input array('flags' => array('standalone' => 0, 'debug' => 1), 'runtime' => 'Runtime'), 'test', 'abc'
     */
    protected static function getFuncName(&$context, $name, $tag) {
        static::addUsageCount($context, 'runtime', $name);

        if ($context['flags']['debug'] && ($name != 'miss')) {
            $dbg = "'$tag', '$name', ";
            $name = 'debug';
            static::addUsageCount($context, 'runtime', 'debug');
        } else {
            $dbg = '';
        }

        return $context['flags']['standalone'] ? "\$cx['funcs']['$name']($dbg" : "LR::$name($dbg";
    }

    /**
     * Internal method used by getArrayCode(). Get variable names translated string.
     *
     * @param array<string> $scopes an array of variable names with single quote
     *
     * @return string PHP array names string
     *
     * @expect '' when input array()
     * @expect '[a]' when input array('a')
     * @expect '[a][b][c]' when input array('a', 'b', 'c')
     */
    protected static function getArrayStr($scopes) {
        return count($scopes) ? '[' . implode('][', $scopes) . ']' : '';
    }

    /**
     * Internal method used by getVariableName(). Get variable names translated string.
     *
     * @param array<string> $list an array of variable names.
     *
     * @return string PHP array names string
     *
     * @expect '' when input array()
     * @expect "['a']" when input array('a')
     * @expect "['a']['b']['c']" when input array('a', 'b', 'c')
     */
    protected static function getArrayCode($list) {
        return static::getArrayStr(array_map(function ($v) {
            return "'$v'";
        }, $list));
    }

    /**
     * Internal method used by compile().
     *
     * @param array<array> $vn variable name array.
     * @param array<string,array|string|integer> $context current compile context
     *
     * @return array<string|array> variable names
     *
     * @expect array('array(array($in),array())', array('this')) when input array(null), array('flags'=>array('spvar'=>true))
     * @expect array('array(array($in,$in),array())', array('this', 'this')) when input array(null, null), array('flags'=>array('spvar'=>true))
     * @expect array('array(array(),array(\'a\'=>$in))', array('this')) when input array('a' => null), array('flags'=>array('spvar'=>true))
     */
    protected static function getVariableNames($vn, &$context) {
        $vars = array(array(), array());
        $exps = array();
        foreach ($vn as $i => $v) {
            $V = static::getVariableNameOrSubExpression($v, $context);
            if (is_string($i)) {
                $vars[1][] = "'$i'=>{$V[0]}";
            } else {
                $vars[0][] = $V[0];
            }
            $exps[] = $V[1];
        }
        return array('array(array(' . implode(',', $vars[0]) . '),array(' . implode(',', $vars[1]) . '))', $exps);
    }

    /**
     * Internal method used by compile().
     *
     * @param string $subExpression subExpression to compile
     * @param array<string,array|string|integer> $context current compile context
     * @param boolean $keepCount keep original usage count
     *
     * @return array<string> code representing passed expression
     */
    protected static function compileSubExpression($subExpression, &$context, $keepCount = false) {
        // mock up a token for this expression
        $token = array_fill(self::POS_LOTHER, self::POS_ROTHER, '');

        // strip outer ( ) from subexpression
        $token[self::POS_INNERTAG] = substr($subExpression, 1, -1);
        $oldCount = $context['usedFeature'];
        list(, $vars) = Parser::parse($token, $context);

        // no separator is needed, this code will be used as a function argument
        $origSeperator = $context['ops']['seperator'];
        $context['ops']['seperator'] = '';
        // override $raw, subexpressions are never escaped
        $ret = static::compileCustomHelper($context, $vars, true, true);

        if (!$ret && $context['flags']['lambda']) {
            $ret = static::compileVariable($context, $vars, true);
        }

        $context['ops']['seperator'] = $origSeperator;

        if ($keepCount) {
            $context['usedFeature'] = $oldCount;
        } else {
            $context['usedFeature']['subexp']++;
            // detect handlebars custom helpers.
            if (isset($context['hbhelpers'][$vars[0][0]])) {
                $context['usedFeature']['hbhelper']++;
            } else {
                // detect custom helpers.
                if (isset($context['helpers'][$vars[0][0]])) {
                    $context['usedFeature']['helper']++;
                }
            }
        }

        return array($ret ? $ret : '', $subExpression);
    }

    /**
     * Internal method used by compile().
     *
     * @param array<array|string|integer> $var variable parsed path
     * @param array<array|string|integer> $context current compile context
     *
     * @return array<string> variable names
     */
    protected static function getVariableNameOrSubExpression($var, &$context) {
        if (isset($var[0]) && preg_match(static::IS_SUBEXP_SEARCH, $var[0])) {
            return static::compileSubExpression($var[0], $context, true);
        }
        return static::getVariableName($var, $context);
    }

    /**
     * Internal method used by compile().
     *
     * @param array<array|string|integer> $var variable parsed path
     * @param array<array|string|integer> $context current compile context
     *
     * @return array<string> variable names
     *
     * @expect array('$in', 'this') when input array(null), array('flags'=>array('spvar'=>true,'debug'=>0))
     * @expect array('((isset($in[\'true\']) && is_array($in)) ? $in[\'true\'] : null)', '[true]') when input array('true'), array('flags'=>array('spvar'=>true,'debug'=>0,'prop'=>0,'method'=>0,'mustlok'=>0,'mustlam'=>0, 'lambda'=>0))
     * @expect array('((isset($in[\'false\']) && is_array($in)) ? $in[\'false\'] : null)', '[false]') when input array('false'), array('flags'=>array('spvar'=>true,'debug'=>0,'prop'=>0,'method'=>0,'mustlok'=>0,'mustlam'=>0, 'lambda'=>0))
     * @expect array('true', 'true') when input array(0, 'true'), array('flags'=>array('spvar'=>true,'debug'=>0))
     * @expect array('false', 'false') when input array(0, 'false'), array('flags'=>array('spvar'=>true,'debug'=>0))
     * @expect array('((isset($in[\'2\']) && is_array($in)) ? $in[\'2\'] : null)', '[2]') when input array('2'), array('flags'=>array('spvar'=>true,'debug'=>0,'prop'=>0,'method'=>0,'mustlok'=>0,'mustlam'=>0, 'lambda'=>0))
     * @expect array('2', '2') when input array(0, '2'), array('flags'=>array('spvar'=>true,'debug'=>0,'prop'=>0,'method'=>0))
     * @expect array('((isset($in[\'@index\']) && is_array($in)) ? $in[\'@index\'] : null)', '[@index]') when input array('@index'), array('flags'=>array('spvar'=>false,'debug'=>0,'prop'=>0,'method'=>0,'mustlok'=>0,'mustlam'=>0, 'lambda'=>0))
     * @expect array("((isset(\$cx['sp_vars']['index']) && is_array(\$cx['sp_vars'])) ? \$cx['sp_vars']['index'] : null)", '@[index]') when input array('@index'), array('flags'=>array('spvar'=>true,'debug'=>0,'prop'=>0,'method'=>0,'mustlok'=>0,'mustlam'=>0, 'lambda'=>0))
     * @expect array("((isset(\$cx['sp_vars']['key']) && is_array(\$cx['sp_vars'])) ? \$cx['sp_vars']['key'] : null)", '@[key]') when input array('@key'), array('flags'=>array('spvar'=>true,'debug'=>0,'prop'=>0,'method'=>0,'mustlok'=>0,'mustlam'=>0, 'lambda'=>0))
     * @expect array("((isset(\$cx['sp_vars']['first']) && is_array(\$cx['sp_vars'])) ? \$cx['sp_vars']['first'] : null)", '@[first]') when input array('@first'), array('flags'=>array('spvar'=>true,'debug'=>0,'prop'=>0,'method'=>0,'mustlok'=>0,'mustlam'=>0, 'lambda'=>0))
     * @expect array("((isset(\$cx['sp_vars']['last']) && is_array(\$cx['sp_vars'])) ? \$cx['sp_vars']['last'] : null)", '@[last]') when input array('@last'), array('flags'=>array('spvar'=>true,'debug'=>0,'prop'=>0,'method'=>0,'mustlok'=>0,'mustlam'=>0, 'lambda'=>0))
     * @expect array('((isset($in[\'"a"\']) && is_array($in)) ? $in[\'"a"\'] : null)', '["a"]') when input array('"a"'), array('flags'=>array('spvar'=>true,'debug'=>0,'prop'=>0,'method'=>0,'mustlok'=>0,'mustlam'=>0, 'lambda'=>0))
     * @expect array('"a"', '"a"') when input array(0, '"a"'), array('flags'=>array('spvar'=>true,'debug'=>0))
     * @expect array('((isset($in[\'a\']) && is_array($in)) ? $in[\'a\'] : null)', '[a]') when input array('a'), array('flags'=>array('spvar'=>true,'debug'=>0,'prop'=>0,'method'=>0,'mustlok'=>0,'mustlam'=>0, 'lambda'=>0))
     * @expect array('((isset($cx[\'scopes\'][count($cx[\'scopes\'])-1][\'a\']) && is_array($cx[\'scopes\'][count($cx[\'scopes\'])-1])) ? $cx[\'scopes\'][count($cx[\'scopes\'])-1][\'a\'] : null)', '../[a]') when input array(1,'a'), array('flags'=>array('spvar'=>true,'debug'=>0,'prop'=>0,'method'=>0,'mustlok'=>0,'mustlam'=>0, 'lambda'=>0))
     * @expect array('((isset($cx[\'scopes\'][count($cx[\'scopes\'])-3][\'a\']) && is_array($cx[\'scopes\'][count($cx[\'scopes\'])-3])) ? $cx[\'scopes\'][count($cx[\'scopes\'])-3][\'a\'] : null)', '../../../[a]') when input array(3,'a'), array('flags'=>array('spvar'=>true,'debug'=>0,'prop'=>0,'method'=>0,'mustlok'=>0,'mustlam'=>0, 'lambda'=>0))
     * @expect array('((isset($in[\'id\']) && is_array($in)) ? $in[\'id\'] : null)', 'this.[id]') when input array(null, 'id'), array('flags'=>array('spvar'=>true,'debug'=>0,'prop'=>0,'method'=>0,'mustlok'=>0,'mustlam'=>0, 'lambda'=>0))
     * @expect array('LR::v($cx, $in, array(\'id\'))', 'this.[id]') when input array(null, 'id'), array('flags'=>array('prop'=>true,'spvar'=>true,'debug'=>0,'method'=>0,'mustlok'=>0,'mustlam'=>0, 'lambda'=>0,'standalone'=>0), 'runtime' => 'Runtime')
     */
    protected static function getVariableName($var, &$context) {
        if (isset($var[0]) && ($var[0] === 0)) {
            return array($var[1], preg_replace('/\'(.*)\'/', '$1', $var[1]));
        }

        $levels = 0;
        $base = '$in';
        $spvar = false;

        if (isset($var[0])) {
            // trace to parent
            if (!is_string($var[0]) && is_int($var[0])) {
                $levels = array_shift($var);
            }
        }

        if (isset($var[0])) {
            // handle @root, @index, @key, @last, etc
            if ($context['flags']['spvar']) {
                if (substr($var[0], 0, 1) === '@') {
                    $spvar = true;
                    $base = "\$cx['sp_vars']";
                    $var[0] = substr($var[0], 1);
                }
            }
        }

        // change base when trace to parent
        if ($levels > 0) {
            if ($spvar) {
                $base .= str_repeat("['_parent']", $levels);
            } else {
                $base = "\$cx['scopes'][count(\$cx['scopes'])-$levels]";
            }
        }

        // Generate normalized expression for debug
        $exp = static::getExpression($levels, $spvar, $var);

        if ((count($var) == 0) || (is_null($var[0]) && (count($var) == 1))) {
            return array($base, $exp);
        }

        if (is_null($var[0])) {
            array_shift($var);
        }

        // To support recursive context lookup, instance properties + methods and lambdas
        // the only way is using slower rendering time variable resolver.
        if ($context['flags']['prop'] || $context['flags']['method'] || $context['flags']['mustlok'] || $context['flags']['mustlam'] || $context['flags']['lambda']) {
            return array(static::getFuncName($context, 'v', $exp) . "\$cx, $base, array(" . implode(',', array_map(function ($V) {
                return "'$V'";
            }, $var)) . '))', $exp);
        }

        $n = static::getArrayCode($var);
        array_pop($var);
        $p = count($var) ? static::getArrayCode($var) : '';

        return array("((isset($base$n) && is_array($base$p)) ? $base$n : " . ($context['flags']['debug'] ? (static::getFuncName($context, 'miss', '') . "\$cx, '$exp')") : 'null' ) . ')', $exp);
    }

    /**
     * Internal method used by compile().
     *
     * @param integer $levels trace N levels top parent scope
     * @param boolean $spvar is the path start with @ or not
     * @param array<string|integer> $var variable parsed path
     *
     * @return string normalized expression for debug display
     *
     * @expect '[a].[b]' when input 0, false, array('a', 'b')
     * @expect '@[root]' when input 0, true, array('root')
     * @expect 'this' when input 0, false, null
     * @expect 'this.[id]' when input 0, false, array(null, 'id')
     * @expect '@[root].[a].[b]' when input 0, true, array('root', 'a', 'b')
     * @expect '../../[a].[b]' when input 2, false, array('a', 'b')
     * @expect '../[a\'b]' when input 1, false, array('a\'b')
     */
    protected static function getExpression($levels, $spvar, $var) {
        return ($spvar ? '@' : '') . str_repeat('../', $levels) . ((is_array($var) && count($var)) ? implode('.', array_map(function($v) {
            return is_null($v) ? 'this' : "[$v]";
        }, $var)) : 'this');
    }

    /**
     * Internal method used by compileToken(). Modify $token when spacing rules matched.
     *
     * @param array<string> $token detected handlebars {{ }} token
     * @param array<array|string|integer> $vars parsed arguments list
     * @param array<string,array|string|integer> $context current compile context
     *
     * @return string|null Return compiled code segment for the token
     */
    public static function handleSpacing(&$token, $vars, &$context) {
        if ($context['flags']['noind']) {
            return;
        }

        // left line change detection
        $lsp = preg_match('/^(.*)(\\r?\\n)([ \\t]*?)$/s', $token[self::POS_LSPACE], $lmatch);
        $ind = $lsp ? $lmatch[3] : $token[self::POS_LSPACE];

        // right line change detection
        $rsp = preg_match('/^([ \\t]*?)(\\r?\\n)(.*)$/s', $token[self::POS_RSPACE], $rmatch);
        $st = true;

        // setup ahead flag
        $ahead = $context['tokens']['ahead'];
        $context['tokens']['ahead'] = preg_match('/^[^\n]*{{/s', $token[self::POS_RSPACE] . $token[self::POS_ROTHER]);

        // reset partial indent
        $context['tokens']['partialind'] = '';

        // same tags in the same line , not standalone
        if (!$lsp && $ahead) {
            $st = false;
        }

        // Do not need standalone detection for these tags
        if (!$token[self::POS_OP] || ($token[self::POS_OP] === '&')) {
            if (!$context['flags']['else'] || !isset($vars[0][0]) || ($vars[0][0] !== 'else')) {
                $st = false;
            }
        }

        // not standalone because other things in the same line ahead
        if ($token[self::POS_LOTHER] && !$token[self::POS_LSPACE]) {
            $st = false;
        }

        // not standalone because other things in the same line behind
        if ($token[self::POS_ROTHER] && !$token[self::POS_RSPACE]) {
            $st = false;
        }

        if ($st && (($lsp && $rsp) // both side cr
            || ($rsp && !$token[self::POS_LOTHER]) // first line without left
            || ($lsp && ($context['tokens']['current'] == $context['tokens']['count']) && !$token[self::POS_ROTHER]) // final line
           )) {
            // handle partial
            if ((!$context['flags']['noind']) && ($token[self::POS_OP] === '>')) {
                $context['tokens']['partialind'] = $ind;
            }
            $token[self::POS_LSPACE] = (isset($lmatch[2]) ? ($lmatch[1] . $lmatch[2]) : '');
            $token[self::POS_RSPACE] = isset($rmatch[3]) ? $rmatch[3] : '';
        }
    }

    /**
     * Internal method used by compile(). Return compiled PHP code partial for a handlebars token.
     *
     * @param array<string> $token detected handlebars {{ }} token
     * @param array<string,array|string|integer> $context current compile context
     *
     * @return string Return compiled code segment for the token
     */
    public static function compileToken(&$token, &$context) {
        list($raw, $vars) = Parser::parse($token, $context);

        // Do not touch the tag, keep it as is.
        if ($raw === -1) {
            return ".'" . token::toString($token) . "'.";
        }

        $named = count(array_diff_key($vars, array_keys(array_keys($vars)))) > 0;

        // Handle spacing (standalone tags, partial indent)
        static::handleSpacing($token, $vars, $context);

        // Handle space control.
        if ($token[self::POS_LSPACECTL]) {
            $token[self::POS_LSPACE] = '';
        }

        if ($token[self::POS_RSPACECTL]) {
            $token[self::POS_RSPACE] = '';
        }

        if ($ret = static::compileSection($token, $context, $vars, $named)) {
            return $ret;
        }

        if (isset($vars[0][0])) {
            if ($ret = static::compileCustomHelper($context, $vars, $raw)) {
                return $ret;
            }
            if ($ret = static::compileElse($context, $vars)) {
                return $ret;
            }
        }

        Validator::noNamedArguments($token, $context, $named, ', maybe you missing the custom helper?');

        return static::compileVariable($context, $vars, $raw);
    }

    /**
     * Internal method used by compile(). Return compiled PHP code partial for a handlebars section token.
     *
     * @param array<string> $token detected handlebars {{ }} token
     * @param array<string,array|string|integer> $context current compile context
     * @param array<array|string|integer> $vars parsed arguments list
     * @param boolean $named is named arguments or not
     *
     * @return string|null Return compiled code segment for the token when the token is section
     */
    protected static function compileSection(&$token, &$context, &$vars, $named) {
        switch ($token[self::POS_OP]) {
            case '>':
                // mustache spec: ignore missing partial
                if (($context['usedFeature']['dynpartial'] === 0) && !isset($context['usedPartial'][$vars[0][0]])) {
                    return $context['ops']['seperator'];
                }
                $p = array_shift($vars);
                if (!isset($vars[0])) {
                    $vars[0] = array();
                }
                $v = static::getVariableNames($vars, $context);
                $tag = ">$p[0] " .implode(' ', $v[1]);
                if ($context['flags']['runpart']) {
                    if (preg_match(static::IS_SUBEXP_SEARCH, $p[0])) {
                        list($p) = static::compileSubExpression($p[0], $context);
                    } else {
                        $p = "'$p[0]'";
                    }
                    $sp = $context['tokens']['partialind'] ? ", '{$context['tokens']['partialind']}'" : '';
                    return $context['ops']['seperator'] . static::getFuncName($context, 'p', $tag) . "\$cx, $p, $v[0]$sp){$context['ops']['seperator']}";
                }
                if ($named || $v[0] !== 'array(array($in),array())') {
                    $context['error'][] = "Do not support {{{$tag}}}, you should do compile with LightnCandy::FLAG_RUNTIMEPARTIAL flag";
                }
                return "{$context['ops']['seperator']}'" . static::compileTemplate($context, preg_replace('/^/m', $context['tokens']['partialind'], $context['usedPartial'][$p[0]]), $p[0]) . "'{$context['ops']['seperator']}";
            case '^':
                // {{^}} means {{else}}
                if (!isset($vars[0][0])) {
                    $vars[0][0] = 'else';
                    $token[self::POS_OP] = '';
                    return;
                }

                // Try to compile as custom helper {{^myHelper}}
                $r = static::compileBlockCustomHelper($context, $vars, true);
                if ($r !== null) {
                    return $r;
                }

                $v = static::getVariableName($vars[0], $context);
                $context['stack'][] = $v[1];
                $context['stack'][] = '^';
                Validator::noNamedArguments($token, $context, $named);
                // Compile to inverted section {{^myVar}}
                return "{$context['ops']['cnd_start']}(" . static::getFuncName($context, 'isec', '^' . $v[1]) . "\$cx, {$v[0]})){$context['ops']['cnd_then']}";
            case '/':
                return static::compileBlockEnd($token, $context, $vars);
            case '!':
            case ' ':
                return $context['ops']['seperator'];
            case '#':
                // Try to compile as custom helper {{#myHelper}}
                $r = static::compileBlockCustomHelper($context, $vars);
                if ($r !== null) {
                    return $r;
                }
                // Compile to section {{#myVar}}
                return static::compileBlockBegin($context, $vars, $token);
        }
    }

    /**
     * Internal method used by compile(). Return compiled PHP code partial for a handlebars block custom helper begin token.
     *
     * @param array<string,array|string|integer> $context current compile context
     * @param array<array|string|integer> $vars parsed arguments list
     * @param boolean $inverted the logic will be inverted
     *
     * @return string|null Return compiled code segment for the token
     */
    protected static function compileBlockCustomHelper(&$context, $vars, $inverted = false) {
        if (!isset($vars[0][0])) {
            return;
        }
        $notHBCH = !isset($context['hbhelpers'][$vars[0][0]]);

        if (!isset($context['blockhelpers'][$vars[0][0]]) && $notHBCH) {
            return;
        }

        $v = static::getVariableName($vars[0], $context);
        $context['stack'][] = $v[1];
        $context['stack'][] = '#';
        $ch = array_shift($vars);
        $inverted = $inverted ? 'true' : 'false';

        static::addUsageCount($context, $notHBCH ? 'blockhelpers' : 'hbhelpers', $ch[0]);
        $v = static::getVariableNames($vars, $context);
        return $context['ops']['seperator'] . static::getFuncName($context, $notHBCH ? 'bch' : 'hbch', ($inverted ? '^' : '#') . implode(' ', $v[1])) . "\$cx, '$ch[0]', {$v[0]}, \$in, $inverted, function(\$cx, \$in) {{$context['ops']['f_start']}";
    }

    /**
     * Internal method used by compile(). Return compiled PHP code partial for a handlebars block end token.
     *
     * @param array<string> $token detected handlebars {{ }} token
     * @param array<string,array|string|integer> $context current compile context
     * @param array<array|string|integer> $vars parsed arguments list
     *
     * @return string Return compiled code segment for the token
     */
    protected static function compileBlockEnd(&$token, &$context, $vars) {
        $each = false;
        $pop = array_pop($context['stack']);
        switch ($token[self::POS_INNERTAG]) {
            case 'if':
            case 'unless':
                if ($pop == ':') {
                    array_pop($context['stack']);
                    return $context['usedFeature']['parent'] ? "{$context['ops']['f_end']}}){$context['ops']['seperator']}" : "{$context['ops']['cnd_end']}";
                }
                return $context['usedFeature']['parent'] ? "{$context['ops']['f_end']}}){$context['ops']['seperator']}" : "{$context['ops']['cnd_else']}''{$context['ops']['cnd_end']}";
            case 'with':
                if ($context['flags']['with']) {
                    if ($pop !== 'with') {
                        $context['error'][] = 'Unexpect token: {{/with}} !';
                        return;
                    }
                    return "{$context['ops']['f_end']}}){$context['ops']['seperator']}";
                }
                break;
            case 'each':
                $each = true;
        }

        switch($pop) {
            case '#':
            case '^':
                $pop2 = array_pop($context['stack']);
                $v = static::getVariableName($vars[0], $context);
                if (!$each && ($pop2 !== $v[1])) {
                    $context['error'][] = 'Unexpect token ' . token::toString($token) . " ! Previous token {{{$pop}$pop2}} is not closed";
                    return;
                }
                if ($pop == '^') {
                    return "{$context['ops']['cnd_else']}''{$context['ops']['cnd_end']}";
                }
                return "{$context['ops']['f_end']}}){$context['ops']['seperator']}";
            default:
                $context['error'][] = 'Unexpect token: ' . token::toString($token) . ' !';
                return;
        }
    }

    /**
     * Internal method used by compile(). Return compiled PHP code partial for a handlebars block begin token.
     *
     * @param array<string,array|string|integer> $context current compile context
     * @param array<array|string|integer> $vars parsed arguments list
     * @param array<string> $token detected handlebars {{ }} token
     *
     * @return string Return compiled code segment for the token
     */
    protected static function compileBlockBegin(&$context, $vars, $token) {
        $each = 'false';
        $v = isset($vars[1]) ? static::getVariableNameOrSubExpression($vars[1], $context) : array(null, array());
        switch (isset($vars[0][0]) ? $vars[0][0] : null) {
            case 'if':
                $context['stack'][] = 'if';
                $includeZero = (isset($vars['includeZero'][1]) && $vars['includeZero'][1]) ? 'true' : 'false';
                return $context['usedFeature']['parent']
                    ? $context['ops']['seperator'] . static::getFuncName($context, 'ifv', 'if ' . $v[1]) . "\$cx, {$v[0]}, {$includeZero}, \$in, function(\$cx, \$in) {{$context['ops']['f_start']}"
                    : "{$context['ops']['cnd_start']}(" . static::getFuncName($context, 'ifvar', $v[1]) . "\$cx, {$v[0]}, {$includeZero})){$context['ops']['cnd_then']}";
            case 'unless':
                $context['stack'][] = 'unless';
                return $context['usedFeature']['parent']
                    ? $context['ops']['seperator'] . static::getFuncName($context, 'unl', 'unless ' . $v[1]) . "\$cx, {$v[0]}, false, \$in, function(\$cx, \$in) {{$context['ops']['f_start']}"
                    : "{$context['ops']['cnd_start']}(!" . static::getFuncName($context, 'ifvar', $v[1]) . "\$cx, {$v[0]}, false)){$context['ops']['cnd_then']}";
            case 'each':
                $each = 'true';
                array_shift($vars);
                if (!isset($vars[0])) {
                    $vars[0] = array(null);
                }
                break;
            case 'with':
                if ($context['flags']['with']) {
                    $context['stack'][] = 'with';
                    return $context['ops']['seperator'] . static::getFuncName($context, 'wi', 'with ' . $v[1]) . "\$cx, {$v[0]}, \$in, function(\$cx, \$in) {{$context['ops']['f_start']}";
                }
        }

        $named = count(array_diff_key($vars, array_keys(array_keys($vars)))) > 0;
        Validator::noNamedArguments($token, $context, $named, ', maybe you missing the block custom helper?');
        $v = static::getVariableNameOrSubExpression($vars[0], $context);
        $context['stack'][] = $v[1];
        $context['stack'][] = '#';
        return $context['ops']['seperator'] . static::getFuncName($context, 'sec', (($each == 'true') ? 'each ' : '') . $v[1]) . "\$cx, {$v[0]}, \$in, $each, function(\$cx, \$in) {{$context['ops']['f_start']}";
    }

    /**
     * Internal method used by compile(). Return compiled PHP code partial for a handlebars custom helper token.
     *
     * @param array<string,array|string|integer> $context current compile context
     * @param array<array|string|integer> $vars parsed arguments list
     * @param boolean $raw is this {{{ token or not
     * @param boolean $err should cause error when missing helper or not
     *
     * @return string|null Return compiled code segment for the token when the token is custom helper
     */
    protected static function compileCustomHelper(&$context, $vars, $raw, $err = false) {
        $notHH = !isset($context['hbhelpers'][$vars[0][0]]);
        if (!isset($context['helpers'][$vars[0][0]]) && $notHH) {
            if ($err) {
                if (!$context['flags']['exhlp']) {
                    $context['error'][] = "Can not find custom helper function defination {$vars[0][0]}() !";
                }
            }
            return;
        }

        $fn = $raw ? 'raw' : $context['ops']['enc'];
        $ch = array_shift($vars);
        $v = static::getVariableNames($vars, $context);
        static::addUsageCount($context, $notHH ? 'helpers' : 'hbhelpers', $ch[0]);
        return $context['ops']['seperator'] . static::getFuncName($context, $notHH ? 'ch' : 'hbch', "$ch[0] " . implode(' ', $v[1])) . "\$cx, '$ch[0]', {$v[0]}, '$fn'" . ($notHH ? '' : ', $in') . "){$context['ops']['seperator']}";
    }

    /**
     * Internal method used by compile(). Return compiled PHP code partial for a handlebars else token.
     *
     * @param array<string,array|string|integer> $context current compile context
     * @param array<array|string|integer> $vars parsed arguments list
     *
     * @return string|null Return compiled code segment for the token when the token is else
     */
    protected static function compileElse(&$context, &$vars) {
        if ($vars[0][0] === 'else') {
            $c = count($context['stack']) - 1;
            if ($c >= 0) {
                switch ($context['stack'][count($context['stack']) - 1]) {
                    case 'if':
                    case 'unless':
                        $context['stack'][] = ':';
                        return $context['usedFeature']['parent'] ? "{$context['ops']['f_end']}}, function(\$cx, \$in) {{$context['ops']['f_start']}" : "{$context['ops']['cnd_else']}";
                    case 'with':
                    case 'each':
                    case '#':
                        return "{$context['ops']['f_end']}}, function(\$cx, \$in) {{$context['ops']['f_start']}";
                    default:
                }
            }
            $context['error'][] = '{{else}} only valid in if, unless, each, and #section context';
        }
    }

    /**
     * Internal method used by compile(). Return compiled PHP code partial for a handlebars variable token.
     *
     * @param array<string,array|string|integer> $context current compile context
     * @param array<array|string|integer> $vars parsed arguments list
     * @param boolean $raw is this {{{ token or not
     *
     * @return string Return compiled code segment for the token
     */
    protected static function compileVariable(&$context, &$vars, $raw) {
        $v = static::getVariableName($vars[0], $context);
        if ($context['flags']['jsobj'] || $context['flags']['jstrue'] || $context['flags']['debug']) {
            return $context['ops']['seperator'] . static::getFuncName($context, $raw ? 'raw' : $context['ops']['enc'], $v[1]) . "\$cx, {$v[0]}){$context['ops']['seperator']}";
        } else {
            return $raw ? "{$context['ops']['seperator']}$v[0]{$context['ops']['seperator']}" : "{$context['ops']['seperator']}htmlentities((string){$v[0]}, ENT_QUOTES, 'UTF-8'){$context['ops']['seperator']}";
        }
    }

    /**
     * Internal method used by compile(). Add usage count to context
     *
     * @param array<string,array|string|integer> $context current context
     * @param string $category ctegory name, can be one of: 'var', 'helpers', 'blockhelpers'
     * @param string $name used name
     * @param integer $count increment
     *
     * @expect 1 when input array('usedCount' => array('test' => array())), 'test', 'testname'
     * @expect 3 when input array('usedCount' => array('test' => array('testname' => 2))), 'test', 'testname'
     * @expect 5 when input array('usedCount' => array('test' => array('testname' => 2))), 'test', 'testname', 3
     */
    protected static function addUsageCount(&$context, $category, $name, $count = 1) {
        if (!isset($context['usedCount'][$category][$name])) {
            $context['usedCount'][$category][$name] = 0;
        }
        return ($context['usedCount'][$category][$name] += $count);
    }
}

