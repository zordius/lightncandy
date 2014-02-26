<?php
/* 

Copyrights for code authored by Yahoo! Inc. is licensed under the following terms:
MIT License
Copyright (c) 2013 Yahoo! Inc. All Rights Reserved.
Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:
The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.
THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.

Origin: https://github.com/zordius/lightncandy 
*/

/**
 * This is abstract engine which defines must-have methods.
 *
 * @package    LightnCandy
 * @author     Zordius <zordius@yahoo-inc.com>
 */

/**
 * LightnCandy static core class.
 */
class LightnCandy {
    // Compile time error handling flags
    const FLAG_ERROR_LOG = 1;
    const FLAG_ERROR_EXCEPTION = 2;

    // Compile the template as standalone php code which can execute without including LightnCandy
    const FLAG_STANDALONE = 4;

    // Handlebars.js compatibility
    const FLAG_JSTRUE = 8;
    const FLAG_JSOBJECT = 16;
    const FLAG_THIS = 32;
    const FLAG_WITH = 64;
    const FLAG_PARENT = 128;
    const FLAG_JSQUOTE = 256;
    const FLAG_ADVARNAME = 512;
    const FLAG_SPACECTL = 1024;

    // Custom helper options
    const FLAG_EXTHELPER = 2048;

    // PHP performance flags
    const FLAG_ECHO = 4096;

    const FLAG_BESTPERFORMANCE = 4096; // FLAG_ECHO
    const FLAG_HANDLEBARSJS = 2040; // FLAG_JSTRUE + FLAG_JSOBJECT + FLAG_THIS + FLAG_WITH + FLAG_PARENT + FLAG_JSQUOTE + FLAG_ADVARNAME + FLAG_SPACECTL

    const PARTIAL_SEARCH = '/\\{\\{>[ \\t]*(.+?)[ \\t]*\\}\\}/s';
    const TOKEN_SEARCH = '/(\s*)(\\{{2,3})(~?)([\\^#\\/!]?)(.+?)(~?)(\\}{2,3})(\s*)/s';
    const VARNAME_SEARCH = '/(\\[[^\\]]+\\]|[^\\[\\]\\.]+)/';

    private static $lastContext;

    /**
     * Compile handlebars template into PHP code.
     *
     * @param string $template handlebars template string
     * @param array $options LightnCandy compile time and run time options, default is Array('flags' => LightnCandy::FLAG_BESTPERFORMANCE)
     *
     * @return string Compiled PHP code when successed. If error happened and compile failed, return false.
     *
     * @codeCoverageIgnore
     */
    public static function compile($template, $options) {
        if (!is_array($options)) {
            $options = Array();
        }

        $flags = isset($options['flags']) ? $options['flags'] : self::FLAG_BESTPERFORMANCE;

        $context = Array(
            'flags' => Array(
                'errorlog' => $flags & self::FLAG_ERROR_LOG,
                'exception' => $flags & self::FLAG_ERROR_EXCEPTION,
                'standalone' => $flags & self::FLAG_STANDALONE,
                'jstrue' => $flags & self::FLAG_JSTRUE,
                'jsobj' => $flags & self::FLAG_JSOBJECT,
                'jsquote' => $flags & self::FLAG_JSQUOTE,
                'this' => $flags & self::FLAG_THIS,
                'with' => $flags & self::FLAG_WITH,
                'parent' => $flags & self::FLAG_PARENT,
                'echo' => $flags & self::FLAG_ECHO,
                'advar' => $flags & self::FLAG_ADVARNAME,
                'exhlp' => $flags & self::FLAG_EXTHELPER,
            ),
            'level' => 0,
            'stack' => Array(),
            'error' => Array(),
            'useVar' => false,
            'vars' => Array(),
            'sp_vars' => Array(),
            'jsonSchema' => Array(
                '$schema' => 'http://json-schema.org/draft-03/schema',
                'description' => 'Template Json Schema'
            ),
            'basedir' => self::_basedir($options),
            'fileext' => self::_fileext($options),
            'usedPartial' => Array(),
            'usedFeature' => Array(
                'rootvar' => 0,
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
                'dot' => 0,
                'comment' => 0,
                'partial' => 0,
                'helper' => 0,
            ),
            'helpers' => Array(),
        );

        $context['ops'] = $context['flags']['echo'] ? Array(
            'seperator' => ',',
            'f_start' => 'echo ',
            'f_end' => ';',
            'op_start' => 'ob_start();echo ',
            'op_end' => ';return ob_get_clean();',
            'cnd_start' => ';if ',
            'cnd_then' => '{echo ',
            'cnd_else' => ';}else{echo ',
            'cnd_end' => ';}echo ',
        ) : Array(
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

        // Generate custom helpers table.
        if (isset($options['helpers']) && is_array($options['helpers'])) {
            foreach ($options['helpers'] as $name => $func) {
                if (is_callable($func)) {
                    $context['helpers'][is_int($name) ? $func : $name] = $func;
                } else {
                    if (!$context['flags']['exhlp']) {
                        $context['error'][] = "Can not find custom helper function defination $func() !";
                    }
                }
            }
        }

        // Scan for partial and replace partial with template.
        $template = LightnCandy::expandPartial($template, $context);

        if (self::_error($context)) {
            return false;
        }

        // Do first time scan to find out used feature, detect template error.
        if (preg_match_all(self::TOKEN_SEARCH, $template, $tokens, PREG_SET_ORDER) > 0) {
            foreach ($tokens as $token) {
                self::scan($token, $context);
            }
        }

        if (self::_error($context)) {
            return false;
        }

        // Check used features and compile flags. If the template is simple enough,
        // we can generate best performance code with enable 'useVar' internal flag.
        if (!$context['flags']['jsobj'] && (($context['usedFeature']['sec'] + $context['usedFeature']['parent'] < 1) || !$context['flags']['jsobj'])) {
            $context['useVar'] = '$in';
        }

        // Do PHP code and json schema generation.
        $code = preg_replace_callback(self::TOKEN_SEARCH, function ($matches) use (&$context) {
            $tmpl = LightnCandy::tokens($matches, $context);
            return "{$matches[1]}'$tmpl'{$matches[8]}";
        }, addcslashes($template, "'"));

        if (self::_error($context)) {
            return false;
        }

        $flagJStrue = self::_on($context['flags']['jstrue']);
        $flagJSObj = self::_on($context['flags']['jsobj']);

        $libstr = self::exportLCRun($context);
        $helpers = self::exportHelper($context);

        // Return generated PHP code string.
        return "<?php return function (\$in) {
    \$cx = Array(
        'flags' => Array(
            'jstrue' => $flagJStrue,
            'jsobj' => $flagJSObj,
        ),
        'helpers' => $helpers,
        'scopes' => Array(\$in),
        'path' => Array(),
$libstr
    );
    {$context['ops']['op_start']}'$code'{$context['ops']['op_end']}
}
?>";
    }

