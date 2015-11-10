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
 * file to handle LightnCandy Context
 *
 * @package    LightnCandy
 * @author     Zordius <zordius@yahoo-inc.com>
 */

namespace LightnCandy;

use \LightnCandy\Flags;

/**
 * LightnCandy class to handle Context
 */
class Context extends Flags {
    /**
     * Create a context from options
     *
     * @param array<string,array|string|integer> $options input options
     *
     * @return array<string,array|string|integer> Context from options
     */
    public static function create($options) {
        if (!is_array($options)) {
            $options = array();
        }

        $flags = isset($options['flags']) ? $options['flags'] : self::FLAG_BESTPERFORMANCE;

        $context = array(
            'flags' => array(
                'errorlog' => $flags & self::FLAG_ERROR_LOG,
                'exception' => $flags & self::FLAG_ERROR_EXCEPTION,
                'skippartial' => $flags & self::FLAG_ERROR_SKIPPARTIAL,
                'standalone' => $flags & self::FLAG_STANDALONE,
                'bare' => $flags & self::FLAG_BARE,
                'noesc' => $flags & self::FLAG_NOESCAPE,
                'jstrue' => $flags & self::FLAG_JSTRUE,
                'jsobj' => $flags & self::FLAG_JSOBJECT,
                'jsquote' => $flags & self::FLAG_JSQUOTE,
                'this' => $flags & self::FLAG_THIS,
                'with' => $flags & self::FLAG_WITH,
                'parent' => $flags & self::FLAG_PARENT,
                'echo' => $flags & self::FLAG_ECHO,
                'advar' => $flags & self::FLAG_ADVARNAME,
                'namev' => $flags & self::FLAG_NAMEDARG,
                'spvar' => $flags & self::FLAG_SPVARS,
                'slash' => $flags & self::FLAG_SLASH,
                'else' => $flags & self::FLAG_ELSE,
                'exhlp' => $flags & self::FLAG_EXTHELPER,
                'lambda' => $flags & self::FLAG_HANDLEBARSLAMBDA,
                'mustlok' => $flags & self::FLAG_MUSTACHELOOKUP,
                'mustlam' => $flags & self::FLAG_MUSTACHELAMBDA,
                'noind' => $flags & self::FLAG_PREVENTINDENT,
                'debug' => $flags & self::FLAG_RENDER_DEBUG,
                'prop' => $flags & self::FLAG_PROPERTY,
                'method' => $flags & self::FLAG_METHOD,
                'runpart' => $flags & self::FLAG_RUNTIMEPARTIAL,
                'rawblock' => $flags & self::FLAG_RAWBLOCK,
            ),
            'level' => 0,
            'scan' => true,
            'stack' => array(),
            'error' => array(),
            'basedir' => self::prepareBasedir($options),
            'fileext' => self::prepareFileext($options),
            'tokens' => array(
                'standalone' => true,
                'ahead' => false,
                'current' => 0,
                'count' => 0,
                'partialind' => '',
            ),
            'usedPartial' => array(),
            'partialStack' => array(),
            'partialCode' => '',
            'usedFeature' => array(
                'rootthis' => 0,
                'enc' => 0,
                'raw' => 0,
                'sec' => 0,
                'isec' => 0,
                'if' => 0,
                'else' => 0,
                'unless' => 0,
                'each' => 0,
                'this' => 0,
                'parent' => 0,
                'with' => 0,
                'comment' => 0,
                'partial' => 0,
                'dynpartial' => 0,
                'helper' => 0,
                'bhelper' => 0,
                'hbhelper' => 0,
                'delimiter' => 0,
                'subexp' => 0,
                'rawblock' => 0,
            ),
            'usedCount' => array(
                'var' => array(),
                'helpers' => array(),
                'blockhelpers' => array(),
                'hbhelpers' => array(),
                'runtime' => array(),
            ),
            'partials' => (isset($options['partials']) && is_array($options['partials'])) ? $options['partials'] : array(),
            'helpers' => array(),
            'blockhelpers' => array(),
            'hbhelpers' => array(),
            'renderex' => isset($options['renderex']) ? $options['renderex'] : '',
            'prepartial' => (isset($options['prepartial']) && is_callable($options['prepartial'])) ? $options['prepartial'] : false,
            'runtime' => isset($options['runtime']) ? $options['runtime'] : '\\LightnCandy\\Runtime',
            'rawblock' => false,
        );

        $context['ops'] = $context['flags']['echo'] ? array(
            'seperator' => ',',
            'f_start' => 'echo ',
            'f_end' => ';',
            'op_start' => 'ob_start();echo ',
            'op_end' => ';return ob_get_clean();',
            'cnd_start' => ';if ',
            'cnd_then' => '{echo ',
            'cnd_else' => ';}else{echo ',
            'cnd_end' => ';}echo ',
        ) : array(
            'seperator' => '.',
            'f_start' => 'return ',
            'f_end' => ';',
            'op_start' => 'return ',
            'op_end' => ';',
            'cnd_start' => '.(',
            'cnd_then' => ' ? ',
            'cnd_else' => ' : ',
            'cnd_end' => ').',
        );

        $context['ops']['enc'] = $context['flags']['jsquote'] ? 'encq' : 'enc';
        self::updateHelperTable($context, $options);
        self::updateHelperTable($context, $options, 'blockhelpers');
        self::updateHelperTable($context, $options, 'hbhelpers');

        return $context;
    }

