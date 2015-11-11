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
 * file to support LightnCandy compiled PHP runtime
 *
 * @package    LightnCandy
 * @author     Zordius <zordius@yahoo-inc.com>
 */

namespace LightnCandy;

/**
 * LightnCandy class for compiled PHP runtime.
 */
class Runtime {
    const DEBUG_ERROR_LOG = 1;
    const DEBUG_ERROR_EXCEPTION = 2;
    const DEBUG_TAGS = 4;
    const DEBUG_TAGS_ANSI = 12;
    const DEBUG_TAGS_HTML = 20;

    /**
     * LightnCandy runtime method for output debug info.
     *
     * @param string $v expression
     * @param string $f runtime function name
     * @param array<string,array|string|integer> $cx render time context
     *
     * @expect '{{123}}' when input '123', 'miss', array('flags' => array('debug' => Runtime::DEBUG_TAGS), 'runtime' => 'LightnCandy\\Runtime'), ''
     * @expect '<!--MISSED((-->{{#123}}<!--))--><!--SKIPPED--><!--MISSED((-->{{/123}}<!--))-->' when input '123', 'wi', array('flags' => array('debug' => Runtime::DEBUG_TAGS_HTML), 'runtime' => 'LightnCandy\\Runtime'), false, false, function () {return 'A';}
     */
    public static function debug($v, $f, $cx) {
        $params = array_slice(func_get_args(), 2);
        $r = call_user_func_array((isset($cx['funcs'][$f]) ? $cx['funcs'][$f] : "{$cx['runtime']}::$f"), $params);

        if ($cx['flags']['debug'] & static::DEBUG_TAGS) {
            $ansi = $cx['flags']['debug'] & (static::DEBUG_TAGS_ANSI - static::DEBUG_TAGS);
            $html = $cx['flags']['debug'] & (static::DEBUG_TAGS_HTML - static::DEBUG_TAGS);
            $cs = ($html ? (($r !== '') ? '<!!--OK((-->' : '<!--MISSED((-->') : '')
                  . ($ansi ? (($r !== '') ? "\033[0;32m" : "\033[0;31m") : '');
            $ce = ($html ? '<!--))-->' : '')
                  . ($ansi ? "\033[0m" : '');
            switch ($f) {
                case 'sec':
                case 'ifv':
                case 'unl':
                case 'wi':
                    if ($r == '') {
                        if ($ansi) {
                            $r = "\033[0;33mSKIPPED\033[0m";
                        }
                        if ($html) {
                            $r = '<!--SKIPPED-->';
                        }
                    }
                    return "$cs{{#{$v}}}$ce{$r}$cs{{/{$v}}}$ce";
                default:
                    return "$cs{{{$v}}}$ce";
            }
        } else {
            return $r;
        }
    }

    /**
     * LightnCandy runtime method for error
     *
     * @param array<string,array|string|integer> $cx render time context
     * @param string $err error message
     *
     * @throws \Exception
     */
    public static function err($cx, $err) {
        if ($cx['flags']['debug'] & static::DEBUG_ERROR_LOG) {
            error_log($err);
            return;
        }
        if ($cx['flags']['debug'] & static::DEBUG_ERROR_EXCEPTION) {
            throw new \Exception($err);
        }
    }

    /**
     * LightnCandy runtime method for missing data error.
     *
     * @param array<string,array|string|integer> $cx render time context
     * @param string $v expression
     */
    public static function miss($cx, $v) {
        static::err($cx, "Runtime: $v is not exist");
    }