    /**
     * Expand partial string recursively.
     *
     * @param string $template template string
     *
     * @return string partial file content
     */
    public static function expandPartial($template, &$context) {
        $template = preg_replace_callback(self::PARTIAL_SEARCH, function ($matches) use (&$context) {
            return LightnCandy::expandPartial(LightnCandy::readPartial($matches[1], $context), $context);
        }, $template);
        return $template;
    }

    /**
     * Read partial file content as string.
     *
     * @param string $name partial file name
     * @param array $context Current context of compiler progress.
     *
     * @return string partial file content
     */
    public static function readPartial($name, &$context) {
        $f = preg_split('/[ \\t]/', $name);
        $context['usedFeature']['partial']++;
        foreach ($context['basedir'] as $dir) {
            foreach ($context['fileext'] as $ext) {
                $fn = "$dir/$f[0]$ext";
                if (file_exists($fn)) {
                    return file_get_contents($fn);
                }
            }
        }
        $context['error'][] = "can not find partial file for '$name', you should set correct basedir and fileext in options";
    }

    /**
     * Internal method used by compile(). Check options and handle fileext.
     *
     * @param array $options current compile option
     *
     * @return array file extensions
     *
     * @expect Array('.tmpl') when input Array()
     * @expect Array('test') when input Array('fileext' => 'test')
     * @expect Array('test1') when input Array('fileext' => Array('test1'))
     * @expect Array('test2', 'test3') when input Array('fileext' => Array('test2', 'test3'))
     */
    protected static function _fileext($options) {
        $exts = isset($options['fileext']) ? $options['fileext'] : '.tmpl';
        return is_array($exts) ? $exts : Array($exts);
    }

    /**
     * Internal method used by compile(). Check options and handle basedir.
     *
     * @param array $options current compile option
     *
     * @return array base directories
     *
     * @expect Array(getcwd()) when input Array()
     * @expect Array(getcwd()) when input Array('basedir' => 0)
     * @expect Array(getcwd()) when input Array('basedir' => '')
     * @expect Array(getcwd()) when input Array('basedir' => Array())
     * @expect Array('src') when input Array('basedir' => Array('src'))
     * @expect Array(getcwd()) when input Array('basedir' => Array('*dir*not*found'))
     * @expect Array('src') when input Array('basedir' => Array('src', 'dir*not*found'))
     * @expect Array('src', 'build') when input Array('basedir' => Array('src', 'build'))
     */
    protected static function _basedir($options) {
        $dirs = isset($options['basedir']) ? $options['basedir'] : 0;
        $dirs = is_array($dirs) ? $dirs : Array($dirs);
        $ret = Array();

        foreach ($dirs as $dir) {
            if (is_string($dir) && is_dir($dir)) {
                $ret[] = $dir;
            }
        }

        if (count($ret) === 0) {
            $ret[] = getcwd();
        }

        return $ret;
    }

    /**
     * Internal method used by compile(). Get PHP code from a closure of function as string.
     *
     * @param object $closure Closure object
     *
     * @codeCoverageIgnore
     */
    protected static function getPHPCode($closure) {
        if (is_string($closure) && preg_match('/(.+)::(.+)/', $closure, $matched)) {
            $ref = new ReflectionMethod($matched[1], $matched[2]);
        } else {
            $ref = new ReflectionFunction($closure);
        }
        $fname = $ref->getFileName();
        $lines = file_get_contents($fname);
        $file = new SplFileObject($fname);

        $file->seek($ref->getStartLine() - 2);
        $spos = $file->ftell();
        $file->seek($ref->getEndLine() - 1);
        $epos = $file->ftell();
        return preg_replace('/^.*?function\s.*?\\((.+?)\\}[,\\s]*$/s', 'function($1}', substr($lines, $spos, $epos - $spos));
    }

    /**
     * Internal method used by compile(). Export required custom helper functions.
     *
     * @param array $context current scaning context
     *
     * @codeCoverageIgnore
     */
    protected static function exportHelper($context) {
        $ret = '';
        foreach ($context['helpers'] as $name => $func) {
            if ((is_object($func) && ($func instanceof Closure)) || ($context['flags']['exhlp'] == 0)) {
                $ret .= ("            '$name' => " . self::getPHPCode($func) . ",\n");
                continue;
            }
            $ret .= "            '$name' => '$func',\n";
        }

        return "Array($ret)";
    }

