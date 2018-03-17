<?php
/*

MIT License
Copyright 2013-2018 Zordius Chen. All Rights Reserved.
Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:
The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.
THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.

Origin: https://github.com/zordius/lightncandy
*/

/**
 * file of LightnCandy Expression handler
 *
 * @package    LightnCandy
 * @author     Zordius <zordius@gmail.com>
 */

namespace LightnCandy;

use \LightnCandy\Validator;
use \LightnCandy\Token;

/**
 * LightnCandy Expression handler
 */
class Expression
{
    /**
     * return 'true' or 'false' string.
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
    public static function boolString($v) {
        return ($v > 0) ? 'true' : 'false';
    }

    /**
     * Get string presentation for a string list
     *
     * @param array<string> $list an array of strings.
     *
     * @return string PHP list string
     *
     * @expect '' when input array()
     * @expect "'a'" when input array('a')
     * @expect "'a','b','c'" when input array('a', 'b', 'c')
     */
    public static function listString($list) {
        return implode(',', (array_map(function ($v) {
            return "'$v'";
        }, $list)));
    }

    /**
     * Get string presentation for an array
     *
     * @param array<string> $list an array of variable names.
     *
     * @return string PHP array names string
     *
     * @expect '' when input array()
     * @expect "['a']" when input array('a')
     * @expect "['a']['b']['c']" when input array('a', 'b', 'c')
     */
    public static function arrayString($list) {
        return implode('', (array_map(function ($v) {
            return "['$v']";
        }, $list)));
    }

    /**
     * Analyze an expression
     *
     * @param array<string,array|string|integer> $context Current context
     * @param array<array|string|integer> $var variable parsed path
     *
     * @return array<integer|boolean|array> analyzed result
     *
     * @expect array(0, false, array('foo')) when input array('flags' => array('spvar' => 0)), array(0, 'foo')
     * @expect array(1, false, array('foo')) when input array('flags' => array('spvar' => 0)), array(1, 'foo')
     */
    public static function analyze($context, $var) {
        $levels = 0;
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
                    $var[0] = substr($var[0], 1);
                }
            }
        }

        return array($levels, $spvar, $var);
    }

    /**
     * get normalized handlebars expression for a variable
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
    public static function toString($levels, $spvar, $var) {
        return ($spvar ? '@' : '') . str_repeat('../', $levels) . ((is_array($var) && count($var)) ? implode('.', array_map(function($v) {
            return ($v === null) ? 'this' : "[$v]";
        }, $var)) : 'this');
    }
}