    /**
     * LightnCandy runtime method for variable lookup. It is slower and only be used for instance property or method detection or lambdas.
     *
     * @param array<string,array|string|integer> $cx render time context
     * @param array<array|string|integer> $base current variable context
     * @param array<string|integer> $path array of names for path
     *
     * @return null|string Return the value or null when not found
     *
     * @expect null when input array('scopes' => array(), 'flags' => array('prop' => 0, 'method' => 0, 'mustlok' => 0)), 0, array('a', 'b')
     * @expect 3 when input array('scopes' => array(), 'flags' => array('prop' => 0, 'method' => 0), 'mustlok' => 0), array('a' => array('b' => 3)), array('a', 'b')
     * @expect null when input array('scopes' => array(), 'flags' => array('prop' => 0, 'method' => 0, 'mustlok' => 0)), (Object) array('a' => array('b' => 3)), array('a', 'b')
     * @expect 3 when input array('scopes' => array(), 'flags' => array('prop' => 1, 'method' => 0, 'mustlok' => 0)), (Object) array('a' => array('b' => 3)), array('a', 'b')
     */
    public static function v($cx, $base, $path) {
        $count = count($cx['scopes']);
        while ($base) {
            $v = $base;
            foreach ($path as $name) {
                if (is_array($v) && isset($v[$name])) {
                    $v = $v[$name];
                    continue;
                }
                if (is_object($v)) {
                    if ($cx['flags']['prop'] && isset($v->$name)) {
                        $v = $v->$name;
                        continue;
                    }
                    if ($cx['flags']['method'] && is_callable(array($v, $name))) {
                        $v = $v->$name();
                        continue;
                    }
                }
                if ($cx['flags']['mustlok']) {
                    unset($v);
                    break;
                }
                return null;
            }
            if (isset($v)) {
                return $v;
            }
            $count--;
            switch ($count) {
                case -1:
                    $base = $cx['sp_vars']['root'];
                    break;
                case -2:
                    return null;
                default:
                    $base = $cx['scopes'][$count];
            }
        }
    }

    /**
     * LightnCandy runtime method for {{#if var}}.
     *
     * @param array<string,array|string|integer> $cx render time context
     * @param array<array|string|integer>|string|integer|null $v value to be tested
     * @param boolean $zero include zero as true
     *
     * @return boolean Return true when the value is not null nor false.
     *
     * @expect false when input array(), null, false
     * @expect false when input array(), 0, false
     * @expect true when input array(), 0, true
     * @expect false when input array(), false, false
     * @expect true when input array(), true, false
     * @expect true when input array(), 1, false
     * @expect false when input array(), '', false
     * @expect false when input array(), array(), false
     * @expect true when input array(), array(''), false
     * @expect true when input array(), array(0), false
     */
    public static function ifvar($cx, $v, $zero) {
        return !is_null($v) && ($v !== false) && ($zero || ($v !== 0) && ($v !== 0.0)) && ($v !== '') && (is_array($v) ? (count($v) > 0) : true);
    }

    /**
     * LightnCandy runtime method for {{#if var}} when {{../var}} used.
     *
     * @param array<string,array|string|integer> $cx render time context
     * @param array<array|string|integer>|string|integer|null $v value to be tested
     * @param boolean $zero include zero as true
     * @param array<array|string|integer> $in input data with current scope
     * @param Closure|null $truecb callback function when test result is true
     * @param Closure|null $falsecb callback function when test result is false
     *
     * @return string The rendered string of the section
     *
     * @expect '' when input array('scopes' => array()), null, false, array(), null
     * @expect '' when input array('scopes' => array()), null, false, array(), function () {return 'Y';}
     * @expect 'Y' when input array('scopes' => array()), 1, false, array(), function () {return 'Y';}
     * @expect 'N' when input array('scopes' => array()), null, false, array(), function () {return 'Y';}, function () {return 'N';}
     */
    public static function ifv($cx, $v, $zero, $in, $truecb, $falsecb = null) {
        $ret = '';
        if (static::ifvar($cx, $v, $zero)) {
            if ($truecb) {
                $cx['scopes'][] = $in;
                $ret = $truecb($cx, $in);
                array_pop($cx['scopes']);
            }
        } else {
            if ($falsecb) {
                $cx['scopes'][] = $in;
                $ret = $falsecb($cx, $in);
                array_pop($cx['scopes']);
            }
        }
        return $ret;
    }