    /**
     * Internal method used by compile(). Export required standalone functions.
     *
     * @param array $context current scaning context
     *
     * @codeCoverageIgnore
     */
    protected static function exportLCRun($context) {
        if ($context['flags']['standalone'] == 0) {
            return '';
        }

        $class = new ReflectionClass('LCRun');
        $fname = $class->getFileName();
        $lines = file_get_contents($fname);
        $file = new SplFileObject($fname);
        $ret = "'funcs' => Array(\n";

        foreach ($class->getMethods() as $method) {
            $file->seek($method->getStartLine() - 2);
            $spos = $file->ftell();
            $file->seek($method->getEndLine() - 2);
            $epos = $file->ftell();
            $ret .= preg_replace('/self::(.+)\(/', '\\$cx[\'funcs\'][\'$1\'](', preg_replace('/public static function (.+)\\(/', '\'$1\' => function (', substr($lines, $spos, $epos - $spos))) . "    },\n";
        }
        unset($file);
        return "$ret)\n";
    }

    /**
     * Internal method used by compile(). Handle exists error and return error status.
     *
     * @param array $context Current context of compiler progress.
     *
     * @return boolean True when error detected
     *
     * @expect true when input Array('level' => 1, 'stack' => Array('X'), 'flags' => Array('errorlog' => 0, 'exception' => 0), 'error' => Array())
     * @expect false when input Array('level' => 0, 'error' => Array())
     * @expect true when input Array('level' => 0, 'error' => Array('some error'), 'flags' => Array('errorlog' => 0, 'exception' => 0))
     */
    protected static function _error(&$context) {
        if ($context['level'] !== 0) {
            $token = array_pop($context['stack']);
            $context['error'][] = "Unclosed token {{{#$token}}} !!";
        }

        self::$lastContext = $context;

        if (count($context['error'])) {
            if ($context['flags']['errorlog']) {
                error_log(implode("\n", $context['error']));
            }
            if ($context['flags']['exception']) {
                throw new Exception($context['error']);
            }
            return true;
        }
        return false;
    }

    /**
     * Internal method used by compile(). Return 'true' or 'false' string.
     *
     * @param mixed $v value
     *
     * @return string 'true' when the value larger then 0
     *
     * @expect 'true' when input 1
     * @expect 'true' when input 999
     * @expect 'false' when input 0
     * @expect 'false' when input -1
     */
    protected static function _on($v) {
        return ($v > 0) ? 'true' : 'false';
    }

    /**
     * Get last compiler context.
     *
     * @return array Context data
     *
     * @codeCoverageIgnore
     */
    public static function getContext() {
        return self::$lastContext;
    }

    /**
     * Get JsonSchema of last compiled handlebars template.
     *
     * @return array JsonSchema data
     *
     * @codeCoverageIgnore
     */
    public static function getJsonSchema() {
        return self::$lastContext['jsonSchema'];
    }

    /**
     * Get JsonSchema of last compiled handlebars template as pretty printed string.
     *
     * @param string $indent indent string.
     *
     * @return string JsonSchema string
     *
     * @codeCoverageIgnore
     */
    public static function getJsonSchemaString($indent = '  ') {
        $level = 0;
        return preg_replace_callback('/\\{|\\[|,|\\]|\\}|:/', function ($matches) use (&$level) {
            switch ($matches[0]) {
                case '}':
                case ']':
                    $level--;
                    $is = str_repeat($indent, $level);
                    return "\n$is{$matches[0]}";
                case ':':
                    return ': ';
            }
            $br = '';
            switch ($matches[0]) {
                case '{':
                case '[':
                    $level++;
                case ',':
                    $br = "\n";
            }
            $is = str_repeat($indent, $level);
            return "{$matches[0]}$br$is";
        }, json_encode(self::getJsonSchema()));
    }

    /**
     * Get a working render function by a string of PHP code. This method may requires php setting allow_url_include=1 and allow_url_fopen=1 , or access right to tmp file system.
     *
     * @param string $php php codes
     * @param string $tmp_dir optional, change temp directory for php include file saved by prepare() when can not include php code with data:// format.
     *
     * @return mixed result of include()
     *
     * @codeCoverageIgnore
     */
    public static function prepare($php, $tmp_dir = false) {
        if (!ini_get('allow_url_include') || !ini_get('allow_url_fopen')) {
            if (!is_dir($tmp_dir)) {
                $tmp_dir = sys_get_temp_dir();
            }
        }

        if ($tmp_dir) {
            $fn = tempnam($tmp_dir, 'lci_');
            if (!$fn) {
                die("Can not generate tmp file under $tmp_dir!!\n");
            }
            if (!file_put_contents($fn, $php)) {
                die("Can not include saved temp php code from $fn, you should add $tmp_dir into open_basedir!!\n");
            }
            return include($fn);
        }

        return include('data://text/plain,' . urlencode($php));
    }

    /**
     * Use a saved PHP file to render the input data.
     *
     * @param string $compiled compiled template php file name
     *
     * @return string rendered result
     *
     * @codeCoverageIgnore
     */
    public static function render($compiled, $data) {
        $func = include($compiled);
        return $func($data);
    }

    /**
     * Internal method used by compile(). Get function name for standalone or none standalone tempalte.
     *
     * @param array $context Current context of compiler progress.
     * @param string $name base function name
     *
     * @return string compiled Function name
     *
     * @expect 'LCRun::test' when input Array('flags' => Array('standalone' => 0)), 'test'
     * @expect 'LCRun::test2' when input Array('flags' => Array('standalone' => 0)), 'test2'
     * @expect "\$cx['funcs']['test3']" when input Array('flags' => Array('standalone' => 1)), 'test3'
     */
    protected static function _fn($context, $name) {
        return $context['flags']['standalone'] ? "\$cx['funcs']['$name']" : "LCRun::$name";
    }