    /**
     * prepare list of template file extensions from options
     *
     * @param array<string,array|string|integer> $options current compile option
     *
     * @return array<string> file extensions
     *
     * @expect array('.tmpl') when input array()
     * @expect array('test') when input array('fileext' => 'test')
     * @expect array('test1') when input array('fileext' => array('test1'))
     * @expect array('test2', 'test3') when input array('fileext' => array('test2', 'test3'))
     */
    protected static function prepareFileExt($options) {
        $exts = isset($options['fileext']) ? $options['fileext'] : '.tmpl';
        return is_array($exts) ? $exts : array($exts);
    }

    /**
     * prepare list of base directory from options
     *
     * @param array<string,array|string|integer> $options current compile option
     *
     * @return array<string> base directories
     *
     * @expect array() when input array()
     * @expect array() when input array('basedir' => array())
     * @expect array('src') when input array('basedir' => array('src'))
     * @expect array('src') when input array('basedir' => array('src', 'dir_not_found'))
     * @expect array('src', 'tests') when input array('basedir' => array('src', 'tests'))
     */
    protected static function prepareBaseDir($options) {
        $dirs = isset($options['basedir']) ? $options['basedir'] : 0;
        $dirs = is_array($dirs) ? $dirs : array($dirs);
        $ret = array();

        foreach ($dirs as $dir) {
            if (is_string($dir) && is_dir($dir)) {
                $ret[] = $dir;
            }
        }

        return $ret;
    }

    /**
     * update specific custom helper table from options
     *
     * @param array<string,array|string|integer> $context prepared context
     * @param array<string,array|string|integer> $options input options
     * @param string $tname helper table name
     *
     * @return array<string,array|string|integer> context with generated helper table
     *
     * @expect array() when input array(), array()
     * @expect array('flags' => array('exhlp' => 1)) when input array('flags' => array('exhlp' => 1)), array('helpers' => array('abc'))
     * @expect array('error' => array('Can not find custom helper function defination abc() !'), 'flags' => array('exhlp' => 0)) when input array('error' => array(), 'flags' => array('exhlp' => 0)), array('helpers' => array('abc'))
     * @expect array('flags' => array('exhlp' => 1), 'helpers' => array('Runtime::raw' => 'Runtime::raw')) when input array('flags' => array('exhlp' => 1), 'helpers' => array()), array('helpers' => array('Runtime::raw'))
     * @expect array('flags' => array('exhlp' => 1), 'helpers' => array('test' => 'Runtime::raw')) when input array('flags' => array('exhlp' => 1), 'helpers' => array()), array('helpers' => array('test' => 'Runtime::raw'))
     */
    protected static function updateHelperTable(&$context, $options, $tname = 'helpers') {
        if (isset($options[$tname]) && is_array($options[$tname])) {
            foreach ($options[$tname] as $name => $func) {
                if (is_callable($func)) {
                    $context[$tname][is_int($name) ? $func : $name] = $func;
                } else {
                    if (is_array($func)) {
                        $context['error'][] = "I found an array in $tname with key as $name, please fix it.";
                    } else {
                        if (!$context['flags']['exhlp']) {
                            $context['error'][] = "Can not find custom helper function defination $func() !";
                        }
                    }
                }
            }
        }
        return $context;
    }
}