    /**
     * LightnCandy runtime method for {{#unless var}} when {{../var}} used.
     *
     * @param array<string,array|string|integer> $cx render time context
     * @param array<array|string|integer>|string|integer|null $var value be tested
     * @param boolean $zero include zero as true
     * @param array<array|string|integer>|string|integer|null $in input data with current scope
     * @param Closure $truecb callback function when test result is true
     * @param Closure|null $falsecb callback function when test result is false
     *
     * @return string Return rendered string when the value is not null nor false.
     *
     * @expect '' when input array('scopes' => array()), null, false, array(), null
     * @expect 'Y' when input array('scopes' => array()), null, false, array(), function () {return 'Y';}
     * @expect '' when input array('scopes' => array()), 1, false, array(), function () {return 'Y';}
     * @expect 'Y' when input array('scopes' => array()), null, false, array(), function () {return 'Y';}, function () {return 'N';}
     * @expect 'N' when input array('scopes' => array()), true, false, array(), function () {return 'Y';}, function () {return 'N';}
     */
    public static function unl($cx, $var, $zero, $in, $truecb, $falsecb = null) {
        return static::ifv($cx, $var, $zero, $in, $falsecb, $truecb);
    }

    /**
     * LightnCandy runtime method for {{^var}} inverted section.
     *
     * @param array<string,array|string|integer> $cx render time context
     * @param array<array|string|integer>|string|integer|null $v value to be tested
     *
     * @return boolean Return true when the value is not null nor false.
     *
     * @expect true when input array(), null
     * @expect false when input array(), 0
     * @expect true when input array(), false
     * @expect false when input array(), 'false'
     * @expect true when input array(), array()
     * @expect false when input array(), array('1')
     */
    public static function isec($cx, $v) {
        return is_null($v) || ($v === false) || (is_array($v) && (count($v) === 0));
    }

    /**
     * LightnCandy runtime method for {{{var}}} .
     *
     * @param array<string,array|string|integer> $cx render time context
     * @param array<array|string|integer>|string|integer|null $v value to be output
     *
     * @return string The raw value of the specified variable
     *
     * @expect true when input array('flags' => array('jstrue' => 0, 'mustlam' => 0, 'lambda' => 0)), true
     * @expect 'true' when input array('flags' => array('jstrue' => 1)), true
     * @expect '' when input array('flags' => array('jstrue' => 0, 'mustlam' => 0, 'lambda' => 0)), false
     * @expect 'false' when input array('flags' => array('jstrue' => 1)), false
     * @expect 'false' when input array('flags' => array('jstrue' => 1)), false, true
     * @expect 'Array' when input array('flags' => array('jstrue' => 1, 'jsobj' => 0)), array('a', 'b')
     * @expect 'a,b' when input array('flags' => array('jstrue' => 1, 'jsobj' => 1, 'mustlam' => 0, 'lambda' => 0)), array('a', 'b')
     * @expect '[object Object]' when input array('flags' => array('jstrue' => 1, 'jsobj' => 1)), array('a', 'c' => 'b')
     * @expect '[object Object]' when input array('flags' => array('jstrue' => 1, 'jsobj' => 1)), array('c' => 'b')
     * @expect 'a,true' when input array('flags' => array('jstrue' => 1, 'jsobj' => 1, 'mustlam' => 0, 'lambda' => 0)), array('a', true)
     * @expect 'a,1' when input array('flags' => array('jstrue' => 0, 'jsobj' => 1, 'mustlam' => 0, 'lambda' => 0)), array('a',true)
     * @expect 'a,' when input array('flags' => array('jstrue' => 0, 'jsobj' => 1, 'mustlam' => 0, 'lambda' => 0)), array('a',false)
     * @expect 'a,false' when input array('flags' => array('jstrue' => 1, 'jsobj' => 1, 'mustlam' => 0, 'lambda' => 0)), array('a',false)
     */
    public static function raw($cx, $v) {
        if ($v === true) {
            if ($cx['flags']['jstrue']) {
                return 'true';
            }
        }

        if (($v === false)) {
            if ($cx['flags']['jstrue']) {
                return 'false';
            }
        }

        if (is_array($v)) {
            if ($cx['flags']['jsobj']) {
                if (count(array_diff_key($v, array_keys(array_keys($v)))) > 0) {
                    return '[object Object]';
                } else {
                    $ret = array();
                    foreach ($v as $k => $vv) {
                        $ret[] = static::raw($cx, $vv);
                    }
                    return join(',', $ret);
                }
            } else {
                return 'Array';
            }
        }

        if ($v instanceof \Closure) {
            if ($cx['flags']['mustlam'] || $cx['flags']['lambda']) {
                $v = $v();
            }
        }

        return "$v";
    }