    /**
     * Internal method used by _qscope(). Get variable names translated string.
     *
     * @param array $scopes an array of variable names with single quote
     *
     * @return string PHP array names string
     * 
     * @expect '' when input Array()
     * @expect '[a]' when input Array('a')
     * @expect '[a][b][c]' when input Array('a', 'b', 'c')
     */
    protected static function _scope($scopes) {
        return count($scopes) ? '[' . implode('][', $scopes) . ']' : '';
    }

    /**
     * Internal method used by _vn(). Get variable names translated string.
     *
     * @param array $list an array of variable names.
     *
     * @return string PHP array names string
     * 
     * @expect '' when input Array()
     * @expect "['a']" when input Array('a')
     * @expect "['a']['b']['c']" when input Array('a', 'b', 'c')
     */
    protected static function _qscope($list) {
        return self::_scope(array_map(function ($v) {return "'$v'";}, $list));
    }

    /**
     * Internal method used by compile(). Get variable names translated string.
     *
     * @param string $vn variable name.
     * @param integer $adv 0 to disable advanced veriable naming, N to enable a.[0].[#123] style.
     *
     * @return string Translated variable name as input array notation.
     * 
     * @expect '' when input '', 0
     * @expect "['a']" when input 'a', 0
     * @expect "['a']" when input 'a', 1
     * @expect "['b']['c']" when input 'b.c', 0
     * @expect "['b']['c']" when input 'b.c', 1
     * @expect "['d']['e']['f']" when input 'd.e.f', 0
     * @expect "['d']['e']['f']" when input 'd.e.f', 1
     * @expect "['[g']['h]']['i']" when input '[g.h].i', 0
     * @expect "['g.h']['i']" when input '[g.h].i', 1
     */
    protected static function _vn($vn, $adv) {
        if ($adv) {
            return $vn ? self::_advn($vn) : '';
        }
        return $vn ? self::_qscope(explode('.', $vn)) : '';
    }

    /**
     * Internal method used by compile(). Get translated variable name.
     *
     * @param string $vn variable name. Illegal variable name will be removed and will never pass into this function.
     *
     * @return string Translated advanced format variable name as input array notation.
     * 
     * @expect "['']" when input ''
     * @expect "['a']" when input 'a'
     * @expect "['a']" when input '[a]'
     * @expect "['a']['b']" when input '[a].b'
     * @expect "['a']['b']" when input 'a.b'
     * @expect "['a']['b']" when input 'a.[b]'
     * @expect "['a']['b[c']" when input 'a.[b[c]'
     * @expect "['a']['b.c']" when input 'a.[b.c]'
     * @expect "['a.b']" when input '[a.b]'
     */
    protected static function _advn($vn) {
        if (!preg_match('/[\\.\\]\\[]/', $vn)) {
            return "['" . $vn . "']";
        }
        if (!preg_match('/\\./', $vn)) {
            return "['" . substr($vn, 1, -1) . "']";
        }
        return preg_replace('/\\]\\.\\[/', '][', preg_replace_callback(self::VARNAME_SEARCH, function ($matches) {
            if (substr($matches[1], 0, 1) === '[') {
                return "['" . substr($matches[1], 1, -1) . "']";
            } else {
                return "['" . $matches[1] . "']";
            }
        }, $vn));
    }

    /**
     * Internal method used by compile(). Fix the variable name to null when reference to {{this}} or {{.}} . When advanced variable name enabled, convert foo.[ba.r].test to foo]ba.r]test . (Always use ] as name spacing notation)
     *
     * @param mixed $v variable name to be fixed.
     * @param array $context Current compile content.
     * 
     */
    protected static function _vx(&$v, $context) {
        $v = trim($v);
        if ($context['flags']['this']) {
            if (($v == 'this') || $v == '.') {
                $v = null;
                return;
            }
        }

        if ($context['flags']['advar'] && preg_match('/\\]/', $v)) {
            $v = substr(preg_replace('/\\]\\.\\[/', ']', preg_replace_callback(self::VARNAME_SEARCH, function ($matches) {
                if (substr($matches[1], 0, 1) === '[') {
                    return $matches[1];
                } else {
                    return '[' . $matches[1] . ']';
                }
            }, $v)) , 1, -1);
        } else {
            $v = preg_replace('/([^\\.\\/])\\./', '$1]', $v);
        }
    }

    /**
     * Internal method used by compile(). Get variable name tokens.
     *
     * @param string $v variable name.
     *
     * @return mixed Variable names array or null.
     * 
     * @expect null when input ''
     * @expect Array('.') when input '.'
     * @expect Array('a') when input 'a'
     * @expect Array('a', 'b') when input 'a.b'
     */
    protected static function _vs($v) {
        if ($v == '.') {
            return Array('.');
        }
        return ($v !== '') ? explode('.', $v) : null;
    }

    /**
     * Internal method used by compile(). Get custom helper arguments.
     *
     * @param array $list an array of arguments.
     * @param array $context Current compile content.
     *
     * @return string PHP arguments string
     * 
     * @expect '' when input Array(), Array('flags' => Array('this' => 0, 'advar' => 0))
     * @expect '' when input Array(), Array('flags' => Array('this' => 0, 'advar' => 1))
     * @expect "'a'" when input Array('a'), Array('flags' => Array('this' => 0, 'advar' => 0))
     * @expect "'this'" when input Array('this'), Array('flags' => Array('this' => 0, 'advar' => 0))
     * @expect "''" when input Array('this'), Array('flags' => Array('this' => 1, 'advar' => 0))
     */
    protected static function _arg($list, $context) {
        $ret = Array();
        foreach ($list as $v) {
            self::_vx($v, $context);
            $ret[] = "'$v'";
        }
        return implode(',', $ret);
    }

