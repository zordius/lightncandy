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
 * file to keep LightnCandy partial methods
 *
 * @package    LightnCandy
 * @author     Zordius <zordius@yahoo-inc.com>
 */

namespace LightnCandy;

use \LightnCandy\Compiler;
use \LightnCandy\String;
use \LightnCandy\Context;

/**
 * LightnCandy Partial handler
 */
class Partial {
    public static $TMP_JS_FUNCTION_STR = "!!\aFuNcTiOn\a!!";                                            

    /**
     * Include all partials when using dynamic partials
     */
    public static function handleDynamicPartial(&$context) {
        if ($context['usedFeature']['dynpartial'] == 0) {
            return;
        }

        foreach ($context['partials'] as $name => $code) {
            static::readPartial($name, $context);
        }
    }

    /**
     * Read partial file content as string and store in context
     *
     * @param string $name partial name
     * @param array<string,array|string|integer> $context Current context of compiler progress.
     */
    public static function readPartial($name, &$context) {
        $context['usedFeature']['partial']++;

        if (isset($context['usedPartial'][$name])) {
            return;
        }

        $cnt = static::resolvePartial($name, $context);

        if ($cnt !== null) {
            $context['usedPartial'][$name] = $cnt;
            return static::compileDynamic($name, $context, $cnt);
        }

        if (!$context['flags']['skippartial']) {
            $context['error'][] = "Can not find partial file for '$name', you should set correct basedir and fileext in options";
        }
    }

    /**
     * preprocess partial template before it be stored into context
     *
     * @param string $tmpl partial template
     * @param string $name partial name
     * @param array<string,array|string|integer> $context Current context of compiler progress.
     *
     * @return string|null $content processed partial template
     *
     * @expect 'hey' when input 'hey', 'haha', Array('prepartial' => false)
     * @expect 'haha-hoho' when input 'hoho', 'haha', Array('prepartial' => function ($tmpl, $name) {return "$name-$tmpl";})
     */
    protected static function prePartial($tmpl, &$name, &$context) {
        return $context['prepartial'] ? $context['prepartial']($tmpl, $name, $context) : $tmpl;
    }

    /**
     * locate partial file, return the file name
     *
     * @param string $name partial name
     * @param array<string,array|string|integer> $context Current context of compiler progress.
     *
     * @return string|null $content partial content
     */
    protected static function resolvePartial(&$name, &$context) {
        if (isset($context['partials'][$name])) {
            return static::prePartial($context['partials'][$name], $name, $context);
        }

        foreach ($context['basedir'] as $dir) {
            foreach ($context['fileext'] as $ext) {
                $fn = "$dir/$name$ext";
                if (file_exists($fn)) {
                    return static::prePartial(file_get_contents($fn), $name, $context);
                }
            }
        }
        return null;
    }

    /**
     * compile a partial to static embed PHP code
     *
     * @param array<string,array|string|integer> $context Current context of compiler progress.
     * @param string $name partial name
     *
     * @return string $code PHP code string
     */
    public static function compileStatic(&$context, $name) {
        // Check for recursive partial
        if (!$context['flags']['runpart']) {
            $context['partialStack'][] = $name;
            $diff = count($context['partialStack']) - count(array_unique($context['partialStack']));
            if ($diff) {
                $context['error'][] = 'I found recursive partial includes as the path: ' . implode(' -> ', $context['partialStack']) . '! You should fix your template or compile with LightnCandy::FLAG_RUNTIMEPARTIAL flag.';
            }
        }

        $code = Compiler::compileTemplate($context, preg_replace('/^/m', $context['tokens']['partialind'], $context['usedPartial'][$name]));

        if (!$context['flags']['runpart']) {
            array_pop($context['partialStack']);
        }

        return $code;
    }

    /**
     * compile partial file, stored in context
     *
     * @param string $name partial name
     * @param array<string,array|string|integer> $context Current context of compiler progress.
     * @param string $content partial content
     */
    protected static function compileDynamic(&$name, &$context, $content) {
        if (!$context['flags']['runpart']) {
            return;
        }

        $tmpContext = $context;
        $code = Compiler::compileTemplate($tmpContext, str_replace('function', static::$TMP_JS_FUNCTION_STR, $context['usedPartial'][$name]), $name);
        Context::merge($context, $tmpContext);
        if (!$context['flags']['noind']) {
            $sp = ', $sp';
            $code = preg_replace('/^/m', "'{$context['ops']['seperator']}\$sp{$context['ops']['seperator']}'", $code);
            // callbacks inside partial should be aware of $sp
            $code = preg_replace('/\bfunction\s*\((.*?)\)\s*{/', 'function(\\1)use($sp){', $code);
        } else {
            $sp = '';
        }
        $code = str_replace(String::escapeTemplate(static::$TMP_JS_FUNCTION_STR), 'function', $code);
        $context['partialCode'] .= "'$name' => function (\$cx, \$in{$sp}) {{$context['ops']['op_start']}'$code'{$context['ops']['op_end']}},";
    }
}