    /**
     * LightnCandy runtime method for {{var}} .
     *
     * @param array<string,array|string|integer> $cx render time context
     * @param array<array|string|integer>|string|integer|null $var value to be htmlencoded
     *
     * @return string The htmlencoded value of the specified variable
     *
     * @expect 'a' when input array('flags' => array('mustlam' => 0, 'lambda' => 0)), 'a'
     * @expect 'a&amp;b' when input array('flags' => array('mustlam' => 0, 'lambda' => 0)), 'a&b'
     * @expect 'a&#039;b' when input array('flags' => array('mustlam' => 0, 'lambda' => 0)), 'a\'b'
     */
    public static function enc($cx, $var) {
        return htmlentities(static::raw($cx, $var), ENT_QUOTES, 'UTF-8');
    }

    /**
     * LightnCandy runtime method for {{var}} , and deal with single quote to same as handlebars.js .
     *
     * @param array<string,array|string|integer> $cx render time context
     * @param array<array|string|integer>|string|integer|null $var value to be htmlencoded
     *
     * @return string The htmlencoded value of the specified variable
     *
     * @expect 'a' when input array('flags' => array('mustlam' => 0, 'lambda' => 0)), 'a'
     * @expect 'a&amp;b' when input array('flags' => array('mustlam' => 0, 'lambda' => 0)), 'a&b'
     * @expect 'a&#x27;b' when input array('flags' => array('mustlam' => 0, 'lambda' => 0)), 'a\'b'
     * @expect '&#x60;a&#x27;b' when input array('flags' => array('mustlam' => 0, 'lambda' => 0)), '`a\'b'
     */
    public static function encq($cx, $var) {
        return preg_replace('/`/', '&#x60;', preg_replace('/&#039;/', '&#x27;', htmlentities(static::raw($cx, $var), ENT_QUOTES, 'UTF-8')));
    }