    /**
     * Internal method used by compile(). Find current json schema target, return childrens.
     *
     * @param array $target current json schema target
     * @param mixed $key move target to child specified with the key
     *
     * @return array children of new json schema target 
     */
    protected static function &_jst(&$target, $key = false) {
        if ($key) {
            if (!isset($target['properties'])) {
                $target['type'] = 'object';
                $target['properties'] = Array();
            }
            if (!isset($target['properties'][$key])) {
                $target['properties'][$key] = Array();
            }
            return $target['properties'][$key];
        } else {
            if (!isset($target['items'])) {
                $target['type'] = 'array';
                $target['items'] = Array();
            }
            return $target['items'];
        }
    }

    /**
     * Internal method used by compile(). Find current json schema target, prepare target parent.
     *
     * @param array $context current compile context
     */
    protected static function &_jsp(&$context) {
        $target = &$context['jsonSchema'];
        foreach ($context['vars'] as $var) {
            if ($var) {
                foreach ($var as $v) {
                    $target = &self::_jst($target, $v);
                }
            }
            $target = &self::_jst($target);
        }
        return $target;
    }

    /**
     * Internal method used by compile(). Define a json schema string/number with the variable name.
     *
     * @param array $context current compile context
     * @param string $var current variable name
     */
    protected static function _jsv(&$context, $var) {
        $target = &self::_jsp($context);
        foreach (self::_vs($var) as $v) {
            $target = &self::_jst($target, $v);
        }
        $target['type'] = Array('string', 'number');
        $target['required'] = true;
    }

    /**
     * Internal method used by scan() and compile(). Parse the token and return parsed result.
     *
     * @param array $token preg_match results
     * @param array $context current compile context
     */
    protected static function _tk(&$token, &$context) {
        $acts = Array();
        trim($token[5]);
        preg_match_all('/(\s*)([^\s]+)/', $token[5], $matched);

        // Parse arguments and deal with "..." or [...]
        if (is_array($matched) && $context['flags']['advar']) {
            $prev = '';
            $expect = 0;
            foreach ($matched[2] as $index => $t) {
                // continue from previous match when expect something
                if ($expect) {
                    $prev .= "{$matched[1][$index]}$t";
                    // end an argument when end with expected charactor
                    if (substr($t, -1, 1) === $expect) {
                        $acts[] = $prev;
                        $prev = '';
                        $expect = 0;
                    }
                    continue;
                }
                // continue to next match when '"' started without ending '"'
                if (preg_match('/^"[^"]+$/', $t)) {
                    $prev = $t;
                    $expect = '"';
                    continue;
                }
                // continue to next match when '[' started without ending ']'
                if (preg_match('/\\[[^\\]]+$/', $t)) {
                    $prev = $t;
                    $expect = ']';
                    continue;
                }
                $acts[] = $t;
            }
        } else {
            $acts = explode(' ', $token[5]);
        }

        // Check for advanced variable.
        foreach ($acts as $act) {
            if ($context['flags']['advar']) {
                    // foo]  Rule 1: no starting [ or [ not start from head
                if (preg_match('/^[^\\[\\.]+[\\]\\[]/', $act)
                    // [bar  Rule 2: no ending ] or ] not in the end
                    || preg_match('/[\\[\\]][^\\]\\.]+$/', $act)
                    // ]bar. Rule 3: middle ] not before .
                    || preg_match('/\\][^\\]\\[\\.]+\\./', $act)
                    // .foo[ Rule 4: middle [ not after .
                    || preg_match('/\\.[^\\]\\[\\.]+\\[/', preg_replace('/\\[[^\\]]+\\]/', '[XXX]', $act))
                ) {
                    $context['error'][] = "Wrong variable naming as '$act' in {$token[2]}{$token[3]}{$token[4]}{$token[5]}{$token[6]}{$token[7]} !";
                }
            }
        }

        return Array(
            ($token[2] === '{{{'), $acts
        );
    }

    /**
     * Internal method used by compile(). Collect handlebars usage information, detect template error.
     *
     * @param array $token detected handlebars {{ }} token
     * @param array $context current scaning context
     */
    protected static function scan($token, &$context) {
        list($raw, $acts) = self::_tk($token, $context);
        list(, , $begintag, $lspctl, $blockop, $intag, $rspctl, $endtag) = $token;

        // {{ }}} or {{{ }} are invalid
        if (strlen($begintag) !== strlen($endtag)) {
            $context['error'][] = "Bad token $begintag$lspctl$blockop$intag$rspctl$endtag ! Do you mean {{ }} or {{{ }}}?";
            return;
        }

        // {{{# }}} or {{{! }}} or {{{/ }}} or {{{^ }}} are invalid.
        if ($raw) {
            if ($blockop) {
                $context['error'][] = "Bad token $begintag$lspctl$blockop$intag$rspctl$endtag ! Do you mean {{" . "$lspctl$blockop$intag$rspctl}}?";
                return;
            }
        }

        // Handle block operations: ^ / ! # .
        switch ($blockop) {
        case '^':
            $context['stack'][] = $intag;
            $context['level']++;
            return $context['usedFeature']['isec']++;

        case '/':
            array_pop($context['stack']);
            $context['level']--;
            return;

        case '!':
            return $context['usedFeature']['comment']++;

        case '#':
            $context['stack'][] = $intag;
            $context['level']++;
            switch ($acts[0]) {
            case 'with':
                if (isset($acts[1]) && !$context['flags']['with']) {
                    $context['error'][] = 'do not support {{#with var}}, you should do compile with LightnCandy::FLAG_WITH flag';
                }
            case 'each':
            case 'unless':
            case 'if':
                return $context['usedFeature'][$acts[0]]++;

            default:
                return $context['usedFeature']['sec']++;
            }

        default:
        }

        $context['usedFeature'][$raw ? 'raw' : 'enc']++;

        // Handle else and this.
        switch ($intag) {
            case 'else':
                return $context['usedFeature']['else']++;

            case 'this':
                if ($context['level'] == 0) {
                    $context['usedFeature']['rootthis']++;
                }
                if (!$context['flags']['this']) {
                    $context['error'][] = 'do not support {{this}}, you should do compile with LightnCandy::FLAG_THIS flag';
                }
                return $context['usedFeature']['this']++;

            case '.':
                if ($context['level'] == 0) {
                    $context['usedFeature']['rootthis']++;
                }
                if (!$context['flags']['this']) {
                    $context['error'][] = 'do not support {{.}}, you should do compile with LightnCandy::FLAG_THIS flag';
                }
                return $context['usedFeature']['dot']++;
        }

        // Handle custom helpers.
        if (isset($context['helpers'][$acts[0]])) {
            return $context['usedFeature']['helper']++;
        }

        // Check for parent context.
        if (preg_match('/\\.\\.(\\/.+)*/', $intag)) {
            if (!$context['flags']['parent']) {
                $context['error'][] = 'do not support {{../var}}, you should do compile with LightnCandy::FLAG_PARENT flag';
            }
            return $context['usedFeature']['parent']++;
        }

        if ($context['level'] == 0) {
            $context['usedFeature']['rootvar']++;
        }
    }

