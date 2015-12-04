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
 * file of LightnCandy Compiler
 *
 * @package    LightnCandy
 * @author     Zordius <zordius@yahoo-inc.com>
 */

namespace LightnCandy;

use \LightnCandy\Validator;
use \LightnCandy\Token;
use \LightnCandy\Expression;
use \LightnCandy\Parser;

/**
 * LightnCandy Compiler
 */
class Compiler extends Validator
{
    public static $lastParsed;

    /**
     * Compile template into PHP code
     *
     * @param array<string,array|string|integer> $context Current context
     * @param string $template handlebars template
     *
     * @return string|null generated PHP code
     */
    public static function compileTemplate(&$context, $template) {
        array_unshift($context['parsed'], array());
        Validator::verify($context, $template);

        if (count($context['error'])) {
            return;
        }

        // Do PHP code generation.
        Parser::setDelimiter($context);

        // Handle dynamic partials
        Partial::handleDynamic($context);

        $code = '';
        foreach ($context['parsed'][0] as $info) {
            if (is_array($info)) {
                $context['tokens']['current']++;
                $tmpl = static::compileToken($context, $info);
                if ($tmpl == $context['ops']['seperator']) {
                    $tmpl = '';
                } else {
                    $tmpl = "'$tmpl'";
                }
                $code .= $tmpl;
            } else {
                $code .= $info;
            }
        }

        static::$lastParsed = array_shift($context['parsed']);

        return $code;
    }