    /**
     * LightnCandy runtime method for {{#var}} section.
     *
     * @param array<string,array|string|integer> $cx render time context
     * @param array<array|string|integer>|string|integer|null $v value for the section
     * @param array<array|string|integer>|string|integer|null $in input data with current scope
     * @param boolean $each true when rendering #each
     * @param Closure $cb callback function to render child context
     * @param Closure|null $else callback function to render child context when {{else}}
     *
     * @return string The rendered string of the section
     *
     * @expect '' when input array('flags' => array('spvar' => 0, 'mustlam' => 0, 'lambda' => 0)), false, false, false, function () {return 'A';}
     * @expect '' when input array('flags' => array('spvar' => 0, 'mustlam' => 0, 'lambda' => 0)), null, null, false, function () {return 'A';}
     * @expect 'A' when input array('flags' => array('spvar' => 0, 'mustlam' => 0, 'lambda' => 0)), true, true, false, function () {return 'A';}
     * @expect 'A' when input array('flags' => array('spvar' => 0, 'mustlam' => 0, 'lambda' => 0)), 0, 0, false, function () {return 'A';}
     * @expect '-a=' when input array('flags' => array('spvar' => 0, 'mustlam' => 0, 'lambda' => 0)), array('a'), array('a'), false, function ($c, $i) {return "-$i=";}
     * @expect '-a=-b=' when input array('flags' => array('spvar' => 0, 'mustlam' => 0, 'lambda' => 0)), array('a','b'), array('a','b'), false, function ($c, $i) {return "-$i=";}
     * @expect '' when input array('flags' => array('spvar' => 0, 'mustlam' => 0, 'lambda' => 0)), 'abc', 'abc', true, function ($c, $i) {return "-$i=";}
     * @expect '-b=' when input array('flags' => array('spvar' => 0, 'mustlam' => 0, 'lambda' => 0)), array('a' => 'b'), array('a' => 'b'), true, function ($c, $i) {return "-$i=";}
     * @expect '1' when input array('flags' => array('spvar' => 0, 'mustlam' => 0, 'lambda' => 0)), 'b', 'b', false, function ($c, $i) {return count($i);}
     * @expect '1' when input array('flags' => array('spvar' => 0, 'mustlam' => 0, 'lambda' => 0)), 1, 1, false, function ($c, $i) {return print_r($i, true);}
     * @expect '0' when input array('flags' => array('spvar' => 0, 'mustlam' => 0, 'lambda' => 0)), 0, 0, false, function ($c, $i) {return print_r($i, true);}
     * @expect '{"b":"c"}' when input array('flags' => array('spvar' => 0, 'mustlam' => 0, 'lambda' => 0)), array('b' => 'c'), array('b' => 'c'), false, function ($c, $i) {return json_encode($i);}
     * @expect 'inv' when input array('flags' => array('spvar' => 0, 'mustlam' => 0, 'lambda' => 0)), array(), 0, true, function ($c, $i) {return 'cb';}, function ($c, $i) {return 'inv';}
     * @expect 'inv' when input array('flags' => array('spvar' => 0, 'mustlam' => 0, 'lambda' => 0)), array(), 0, false, function ($c, $i) {return 'cb';}, function ($c, $i) {return 'inv';}
     * @expect 'inv' when input array('flags' => array('spvar' => 0, 'mustlam' => 0, 'lambda' => 0)), false, 0, true, function ($c, $i) {return 'cb';}, function ($c, $i) {return 'inv';}
     * @expect 'inv' when input array('flags' => array('spvar' => 0, 'mustlam' => 0, 'lambda' => 0)), false, 0, false, function ($c, $i) {return 'cb';}, function ($c, $i) {return 'inv';}
     * @expect 'inv' when input array('flags' => array('spvar' => 0, 'mustlam' => 0, 'lambda' => 0)), '', 0, true, function ($c, $i) {return 'cb';}, function ($c, $i) {return 'inv';}
     * @expect 'cb' when input array('flags' => array('spvar' => 0, 'mustlam' => 0, 'lambda' => 0)), '', 0, false, function ($c, $i) {return 'cb';}, function ($c, $i) {return 'inv';}
     * @expect 'inv' when input array('flags' => array('spvar' => 0, 'mustlam' => 0, 'lambda' => 0)), 0, 0, true, function ($c, $i) {return 'cb';}, function ($c, $i) {return 'inv';}
     * @expect 'cb' when input array('flags' => array('spvar' => 0, 'mustlam' => 0, 'lambda' => 0)), 0, 0, false, function ($c, $i) {return 'cb';}, function ($c, $i) {return 'inv';}
     * @expect 'inv' when input array('flags' => array('spvar' => 0, 'mustlam' => 0, 'lambda' => 0)), new stdClass, 0, true, function ($c, $i) {return 'cb';}, function ($c, $i) {return 'inv';}
     * @expect 'cb' when input array('flags' => array('spvar' => 0, 'mustlam' => 0, 'lambda' => 0)), new stdClass, 0, false, function ($c, $i) {return 'cb';}, function ($c, $i) {return 'inv';}
     * @expect '268' when input array('flags' => array('spvar' => 1, 'mustlam' => 0, 'lambda' => 0), 'sp_vars'=>array('root' => 0)), array(1,3,4), 0, false, function ($c, $i) {return $i * 2;}
     * @expect '038' when input array('flags' => array('spvar' => 1, 'mustlam' => 0, 'lambda' => 0), 'sp_vars'=>array('root' => 0)), array(1,3,'a'=>4), 0, true, function ($c, $i) {return $i * $c['sp_vars']['index'];}
     */
    public static function sec($cx, $v, $in, $each, $cb, $else = null) {
        $isAry = is_array($v) || ($v instanceof \ArrayObject);
        $isTrav = $v instanceof \Traversable;
        $loop = $each;
        $keys = null;
        $last = null;
        $isObj = false;

        if ($isAry && $else !== null && count($v) === 0) {
            $cx['scopes'][] = $in;
            $ret = $else($cx, $in);
            array_pop($cx['scopes']);
            return $ret;
        }

        // #var, detect input type is object or not
        if (!$loop && $isAry) {
            $keys = array_keys($v);
            $loop = (count(array_diff_key($v, array_keys($keys))) == 0);
            $isObj = !$loop;
        }

        if ($cx['flags']['mustlam'] && ($v instanceof \Closure)) {
            self:err('Do not support Section Lambdas!');
        }

        if (($loop && $isAry) || $isTrav) {
            if ($each && !$isTrav) {
                // Detect input type is object or not when never done once
                if ($keys == null) {
                    $keys = array_keys($v);
                    $isObj = (count(array_diff_key($v, array_keys($keys))) > 0);
                }
            }
            $ret = array();
            $cx['scopes'][] = $in;
            $i = 0;
            if ($cx['flags']['spvar']) {
                $old_spvar = $cx['sp_vars'];
                $cx['sp_vars'] = array(
                    '_parent' => $old_spvar,
                    'root' => $old_spvar['root'],
                );
                if (!$isTrav) {
                    $last = count($keys) - 1;
                }
            }

            $isSparceArray = $isObj && (count(array_filter(array_keys($v), 'is_string')) == 0);
            foreach ($v as $index => $raw) {
                if ($cx['flags']['spvar']) {
                    $cx['sp_vars']['first'] = ($i === 0);
                    $cx['sp_vars']['last'] = ($i == $last);
                    $cx['sp_vars']['key'] = $index;
                    $cx['sp_vars']['index'] = $isSparceArray ? $index : $i;
                    $i++;
                }
                $ret[] = $cb($cx, $raw);
            }
            if ($cx['flags']['spvar']) {
                if ($isObj) {
                    unset($cx['sp_vars']['key']);
                } else {
                    unset($cx['sp_vars']['last']);
                }
                unset($cx['sp_vars']['index']);
                unset($cx['sp_vars']['first']);
                $cx['sp_vars'] = $old_spvar;
            }
            array_pop($cx['scopes']);
            return join('', $ret);
        }
        if ($each) {
            if ($else !== null) {
                $cx['scopes'][] = $in;
                $ret = $else($cx, $v);
                array_pop($cx['scopes']);
                return $ret;
            }
            return '';
        }
        if ($isAry) {
            $cx['scopes'][] = $in;
            $ret = $cb($cx, $v);
            array_pop($cx['scopes']);
            return $ret;
        }

        if ($v === true) {
            return $cb($cx, $in);
        }

        if (!is_null($v) && ($v !== false)) {
            return $cb($cx, $v);
        }

        if ($else !== null) {
            $cx['scopes'][] = $in;
            $ret = $else($cx, $in);
            array_pop($cx['scopes']);
            return $ret;
        }

        return '';
    }