    /**
     * Internal method used by compile(). Return compiled PHP code partial for a handlebars token.
     *
     * @param array $token detected handlebars {{ }} token
     * @param array $context current scaning context
     */
    public static function tokens(&$token, &$context) {
        list($raw, $acts) = self::_tk($token, $context);
        list(, , $begintag, $lspctl, $blockop, $intag, $rspctl, $endtag) = $token;

        // Handle space control.
        if ($lspctl) {
            $token[1] = '';
        }

        if ($rspctl) {
            $token[8] = '';
        }

        // Handle block operations: ^ / ! # .
        switch ($blockop) {
        case '^':
            $context['stack'][] = $intag;
            $context['stack'][] = '^';
            if ($context['useVar']) {
                $v = $context['useVar'] . "['{$intag}']";
                return "{$context['ops']['cnd_start']}(is_null($v) && ($v !== false)){$context['ops']['cnd_then']}"; 
            } else {
                return "{$context['ops']['cnd_start']}(" . self::_fn($context, 'isec') . "('$intag', \$cx, \$in)){$context['ops']['cnd_then']}";
            }
        case '/':
            $each = false;
            switch ($intag) {
            case 'if':
            case 'unless':
                $pop = array_pop($context['stack']);
                if ($pop == ':') {
                    array_pop($context['stack']);
                    return $context['usedFeature']['parent'] ? "{$context['ops']['f_end']}}){$context['ops']['seperator']}" : "{$context['ops']['cnd_end']}";
                }
                return $context['usedFeature']['parent'] ? "{$context['ops']['f_end']}}){$context['ops']['seperator']}" : "{$context['ops']['cnd_else']}''{$context['ops']['cnd_end']}";
            case 'with':
                $pop = array_pop($context['stack']);
                if ($pop !== 'with') {
                   $context['error'][] = 'Unexpect token /with !';
                   return;
                }
                return "{$context['ops']['f_end']}}){$context['ops']['seperator']}";
            case 'each':
                $each = true;
            default:
                self::_vx($intag, $context);
                array_pop($context['vars']);
                $pop = array_pop($context['stack']);
                switch($pop) {
                case '#':
                case '^':
                    $pop2 = array_pop($context['stack']);
                    if (!$each && ($pop2 !== $intag)) {
                        $context['error'][] = "Unexpect token $begintag$lspctl$blockop$intag$rspctl$endtag ! Previous token $pop$pop2 is not closed";
                        return;
                    }
                    if ($pop == '^') {
                        return $context['usedFeature']['parent'] ? "{$context['ops']['f_end']}}){$context['ops']['seperator']}" : "{$context['ops']['cnd_else']}''{$context['ops']['cnd_end']}";
                    }
                    return "{$context['ops']['f_end']}}){$context['ops']['seperator']}";
                default:
                    $context['error'][] = "Unexpect token: $begintag$lspctl$blockop$intag$rspctl$endtag !";
                    return;
                }
            }
        case '#':
            $each = 'false';
            switch ($acts[0]) {
            case 'if':
                $context['stack'][] = 'if';
                self::_vx($acts[1], $context);
                return $context['usedFeature']['parent'] 
                       ? $context['ops']['seperator'] . self::_fn($context, 'ifv') . "('{$acts[1]}', \$cx, \$in, function(\$cx, \$in) {{$context['ops']['f_start']}"
                       : "{$context['ops']['cnd_start']}(" . self::_fn($context, 'ifvar') . "('{$acts[1]}', \$cx, \$in)){$context['ops']['cnd_then']}";
            case 'unless':
                $context['stack'][] = 'unless';
                self::_vx($acts[1], $context);
                return $context['usedFeature']['parent']
                       ? $context['ops']['seperator'] . self::_fn($context, 'unl') . "('{$acts[1]}', \$cx, \$in, function(\$cx, \$in) {{$context['ops']['f_start']}"
                       : "{$context['ops']['cnd_start']}(!" . self::_fn($context, 'ifvar') . "('{$acts[1]}', \$cx, \$in)){$context['ops']['cnd_then']}";
            case 'each':
                $each = 'true';
            case 'with':
                $intag = $acts[1];
            default:
                if (($acts[0] === 'with') && $context['flags']['with']) {
                    self::_vx($acts[1], $context);
                    $context['vars'][] = self::_vs($acts[1]);
                    $context['stack'][] = 'with';
                    return $context['ops']['seperator'] . self::_fn($context, 'wi') . "('{$acts[1]}', \$cx, \$in, function(\$cx, \$in) {{$context['ops']['f_start']}";
                }
                self::_vx($intag, $context);
                $context['vars'][] = self::_vs($intag);
                self::_jsp($context);
                $context['stack'][] = $intag;
                $context['stack'][] = '#';
                return $context['ops']['seperator'] . self::_fn($context, 'sec') . "('$intag', \$cx, \$in, $each, function(\$cx, \$in) {{$context['ops']['f_start']}";
            }
        case '!':
            return $context['ops']['seperator'];
        }

        // Handle custom helpers.
        self::_vx($acts[0], $context);
        $fn = $raw ? 'raw' : $context['ops']['enc'];
        if (isset($context['helpers'][$acts[0]])) {
            $ch = array_shift($acts);
            return $context['ops']['seperator'] . self::_fn($context, 'ch') . "('$ch', Array(" . self::_arg($acts, $context) . "), '$fn', \$cx, \$in){$context['ops']['seperator']}";
        }

        // Handle else.
        if ($acts[0] ==='else') {
            $context['stack'][] = ':';
            return $context['usedFeature']['parent'] ? "{$context['ops']['f_end']}}, function(\$cx, \$in) {{$context['ops']['f_start']}" : "{$context['ops']['cnd_else']}";
        }

        // Handle variables.
        self::_jsv($context, $acts[0]); // TODO: more variables should be placed in json schema in custom helper calls
        if ($context['useVar']) {
            $v = $context['useVar'] . self::_vn($acts[0], $context['flags']['advar']);
            if ($context['flags']['jstrue']) {
                return $raw ? "{$context['ops']['cnd_start']}($v === true){$context['ops']['cnd_then']}'true'{$context['ops']['cnd_else']}$v{$context['ops']['cnd_end']}" : "{$context['ops']['cnd_start']}($v === true){$context['ops']['cnd_then']}'true'{$context['ops']['cnd_else']}htmlentities($v, ENT_QUOTES){$context['ops']['cnd_end']}";
            } else {
                return $raw ? "{$context['ops']['seperator']}$v{$context['ops']['seperator']}" : "{$context['ops']['seperator']}htmlentities($v, ENT_QUOTES){$context['ops']['seperator']}";
            }
        } else {
            return $context['ops']['seperator'] . self::_fn($context, $fn) . "('{$acts[0]}', \$cx, \$in){$context['ops']['seperator']}";
        }
    }
}

