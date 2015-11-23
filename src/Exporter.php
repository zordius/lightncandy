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
 * file to keep LightnCandy Exporter
 *
 * @package    LightnCandy
 * @author     Zordius <zordius@yahoo-inc.com>
 */

namespace LightnCandy;

/**
 * LightnCandy major static class
 */
class Exporter
{
    /**
     * Get PHP code string from a closure of function as string
     *
     * @param object $closure Closure object
     *
     * @return string
     *
     * @expect 'function($a) {return;}' when input function ($a) {return;}
     * @expect 'function($a) {return;}' when input    function ($a) {return;}
     */
    protected static function closure($closure) {
        if (is_string($closure) && preg_match('/(.+)::(.+)/', $closure, $matched)) {
            $ref = new \ReflectionMethod($matched[1], $matched[2]);
        } else {
            $ref = new \ReflectionFunction($closure);
        }
        $fname = $ref->getFileName();

        $lines = file_get_contents($fname);
        $file = new \SplFileObject($fname);
        $file->seek($ref->getStartLine() - 2);
        $spos = $file->ftell();
        $file->seek($ref->getEndLine() - 1);
        $epos = $file->ftell();

        return preg_replace('/^.*?function(\s+[^\s\\(]+?)?\s*?\\((.+?)\\}[,\\s]*;?$/s', 'function($2}', substr($lines, $spos, $epos - $spos));
    }

    /**
     * Export required custom helper functions
     *
     * @param string $tname   helper table name
     * @param array<string,array|string|integer> $context current compile context
     *
     * @return string
     */
    public static function helpers($context, $tname = 'helpers') {
        $ret = '';
        foreach ($context[$tname] as $name => $func) {
            if (!isset($context['usedCount'][$tname][$name])) {
                continue;
            }
            if ((is_object($func) && ($func instanceof \Closure)) || ($context['flags']['exhlp'] == 0)) {
                $ret .= ("            '$name' => " . static::closure($func) . ",\n");
                continue;
            }
            $ret .= "            '$name' => '$func',\n";
        }

        return "array($ret)";
    }

    /**
     * Export required standalone Runtime methods
     *
     * @param array<string,array|string|integer> $context current compile context
     *
     * @return string
     */
    public static function runtime($context) {
        if ($context['flags']['standalone'] == 0) {
            return '';
        }

        $class = new \ReflectionClass($context['runtime']);
        $fname = $class->getFileName();
        $lines = file_get_contents($fname);
        $file = new \SplFileObject($fname);
        $methods = array();
        $ret = "'funcs' => array(\n";

        foreach ($class->getMethods() as $method) {
            $name = $method->getName();
            $file->seek($method->getStartLine() - 2);
            $spos = $file->ftell();
            $file->seek($method->getEndLine() - 2);
            $epos = $file->ftell();
            $methods[$name] = static::scanDependency($context, preg_replace('/public static function (.+)\\(/', '\'$1\' => function (', substr($lines, $spos, $epos - $spos)));
        }
        unset($file);

        $exports = array_keys($context['usedCount']['runtime']);

        while (true) {
            if (array_sum(array_map(function ($name) use (&$exports, $methods) {
                $n = 0;
                foreach ($methods[$name][1] as $child => $count) {
                    if (!in_array($child, $exports)) {
                       $exports[] = $child;
                       $n++;
                    }
                }
                return $n;
            }, $exports)) == 0) {
                break;
            }
        }

        foreach ($exports as $export) {
            $ret .= ($methods[$export][0] . "    },\n");
        }

        return "$ret)\n";
    }

    /**
     * Export Runtime constants
     *
     * @param array<string,array|string|integer> $context current compile context
     *
     * @return string
     */
    public static function constants($context) {
        if ($context['flags']['standalone'] == 0) {
            return 'array()';
        }

        $class = new \ReflectionClass($context['runtime']);
        $constants = $class->getConstants();
        $ret = " array(\n";
        foreach($constants as $name => $value) {
            $ret .= "            '$name' => ".  (is_string($value) ? "'$value'" : $value ) . ",\n";
        }
        $ret .= "        )";
        return $ret;
    }

    /**
     * Scan for required standalone functions
     *
     * @param array<string,array|string|integer> $context current compile context
     * @param string $code PHP code string of the method
     *
     * @return array<string|array> list of converted code and children array
     */
    protected static function scanDependency($context, $code) {
        $child = array();

        $code = preg_replace_callback('/static::(\w+?)\s*\(/', function ($matches) use ($context, &$child) {
            if (!isset($child[$matches[1]])) {
                $child[$matches[1]] = 0;
            }
            $child[$matches[1]]++;

            return "\$cx['funcs']['{$matches[1]}'](";
        }, $code);

        // replace the constants
        $code = preg_replace('/static::([A-Z0-9_]+)/', "\$cx['constants']['$1']", $code);
        return array($code, $child);
    }
}