    /**
     * LightnCandy runtime method for {{#with var}} .
     *
     * @param array<string,array|string|integer> $cx render time context
     * @param array<array|string|integer>|string|integer|null $v value to be the new context
     * @param array<array|string|integer>|string|integer|null $in input data with current scope
     * @param Closure $cb callback function to render child context
     * @param Closure|null $else callback function to render child context when {{else}}
     *
     * @return string The rendered string of the token
     *
     * @expect '' when input array(), false, false, function () {return 'A';}
     * @expect '' when input array(), null, null, function () {return 'A';}
     * @expect '{"a":"b"}' when input array(), array('a'=>'b'), array('a'=>'c'), function ($c, $i) {return json_encode($i);}
     * @expect '-b=' when input array(), 'b', array('a'=>'b'), function ($c, $i) {return "-$i=";}
     */
    public static function wi($cx, $v, $in, $cb, $else = null) {
        if (($v === false) || ($v === null)) {
            return $else ? $else($cx, $in) : '';
        }
        $cx['scopes'][] = $in;
        $ret = $cb($cx, $v);
        array_pop($cx['scopes']);
        return $ret;
    }

    /**
     * LightnCandy runtime method for {{> partial}} .
     *
     * @param array<string,array|string|integer> $cx render time context
     * @param string $p partial name
     * @param array<array|string|integer>|string|integer|null $v value to be the new context
     *
     * @return string The rendered string of the partial
     *
     */
    public static function p($cx, $p, $v, $sp = '') {
        $param = $v[0][0];

        if (is_array($v[1])) {
            if (is_array($v[0][0])) {
                $param = array_merge($v[0][0], $v[1]);
            } else if (($cx['flags']['method'] || $cx['flags']['prop']) && is_object($v[0][0])) {
                foreach ($v[1] as $i => $v) {
                    $param->$i = $v;
                }
            }
        }

        return call_user_func($cx['partials'][$p], $cx, $param, $sp);
    }