/**
 * LightnCandy static class for compiled template runtime methods.
 */
class LCRun {
    /**
     * LightnCandy runtime method for {{#if var}}.
     *
     * @param string $var variable name to be tested
     * @param array $cx render time context
     * @param array $in input data with current scope
     *
     * @return boolean Return true when the value is not null nor false.
     */
    public static function ifvar($var, $cx, $in) {
        $v = self::val($var, $cx, $in);
        return !is_null($v) && ($v !== false) && ($v !== 0) && ($v !== '') && (is_array($v) ? (count($v) > 0) : true);
    }

    /**
     * LightnCandy runtime method for {{#if var}} when {{../var}} used.
     *
     * @param string $var variable name to be tested
     * @param array $cx render time context
     * @param array $in input data with current scope
     * @param function $truecb callback function when test result is true
     * @param function $falsecb callback function when test result is false
     *
     * @return string The rendered string of the section
     */
    public static function ifv($var, $cx, $in, $truecb, $falsecb = null) {
        $v = self::val($var, $cx, $in);
        $ret = '';
        if (is_null($v) || ($v === false) || ($v === 0) || ($v === '') || (is_array($v) && (count($v) == 0))) {
            if ($falsecb) {
                $cx['scopes'][] = $in;
                $ret = $falsecb($cx, $in);
                array_pop($cx['scopes']);
            }
        } else {
            if ($truecb) {
                $cx['scopes'][] = $in;
                $ret = $truecb($cx, $in);
                array_pop($cx['scopes']);
            }
        }
        return $ret;
    }

    /**
     * LightnCandy runtime method for {{$unless var}} when {{../var}} used.
     *
     * @param string $var variable name to be tested
     * @param array $cx render time context
     * @param array $in input data with current scope
     *
     * @return boolean Return true when the value is not null nor false.
     */
    public static function unl($var, $cx, $in, $truecb, $falsecb = null) {
        return self::ifv($var, $cx, $in, $falsecb, $truecb);
    }

    /**
     * LightnCandy runtime method for {{^var}} inverted section.
     *
     * @param string $var variable name to be tested
     * @param array $cx render time context
     * @param array $in input data with current scope
     *
     * @return boolean Return true when the value is not null nor false.
     */
    public static function isec($var, $cx, $in) {
        $v = self::val($var, $cx, $in);
        return is_null($v) || ($v === false);
    }

    /**
     * LightnCandy runtime method to get input value.
     *
     * @param string $var variable name to get the raw value
     * @param array $cx render time context
     * @param array $in input data with current scope
     *
     * @return mixed The raw value of the specified variable
     */
    public static function val($var, $cx, $in) {
        $levels = 0;

        if ($var === '@index') {
            return $cx['sp_vars']['index'];
        }
        if ($var === '@key') {
            return $cx['sp_vars']['key'];
        }
        if (preg_match('/^"(.*)"$/', $var, $matched)) {
            return $matched[1];
        }

        // Tract to parent. Do not support foo/../bar style variable name
        $var = preg_replace_callback('/\\.\\.\\//', function() use (&$levels) {
            $levels++;
            return '';
        }, $var);

        // response '' when beyand root.
        if ($levels > 0) {
            $pos = count($cx['scopes']) - $levels;
            if ($pos >= 0) {
                $in = $cx['scopes'][$pos];
            } else {
                return '';
            }
        }

        // path search on objects
        if (preg_match('/(.+?)\\](.+)/', $var, $matched)) {
            if (array_key_exists($matched[1], $in)) {
                return self::val($matched[2], $cx, $in[$matched[1]]);
            } else {
                return null;
            }
        }

        return ($var === '') ? $in : (is_array($in) && isset($in[$var]) ? $in[$var] : null);
    }