    /**
     * Compose LightnCandy render codes for include()
     *
     * @param array<string,array|string|integer> $context Current context
     * @param string $code generated PHP code
     *
     * @return string Composed PHP code
     */
    public static function composePHPRender($context, $code) {
        $flagJStrue = Expression::boolString($context['flags']['jstrue']);
        $flagJSObj = Expression::boolString($context['flags']['jsobj']);
        $flagSPVar = Expression::boolString($context['flags']['spvar']);
        $flagProp = Expression::boolString($context['flags']['prop']);
        $flagMethod = Expression::boolString($context['flags']['method']);
        $flagLambda = Expression::boolString($context['flags']['lambda']);
        $flagMustlok = Expression::boolString($context['flags']['mustlok']);
        $flagMustlam = Expression::boolString($context['flags']['mustlam']);
        $flagEcho = Expression::boolString($context['flags']['echo']);
        $flagPartNC = Expression::boolString($context['flags']['partnc']);
        $flagKnownHlp = Expression::boolString($context['flags']['knohlp']);

        $libstr = Exporter::runtime($context);
        $constants = Exporter::constants($context);
        $helpers = Exporter::helpers($context);
        $bhelpers = Exporter::helpers($context, 'blockhelpers');
        $hbhelpers = Exporter::helpers($context, 'hbhelpers');
        $partials = implode(",\n", $context['partialCode']);
        $debug = Runtime::DEBUG_ERROR_LOG;
        $phpstart = $context['flags']['bare'] ? '' : "<?php use {$context['runtime']} as LR;\n";
        $phpend = $context['flags']['bare'] ? ';' : "\n?>";

        // Return generated PHP code string.
        return "{$phpstart}return function (\$in, \$options = null) {
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
            'partnc' => $flagPartNC,
            'knohlp' => $flagKnownHlp,
            'debug' => isset(\$options['debug']) ? \$options['debug'] : $debug,
        ),
        'constants' => $constants,
        'helpers' => $helpers,
        'blockhelpers' => $bhelpers,
        'hbhelpers' => isset(\$options['helpers']) ? array_merge($hbhelpers, \$options['helpers']) : $hbhelpers,
        'partials' => array($partials),
        'scopes' => array(),
        'sp_vars' => isset(\$options['data']) ? array_merge(array('root' => \$in), \$options['data']) : array('root' => \$in),
        'blparam' => array(),
        'runtime' => '{$context['runtime']}',
$libstr
    );
    {$context['renderex']}
    {$context['ops']['op_start']}'$code'{$context['ops']['op_end']}
}$phpend";
    }

    /**
     * Get function name for standalone or none standalone template.
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
     * Get string presentation of variables
     *
     * @param array<string,array|string|integer> $context current compile context
     * @param array<array> $vn variable name array.
     * @param array<string>|null $blockParams block param list
     *
     * @return array<string|array> variable names
     *
     * @expect array('array(array($in),array())', array('this')) when input array('flags'=>array('spvar'=>true)), array(null)
     * @expect array('array(array($in,$in),array())', array('this', 'this')) when input array('flags'=>array('spvar'=>true)), array(null, null)
     * @expect array('array(array(),array(\'a\'=>$in))', array('this')) when input array('flags'=>array('spvar'=>true)), array('a' => null)
     */
    protected static function getVariableNames(&$context, $vn, $blockParams = null) {
        $vars = array(array(), array());
        $exps = array();
        foreach ($vn as $i => $v) {
            $V = static::getVariableNameOrSubExpression($context, $v);
            if (is_string($i)) {
                $vars[1][] = "'$i'=>{$V[0]}";
            } else {
                $vars[0][] = $V[0];
            }
            $exps[] = $V[1];
        }
        $bp = $blockParams ? (',array(' . Expression::listString($blockParams) . ')') : '';
        return array('array(array(' . implode(',', $vars[0]) . '),array(' . implode(',', $vars[1]) . ")$bp)", $exps);
    }

    /**
     * Get string presentation of a sub expression
     *
     * @param array<string,array|string|integer> $context current compile context
     * @param array<boolean|integer|string|array> $vars parsed arguments list
     *
     * @return array<string> code representing passed expression
     */
    public static function compileSubExpression(&$context, $vars) {
        $origSeperator = $context['ops']['seperator'];
        $context['ops']['seperator'] = '';

        $ret = static::customHelper($context, $vars, true);

        if (($ret === null) && $context['flags']['lambda']) {
            $ret = static::compileVariable($context, $vars, true);
        }

        $context['ops']['seperator'] = $origSeperator;

        return array($ret ? $ret : '', 'FIXME: $subExpression');
    }

    /**
     * Get string presentation of a subexpression or a variable
     *
     * @param array<array|string|integer> $context current compile context
     * @param array<array|string|integer> $var variable parsed path
     *
     * @return array<string> variable names
     */
    protected static function getVariableNameOrSubExpression(&$context, $var) {
        return Parser::isSubExp($var) ? static::compileSubExpression($context, $var[1]) : static::getVariableName($context, $var);
    }

    /**
     * Get string presentation of a variable
     *
     * @param array<array|string|integer> $var variable parsed path
     * @param array<array|string|integer> $context current compile context
     * @param array<string> $lookup extra lookup string as valid PHP variable name
     *
     * @return array<string> variable names
     *
     * @expect array('$in', 'this') when input array('flags'=>array('spvar'=>true,'debug'=>0)), array(null)
     * @expect array('((isset($in[\'true\']) && is_array($in)) ? $in[\'true\'] : null)', '[true]') when input array('flags'=>array('spvar'=>true,'debug'=>0,'prop'=>0,'method'=>0,'mustlok'=>0,'mustlam'=>0, 'lambda'=>0)), array('true')
     * @expect array('((isset($in[\'false\']) && is_array($in)) ? $in[\'false\'] : null)', '[false]') when input array('flags'=>array('spvar'=>true,'debug'=>0,'prop'=>0,'method'=>0,'mustlok'=>0,'mustlam'=>0, 'lambda'=>0)), array('false')
     * @expect array('true', 'true') when input array('flags'=>array('spvar'=>true,'debug'=>0)), array(-1, 'true')
     * @expect array('false', 'false') when input array('flags'=>array('spvar'=>true,'debug'=>0)), array(-1, 'false')
     * @expect array('((isset($in[\'2\']) && is_array($in)) ? $in[\'2\'] : null)', '[2]') when input array('flags'=>array('spvar'=>true,'debug'=>0,'prop'=>0,'method'=>0,'mustlok'=>0,'mustlam'=>0, 'lambda'=>0)), array('2')
     * @expect array('2', '2') when input array('flags'=>array('spvar'=>true,'debug'=>0,'prop'=>0,'method'=>0)), array(-1, '2')
     * @expect array('((isset($in[\'@index\']) && is_array($in)) ? $in[\'@index\'] : null)', '[@index]') when input array('flags'=>array('spvar'=>false,'debug'=>0,'prop'=>0,'method'=>0,'mustlok'=>0,'mustlam'=>0, 'lambda'=>0)), array('@index')
     * @expect array("((isset(\$cx['sp_vars']['index']) && is_array(\$cx['sp_vars'])) ? \$cx['sp_vars']['index'] : null)", '@[index]') when input array('flags'=>array('spvar'=>true,'debug'=>0,'prop'=>0,'method'=>0,'mustlok'=>0,'mustlam'=>0, 'lambda'=>0)), array('@index')
     * @expect array("((isset(\$cx['sp_vars']['key']) && is_array(\$cx['sp_vars'])) ? \$cx['sp_vars']['key'] : null)", '@[key]') when input array('flags'=>array('spvar'=>true,'debug'=>0,'prop'=>0,'method'=>0,'mustlok'=>0,'mustlam'=>0, 'lambda'=>0)), array('@key')
     * @expect array("((isset(\$cx['sp_vars']['first']) && is_array(\$cx['sp_vars'])) ? \$cx['sp_vars']['first'] : null)", '@[first]') when input array('flags'=>array('spvar'=>true,'debug'=>0,'prop'=>0,'method'=>0,'mustlok'=>0,'mustlam'=>0, 'lambda'=>0)), array('@first')
     * @expect array("((isset(\$cx['sp_vars']['last']) && is_array(\$cx['sp_vars'])) ? \$cx['sp_vars']['last'] : null)", '@[last]') when input array('flags'=>array('spvar'=>true,'debug'=>0,'prop'=>0,'method'=>0,'mustlok'=>0,'mustlam'=>0, 'lambda'=>0)), array('@last')
     * @expect array('((isset($in[\'"a"\']) && is_array($in)) ? $in[\'"a"\'] : null)', '["a"]') when input array('flags'=>array('spvar'=>true,'debug'=>0,'prop'=>0,'method'=>0,'mustlok'=>0,'mustlam'=>0, 'lambda'=>0)), array('"a"')
     * @expect array('"a"', '"a"') when input array('flags'=>array('spvar'=>true,'debug'=>0)), array(-1, '"a"')
     * @expect array('((isset($in[\'a\']) && is_array($in)) ? $in[\'a\'] : null)', '[a]') when input array('flags'=>array('spvar'=>true,'debug'=>0,'prop'=>0,'method'=>0,'mustlok'=>0,'mustlam'=>0, 'lambda'=>0)), array('a')
     * @expect array('((isset($cx[\'scopes\'][count($cx[\'scopes\'])-1][\'a\']) && is_array($cx[\'scopes\'][count($cx[\'scopes\'])-1])) ? $cx[\'scopes\'][count($cx[\'scopes\'])-1][\'a\'] : null)', '../[a]') when input array('flags'=>array('spvar'=>true,'debug'=>0,'prop'=>0,'method'=>0,'mustlok'=>0,'mustlam'=>0, 'lambda'=>0)), array(1,'a')
     * @expect array('((isset($cx[\'scopes\'][count($cx[\'scopes\'])-3][\'a\']) && is_array($cx[\'scopes\'][count($cx[\'scopes\'])-3])) ? $cx[\'scopes\'][count($cx[\'scopes\'])-3][\'a\'] : null)', '../../../[a]') when input array('flags'=>array('spvar'=>true,'debug'=>0,'prop'=>0,'method'=>0,'mustlok'=>0,'mustlam'=>0, 'lambda'=>0)), array(3,'a')
     * @expect array('((isset($in[\'id\']) && is_array($in)) ? $in[\'id\'] : null)', 'this.[id]') when input array('flags'=>array('spvar'=>true,'debug'=>0,'prop'=>0,'method'=>0,'mustlok'=>0,'mustlam'=>0, 'lambda'=>0)), array(null, 'id')
     * @expect array('LR::v($cx, $in, isset($in) ? $in : null, array(\'id\'))', 'this.[id]') when input array('flags'=>array('prop'=>true,'spvar'=>true,'debug'=>0,'method'=>0,'mustlok'=>0,'mustlam'=>0, 'lambda'=>0,'standalone'=>0), 'runtime' => 'Runtime'), array(null, 'id')
     */
    protected static function getVariableName(&$context, $var, $lookup = null, $args = null) {
        if (isset($var[0]) && ($var[0] === Parser::LITERAL)) {
            if ($var[1] === "undefined") {
                $var[1] = "null";
            }
            return array($var[1], preg_replace('/\'(.*)\'/', '$1', $var[1]));
        }

        list($levels, $spvar, $var) = Expression::analyze($context, $var);
        $exp = Expression::toString($levels, $spvar, $var);
        $base = $spvar ? "\$cx['sp_vars']" : '$in';

        // change base when trace to parent
        if ($levels > 0) {
            if ($spvar) {
                $base .= str_repeat("['_parent']", $levels);
            } else {
                $base = "\$cx['scopes'][count(\$cx['scopes'])-$levels]";
            }
        }

        if ((count($var) == 0) || (($var[0] === null) && (count($var) == 1))) {
            return array($base, $exp);
        }

        if ($var[0] === null) {
            array_shift($var);
        }

        // To support recursive context lookup, instance properties + methods and lambdas
        // the only way is using slower rendering time variable resolver.
        if ($context['flags']['prop'] || $context['flags']['method'] || $context['flags']['mustlok'] || $context['flags']['mustlam'] || $context['flags']['lambda']) {
            $L = $lookup ? ", $lookup[0]" : '';
            $A = $args ? ",$args[0]" : '';
            $E = $args ? ' ' . implode(' ', $args[1]) : '';
            return array(static::getFuncName($context, 'v', $exp) . "\$cx, \$in, isset($base) ? $base : null, array(" . Expression::listString($var) . "$L)$A)", $lookup ? "lookup $exp $lookup[1]" : "$exp$E");
        }

        $n = Expression::arrayString($var);
        array_pop($var);
        $L = $lookup ? "[{$lookup[0]}]" : '';
        $p = $lookup ? $n : (count($var) ? Expression::arrayString($var) : '');

        return array("((isset($base$n$L) && is_array($base$p)) ? $base$n$L : " . ($context['flags']['debug'] ? (static::getFuncName($context, 'miss', '') . "\$cx, '$exp')") : 'null' ) . ')', $lookup ? "lookup $exp $lookup[1]" : $exp);
    }

    /**
     * Return compiled PHP code for a handlebars token
     *
     * @param array<string,array|string|integer> $context current compile context
     * @param array<string,array|boolean> $info parsed information
     *
     * @return string Return compiled code segment for the token
     */
    protected static function compileToken(&$context, $info) {
        list($raw, $vars, $token, $indent) = $info;

        $context['tokens']['partialind'] = $indent;
        $context['currentToken'] = $token;

        // Do not touch the tag, keep it as is.
        if ($raw === -1) {
            return ".'" . Token::toString($token) . "'.";
        }

        if ($ret = static::operator($token[Token::POS_OP], $context, $vars)) {
            return $ret;
        }

        if (isset($vars[0][0])) {
            if ($ret = static::customHelper($context, $vars, $raw)) {
                return $ret;
            }
            if ($vars[0][0] === 'else') {
                return static::doElse($context);
            }
            if ($vars[0][0] === 'lookup') {
                return static::compileLookup($context, $vars, $raw);
            }
        }

        return static::compileVariable($context, $vars, $raw);
    }

    /**
     * handle partial
     *
     * @param array<string,array|string|integer> $context current compile context
     * @param array<boolean|integer|string|array> $vars parsed arguments list
     *
     * @return string Return compiled code segment for the partial
     */
    public static function partial(&$context, $vars) {
        // mustache spec: ignore missing partial
        if (($context['usedFeature']['dynpartial'] === 0) && ($context['usedFeature']['inlpartial'] === 0) && !isset($context['usedPartial'][$vars[0][0]])) {
            return $context['ops']['seperator'];
        }
        Parser::getBlockParams($vars);
        $p = array_shift($vars);
        if ($context['flags']['runpart']) {
            if (!isset($vars[0])) {
                $vars[0] = $context['flags']['partnc'] ? array(0, 'null') : array();
            }
            $v = static::getVariableNames($context, $vars);
            $tag = ">$p[0] " .implode(' ', $v[1]);
            if (Parser::isSubExp($p)) {
                list($p) = static::compileSubExpression($context, $p[1]);
            } else {
                $p = "'$p[0]'";
            }
            $sp = $context['tokens']['partialind'] ? ", '{$context['tokens']['partialind']}'" : '';
            return $context['ops']['seperator'] . static::getFuncName($context, 'p', $tag) . "\$cx, $p, $v[0]$sp){$context['ops']['seperator']}";
        }
        return "{$context['ops']['seperator']}'" . Partial::compileStatic($context, $p[0]) . "'{$context['ops']['seperator']}";
    }

    /**
     * handle inline partial
     *
     * @param array<string,array|string|integer> $context current compile context
     * @param array<boolean|integer|string|array> $vars parsed arguments list
     *
     * @return string Return compiled code segment for the partial
     */
    public static function inline(&$context, $vars) {
        Parser::getBlockParams($vars);
        list($code) = array_shift($vars);
        $p = array_shift($vars);
        if (!isset($vars[0])) {
            $vars[0] = $context['flags']['partnc'] ? array(0, 'null') : array();
        }
        $v = static::getVariableNames($context, $vars);
        $tag = ">*inline $p[0]" .implode(' ', $v[1]);
        return $context['ops']['seperator'] . static::getFuncName($context, 'in', $tag) . "\$cx, '{$p[0]}', $code){$context['ops']['seperator']}";
    }

    /**
     * Return compiled PHP code for a handlebars inverted section begin token
     *
     * @param array<string,array|string|integer> $context current compile context
     * @param array<boolean|integer|string|array> $vars parsed arguments list
     *
     * @return string Return compiled code segment for the token
     */
    protected static function invertedSection(&$context, $vars) {
        $v = static::getVariableName($context, $vars[0]);
        return "{$context['ops']['cnd_start']}(" . static::getFuncName($context, 'isec', '^' . $v[1]) . "\$cx, {$v[0]})){$context['ops']['cnd_then']}";
    }

    /**
     * Return compiled PHP code for a handlebars block custom helper begin token
     *
     * @param array<string,array|string|integer> $context current compile context
     * @param array<boolean|integer|string|array> $vars parsed arguments list
     * @param boolean $inverted the logic will be inverted
     *
     * @return string Return compiled code segment for the token
     */
    protected static function blockCustomHelper(&$context, $vars, $inverted = false) {
        $notHBCH = !isset($context['hbhelpers'][$vars[0][0]]);

        $bp = Parser::getBlockParams($vars);
        $ch = array_shift($vars);
        $inverted = $inverted ? 'true' : 'false';
        static::addUsageCount($context, $notHBCH ? 'blockhelpers' : 'hbhelpers', $ch[0]);
        $v = static::getVariableNames($context, $vars, $bp);

        return $context['ops']['seperator'] . static::getFuncName($context, $notHBCH ? 'bch' : 'hbch', ($inverted ? '^' : '#') . implode(' ', $v[1])) . "\$cx, '$ch[0]', {$v[0]}, \$in, $inverted, function(\$cx, \$in) {{$context['ops']['f_start']}";
    }

    /**
     * Return compiled PHP code for a handlebars block end token
     *
     * @param array<string,array|string|integer> $context current compile context
     * @param array<boolean|integer|string|array> $vars parsed arguments list
     * @param string|null $matchop should also match to this operator
     *
     * @return string Return compiled code segment for the token
     */
    protected static function blockEnd(&$context, $vars, $matchop = NULL) {
        $pop = $context['stack'][count($context['stack']) - 1];
        switch ($context['currentToken'][Token::POS_INNERTAG]) {
            case 'if':
            case 'unless':
                if ($pop === ':') {
                    array_pop($context['stack']);
                    return "{$context['ops']['cnd_end']}";
                }
                if (!$context['flags']['nohbh']) {
                    return "{$context['ops']['cnd_else']}''{$context['ops']['cnd_end']}";
                }
                break;
            case 'with':
                if (!$context['flags']['nohbh']) {
                    return "{$context['ops']['f_end']}}){$context['ops']['seperator']}";
                }
        }

        if ($pop === ':') {
            array_pop($context['stack']);
            return "{$context['ops']['f_end']}}){$context['ops']['seperator']}";
        }

        switch($pop) {
            case '#':
                return "{$context['ops']['f_end']}}){$context['ops']['seperator']}";
            case '^':
                return "{$context['ops']['cnd_else']}''{$context['ops']['cnd_end']}";
        }
    }

    /**
     * Return compiled PHP code for a handlebars block begin token
     *
     * @param array<string,array|string|integer> $context current compile context
     * @param array<boolean|integer|string|array> $vars parsed arguments list
     *
     * @return string Return compiled code segment for the token
     */
    protected static function blockBegin(&$context, $vars) {
        $v = isset($vars[1]) ? static::getVariableNameOrSubExpression($context, $vars[1]) : array(null, array());
        if (!$context['flags']['nohbh']) {
            switch (isset($vars[0][0]) ? $vars[0][0] : null) {
                case 'if':
                    $includeZero = (isset($vars['includeZero'][1]) && $vars['includeZero'][1]) ? 'true' : 'false';
                    return "{$context['ops']['cnd_start']}(" . static::getFuncName($context, 'ifvar', $v[1]) . "\$cx, {$v[0]}, {$includeZero})){$context['ops']['cnd_then']}";
                case 'unless':
                    return "{$context['ops']['cnd_start']}(!" . static::getFuncName($context, 'ifvar', $v[1]) . "\$cx, {$v[0]}, false)){$context['ops']['cnd_then']}";
                case 'each':
                    return static::section($context, $vars, true);
                case 'with':
                    if ($r = static::with($context, $vars)) {
                        return $r;
                    }
            }
        }

        return static::section($context, $vars);
    }

    /**
     * compile {{#foo}} token
     *
     * @param array<string,array|string|integer> $context current compile context
     * @param array<boolean|integer|string|array> $vars parsed arguments list
     * @param boolean $isEach the section is #each
     *
     * @return string|null Return compiled code segment for the token
     */
    protected static function section(&$context, $vars, $isEach = false) {
        $bs = 'null';
        $be = '';
        if ($isEach) {
            $bp = Parser::getBlockParams($vars);
            $bs = $bp ? ('array(' . Expression::listString($bp) . ')') : 'null';
            $be = $bp ? " as |$bp[0] $bp[1]|" : '';
            array_shift($vars);
            if (!isset($vars[0])) {
                $vars[0] = array(null);
            }
        }
        if ($context['flags']['lambda'] && !$isEach) {
            $V = array_shift($vars);
            $v = static::getVariableName($context, $V, null, count($vars) ? static::getVariableNames($context, $vars) : array('0',array('')));
        } else {
            $v = static::getVariableNameOrSubExpression($context, $vars[0]);
        }
        $each = $isEach ? 'true' : 'false';
        return $context['ops']['seperator'] . static::getFuncName($context, 'sec', ($isEach ? 'each ' : '') . $v[1] . $be) . "\$cx, {$v[0]}, $bs, \$in, $each, function(\$cx, \$in) {{$context['ops']['f_start']}";
    }

    /**
     * compile {{with}} token
     *
     * @param array<string,array|string|integer> $context current compile context
     * @param array<boolean|integer|string|array> $vars parsed arguments list
     *
     * @return string|null Return compiled code segment for the token
     */
    protected static function with(&$context, $vars) {
        $v = isset($vars[1]) ? static::getVariableNameOrSubExpression($context, $vars[1]) : array(null, array());
        $bp = Parser::getBlockParams($vars);
        $bs = $bp ? ('array(' . Expression::listString($bp) . ')') : 'null';
        $be = $bp ? " as |$bp[0]|" : '';
        return $context['ops']['seperator'] . static::getFuncName($context, 'wi', 'with ' . $v[1] . $be) . "\$cx, {$v[0]}, $bs, \$in, function(\$cx, \$in) {{$context['ops']['f_start']}";
    }

    /**
     * Return compiled PHP code for a handlebars custom helper token
     *
     * @param array<string,array|string|integer> $context current compile context
     * @param array<boolean|integer|string|array> $vars parsed arguments list
     * @param boolean $raw is this {{{ token or not
     *
     * @return string|null Return compiled code segment for the token when the token is custom helper
     */
    protected static function customHelper(&$context, $vars, $raw) {
        $notHH = !isset($context['hbhelpers'][$vars[0][0]]);
        if (!isset($context['helpers'][$vars[0][0]]) && $notHH) {
            return;
        }

        $fn = $raw ? 'raw' : $context['ops']['enc'];
        $ch = array_shift($vars);
        $v = static::getVariableNames($context, $vars);
        static::addUsageCount($context, $notHH ? 'helpers' : 'hbhelpers', $ch[0]);
        return $context['ops']['seperator'] . static::getFuncName($context, $notHH ? 'ch' : 'hbch', "$ch[0] " . implode(' ', $v[1])) . "\$cx, '$ch[0]', {$v[0]}, '$fn'" . ($notHH ? '' : ', $in') . "){$context['ops']['seperator']}";
    }

    /**
     * Return compiled PHP code for a handlebars else token
     *
     * @param array<string,array|string|integer> $context current compile context
     *
     * @return string Return compiled code segment for the token when the token is else
     */
    protected static function doElse(&$context) {
        switch ($context['stack'][count($context['stack']) - 2]) {
            case '[if]':
            case '[unless]':
                $context['stack'][] = ':';
                return "{$context['ops']['cnd_else']}";
            default:
                return "{$context['ops']['f_end']}}, function(\$cx, \$in) {{$context['ops']['f_start']}";
        }
    }

    /**
     * Return compiled PHP code for a handlebars lookup token
     *
     * @param array<string,array|string|integer> $context current compile context
     * @param array<boolean|integer|string|array> $vars parsed arguments list
     * @param boolean $raw is this {{{ token or not
     *
     * @return string Return compiled code segment for the token
     */
    protected static function compileLookup(&$context, &$vars, $raw) {
        $v2 = static::getVariableName($context, $vars[2]);
        $v = static::getVariableName($context, $vars[1], $v2);
        if ($context['flags']['hbesc'] || $context['flags']['jsobj'] || $context['flags']['jstrue'] || $context['flags']['debug']) {
            return $context['ops']['seperator'] . static::getFuncName($context, $raw ? 'raw' : $context['ops']['enc'], $v[1]) . "\$cx, {$v[0]}){$context['ops']['seperator']}";
        } else {
            return $raw ? "{$context['ops']['seperator']}$v[0]{$context['ops']['seperator']}" : "{$context['ops']['seperator']}htmlentities((string){$v[0]}, ENT_QUOTES, 'UTF-8'){$context['ops']['seperator']}";
        }
    }

    /**
     * Return compiled PHP code for a handlebars variable token
     *
     * @param array<string,array|string|integer> $context current compile context
     * @param array<boolean|integer|string|array> $vars parsed arguments list
     * @param boolean $raw is this {{{ token or not
     *
     * @return string Return compiled code segment for the token
     */
    protected static function compileVariable(&$context, &$vars, $raw) {
        if ($context['flags']['lambda']) {
            $V = array_shift($vars);
            $v = static::getVariableName($context, $V, null, count($vars) ? static::getVariableNames($context, $vars) : array('0',array('')));
        } else {
            $v = static::getVariableName($context, $vars[0]);
        }
        if ($context['flags']['hbesc'] || $context['flags']['jsobj'] || $context['flags']['jstrue'] || $context['flags']['debug']) {
            return $context['ops']['seperator'] . static::getFuncName($context, $raw ? 'raw' : $context['ops']['enc'], $v[1]) . "\$cx, {$v[0]}){$context['ops']['seperator']}";
        } else {
            return $raw ? "{$context['ops']['seperator']}$v[0]{$context['ops']['seperator']}" : "{$context['ops']['seperator']}htmlentities((string){$v[0]}, ENT_QUOTES, 'UTF-8'){$context['ops']['seperator']}";
        }
    }

    /**
     * Add usage count to context
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