    /**
     * LightnCandy runtime method for custom helpers.
     *
     * @param array<string,array|string|integer> $cx render time context
     * @param string $ch the name of custom helper to be executed
     * @param array<array> $vars variables for the helper
     * @param string $op the name of variable resolver. should be one of: 'raw', 'enc', or 'encq'.
     *
     * @return string The rendered string of the token
     *
     * @expect '=-=' when input array('helpers' => array('a' => function ($i) {return "=$i[0]=";})), 'a', array(array('-'),array()), 'raw'
     * @expect '=&amp;=' when input array('helpers' => array('a' => function ($i) {return "=$i[0]=";})), 'a', array(array('&'),array()), 'enc'
     * @expect '=&#x27;=' when input array('helpers' => array('a' => function ($i) {return "=$i[0]=";})), 'a', array(array('\''),array()), 'encq'
     * @expect '=b=' when input array('helpers' => array('a' => function ($i,$j) {return "={$j['a']}=";})), 'a', array(array(),array('a' => 'b')), 'raw'
     */
    public static function ch($cx, $ch, $vars, $op) {
        return static::chret(call_user_func_array($cx['helpers'][$ch], $vars), $op);
    }

    /**
     * LightnCandy runtime method to handle response of custom helpers.
     *
     * @param string|array<string,array|string|integer> $ret return value from custom helper
     * @param string $op the name of variable resolver. should be one of: 'raw', 'enc', or 'encq'.
     *
     * @return string The rendered string of the token
     *
     * @expect '=&=' when input '=&=', 'raw'
     * @expect '=&amp;&#039;=' when input '=&\'=', 'enc'
     * @expect '=&amp;&#x27;=' when input '=&\'=', 'encq'
     * @expect '=&amp;&#039;=' when input array('=&\'='), 'enc'
     * @expect '=&amp;&#x27;=' when input array('=&\'='), 'encq'
     * @expect '=&amp;=' when input array('=&=', false), 'enc'
     * @expect '=&=' when input array('=&=', false), 'raw'
     * @expect '=&=' when input array('=&=', 'raw'), 'enc'
     * @expect '=&amp;&#x27;=' when input array('=&\'=', 'encq'), 'raw'
     */
    public static function chret($ret, $op) {
        if (is_array($ret)) {
            if (isset($ret[1]) && $ret[1]) {
                $op = $ret[1];
            }
            $ret = $ret[0];
        }

        switch ($op) {
            case 'enc':
                return htmlentities($ret, ENT_QUOTES, 'UTF-8');
            case 'encq':
                return preg_replace('/&#039;/', '&#x27;', htmlentities($ret, ENT_QUOTES, 'UTF-8'));
        }
        return $ret;
    }