    /**
     * LightnCandy runtime method for {{{var}}} .
     *
     * @param string $var variable name to get the raw value
     * @param array $cx render time context
     * @param array $in input data with current scope
     * @param boolean $loop true when in loop
     *
     * @return string The raw value of the specified variable
     */
    public static function raw($var, $cx, $in, $loop = false) {
        $v = self::val($var, $cx, $in);
        if ($v === true) {
            if ($cx['flags']['jstrue']) {
                return 'true';
            }
        }

        if ($loop && ($v === false)) {
            if ($cx['flags']['jstrue']) {
                return 'false';
            }
        }

        if (is_array($v)) {
            if ($cx['flags']['jsobj']) {
                if (count(array_diff_key($v, array_keys(array_keys($v)))) > 0) {
                    return '[object Object]';
                } else {
                    $ret = Array();
                    foreach ($v as $k => $vv) {
                        $ret[] = self::raw($k, $cx, $v, true);
                    }
                    return join(',', $ret);
                }
            }
        }

        return $v;
    }

    /**
     * LightnCandy runtime method for {{var}} .
     *
     * @param string $var variable name to get the htmlencoded value
     * @param array $cx render time context
     * @param array $in input data with current scope
     *
     * @return mixed The htmlencoded value of the specified variable
     */
    public static function enc($var, $cx, $in) {
        return htmlentities(self::raw($var, $cx, $in), ENT_QUOTES);
    }

    /**
     * LightnCandy runtime method for {{var}} , and deal with single quote to same as handlebars.js .
     *
     * @param string $var variable name to get the htmlencoded value
     * @param array $cx render time context
     * @param array $in input data with current scope
     *
     * @return string The htmlencoded value of the specified variable
     */
    public static function encq($var, $cx, $in) {
        return preg_replace('/&#039;/', '&#x27;', htmlentities(self::raw($var, $cx, $in), ENT_QUOTES));
    }

    /**
     * LightnCandy runtime method for {{#var}} section.
     *
     * @param string $var variable name for section
     * @param array $cx render time context
     * @param array $in input data with current scope
     * @param boolean $each true when rendering #each
     * @param function $cb callback function to render child context
     *
     * @return string The rendered string of the section
     */
    public static function sec($var, &$cx, $in, $each, $cb) {
        $v = self::val($var, $cx, $in);
        $isary = is_array($v);
        $loop = $each;
        if (!$loop && $isary) {
            $loop = (count(array_diff_key($v, array_keys(array_keys($v)))) == 0);
        }
        if ($loop && $isary) {
            if ($each) {
                $is_obj = count(array_diff_key($v, array_keys(array_keys($v)))) > 0;
            } else {
                $is_obj = false;
            }
            $ret = Array();
            $cx['scopes'][] = $in;
            $i = 0;
            foreach ($v as $index => $raw) {
                if ($is_obj) {
                    $cx['sp_vars']['key'] = $index;
                    $cx['sp_vars']['index'] = $i;
                    $i++;
                } else {
                    $cx['sp_vars']['index'] = $index;
                }
                $ret[] = $cb($cx, $raw);
            }
            if ($is_obj) {
                unset($cx['sp_vars']['key']);
            }
            unset($cx['sp_vars']['index']);
            array_pop($cx['scopes']);
            return join('', $ret);
        }
        if ($each) {
            return '';
        }
        if ($isary) {
            $cx['scopes'][] = $v;
            $ret = $cb($cx, $v);
            array_pop($cx['scopes']);
            return $ret;
        }
        if ($v === true) {
            return $cb($cx, $in);
        }
        if (is_string($v)) {
            return $cb($cx, Array());
        }
        if (!is_null($v) && ($v !== false)) {
            return $cb($cx, $v);
        }
        return '';
    }

    /**
     * LightnCandy runtime method for {{#with var}} .
     *
     * @param string $var variable name for section
     * @param array $cx render time context
     * @param array $in input data with current scope
     * @param function $cb callback function to render child context
     *
     * @return string The rendered string of the token
     */
    public static function wi($var, &$cx, $in, $cb) {
        $v = self::val($var, $cx, $in);
        if (($v === false) || ($v === null)) {
            return '';
        }
        $cx['scopes'][] = $in;
        $ret = $cb($cx, $v);
        array_pop($cx['scopes']);
        return $ret;
    }

    /**
     * LightnCandy runtime method for custom helpers.
     *
     * @param string $ch the name of custom helper to be executed
     * @param array $vars variable names for helpers
     * @param string $op the name of variable resolver. should be one of: 'raw', 'enc', or 'encq'.
     * @param array $cx render time context
     * @param array $in input data with current scope
     *
     * @return string The rendered string of the token
     */
    public static function ch($ch, $vars, $op, &$cx, $in) {
        $args = Array();
        foreach ($vars as $v) {
            $args[] = self::raw($v, $cx, $in);
        }

        $r = call_user_func_array($cx['helpers'][$ch], $args);
        switch ($op) {
            case 'enc': 
                return htmlentities($r, ENT_QUOTES);
            case 'encq':
                return preg_replace('/&#039;/', '&#x27;', htmlentities($r, ENT_QUOTES));
            default:
                return $r;
        }
    }
}
?>
