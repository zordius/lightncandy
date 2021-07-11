<?php
/*

MIT License
Copyright 2013-2021 Zordius Chen. All Rights Reserved.
Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:
The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.
THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.

Origin: https://github.com/zordius/lightncandy
*/

/**
 * file to support LightnCandy compiled PHP runtime
 *
 * @package    LightnCandy
 * @author     Zordius <zordius@gmail.com>
 */

namespace LightnCandy;

/**
 * LightnCandy class for Object property access on a string.
 */
class StringObject
{
    protected $string;

    public function __construct($string)
    {
        $this->string = $string;
    }

    public function __toString()
    {
        return strval($this->string);
    }
}

/**
 * LightnCandy class for compiled PHP runtime.
 */
class Runtime extends Encoder
{
    const DEBUG_ERROR_LOG = 1;
    const DEBUG_ERROR_EXCEPTION = 2;
    const DEBUG_TAGS = 4;
    const DEBUG_TAGS_ANSI = 12;
    const DEBUG_TAGS_HTML = 20;

    /**
     * Output debug info.
     *
     * @param string $v expression
     * @param string $f runtime function name
     * @param array<string,array|string|integer> $cx render time context for lightncandy
     *
     * @expect '{{123}}' when input '123', 'miss', array('flags' => array('debug' => Runtime::DEBUG_TAGS), 'runtime' => 'LightnCandy\\Runtime'), ''
     * @expect '<!--MISSED((-->{{#123}}<!--))--><!--SKIPPED--><!--MISSED((-->{{/123}}<!--))-->' when input '123', 'wi', array('flags' => array('debug' => Runtime::DEBUG_TAGS_HTML), 'runtime' => 'LightnCandy\\Runtime'), false, null, false, function () {return 'A';}
     */
    public static function debug($v, $f, $cx)
    {
        // Build array of reference for call_user_func_array
        $P = func_get_args();
        $params = array();
        for ($i=2;$i<count($P);$i++) {
            $params[] = &$P[$i];
        }
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
     * Handle error by error_log or throw exception.
     *
     * @param array<string,array|string|integer> $cx render time context for lightncandy
     * @param string $err error message
     *
     * @throws \Exception
     */
    public static function err($cx, $err)
    {
        if ($cx['flags']['debug'] & static::DEBUG_ERROR_LOG) {
            error_log($err);
            return;
        }
        if ($cx['flags']['debug'] & static::DEBUG_ERROR_EXCEPTION) {
            throw new \Exception($err);
        }
    }

    /**
     * Handle missing data error.
     *
     * @param array<string,array|string|integer> $cx render time context for lightncandy
     * @param string $v expression
     */
    public static function miss($cx, $v)
    {
        static::err($cx, "Runtime: $v does not exist");
    }

    /**
     * For {{log}} .
     *
     * @param array<string,array|string|integer> $cx render time context for lightncandy
     * @param string $v expression
     */
    public static function lo($cx, $v)
    {
        error_log(var_export($v[0], true));
        return '';
    }

    /**
     * Resursive lookup variable and helpers. This is slow and will only be used for instance property or method detection or lambdas.
     *
     * @param array<string,array|string|integer> $cx render time context for lightncandy
     * @param array|string|boolean|integer|double|null $in current context
     * @param array<array|string|integer> $base current variable context
     * @param array<string|integer> $path array of names for path
     * @param array|null $args extra arguments for lambda
     *
     * @return null|string Return the value or null when not found
     *
     * @expect null when input array('scopes' => array(), 'flags' => array('prop' => 0, 'method' => 0, 'mustlok' => 0)), null, 0, array('a', 'b')
     * @expect 3 when input array('scopes' => array(), 'flags' => array('prop' => 0, 'method' => 0), 'mustlok' => 0), null, array('a' => array('b' => 3)), array('a', 'b')
     * @expect null when input array('scopes' => array(), 'flags' => array('prop' => 0, 'method' => 0, 'mustlok' => 0)), null, (Object) array('a' => array('b' => 3)), array('a', 'b')
     * @expect 3 when input array('scopes' => array(), 'flags' => array('prop' => 1, 'method' => 0, 'mustlok' => 0)), null, (Object) array('a' => array('b' => 3)), array('a', 'b')
     */
    public static function v($cx, $in, $base, $path, $args = null)
    {
        $count = count($cx['scopes']);
        $plen = count($path);
        while ($base) {
            $v = $base;
            foreach ($path as $i => $name) {
                if (is_array($v)) {
                    if (isset($v[$name])) {
                        $v = $v[$name];
                        continue;
                    }
                    if (($i === $plen - 1) && ($name === 'length')) {
                        return count($v);
                    }
                }
                if (is_object($v)) {
                    if ($cx['flags']['prop'] && !($v instanceof \Closure) && isset($v->$name)) {
                        $v = $v->$name;
                        continue;
                    }
                    if ($cx['flags']['method'] && is_callable(array($v, $name))) {
                        try {
                            $v = $v->$name();
                            continue;
                        } catch (\BadMethodCallException $e) {}
                    }
                    if ($v instanceof \ArrayAccess) {
                        if (isset($v[$name])) {
                            $v = $v[$name];
                            continue;
                        }
                    }
                }
                if ($cx['flags']['mustlok']) {
                    unset($v);
                    break;
                }
                return null;
            }
            if (isset($v)) {
                if ($v instanceof \Closure) {
                    if ($cx['flags']['mustlam'] || $cx['flags']['lambda']) {
                        if (!$cx['flags']['knohlp'] && !is_null($args)) {
                            $A = $args ? $args[0] : array();
                            $A[] = array('hash' => is_array( $args ) ? $args[1] : null, '_this' => $in);
                        } else {
                            $A = array($in);
                        }
                        $v = call_user_func_array($v, $A);
                    }
                }
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
        if ($args) {
            static::err($cx, 'Can not find helper or lambda: "' . implode('.', $path) . '" !');
        }
    }

    /**
     * For {{#if}} .
     *
     * @param array<string,array|string|integer> $cx render time context for lightncandy
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
    public static function ifvar($cx, $v, $zero)
    {
        return ($v !== null) && ($v !== false) && ($zero || ($v !== 0) && ($v !== 0.0)) && ($v !== '') && (is_array($v) ? (count($v) > 0) : true);
    }

    /**
     * For {{^var}} .
     *
     * @param array<string,array|string|integer> $cx render time context for lightncandy
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
    public static function isec($cx, $v)
    {
        return ($v === null) || ($v === false) || (is_array($v) && (count($v) === 0));
    }

    /**
     * For {{var}} .
     *
     * @param array<string,array|string|integer> $cx render time context for lightncandy
     * @param array<array|string|integer>|string|integer|null $var value to be htmlencoded
     *
     * @return string The htmlencoded value of the specified variable
     *
     * @expect 'a' when input array('flags' => array('mustlam' => 0, 'lambda' => 0)), 'a'
     * @expect 'a&amp;b' when input array('flags' => array('mustlam' => 0, 'lambda' => 0)), 'a&b'
     * @expect 'a&#039;b' when input array('flags' => array('mustlam' => 0, 'lambda' => 0)), 'a\'b'
     * @expect 'a&b' when input null, new \LightnCandy\SafeString('a&b')
     */
    public static function enc($cx, $var)
    {
        // Use full namespace classname for more specific code export/match in Exporter.php replaceSafeString.
        if ($var instanceof \LightnCandy\SafeString) {
            return (string)$var;
        }

        return htmlspecialchars(static::raw($cx, $var), ENT_QUOTES, 'UTF-8');
    }

    /**
     * For {{var}} , do html encode just like handlebars.js .
     *
     * @param array<string,array|string|integer> $cx render time context for lightncandy
     * @param array<array|string|integer>|string|integer|null $var value to be htmlencoded
     *
     * @return string The htmlencoded value of the specified variable
     *
     * @expect 'a' when input array('flags' => array('mustlam' => 0, 'lambda' => 0)), 'a'
     * @expect 'a&amp;b' when input array('flags' => array('mustlam' => 0, 'lambda' => 0)), 'a&b'
     * @expect 'a&#x27;b' when input array('flags' => array('mustlam' => 0, 'lambda' => 0)), 'a\'b'
     * @expect '&#x60;a&#x27;b' when input array('flags' => array('mustlam' => 0, 'lambda' => 0)), '`a\'b'
     */
    public static function encq($cx, $var)
    {
        // Use full namespace classname for more specific code export/match in Exporter.php replaceSafeString.
        if ($var instanceof \LightnCandy\SafeString) {
            return (string)$var;
        }

        return str_replace(array('=', '`', '&#039;'), array('&#x3D;', '&#x60;', '&#x27;'), htmlspecialchars(static::raw($cx, $var), ENT_QUOTES, 'UTF-8'));
    }

    /**
     * For {{#var}} or {{#each}} .
     *
     * @param array<string,array|string|integer> $cx render time context for lightncandy
     * @param array<array|string|integer>|string|integer|null $v value for the section
     * @param array<string>|null $bp block parameters
     * @param array<array|string|integer>|string|integer|null $in input data with current scope
     * @param boolean $each true when rendering #each
     * @param Closure $cb callback function to render child context
     * @param Closure|null $else callback function to render child context when {{else}}
     *
     * @return string The rendered string of the section
     *
     * @expect '' when input array('flags' => array('spvar' => 0, 'mustlam' => 0, 'mustsec' => 0, 'lambda' => 0)), false, null, false, false, function () {return 'A';}
     * @expect '' when input array('flags' => array('spvar' => 0, 'mustlam' => 0, 'mustsec' => 0, 'lambda' => 0)), null, null, null, false, function () {return 'A';}
     * @expect 'A' when input array('flags' => array('spvar' => 0, 'mustlam' => 0, 'mustsec' => 0, 'lambda' => 0)), true, null, true, false, function () {return 'A';}
     * @expect 'A' when input array('flags' => array('spvar' => 0, 'mustlam' => 0, 'mustsec' => 0, 'lambda' => 0)), 0, null, 0, false, function () {return 'A';}
     * @expect '-a=' when input array('scopes' => array(), 'flags' => array('spvar' => 0, 'mustlam' => 0, 'lambda' => 0)), array('a'), null, array('a'), false, function ($c, $i) {return "-$i=";}
     * @expect '-a=-b=' when input array('scopes' => array(), 'flags' => array('spvar' => 0, 'mustlam' => 0, 'lambda' => 0)), array('a','b'), null, array('a','b'), false, function ($c, $i) {return "-$i=";}
     * @expect '' when input array('scopes' => array(), 'flags' => array('spvar' => 0, 'mustlam' => 0, 'lambda' => 0)), 'abc', null, 'abc', true, function ($c, $i) {return "-$i=";}
     * @expect '-b=' when input array('scopes' => array(), 'flags' => array('spvar' => 0, 'mustlam' => 0, 'lambda' => 0)), array('a' => 'b'), null, array('a' => 'b'), true, function ($c, $i) {return "-$i=";}
     * @expect 'b' when input array('flags' => array('spvar' => 0, 'mustlam' => 0, 'mustsec' => 0, 'lambda' => 0)), 'b', null, 'b', false, function ($c, $i) {return print_r($i, true);}
     * @expect '1' when input array('flags' => array('spvar' => 0, 'mustlam' => 0, 'mustsec' => 0, 'lambda' => 0)), 1, null, 1, false, function ($c, $i) {return print_r($i, true);}
     * @expect '0' when input array('flags' => array('spvar' => 0, 'mustlam' => 0, 'mustsec' => 0, 'lambda' => 0)), 0, null, 0, false, function ($c, $i) {return print_r($i, true);}
     * @expect '{"b":"c"}' when input array('flags' => array('spvar' => 0, 'mustlam' => 0, 'lambda' => 0)), array('b' => 'c'), null, array('b' => 'c'), false, function ($c, $i) {return json_encode($i);}
     * @expect 'inv' when input array('flags' => array('spvar' => 0, 'mustlam' => 0, 'lambda' => 0)), array(), null, 0, true, function ($c, $i) {return 'cb';}, function ($c, $i) {return 'inv';}
     * @expect 'inv' when input array('flags' => array('spvar' => 0, 'mustlam' => 0, 'lambda' => 0)), array(), null, 0, false, function ($c, $i) {return 'cb';}, function ($c, $i) {return 'inv';}
     * @expect 'inv' when input array('flags' => array('spvar' => 0, 'mustlam' => 0, 'lambda' => 0)), false, null, 0, true, function ($c, $i) {return 'cb';}, function ($c, $i) {return 'inv';}
     * @expect 'inv' when input array('flags' => array('spvar' => 0, 'mustlam' => 0, 'mustsec' => 0, 'lambda' => 0)), false, null, 0, false, function ($c, $i) {return 'cb';}, function ($c, $i) {return 'inv';}
     * @expect 'inv' when input array('flags' => array('spvar' => 0, 'mustlam' => 0, 'lambda' => 0)), '', null, 0, true, function ($c, $i) {return 'cb';}, function ($c, $i) {return 'inv';}
     * @expect 'cb' when input array('flags' => array('spvar' => 0, 'mustlam' => 0, 'mustsec' => 0, 'lambda' => 0)), '', null, 0, false, function ($c, $i) {return 'cb';}, function ($c, $i) {return 'inv';}
     * @expect 'inv' when input array('flags' => array('spvar' => 0, 'mustlam' => 0, 'lambda' => 0)), 0, null, 0, true, function ($c, $i) {return 'cb';}, function ($c, $i) {return 'inv';}
     * @expect 'cb' when input array('flags' => array('spvar' => 0, 'mustlam' => 0, 'mustsec' => 0, 'lambda' => 0)), 0, null, 0, false, function ($c, $i) {return 'cb';}, function ($c, $i) {return 'inv';}
     * @expect 'inv' when input array('flags' => array('spvar' => 0, 'mustlam' => 0, 'lambda' => 0)), new stdClass, null, 0, true, function ($c, $i) {return 'cb';}, function ($c, $i) {return 'inv';}
     * @expect 'cb' when input array('flags' => array('spvar' => 0, 'mustlam' => 0, 'mustsec' => 0, 'lambda' => 0)), new stdClass, null, 0, false, function ($c, $i) {return 'cb';}, function ($c, $i) {return 'inv';}
     * @expect '268' when input array('scopes' => array(), 'flags' => array('spvar' => 1, 'mustlam' => 0, 'lambda' => 0), 'sp_vars'=>array('root' => 0)), array(1,3,4), null, 0, false, function ($c, $i) {return $i * 2;}
     * @expect '038' when input array('scopes' => array(), 'flags' => array('spvar' => 1, 'mustlam' => 0, 'lambda' => 0), 'sp_vars'=>array('root' => 0)), array(1,3,'a'=>4), null, 0, true, function ($c, $i) {return $i * $c['sp_vars']['index'];}
     */
    public static function sec($cx, $v, $bp, $in, $each, $cb, $else = null)
    {
        $push = ($in !== $v) || $each;

        $isAry = is_array($v) || ($v instanceof \ArrayObject);
        $isTrav = $v instanceof \Traversable;
        $loop = $each;
        $keys = null;
        $last = null;
        $isObj = false;

        if ($isAry && $else !== null && count($v) === 0) {
            return $else($cx, $in);
        }

        // #var, detect input type is object or not
        if (!$loop && $isAry) {
            $keys = array_keys($v);
            $loop = (count(array_diff_key($v, array_keys($keys))) == 0);
            $isObj = !$loop;
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
            if ($push) {
                $cx['scopes'][] = $in;
            }
            $i = 0;
            if ($cx['flags']['spvar']) {
                $old_spvar = $cx['sp_vars'];
                $cx['sp_vars'] = array_merge(array('root' => $old_spvar['root']), $old_spvar, array('_parent' => $old_spvar));
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
                if (isset($bp[0])) {
                    $raw = static::m($cx, $raw, array($bp[0] => $raw));
                }
                if (isset($bp[1])) {
                    $raw = static::m($cx, $raw, array($bp[1] => $index));
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
            if ($push) {
                array_pop($cx['scopes']);
            }
            return join('', $ret);
        }
        if ($each) {
            if ($else !== null) {
                $ret = $else($cx, $v);
                return $ret;
            }
            return '';
        }
        if ($isAry) {
            if ($push) {
                $cx['scopes'][] = $in;
            }
            $ret = $cb($cx, $v);
            if ($push) {
                array_pop($cx['scopes']);
            }
            return $ret;
        }

        if ($cx['flags']['mustsec']) {
            return $v ? $cb($cx, $in) : '';
        }

        if ($v === true) {
            return $cb($cx, $in);
        }

        if (($v !== null) && ($v !== false)) {
            return $cb($cx, $v);
        }

        if ($else !== null) {
            $ret = $else($cx, $in);
            return $ret;
        }

        return '';
    }

    /**
     * For {{#with}} .
     *
     * @param array<string,array|string|integer> $cx render time context for lightncandy
     * @param array<array|string|integer>|string|integer|null $v value to be the new context
     * @param array<array|string|integer>|string|integer|null $in input data with current scope
     * @param array<string>|null $bp block parameters
     * @param Closure $cb callback function to render child context
     * @param Closure|null $else callback function to render child context when {{else}}
     *
     * @return string The rendered string of the token
     *
     * @expect '' when input array(), false, null, false, function () {return 'A';}
     * @expect '' when input array(), null, null, null, function () {return 'A';}
     * @expect '{"a":"b"}' when input array(), array('a'=>'b'), null, array('a'=>'c'), function ($c, $i) {return json_encode($i);}
     * @expect '-b=' when input array(), 'b', null, array('a'=>'b'), function ($c, $i) {return "-$i=";}
     */
    public static function wi($cx, $v, $bp, $in, $cb, $else = null)
    {
        if (isset($bp[0])) {
            $v = static::m($cx, $v, array($bp[0] => $v));
        }
        if (($v === false) || ($v === null) || (is_array($v) && (count($v) === 0))) {
            return $else ? $else($cx, $in) : '';
        }
        if ($v === $in) {
            $ret = $cb($cx, $v);
        } else {
            $cx['scopes'][] = $in;
            $ret = $cb($cx, $v);
            array_pop($cx['scopes']);
        }
        return $ret;
    }

    /**
     * Get merged context.
     *
     * @param array<string,array|string|integer> $cx render time context for lightncandy
     * @param array<array|string|integer>|string|integer|null $a the context to be merged
     * @param array<array|string|integer>|string|integer|null $b the new context to overwrite
     *
     * @return array<array|string|integer>|string|integer the merged context object
     *
     */
    public static function m($cx, $a, $b)
    {
        if (is_array($b)) {
            if ($a === null) {
                return $b;
            } elseif (is_array($a)) {
                return array_merge($a, $b);
            } elseif ($cx['flags']['method'] || $cx['flags']['prop']) {
                if (!is_object($a)) {
                    $a = new StringObject($a);
                }
                foreach ($b as $i => $v) {
                    $a->$i = $v;
                }
            }
        }
        return $a;
    }

    /**
     * For {{> partial}} .
     *
     * @param array<string,array|string|integer> $cx render time context for lightncandy
     * @param string $p partial name
     * @param array<array|string|integer>|string|integer|null $v value to be the new context
     *
     * @return string The rendered string of the partial
     *
     */
    public static function p($cx, $p, $v, $pid, $sp = '')
    {
        $pp = ($p === '@partial-block') ? "$p" . ($pid > 0 ? $pid : $cx['partialid']) : $p;

        if (!isset($cx['partials'][$pp])) {
            static::err($cx, "Can not find partial named as '$p' !!");
            return '';
        }

        $cx['partialid'] = ($p === '@partial-block') ? (($pid > 0) ? $pid : (($cx['partialid'] > 0) ? $cx['partialid'] - 1 : 0)) : $pid;

        return call_user_func($cx['partials'][$pp], $cx, static::m($cx, $v[0][0], $v[1]), $sp);
    }

    /**
     * For {{#* inlinepartial}} .
     *
     * @param array<string,array|string|integer> $cx render time context for lightncandy
     * @param string $p partial name
     * @param Closure $code the compiled partial code
     *
     */
    public static function in(&$cx, $p, $code)
    {
        $cx['partials'][$p] = $code;
    }

    /* For single custom helpers.
     *
     * @param array<string,array|string|integer> $cx render time context for lightncandy
     * @param string $ch the name of custom helper to be executed
     * @param array<array|string|integer>|string|integer|null $vars variables for the helper
     * @param string $op the name of variable resolver. should be one of: 'raw', 'enc', or 'encq'.
     * @param array<string,array|string|integer> $_this current rendering context for the helper
     *
     * @return string The rendered string of the token
     */
    public static function hbch(&$cx, $ch, $vars, $op, &$_this)
    {
        if (isset($cx['blparam'][0][$ch])) {
            return $cx['blparam'][0][$ch];
        }

        $options = array(
            'name' => $ch,
            'hash' => $vars[1],
            'contexts' => count($cx['scopes']) ? $cx['scopes'] : array(null),
            'fn.blockParams' => 0,
            '_this' => &$_this
        );

        if ($cx['flags']['spvar']) {
            $options['data'] = &$cx['sp_vars'];
        }

        return static::exch($cx, $ch, $vars, $options);
    }

    /**
     * For block custom helpers.
     *
     * @param array<string,array|string|integer> $cx render time context for lightncandy
     * @param string $ch the name of custom helper to be executed
     * @param array<array|string|integer>|string|integer|null $vars variables for the helper
     * @param array<string,array|string|integer> $_this current rendering context for the helper
     * @param boolean $inverted the logic will be inverted
     * @param Closure|null $cb callback function to render child context
     * @param Closure|null $else callback function to render child context when {{else}}
     *
     * @return string The rendered string of the token
     */
    public static function hbbch(&$cx, $ch, $vars, &$_this, $inverted, $cb, $else = null)
    {
        $options = array(
            'name' => $ch,
            'hash' => $vars[1],
            'contexts' => count($cx['scopes']) ? $cx['scopes'] : array(null),
            'fn.blockParams' => 0,
            '_this' => &$_this,
        );

        if ($cx['flags']['spvar']) {
            $options['data'] = &$cx['sp_vars'];
        }

        if (isset($vars[2])) {
            $options['fn.blockParams'] = count($vars[2]);
        }

        // $invert the logic
        if ($inverted) {
            $tmp = $else;
            $else = $cb;
            $cb = $tmp;
        }

        $options['fn'] = function ($context = '_NO_INPUT_HERE_', $data = null) use ($cx, &$_this, $cb, $options, $vars) {
            if ($cx['flags']['echo']) {
                ob_start();
            }
            if (isset($data['data'])) {
                $old_spvar = $cx['sp_vars'];
                $cx['sp_vars'] = array_merge(array('root' => $old_spvar['root']), $data['data'], array('_parent' => $old_spvar));
            }
            $ex = false;
            if (isset($data['blockParams']) && isset($vars[2])) {
                $ex = array_combine($vars[2], array_slice($data['blockParams'], 0, count($vars[2])));
                array_unshift($cx['blparam'], $ex);
            } elseif (isset($cx['blparam'][0])) {
                $ex = $cx['blparam'][0];
            }
            if (($context === '_NO_INPUT_HERE_') || ($context === $_this)) {
                $ret = $cb($cx, is_array($ex) ? static::m($cx, $_this, $ex) : $_this);
            } else {
                $cx['scopes'][] = $_this;
                $ret = $cb($cx, is_array($ex) ? static::m($cx, $context, $ex) : $context);
                array_pop($cx['scopes']);
            }
            if (isset($data['data'])) {
                $cx['sp_vars'] = $old_spvar;
            }
            return $cx['flags']['echo'] ? ob_get_clean() : $ret;
        };

        if ($else) {
            $options['inverse'] = function ($context = '_NO_INPUT_HERE_') use ($cx, $_this, $else) {
                if ($cx['flags']['echo']) {
                    ob_start();
                }
                if ($context === '_NO_INPUT_HERE_') {
                    $ret = $else($cx, $_this);
                } else {
                    $cx['scopes'][] = $_this;
                    $ret = $else($cx, $context);
                    array_pop($cx['scopes']);
                }
                return $cx['flags']['echo'] ? ob_get_clean() : $ret;
            };
        } else {
            $options['inverse'] = function () {
                return '';
            };
        }

        return static::exch($cx, $ch, $vars, $options);
    }

    /**
     * Execute custom helper with prepared options
     *
     * @param array<string,array|string|integer> $cx render time context for lightncandy
     * @param string $ch the name of custom helper to be executed
     * @param array<array|string|integer>|string|integer|null $vars variables for the helper
     * @param array<string,array|string|integer> $options the options object
     *
     * @return string The rendered string of the token
     */
    public static function exch($cx, $ch, $vars, &$options)
    {
        $args = $vars[0];
        $args[] = &$options;
        $r = true;

        try {
            $r = call_user_func_array($cx['helpers'][$ch], $args);
        } catch (\Throwable $E) {
            static::err($cx, "Runtime: call custom helper '$ch' error: " . $E->getMessage());
        }

        return $r;
    }
}