    /**
     * LightnCandy runtime method for Handlebars.js style custom helpers.
     *
     * @param array<string,array|string|integer> $cx render time context
     * @param string $ch the name of custom helper to be executed
     * @param array<array|string|integer>|string|integer|null $vars variables for the helper
     * @param string $op the name of variable resolver. should be one of: 'raw', 'enc', or 'encq'.
     * @param boolean $inverted the logic will be inverted
     * @param Closure|null $cb callback function to render child context
     * @param Closure|null $else callback function to render child context when {{else}}
     *
     * @return string The rendered string of the token
     */
    public static function hbch($cx, $ch, $vars, $op, $inverted, $cb = null, $else = null) {
        $isBlock = (is_object($cb) && ($cb instanceof \Closure));
        $args = $vars[0];
        $options = array(
            'name' => $ch,
            'hash' => $vars[1],
            '_this' => $isBlock ? $op : $inverted,
        );

        // $invert the logic
        if ($inverted) {
            $tmp = $else;
            $else = $cb;
            $cb = $tmp;
        }

        if ($isBlock) {
            $options['fn'] = function ($context = '_NO_INPUT_HERE_', $data = null) use ($cx, $op, $cb) {
                if ($cx['flags']['echo']) {
                    ob_start();
                }
                $cx['scopes'][] = $op;
                if ($data) {
                    $tmp_data = $cx['sp_vars'];
                    $cx['sp_vars'] = array_merge($cx['sp_vars'], $data['data']);
                }
                if ($context === '_NO_INPUT_HERE_') {
                    $ret = $cb($cx, $op);
                } else {
                    $ret = $cb($cx, $context);
                }
                array_pop($cx['scopes']);
                if ($data) {
                    $cx['sp_vars'] = $tmp_data;
                }
                return $cx['flags']['echo'] ? ob_get_clean() : $ret;
            };
        }

        if ($else) {
            $options['inverse'] = function ($context = '_NO_INPUT_HERE_', $data = null) use ($cx, $op, $else) {
                if ($cx['flags']['echo']) {
                    ob_start();
                }
                if ($context === '_NO_INPUT_HERE_') {
                    $ret = $else($cx, $op);
                } else {
                    $cx['scopes'][] = $op;
                    $ret = $else($cx, $context);
                    array_pop($cx['scopes']);
                }
                return $cx['flags']['echo'] ? ob_get_clean() : $ret;
            };
        }

        // prepare $options['data']
        if ($cx['flags']['spvar']) {
            $options['data'] = $cx['sp_vars'];
        }

        $args[] = $options;
        $e = null;
        $r = true;

        try {
            $r = call_user_func_array($cx['hbhelpers'][$ch], $args);
        } catch (\Exception $E) {
            $e = "Runtime: call custom helper '$ch' error: " . $E->getMessage();
        }

        if($e !== null) {
            static::err($cx, $e);
        }

        return static::chret($r, $isBlock ? 'raw' : $op);
    }

    /**
     * LightnCandy runtime method for block custom helpers.
     *
     * @param array<string,array|string|integer> $cx render time context
     * @param string $ch the name of custom helper to be executed
     * @param array<array|string|integer>|string|integer|null $vars variables for the helper
     * @param array<array|string|integer>|string|integer|null $in input data with current scope
     * @param boolean $inverted the logic will be inverted
     * @param Closure $cb callback function to render child context
     * @param Closure|null $else callback function to render child context when {{else}}
     *
     * @return string The rendered string of the token
     *
     * @expect '4.2.3' when input array('blockhelpers' => array('a' => function ($cx) {return array($cx,2,3);})), 'a', array(0, 0), 4, false, function($cx, $i) {return implode('.', $i);}
     * @expect '2.6.5' when input array('blockhelpers' => array('a' => function ($cx,$in) {return array($cx,$in[0],5);})), 'a', array('6', 0), 2, false, function($cx, $i) {return implode('.', $i);}
     * @expect '' when input array('blockhelpers' => array('a' => function ($cx,$in) {})), 'a', array('6', 0), 2, false, function($cx, $i) {return implode('.', $i);}
     */
    public static function bch($cx, $ch, $vars, $in, $inverted, $cb, $else = null) {
        $r = call_user_func($cx['blockhelpers'][$ch], $in, $vars[0], $vars[1]);

        // $invert the logic
        if ($inverted) {
            $tmp = $else;
            $else = $cb;
            $cb = $tmp;
        }

        $ret = '';
        if (is_null($r)) {
            if ($else) {
                $cx['scopes'][] = $in;
                $ret = $else($cx, $r);
                array_pop($cx['scopes']);
            }
        } else {
            if ($cb) {
                $cx['scopes'][] = $in;
                $ret = $cb($cx, $r);
                array_pop($cx['scopes']);
            }
        }

        return $ret;
    }
}

