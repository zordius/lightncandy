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

    // Compile the template as standalone PHP code which can execute without including LightnCandy
    const FLAG_STANDALONE = 4;

    // JavaScript compatibility
    const FLAG_JSTRUE = 8;
    const FLAG_JSOBJECT = 16;

    // Handlebars.js compatibility
    const FLAG_THIS = 32;
    const FLAG_WITH = 64;
    const FLAG_PARENT = 128;
    const FLAG_JSQUOTE = 256;
    const FLAG_ADVARNAME = 512;
    const FLAG_SPACECTL = 1024;
    const FLAG_NAMEDARG = 2048;
    const FLAG_SPVARS = 4096;

    // PHP performance flags
    const FLAG_EXTHELPER = 8192;
    const FLAG_ECHO = 16384;

    // alias flags
    const FLAG_BESTPERFORMANCE = 16384; // FLAG_ECHO
    const FLAG_JS = 24; // FLAG_JSTRUE + FLAG_JSOBJECT
    const FLAG_HANDLEBARS = 8160; // FLAG_THIS + FLAG_WITH + FLAG_PARENT + FLAG_JSQUOTE + FLAG_ADVARNAME + FLAG_SPACECTL + FLAG_NAMEDARG + FLAG_SPVARS
    const FLAG_HANDLEBARSJS = 8184; // FLAG_JS + FLAG_HANDLEBARS

    // RegExps
    const PARTIAL_SEARCH = '/\\{\\{>[ \\t]*(.+?)[ \\t]*\\}\\}/s';
    const TOKEN_SEARCH = '/(\s*)(\\{{2,3})(~?)([\\^#\\/!]?)(.+?)(~?)(\\}{2,3})(\s*)/s';
    const VARNAME_SEARCH = '/(\\[[^\\]]+\\]|[^\\[\\]\\.]+)/';
    const EXTENDED_COMMENT_SEARCH = '/{{!--.*?--}}/s';

    // Positions of matched token
    const POS_LSPACE = 1;
    const POS_BEGINTAG = 2;
    const POS_LSPACECTL = 3;
    const POS_OP = 4;
    const POS_INNERTAG = 5;
    const POS_RSPACECTL = 6;
    const POS_ENDTAG = 7;
    const POS_RSPACE = 8;

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
    public static function compile($template, $options = Array('flags' => self::FLAG_BESTPERFORMANCE)) {
        $context = self::buildContext($options);

        // Scan for partial and replace partial with template.
        $template = self::expandPartial($template, $context);

        if (self::handleError($context)) {
            return false;
        }

        // Strip extended comments
        $template = preg_replace( self::EXTENDED_COMMENT_SEARCH, '', $template );

        // Do first time scan to find out used feature, detect template error.
        if (preg_match_all(self::TOKEN_SEARCH, $template, $tokens, PREG_SET_ORDER) > 0) {
            foreach ($tokens as $token) {
                self::scanFeatures($token, $context);
            }
        }

        if (self::handleError($context)) {
            return false;
        }

        // Do PHP code and json schema generation.
        $code = preg_replace_callback(self::TOKEN_SEARCH, function ($matches) use (&$context) {
            $tmpl = LightnCandy::compileToken($matches, $context);
            return "{$matches[LightnCandy::POS_LSPACE]}'$tmpl'{$matches[LightnCandy::POS_RSPACE]}";
        }, addcslashes($template, "'"));

        if (self::handleError($context)) {
            return false;
        }

        return self::composePHPRender($context, $code);
    }

    /**
     * Compose LightnCandy render codes for include()
     *
     * @param array $context Current context
     * @param string $code generated PHP code
     *
     * @return string Composed PHP code
     *
     * @codeCoverageIgnore
     */
    protected static function composePHPRender($context, $code) {
        $flagJStrue = self::getBoolStr($context['flags']['jstrue']);
        $flagJSObj = self::getBoolStr($context['flags']['jsobj']);
        $flagSPVar = self::getBoolStr($context['flags']['spvar']);

        $libstr = self::exportLCRun($context);
        $helpers = self::exportHelper($context);
        $bhelpers = self::exportHelper($context, 'blockhelpers');

        // Return generated PHP code string.
        return "<?php return function (\$in) {
    \$cx = Array(
        'flags' => Array(
            'jstrue' => $flagJStrue,
            'jsobj' => $flagJSObj,
            'spvar' => $flagSPVar,
        ),
        'helpers' => $helpers,
        'blockhelpers' => $bhelpers,
        'scopes' => Array(\$in),
        'sp_vars' => Array(),
        'path' => Array(),
$libstr
    );
    {$context['ops']['op_start']}'$code'{$context['ops']['op_end']}
}
?>";
    }

    /**
     * Build context from options
     *
     * @param mixed $options input options
     *
     * @return array Context from options
     *
     * @codeCoverageIgnore
     */
    protected static function buildContext($options) {
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
                'namev' => $flags & self::FLAG_NAMEDARG,
                'spvar' => $flags & self::FLAG_SPVARS,
                'exhlp' => $flags & self::FLAG_EXTHELPER,
            ),
            'level' => 0,
            'stack' => Array(),
            'error' => Array(),
            'vars' => Array(),
            'jsonSchema' => Array(
                '$schema' => 'http://json-schema.org/draft-03/schema',
                'description' => 'Template Json Schema'
            ),
            'basedir' => self::buildCXBasedir($options),
            'fileext' => self::buildCXFileext($options),
            'usedPartial' => Array(),
            'usedFeature' => Array(
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
                'bhelper' => 0,
            ),
            'usedCount' => Array(
                'var' => Array(),
                'helpers' => Array(),
                'blockhelpers' => Array(),
            ),
            'helpers' => Array(),
            'blockhelpers' => Array(),
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
        return self::buildHelperTable(self::buildHelperTable($context, $options), $options, 'blockhelpers');
    }

    /**
     * Build custom helper table
     *
     * @param array $context prepared context
     * @param mixed $options input options
     * @param string $tname helper table name
     *
     * @return array context with generated helper table
     *
     * @expect Array() when input Array(), Array()
     * @expect Array('flags' => Array('exhlp' => 1)) when input Array('flags' => Array('exhlp' => 1)), Array('helpers' => Array('abc'))
     * @expect Array('error' => Array('Can not find custom helper function defination abc() !'), 'flags' => Array('exhlp' => 0)) when input Array('error' => Array(), 'flags' => Array('exhlp' => 0)), Array('helpers' => Array('abc'))
     * @expect Array('flags' => Array('exhlp' => 1), 'helpers' => Array('LCRun2::raw' => 'LCRun2::raw')) when input Array('flags' => Array('exhlp' => 1), 'helpers' => Array()), Array('helpers' => Array('LCRun2::raw'))
     * @expect Array('flags' => Array('exhlp' => 1), 'helpers' => Array('test' => 'LCRun2::raw')) when input Array('flags' => Array('exhlp' => 1), 'helpers' => Array()), Array('helpers' => Array('test' => 'LCRun2::raw'))
     */
    protected static function buildHelperTable($context, $options, $tname = 'helpers') {
        if (isset($options[$tname]) && is_array($options[$tname])) {
            foreach ($options[$tname] as $name => $func) {
                if (is_callable($func)) {
                    $context[$tname][is_int($name) ? $func : $name] = $func;
                } else {
                    if (!$context['flags']['exhlp']) {
                        $context['error'][] = "Can not find custom helper function defination $func() !";
                    }
                }
            }
        }
        return $context;
    }

    /**
     * Expand partial string recursively.
     *
     * @param string $template template string
     *
     * @param mixed $context
     *
     * @return string expanded template
     *
     * @expect "123\n" when input '{{> test1}}', Array('basedir' => Array('tests'), 'usedFeature' => Array('partial' =>0), 'fileext' => Array('.tmpl'))
     * @expect "a123\nb\n" when input '{{> test2}}', Array('basedir' => Array('tests'), 'usedFeature' => Array('partial' =>0), 'fileext' => Array('.tmpl'))
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
     *
     * @expect "123\n" when input 'test1', Array('basedir' => Array('tests'), 'usedFeature' => Array('partial' =>0), 'fileext' => Array('.tmpl'))
     * @expect "a{{> test1}}b\n" when input 'test2', Array('basedir' => Array('tests'), 'usedFeature' => Array('partial' =>0), 'fileext' => Array('.tmpl'))
     * @expect null when input 'test3', Array('basedir' => Array('tests'), 'usedFeature' => Array('partial' =>0), 'fileext' => Array('.tmpl'))
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
    protected static function buildCXFileext($options) {
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
     * @expect Array(getcwd()) when input Array('basedir' => Array('dir_not_found'))
     * @expect Array('src') when input Array('basedir' => Array('src', 'dir_not_found'))
     * @expect Array('src', 'tests') when input Array('basedir' => Array('src', 'tests'))
     */
    protected static function buildCXBasedir($options) {
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
     * @return string
     * @expect 'function($a) {return;}' when input function ($a) {return;}
     * @expect 'function($a) {return;}' when input    function ($a) {return;}
     * @expect '' when input 'Directory::close'
     */
    protected static function getPHPCode($closure) {
        if (is_string($closure) && preg_match('/(.+)::(.+)/', $closure, $matched)) {
            $ref = new ReflectionMethod($matched[1], $matched[2]);
        } else {
            $ref = new ReflectionFunction($closure);
        }
        $fname = $ref->getFileName();

        // This never happened, only for Unit testing.
        if (!is_file($fname)) {
            return '';
        }

        $lines = file_get_contents($fname);
        $file = new SplFileObject($fname);
        $file->seek($ref->getStartLine() - 2);
        $spos = $file->ftell();
        $file->seek($ref->getEndLine() - 1);
        $epos = $file->ftell();

        return preg_replace('/^.*?function\s.*?\\((.+?)\\}[,\\s]*;?$/s', 'function($1}', substr($lines, $spos, $epos - $spos));
    }

    /**
     * Internal method used by compile(). Export required custom helper functions.
     *
     * @param string $tname   helper table name
     *
     * @param array  $context current compile context
     *
     * @return string
     * @codeCoverageIgnore
     */
    protected static function exportHelper($context, $tname = 'helpers') {
        $ret = '';
        foreach ($context[$tname] as $name => $func) {
            if (!isset($context['usedCount'][$tname][$name])) {
                continue;
            }
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
     * @param array $context current compile context
     *
     * @return string
     * @codeCoverageIgnore
     */
    protected static function exportLCRun($context) {
        if ($context['flags']['standalone'] == 0) {
            return '';
        }

        $class = new ReflectionClass('LCRun2');
        $fname = $class->getFileName();
        $lines = file_get_contents($fname);
        $file = new SplFileObject($fname);
        $ret = "'funcs' => Array(\n";

        foreach ($class->getMethods() as $method) {
            $file->seek($method->getStartLine() - 2);
            $spos = $file->ftell();
            $file->seek($method->getEndLine() - 2);
            $epos = $file->ftell();
            $ret .= preg_replace('/self::(.+?)\(/', '\\$cx[\'funcs\'][\'$1\'](', preg_replace('/public static function (.+)\\(/', '\'$1\' => function (', substr($lines, $spos, $epos - $spos))) . "    },\n";
        }
        unset($file);
        return "$ret)\n";
    }

    /**
     * Internal method used by compile(). Handle exists error and return error status.
     *
     * @param array $context Current context of compiler progress.
     *
     * @throws Exception
     * @return boolean True when error detected
     *
     * @expect true when input Array('level' => 1, 'stack' => Array('X'), 'flags' => Array('errorlog' => 0, 'exception' => 0), 'error' => Array())
     * @expect false when input Array('level' => 0, 'error' => Array())
     * @expect true when input Array('level' => 0, 'error' => Array('some error'), 'flags' => Array('errorlog' => 0, 'exception' => 0))
     */
    protected static function handleError(&$context) {
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
                throw new Exception(implode("\n", $context['error']));
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
    protected static function getBoolStr($v) {
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
        return preg_replace_callback('/\\{|\\[|,|\\]|\\}|:/', function ($matches) use (&$level, $indent) {
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
                    // Continue to add CR
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
     * @param string      $php PHP code
     * @param string|null $tmp_dir Optional, change temp directory for php include file saved by prepare() when cannot include PHP code with data:// format.
     *
     * @return Closure result of include()
     *
     * @deprecated
     * @codeCoverageIgnore
     */
    public static function prepare($php, $tmp_dir = null) {
        if (!ini_get('allow_url_include') || !ini_get('allow_url_fopen')) {
            if (!$tmp_dir || !is_dir($tmp_dir)) {
                $tmp_dir = sys_get_temp_dir();
            }
        }

        if ($tmp_dir) {
            $fn = tempnam($tmp_dir, 'lci_');
            if (!$fn) {
                error_log("Can not generate tmp file under $tmp_dir!!\n");
                return false;
            }
            if (!file_put_contents($fn, $php)) {
                error_log("Can not include saved temp php code from $fn, you should add $tmp_dir into open_basedir!!\n");
                return false;
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
     * @param mixed $data
     *
     * @return string rendered result
     *
     * @codeCoverageIgnore
     */
    public static function render($compiled, $data) {
        /** @var Closure $func */
        $func = include($compiled);
        return $func($data);
    }

    /**
     * Internal method used by compile(). Get function name for standalone or none standalone template.
     *
     * @param array $context Current context of compiler progress.
     * @param string $name base function name
     *
     * @return string compiled Function name
     *
     * @expect 'LCRun2::test' when input Array('flags' => Array('standalone' => 0)), 'test'
     * @expect 'LCRun2::test2' when input Array('flags' => Array('standalone' => 0)), 'test2'
     * @expect "\$cx['funcs']['test3']" when input Array('flags' => Array('standalone' => 1)), 'test3'
     */
    protected static function getFuncName($context, $name) {
        return $context['flags']['standalone'] ? "\$cx['funcs']['$name']" : "LCRun2::$name";
    }

    /**
     * Internal method used by getArrayCode(). Get variable names translated string.
     *
     * @param array $scopes an array of variable names with single quote
     *
     * @return string PHP array names string
     * 
     * @expect '' when input Array()
     * @expect '[a]' when input Array('a')
     * @expect '[a][b][c]' when input Array('a', 'b', 'c')
     */
    protected static function getArrayStr($scopes) {
        return count($scopes) ? '[' . implode('][', $scopes) . ']' : '';
    }

    /**
     * Internal method used by getVariableName(). Get variable names translated string.
     *
     * @param array $list an array of variable names.
     *
     * @return string PHP array names string
     * 
     * @expect '' when input Array()
     * @expect "['a']" when input Array('a')
     * @expect "['a']['b']['c']" when input Array('a', 'b', 'c')
     */
    protected static function getArrayCode($list) {
        return self::getArrayStr(array_map(function ($v) {return "'$v'";}, $list));
    }

    /**
     * Internal method used by compile().
     *
     * @param array $vn variable name array.
     * @param array $context current compile context
     *
     * @return string variable names
     *
     * @expect 'Array($in)' when input Array(null), Array('flags'=>Array('spvar'=>true))
     * @expect 'Array($in,$in)' when input Array(null, null), Array('flags'=>Array('spvar'=>true))
     */
    protected static function getVariableNames($vn, $context) {
        $ret = Array();
        foreach ($vn as $i => $v) {
            $ret[] = (is_string($i) ? "'$i'=>" : '') . self::getVariableName($v, $context);
        }
        return 'Array(' . implode(',', $ret) . ')';
    }

    /**
     * Internal method used by compile().
     *
     * @param array $var variable name.
     * @param array $context current compile context
     *
     * @return array variable names
     *
     * @expect '$in' when input Array(null), Array('flags'=>Array('spvar'=>true))
     * @expect '((is_array($in) && isset($in[\'@index\'])) ? $in[\'@index\'] : null)' when input Array('@index'), Array('flags'=>Array('spvar'=>false))
     * @expect '$cx[\'sp_vars\'][\'index\']' when input Array('@index'), Array('flags'=>Array('spvar'=>true))
     * @expect '$cx[\'sp_vars\'][\'key\']' when input Array('@key'), Array('flags'=>Array('spvar'=>true))
     * @expect '$cx[\'sp_vars\'][\'first\']' when input Array('@first'), Array('flags'=>Array('spvar'=>true))
     * @expect '$cx[\'sp_vars\'][\'last\']' when input Array('@last'), Array('flags'=>Array('spvar'=>true))
     * @expect '$cx[\'scopes\'][0]' when input Array('@root'), Array('flags'=>Array('spvar'=>true))
     * @expect '\'a\'' when input Array('"a"'), Array(), Array('flags'=>Array('spvar'=>true))
     * @expect '((is_array($in) && isset($in[\'a\'])) ? $in[\'a\'] : null)' when input Array('a'), Array('flags'=>Array('spvar'=>true))
     * @expect '((is_array($cx[\'scopes\'][count($cx[\'scopes\'])-1]) && isset($cx[\'scopes\'][count($cx[\'scopes\'])-1][\'a\'])) ? $cx[\'scopes\'][count($cx[\'scopes\'])-1][\'a\'] : null)' when input Array(1,'a'), Array('flags'=>Array('spvar'=>true))
     * @expect '((is_array($cx[\'scopes\'][count($cx[\'scopes\'])-3]) && isset($cx[\'scopes\'][count($cx[\'scopes\'])-3][\'a\'])) ? $cx[\'scopes\'][count($cx[\'scopes\'])-3][\'a\'] : null)' when input Array(3,'a'), Array('flags'=>Array('spvar'=>true))
     */
    protected static function getVariableName($var, $context) {
        $levels = 0;

        if ($context['flags']['spvar']) {
            switch ($var[0]) {
            case '@index':
            case '@first':
            case '@last':
            case '@key':
                return "\$cx['sp_vars']['" . substr($var[0], 1) . "']";
            }
        }

        // Handle double quoted string
        if (preg_match('/^"(.*)"$/', $var[0], $matched)) {
            return "'{$matched[1]}'";
        }

        $base = '$in';
        // trace to parent
        if (!is_string($var[0]) && is_int($var[0])) {
            $levels = array_shift($var);
        }

        // change base when trace to parent
        if ($levels > 0) {
            $base = "\$cx['scopes'][count(\$cx['scopes'])-$levels]";
        }

        // Handle @root
        if ($context['flags']['spvar'] && ($var[0] === '@root')) {
            array_shift($var);
            $base = '$cx[\'scopes\'][0]';
        }

        if ((count($var) == 0) || is_null($var[0])) {
            return $base;
        }

        $n = self::getArrayCode($var);
        array_pop($var);
        $p = count($var) ? self::getArrayCode($var) : '';

        return "((is_array($base$p) && isset($base$n)) ? $base$n : null)";
    }

    /**
     * Internal method used by compile(). Return array presentation for a variable name
     *
     * @param mixed $v variable name to be fixed.
     * @param array $context Current compile content.
     * 
     * @return array Return variable name array
     *
     * @expect Array('this') when input 'this', Array('flags' => Array('advar' => 0, 'this' => 0))
     * @expect Array(null) when input 'this', Array('flags' => Array('advar' => 0, 'this' => 1))
     * @expect Array(1, null) when input '../', Array('flags' => Array('advar' => 0, 'this' => 1, 'parent' => 1), 'usedFeature' => Array('parent' => 0))
     * @expect Array(1, null) when input '../.', Array('flags' => Array('advar' => 0, 'this' => 1, 'parent' => 1), 'usedFeature' => Array('parent' => 0))
     * @expect Array(1, null) when input '../this', Array('flags' => Array('advar' => 0, 'this' => 1, 'parent' => 1), 'usedFeature' => Array('parent' => 0))
     * @expect Array(1, 'a') when input '../a', Array('flags' => Array('advar' => 0, 'this' => 1, 'parent' => 1), 'usedFeature' => Array('parent' => 0))
     * @expect Array(2, 'a', 'b') when input '../../a.b', Array('flags' => Array('advar' => 0, 'this' => 0, 'parent' => 1), 'usedFeature' => Array('parent' => 0))
     * @expect Array(2, '[a]', 'b') when input '../../[a].b', Array('flags' => Array('advar' => 0, 'this' => 0, 'parent' => 1), 'usedFeature' => Array('parent' => 0))
     * @expect Array(2, 'a', 'b') when input '../../[a].b', Array('flags' => Array('advar' => 1, 'this' => 0, 'parent' => 1), 'usedFeature' => Array('parent' => 0))
     * @expect Array('"a.b"') when input '"a.b"', Array('flags' => Array('advar' => 1, 'this' => 0, 'parent' => 1), 'usedFeature' => Array('parent' => 0))
     */
    protected static function fixVariable($v, &$context) {
        $ret = Array();
        $levels = 0;

        // handle double quoted string
        if (preg_match('/^"(.*)"$/', $v, $matched)) {
            return Array($v);
        }

        // Trace to parent for ../ N times
        $v = preg_replace_callback('/\\.\\.\\//', function() use (&$levels) {
            $levels++;
            return '';
        }, trim($v));

        if ($levels) {
            $ret[] = $levels;
            if (!$context['flags']['parent']) {
                $context['error'][] = 'do not support {{../var}}, you should do compile with LightnCandy::FLAG_PARENT flag';
            }
            $context['usedFeature']['parent']++;
        }

        if ($context['flags']['advar'] && preg_match('/\\]/', $v)) {
            preg_match_all(self::VARNAME_SEARCH, $v, $matched);
        } else {
            preg_match_all('/([^\\.\\/]+)/', $v, $matched);
        }

        if (($v === '.') || ($v === '')) {
            $matched = Array(null, Array('.'));
        }

        foreach ($matched[1] as $m) {
            if ($context['flags']['advar'] && substr($m, 0, 1) === '[') {
                $ret[] = substr($m, 1, -1);
            } else {
                $ret[] = ($context['flags']['this'] && (($m === 'this') || ($m === '.'))) ? null : $m;
            }
        }

        return $ret;
    }

    /**
     * Internal method used by compile(). Find current json schema target, return childrens.
     *
     * @param array $target current json schema target
     * @param mixed $key move target to child specified with the key
     *
     * @return array children of new JSON schema target 
     *
     * @expect Array() when input Array(), false
     * @expect Array() when input Array(), true
     */
    protected static function &setJSONTarget(&$target, $key = false) {
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
     * Internal method used by compile(). Find current JSON schema target, prepare target parent.
     *
     * @param array $context current compile context
     *
     * @codeCoverageIgnore
     */
    protected static function &setJSONParent(&$context) {
        $target = &$context['jsonSchema'];
        foreach ($context['vars'] as $var) {
            if ($var) {
                foreach ($var as $v) {
                    $target = &self::setJSONTarget($target, $v);
                }
            }
            $target = &self::setJSONTarget($target);
        }
        return $target;
    }

    /**
     * Internal method used by compile(). Define a JSON schema string/number with provided variable name.
     *
     * @param array $context current compile context
     * @param array $var current variable name
     *
     * @codeCoverageIgnore
     */
    protected static function addJsonSchema(&$context, $var) {
        $target = &self::setJSONParent($context);
        foreach ($var as $v) {
            $target = &self::setJSONTarget($target, $v);
        }
        $target['type'] = Array('string', 'number');
        $target['required'] = true;
    }

    /**
     * Internal method used by scanFeatures() and compile(). Parse the token and return parsed result.
     *
     * @param array $token preg_match results
     * @param array $context current compile context
     *
     * @return array Return parsed result
     *
     * @expect Array(false, Array(Array(null))) when input Array(0,0,0,0,0,''), Array('flags' => Array('advar' => 0, 'this' => 1, 'namev' => 0))
     * @expect Array(true, Array(Array(null))) when input Array(0,0,'{{{',0,0,''), Array('flags' => Array('advar' => 0, 'this' => 1, 'namev' => 0))
     * @expect Array(false, Array(Array('a'))) when input Array(0,0,0,0,0,'a'), Array('flags' => Array('advar' => 0, 'this' => 1, 'namev' => 0))
     * @expect Array(false, Array(Array('a'), Array('b'))) when input Array(0,0,0,0,0,'a  b'), Array('flags' => Array('advar' => 0, 'this' => 1, 'namev' => 0))
     * @expect Array(false, Array(Array('a'), Array('"b'), Array('c"'))) when input Array(0,0,0,0,0,'a "b c"'), Array('flags' => Array('advar' => 0, 'this' => 1, 'namev' => 0))
     * @expect Array(false, Array(Array('a'), Array('"b c"'))) when input Array(0,0,0,0,0,'a "b c"'), Array('flags' => Array('advar' => 1, 'this' => 1, 'namev' => 0))
     * @expect Array(false, Array(Array('a'), Array('[b'), Array('c]'))) when input Array(0,0,0,0,0,'a [b c]'), Array('flags' => Array('advar' => 0, 'this' => 1, 'namev' => 0))
     * @expect Array(false, Array(Array('a'), Array('[b'), Array('c]'))) when input Array(0,0,0,0,0,'a [b c]'), Array('flags' => Array('advar' => 0, 'this' => 1, 'namev' => 1))
     * @expect Array(false, Array(Array('a'), Array('b c'))) when input Array(0,0,0,0,0,'a [b c]'), Array('flags' => Array('advar' => 1, 'this' => 1, 'namev' => 0))
     * @expect Array(false, Array(Array('a'), Array('b c'))) when input Array(0,0,0,0,0,'a [b c]'), Array('flags' => Array('advar' => 1, 'this' => 1, 'namev' => 1))
     * @expect Array(false, Array(Array('a'), 'q' => Array('b c'))) when input Array(0,0,0,0,0,'a q=[b c]'), Array('flags' => Array('advar' => 1, 'this' => 1, 'namev' => 1))
     * @expect Array(false, Array(Array('a'), Array('q=[b c'))) when input Array(0,0,0,0,0,'a [q=[b c]'), Array('flags' => Array('advar' => 1, 'this' => 1, 'namev' => 1))
     * @expect Array(false, Array(Array('a'), 'q' => Array('[b'), Array('c]'))) when input Array(0,0,0,0,0,'a q=[b c]'), Array('flags' => Array('advar' => 0, 'this' => 1, 'namev' => 1))
     */
    protected static function parseTokenArgs(&$token, &$context) {
        $vars = Array();
        trim($token[self::POS_INNERTAG]);
        $count = preg_match_all('/(\s*)([^\s]+)/', $token[self::POS_INNERTAG], $matched);

        // Parse arguments and deal with "..." or [...]
        if (($count > 0) && $context['flags']['advar']) {
            $prev = '';
            $expect = 0;
            foreach ($matched[2] as $index => $t) {
                // continue from previous match when expect something
                if ($expect) {
                    $prev .= "{$matched[1][$index]}$t";
                    // end an argument when end with expected charactor
                    if (substr($t, -1, 1) === $expect) {
                        $vars[] = $prev;
                        $prev = '';
                        $expect = 0;
                    }
                    continue;
                }
                // continue to next match when begin with '"' without ending '"'
                if (preg_match('/^"[^"]+$/', $t)) {
                    $prev = $t;
                    $expect = '"';
                    continue;
                }

                // continue to next match when '="' exists without ending '"'
                if (preg_match('/="[^"]+$/', $t)) {
                    $prev = $t;
                    $expect = '"';
                    continue;
                }

                // continue to next match when '[' exists without ending ']'
                if (preg_match('/\\[[^\\]]+$/', $t)) {
                    $prev = $t;
                    $expect = ']';
                    continue;
                }
                $vars[] = $t;
            }
        } else {
            $vars = ($count > 0) ? $matched[2] : explode(' ', $token[self::POS_INNERTAG]);
        }

        // Check for advanced variable.
        $ret = Array();
        $i = 0;
        foreach ($vars as $idx => $var) {
            if ($context['flags']['namev']) {
                if (preg_match('/^((\\[([^\\]]+)\\])|([^=^[]+))=(.+)$/', $var, $m)) {
                    if (!$context['flags']['advar'] && $m[3]) {
                        $context['error'][] = "Wrong argument name as '$m[3]' in " . self::tokenString($token) . ' !';
                    }
                    $idx = $m[3] ? $m[3] : $m[4];
                    $var = $m[5];
                }
            }
            if ($context['flags']['advar']) {
                    // foo]  Rule 1: no starting [ or [ not start from head
                if (preg_match('/^[^\\[\\.]+[\\]\\[]/', $var)
                    // [bar  Rule 2: no ending ] or ] not in the end
                    || preg_match('/[\\[\\]][^\\]\\.]+$/', $var)
                    // ]bar. Rule 3: middle ] not before .
                    || preg_match('/\\][^\\]\\[\\.]+\\./', $var)
                    // .foo[ Rule 4: middle [ not after .
                    || preg_match('/\\.[^\\]\\[\\.]+\\[/', preg_replace('/\\[[^\\]]+\\]/', '[XXX]', $var))
                ) {
                    $context['error'][] = "Wrong variable naming as '$var' in " . self::tokenString($token) . ' !';
                }
            }

            if (is_string($idx)) {
                $ret[$idx] = is_numeric($var) ? Array('"' . $var . '"') : self::fixVariable($var, $context);
            } else {
                $ret[$i] = self::fixVariable($var, $context);
                $i++;
            }
        }

        return Array(($token[self::POS_BEGINTAG] === '{{{'), $ret);
    }

    /**
     * Internal method used by scanFeatures(). return token string
     *
     * @param string[] $token detected handlebars {{ }} token
     * @param integer $remove remove how many heading and ending token
     *
     * @return string Return whole token
     * 
     * @expect 'b' when input Array('a', 'b', 'c')
     * @expect 'c' when input Array('a', 'b', 'c', 'd', 'e'), 2
     */
    protected static function tokenString($token, $remove = 1) {
        return implode('', array_slice($token, $remove, -$remove));
    }

    /**
     * Internal method used by scanFeatures(). Validate start and and.
     *
     * @param string[] $token detected handlebars {{ }} token
     * @param array $context current compile context
     * @param boolean $raw the token is started with {{{ or not
     *
     * @return boolean|null Return true when invalid
     * 
     * @expect null when input array_fill(0, 8, ''), Array(), true
     * @expect true when input range(0, 7), Array(), true
     */
    protected static function validateStartEnd($token, &$context, $raw) {
        // {{ }}} or {{{ }} are invalid
        if (strlen($token[self::POS_BEGINTAG]) !== strlen($token[self::POS_ENDTAG])) {
            $context['error'][] = 'Bad token ' . self::tokenString($token) . ' ! Do you mean {{ }} or {{{ }}}?';
            return true;
        }
        // {{{# }}} or {{{! }}} or {{{/ }}} or {{{^ }}} are invalid.
        if ($raw && $token[self::POS_OP]) {
            $context['error'][] = 'Bad token ' . self::tokenString($token) . ' ! Do you mean {{' . self::tokenString($token, 2) . '}}?';
            return true;
        }
    }

    /**
     * Internal method used by compile(). Collect handlebars usage information, detect template error.
     *
     * @param string[] $token detected handlebars {{ }} token
     * @param array $context current compile context
     * @param array $vars parsed arguments list
     *
     * @return mixed Return true when invalid or detected
     * 
     * @expect null when input Array(0, 0, 0, 0, ''), Array(), Array()
     * @expect 2 when input Array(0, 0, 0, 0, '^', '...'), Array('usedFeature' => Array('isec' => 1), 'level' => 0), Array()
     * @expect 3 when input Array(0, 0, 0, 0, '!', '...'), Array('usedFeature' => Array('comment' => 2)), Array()
     * @expect true when input Array(0, 0, 0, 0, '/'), Array('stack' => Array(1), 'level' => 1), Array()
     * @expect 4 when input Array(0, 0, 0, 0, '#', '...'), Array('usedFeature' => Array('sec' => 3), 'level' => 0), Array('x')
     * @expect 5 when input Array(0, 0, 0, 0, '#', '...'), Array('usedFeature' => Array('if' => 4), 'level' => 0), Array('if')
     * @expect 6 when input Array(0, 0, 0, 0, '#', '...'), Array('usedFeature' => Array('with' => 5), 'level' => 0, 'flags' => Array('with' => 1)), Array('with')
     * @expect 7 when input Array(0, 0, 0, 0, '#', '...'), Array('usedFeature' => Array('each' => 6), 'level' => 0), Array('each')
     * @expect 8 when input Array(0, 0, 0, 0, '#', '...'), Array('usedFeature' => Array('unless' => 7), 'level' => 0), Array('unless')
     */
    protected static function validateOperations($token, &$context, $vars) {
        switch ($token[self::POS_OP]) {
        case '^':
            $context['stack'][] = $token[self::POS_INNERTAG];
            $context['level']++;
            return ++$context['usedFeature']['isec'];

        case '/':
            array_pop($context['stack']);
            $context['level']--;
            return true;

        case '!':
            return ++$context['usedFeature']['comment'];

        case '#':
            $context['stack'][] = $token[self::POS_INNERTAG];
            $context['level']++;

            // detect block custom helpers.
            if (isset($context['blockhelpers'][$vars[0][0]])) {
                return ++$context['usedFeature']['bhelper'];
            }

            switch ($vars[0]) {
            case 'with':
                if (isset($vars[1]) && !$context['flags']['with']) {
                    $context['error'][] = 'do not support {{#with var}}, you should do compile with LightnCandy::FLAG_WITH flag';
                }
                if ((count($vars) < 2) && $context['flags']['with']) {
                    $context['error'][] = 'no argument after {{#with}} !';
                }
                // Continue to add usage...
            case 'each':
            case 'unless':
            case 'if':
                return ++$context['usedFeature'][$vars[0]];

            default:
                return ++$context['usedFeature']['sec'];
            }
        }
    }

    /**
     * Internal method used by compile(). Collect handlebars usage information, detect template error.
     *
     * @param string[] $token detected handlebars {{ }} token
     * @param array $context current compile context
     *
     * @codeCoverageIgnore
     */
    protected static function scanFeatures($token, &$context) {
        list($raw, $vars) = self::parseTokenArgs($token, $context);

        if (self::validateStartEnd($token, $context, $raw)) {
            return;
        }

        if (self::validateOperations($token, $context, $vars)) {
            return;
        }

        $context['usedFeature'][$raw ? 'raw' : 'enc']++;

        if (count($vars) == 0) {
            return $context['error'][] = 'Wrong variable naming in ' . self::tokenString($token);
        }

        // validate else and this.
        switch ($vars[0]) {
        case 'else':
            return $context['usedFeature']['else']++;

        case 'this':
        case '.':
            if ($context['level'] == 0) {
                $context['usedFeature']['rootthis']++;
            }
            if (!$context['flags']['this']) {
                $context['error'][] = "do not support {{{$vars[0]}}}, you should do compile with LightnCandy::FLAG_THIS flag";
            }
            return $context['usedFeature'][($vars[0] == '.') ? 'dot' : 'this']++;
        }

        // detect custom helpers.
        if (isset($context['helpers'][$vars[0][0]])) {
            return $context['usedFeature']['helper']++;
        }
    }

    /**
     * Internal method used by compile(). Show error message when named arguments appear without custom helper.
     *
     * @param array $token detected handlebars {{ }} token
     * @param array $context current compile context
     * @param boolean $named is named arguments
     *
     */
    public static function noNamedArguments($token, &$context, $named) {
        if ($named) {
            $context['error'][] = 'do not support name=value in ' . self::tokenString($token) . '!';
        }
    }

    /**
     * Internal method used by compile(). Return compiled PHP code partial for a handlebars token.
     *
     * @param array $token detected handlebars {{ }} token
     * @param array $context current compile context
     *
     * @return string Return compiled code segment for the token
     *
     * @codeCoverageIgnore
     */
    public static function compileToken(&$token, &$context) {
        list($raw, $vars) = self::parseTokenArgs($token, $context);
        $named = count(array_diff_key($vars, array_keys(array_keys($vars)))) > 0;

        // Handle space control.
        if ($token[self::POS_LSPACECTL]) {
            $token[self::POS_LSPACE] = '';
        }

        if ($token[self::POS_RSPACECTL]) {
            $token[self::POS_RSPACE] = '';
        }

        if ($ret = self::compileSection($token, $context, $vars, $named)) {
            return $ret;
        }

        if ($ret = self::compileCustomHelper($context, $vars, $raw, $named)) {
            return $ret;
        }

        if ($ret = self::compileElse($context, $vars)) {
            return $ret;
        }

        self::noNamedArguments($token, $context, $named);

        return self::compileVariable($context, $vars, $raw);
    }

    /**
     * Internal method used by compile(). Return compiled PHP code partial for a handlebars section token.
     *
     * @param array $token detected handlebars {{ }} token
     * @param array $context current compile context
     * @param array $vars parsed arguments list
     * @param boolean $named is named arguments or not
     *
     * @return string|null Return compiled code segment for the token when the token is section
     *
     * @codeCoverageIgnore
     */
    protected static function compileSection(&$token, &$context, $vars, $named) {
        switch ($token[self::POS_OP]) {
        case '^':
            $v = self::getVariableName($vars[0], $context);
            $context['stack'][] = self::getArrayCode($vars[0]);
            $context['stack'][] = '^';
            self::noNamedArguments($token, $context, $named);
            return "{$context['ops']['cnd_start']}(" . self::getFuncName($context, 'isec') . "($v)){$context['ops']['cnd_then']}";
        case '/':
            return self::compileBlockEnd($token, $context, $vars);
        case '!':
            return $context['ops']['seperator'];
        case '#':
            $r = self::compileBlockCustomHelper($context, $vars);
            if ($r) {
                return $r;
            }
            self::noNamedArguments($token, $context, $named);
            return self::compileBlockBegin($context, $vars);
        }
    }

    /**
     * Internal method used by compile(). Return compiled PHP code partial for a handlebars block custom helper begin token.
     *
     * @param array $context current compile context
     * @param array $vars parsed arguments list
     *
     * @return string Return compiled code segment for the token
     *
     * @codeCoverageIgnore
     */
    protected static function compileBlockCustomHelper(&$context, $vars) {
        if (!isset($context['blockhelpers'][$vars[0][0]])) {
            return;
        }
        $context['vars'][] = $vars[0];
        $context['stack'][] = self::getArrayCode($vars[0]);
        $context['stack'][] = '#';
        $ch = array_shift($vars);
        self::addUsageCount($context, 'blockhelpers', $ch[0]);
        $v = self::getVariableNames($vars, $context);
        return $context['ops']['seperator'] . self::getFuncName($context, 'bch') . "('$ch[0]', $v, \$cx, \$in, function(\$cx, \$in) {{$context['ops']['f_start']}";
    }

    /**
     * Internal method used by compile(). Return compiled PHP code partial for a handlebars block end token.
     *
     * @param array $token detected handlebars {{ }} token
     * @param array $context current compile context
     * @param array $vars parsed arguments list
     *
     * @return string Return compiled code segment for the token
     *
     * @codeCoverageIgnore
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
                if ($pop !== 'with') {
                   $context['error'][] = 'Unexpect token /with !';
                   return;
                }
                return "{$context['ops']['f_end']}}){$context['ops']['seperator']}";
            case 'each':
                $each = true;
                // Continue to same logic {{/each}} === {{/any_value}}
            default:
                array_pop($context['vars']);
                switch($pop) {
                case '#':
                case '^':
                    $pop2 = array_pop($context['stack']);
                    if (!$each && ($pop2 !== self::getArrayCode($vars[0]))) {
                        $context['error'][] = 'Unexpect token ' . self::tokenString($token) . " ! Previous token $pop$pop2 is not closed";
                        return;
                    }
                    if ($pop == '^') {
                        return $context['usedFeature']['parent'] ? "{$context['ops']['f_end']}}){$context['ops']['seperator']}" : "{$context['ops']['cnd_else']}''{$context['ops']['cnd_end']}";
                    }
                    return "{$context['ops']['f_end']}}){$context['ops']['seperator']}";
                default:
                    $context['error'][] = 'Unexpect token: ' . self::tokenString($token) . ' !';
                    return;
                }
            }
    }

    /**
     * Internal method used by compile(). Return compiled PHP code partial for a handlebars block begin token.
     *
     * @param array $context current compile context
     * @param array $vars parsed arguments list
     *
     * @return string Return compiled code segment for the token
     *
     * @codeCoverageIgnore
     */
    protected static function compileBlockBegin(&$context, $vars) {
        $each = 'false';
        $v = isset($vars[1]) ? self::getVariableName($vars[1], $context) : null;
        switch ($vars[0][0]) {
        case 'if':
            $context['stack'][] = 'if';
            return $context['usedFeature']['parent'] 
                ? $context['ops']['seperator'] . self::getFuncName($context, 'ifv') . "($v, \$cx, \$in, function(\$cx, \$in) {{$context['ops']['f_start']}"
                : "{$context['ops']['cnd_start']}(" . self::getFuncName($context, 'ifvar') . "($v)){$context['ops']['cnd_then']}";
        case 'unless':
            $context['stack'][] = 'unless';
            return $context['usedFeature']['parent']
                ? $context['ops']['seperator'] . self::getFuncName($context, 'unl') . "($v, \$cx, \$in, function(\$cx, \$in) {{$context['ops']['f_start']}"
                : "{$context['ops']['cnd_start']}(!" . self::getFuncName($context, 'ifvar') . "($v)){$context['ops']['cnd_then']}";
        case 'each':
            $each = 'true';
            array_shift($vars);
            break;
        case 'with':
            if ($context['flags']['with']) {
                $context['vars'][] = $vars[1];
                $context['stack'][] = 'with';
                return $context['ops']['seperator'] . self::getFuncName($context, 'wi') . "($v, \$cx, \$in, function(\$cx, \$in) {{$context['ops']['f_start']}";
            }
        }

        $v = self::getVariableName($vars[0], $context);
        $context['vars'][] = $vars[0];
        $context['stack'][] = self::getArrayCode($vars[0]);
        $context['stack'][] = '#';
        return $context['ops']['seperator'] . self::getFuncName($context, 'sec') . "($v, \$cx, \$in, $each, function(\$cx, \$in) {{$context['ops']['f_start']}";
    }

    /**
     * Internal method used by compile(). Return compiled PHP code partial for a handlebars custom helper token.
     *
     * @param array $context current compile context
     * @param array $vars parsed arguments list
     * @param boolean $raw is this {{{ token or not
     * @param boolean $named is named arguments or not
     *
     * @return string|null Return compiled code segment for the token when the token is custom helper
     *
     * @codeCoverageIgnore
     */
    protected static function compileCustomHelper(&$context, &$vars, $raw, $named) {
        $fn = $raw ? 'raw' : $context['ops']['enc'];
        if (isset($context['helpers'][$vars[0][0]])) {
            $ch = array_shift($vars);
            self::addUsageCount($context, 'helpers', $ch[0]);
            foreach ($vars as $var) {
                self::addJsonSchema($context, $var);
            }
            return $context['ops']['seperator'] . self::getFuncName($context, 'ch') . "('$ch[0]', " . self::getVariableNames($vars, $context) . ", '$fn', \$cx" . ($named ? ', true' : '') . "){$context['ops']['seperator']}";
        }
    }

   /**
     * Internal method used by compile(). Return compiled PHP code partial for a handlebars else token.
     *
     * @param array $context current compile context
     * @param array $vars parsed arguments list
     *
     * @return string|null Return compiled code segment for the token when the token is else
     *
     * @codeCoverageIgnore
     */
    protected static function compileElse(&$context, &$vars) {
        if ($vars[0][0] === 'else') {
            switch ($context['stack'][count($context['stack']) - 1]) {
            case 'if':
            case 'unless':
                $context['stack'][] = ':';
                return $context['usedFeature']['parent'] ? "{$context['ops']['f_end']}}, function(\$cx, \$in) {{$context['ops']['f_start']}" : "{$context['ops']['cnd_else']}";
            case 'each':
            case '#':
                return "{$context['ops']['f_end']}}, function(\$cx, \$in) {{$context['ops']['f_start']}";
            default:
                $context['error'][] = '{{else}} only valid in if, unless, each, and #section context';
            }
        }
    }

   /**
     * Internal method used by compile(). Return compiled PHP code partial for a handlebars variable token.
     *
     * @param array $context current compile context
     * @param array $vars parsed arguments list
     * @param boolean $raw is this {{{ token or not
     *
     * @return string Return compiled code segment for the token
     *
     * @codeCoverageIgnore
     */
    protected static function compileVariable(&$context, &$vars, $raw) {
        self::addJsonSchema($context, $vars[0]);
        $v = self::getVariableName($vars[0], $context);
        if ($context['flags']['jsobj'] || $context['flags']['jstrue']) {
            return $context['ops']['seperator'] . self::getFuncName($context, $raw ? 'raw' : $context['ops']['enc']) . "($v, \$cx){$context['ops']['seperator']}";
        } else {
            return $raw ? "{$context['ops']['seperator']}$v{$context['ops']['seperator']}" : "{$context['ops']['seperator']}htmlentities($v, ENT_QUOTES, 'UTF-8'){$context['ops']['seperator']}";
        }
    }

   /**
     * Internal method used by compile(). Add usage count to context
     *
     * @param array $context current context
     * @param string $category ctegory name, can be one of: 'var', 'helpers', 'blockhelpers'
     * @param string $name used name
     *
     * @codeCoverageIgnore
     */
    protected static function addUsageCount(&$context, $category, $name) {
         if (!isset($context['usedCount'][$category][$name])) {
             $context['usedCount'][$category][$name] = 0;
         }
         $context['usedCount'][$category][$name]++;
    }
}

/**
 * LightnCandy static class for compiled template runtime methods.
 */
class LCRun2 {
    /**
     * LightnCandy runtime method for {{#if var}}.
     *
     * @param mixed $v value to be tested
     *
     * @return boolean Return true when the value is not null nor false.
     * 
     * @expect false when input null
     * @expect false when input 0
     * @expect false when input false
     * @expect true when input true
     * @expect true when input 1
     * @expect false when input ''
     * @expect false when input Array()
     * @expect true when input Array('')
     * @expect true when input Array(0)
     */
    public static function ifvar($v) {
        return !is_null($v) && ($v !== false) && ($v !== 0) && ($v !== '') && (is_array($v) ? (count($v) > 0) : true);
    }

    /**
     * LightnCandy runtime method for {{#if var}} when {{../var}} used.
     *
     * @param array $v value to be tested
     * @param array $cx render time context
     * @param array $in input data with current scope
     * @param Closure $truecb callback function when test result is true
     * @param Closure $falsecb callback function when test result is false
     *
     * @return string The rendered string of the section
     * 
     * @expect '' when input null, Array('scopes' => Array()), Array(), null
     * @expect '' when input null, Array('scopes' => Array()), Array(), function () {return 'Y';}
     * @expect 'Y' when input 1, Array('scopes' => Array()), Array(), function () {return 'Y';}
     * @expect 'N' when input null, Array('scopes' => Array()), Array(), function () {return 'Y';}, function () {return 'N';}
     */
    public static function ifv($v, $cx, $in, $truecb, $falsecb = null) {
        $ret = '';
        if (self::ifvar($v)) {
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
     * @param mixed $var value be tested
     * @param array $cx render time context
     * @param array $in input data with current scope
     *
     * @return string Return rendered string when the value is not null nor false.
     *
     * @expect '' when input null, Array('scopes' => Array()), Array(), null
     * @expect 'Y' when input null, Array('scopes' => Array()), Array(), function () {return 'Y';}
     * @expect '' when input 1, Array('scopes' => Array()), Array(), function () {return 'Y';}
     * @expect 'Y' when input null, Array('scopes' => Array()), Array(), function () {return 'Y';}, function () {return 'N';}
     * @expect 'N' when input true, Array('scopes' => Array()), Array(), function () {return 'Y';}, function () {return 'N';}
     */
    public static function unl($var, $cx, $in, $truecb, $falsecb = null) {
        return self::ifv($var, $cx, $in, $falsecb, $truecb);
    }

    /**
     * LightnCandy runtime method for {{^var}} inverted section.
     *
     * @param mixed $v value to be tested
     *
     * @return boolean Return true when the value is not null nor false.
     *
     * @expect true when input null
     * @expect false when input 0
     * @expect true when input false
     * @expect false when input 'false'
     */
    public static function isec($v) {
        return is_null($v) || ($v === false);
    }

    /**
     * LightnCandy runtime method for {{{var}}} .
     *
     * @param mixed $v value to be output
     * @param array $cx render time context
     * @param boolean $loop true when in loop
     *
     * @return string The raw value of the specified variable
     *
     * @expect true when input true, Array('flags' => Array('jstrue' => 0))
     * @expect 'true' when input true, Array('flags' => Array('jstrue' => 1))
     * @expect '' when input false, Array('flags' => Array('jstrue' => 0))
     * @expect '' when input false, Array('flags' => Array('jstrue' => 1))
     * @expect 'false' when input false, Array('flags' => Array('jstrue' => 1)), true
     * @expect Array('a', 'b') when input Array('a', 'b'), Array('flags' => Array('jstrue' => 1, 'jsobj' => 0))
     * @expect 'a,b' when input Array('a','b'), Array('flags' => Array('jstrue' => 1, 'jsobj' => 1))
     * @expect '[object Object]' when input Array('a', 'c' => 'b'), Array('flags' => Array('jstrue' => 1, 'jsobj' => 1))
     * @expect '[object Object]' when input Array('c' => 'b'), Array('flags' => Array('jstrue' => 1, 'jsobj' => 1))
     * @expect 'a,true' when input Array('a', true), Array('flags' => Array('jstrue' => 1, 'jsobj' => 1))
     * @expect 'a,1' when input Array('a',true), Array('flags' => Array('jstrue' => 0, 'jsobj' => 1))
     * @expect 'a,' when input Array('a',false), Array('flags' => Array('jstrue' => 0, 'jsobj' => 1))
     * @expect 'a,false' when input Array('a',false), Array('flags' => Array('jstrue' => 1, 'jsobj' => 1))
     */
    public static function raw($v, $cx, $loop = false) {
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
                        $ret[] = self::raw($vv, $cx, true);
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
     * @param mixed $var value to be htmlencoded
     * @param array $cx render time context
     *
     * @return string The htmlencoded value of the specified variable
     *
     * @expect 'a' when input 'a', Array()
     * @expect 'a&amp;b' when input 'a&b', Array()
     * @expect 'a&#039;b' when input 'a\'b', Array()
     */
    public static function enc($var, $cx) {
        return htmlentities(self::raw($var, $cx), ENT_QUOTES, 'UTF-8');
    }

    /**
     * LightnCandy runtime method for {{var}} , and deal with single quote to same as handlebars.js .
     *
     * @param mixed $var value to be htmlencoded
     * @param array $cx render time context
     *
     * @return string The htmlencoded value of the specified variable
     *
     * @expect 'a' when input 'a', Array()
     * @expect 'a&amp;b' when input 'a&b', Array()
     * @expect 'a&#x27;b' when input 'a\'b', Array()
     */
    public static function encq($var, $cx) {
        return preg_replace('/&#039;/', '&#x27;', htmlentities(self::raw($var, $cx), ENT_QUOTES, 'UTF-8'));
    }

    /**
     * LightnCandy runtime method for {{#var}} section.
     *
     * @param mixed $v value for the section
     * @param array $cx render time context
     * @param array $in input data with current scope
     * @param boolean $each true when rendering #each
     * @param Closure $cb callback function to render child context
     *
     * @return string The rendered string of the section
     *
     * @expect '' when input false, Array(), false, false, function () {return 'A';}
     * @expect '' when input null, Array(), null, false, function () {return 'A';}
     * @expect 'A' when input true, Array(), true, false, function () {return 'A';}
     * @expect 'A' when input 0, Array(), 0, false, function () {return 'A';}
     * @expect '-a=' when input Array('a'), Array(), Array('a'), false, function ($c, $i) {return "-$i=";}
     * @expect '-a=-b=' when input Array('a','b'), Array(), Array('a','b'), false, function ($c, $i) {return "-$i=";}
     * @expect '' when input 'abc', Array(), 'abc', true, function ($c, $i) {return "-$i=";}
     * @expect '-b=' when input Array('a'=>'b'), Array(), Array('a' => 'b'), true, function ($c, $i) {return "-$i=";}
     * @expect 0 when input 'b', Array(), 'b', false, function ($c, $i) {return count($i);}
     * @expect '1' when input 1, Array(), 1, false, function ($c, $i) {return print_r($i, true);}
     * @expect '0' when input 0, Array(), 0, false, function ($c, $i) {return print_r($i, true);}
     * @expect '{"b":"c"}' when input Array('b'=>'c'), Array(), Array('b' => 'c'), false, function ($c, $i) {return json_encode($i);}
     * @expect 'inv' when input Array(), Array(), 0, true, function ($c, $i) {return 'cb';}, function ($c, $i) {return 'inv';}
     * @expect 'inv' when input Array(), Array(), 0, false, function ($c, $i) {return 'cb';}, function ($c, $i) {return 'inv';}
     * @expect 'inv' when input false, Array(), 0, true, function ($c, $i) {return 'cb';}, function ($c, $i) {return 'inv';}
     * @expect 'inv' when input false, Array(), 0, false, function ($c, $i) {return 'cb';}, function ($c, $i) {return 'inv';}
     * @expect 'inv' when input '', Array(), 0, true, function ($c, $i) {return 'cb';}, function ($c, $i) {return 'inv';}
     * @expect 'cb' when input '', Array(), 0, false, function ($c, $i) {return 'cb';}, function ($c, $i) {return 'inv';}
     * @expect 'inv' when input 0, Array(), 0, true, function ($c, $i) {return 'cb';}, function ($c, $i) {return 'inv';}
     * @expect 'cb' when input 0, Array(), 0, false, function ($c, $i) {return 'cb';}, function ($c, $i) {return 'inv';}
     * @expect 'inv' when input new stdClass, Array(), 0, true, function ($c, $i) {return 'cb';}, function ($c, $i) {return 'inv';}
     * @expect 'cb' when input new stdClass, Array(), 0, false, function ($c, $i) {return 'cb';}, function ($c, $i) {return 'inv';}
     */
    public static function sec($v, &$cx, $in, $each, $cb, $inv = null) {
        $isary = is_array($v);
        $loop = $each;
        $keys = null;

        if ($isary && $inv !== null && count($v) === 0) {
            $cx['scopes'][] = $in;
            $ret = $inv($cx, $v);
            array_pop($cx['scopes']);
            return $ret;
        }
        if (!$loop && $isary) {
            $keys = array_keys($v);
            $loop = (count(array_diff_key($v, array_keys($keys))) == 0);
            $is_obj = !$loop;
        }
        if ($loop && $isary) {
            if ($each) {
                if ($keys == null) {
                    $keys = array_keys($v);
                    $is_obj = (count(array_diff_key($v, array_keys($keys))) > 0);
                }
            } else {
                $is_obj = false;
            }
            $ret = Array();
            $cx['scopes'][] = $in;
            $i = 0;
            if ($cx['flags']['spvar']) {
                $last = count($keys) - 1;
            }
            foreach ($v as $index => $raw) {
                if ($cx['flags']['spvar']) {
                    $cx['sp_vars']['first'] = ($i === 0);
                    if ($is_obj) {
                        $cx['sp_vars']['key'] = $index;
                        $cx['sp_vars']['index'] = $i;
                    } else {
                        $cx['sp_vars']['last'] = ($i == $last);
                        $cx['sp_vars']['index'] = $index;
                    }
                $i++;
                }
                $ret[] = $cb($cx, $raw);
            }
            if ($cx['flags']['spvar']) {
                if ($is_obj) {
                    unset($cx['sp_vars']['key']);
                } else {
                    unset($cx['sp_vars']['last']);
                }
                unset($cx['sp_vars']['index']);
                unset($cx['sp_vars']['first']);
            }
            array_pop($cx['scopes']);
            return join('', $ret);
        }
        if ($each) {
            if ($inv !== null) {
                $cx['scopes'][] = $in;
                $ret = $inv($cx, $v);
                array_pop($cx['scopes']);
                return $ret;
            }
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

        if ($inv !== null) {
            $cx['scopes'][] = $in;
            $ret = $inv($cx, $v);
            array_pop($cx['scopes']);
            return $ret;
        }

        return '';
    }

    /**
     * LightnCandy runtime method for {{#with var}} .
     *
     * @param mixed $v value to be the new context
     * @param array $cx render time context
     * @param array $in input data with current scope
     * @param Closure $cb callback function to render child context
     *
     * @return string The rendered string of the token
     *
     * @expect '' when input false, Array(), false, function () {return 'A';}
     * @expect '' when input null, Array(), null, function () {return 'A';}
     * @expect '-Array=' when input Array('a'=>'b'), Array(), Array('a' => 'b'), function ($c, $i) {return "-$i=";}
     * @expect '-b=' when input 'b', Array(), Array('a' => 'b'), function ($c, $i) {return "-$i=";}
     */
    public static function wi($v, &$cx, $in, $cb) {
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
     * @param array $vars variables for the helper
     * @param string $op the name of variable resolver. should be one of: 'raw', 'enc', or 'encq'.
     * @param array $cx render time context
     * @param boolean $named input arguments are named
     *
     * @return mixed The rendered string of the token, or Array with the rendered string and encode_flag
     *
     * @expect '=-=' when input 'a', Array('-'), 'raw', Array('helpers' => Array('a' => function ($i) {return "=$i=";}))
     * @expect '=&amp;=' when input 'a', Array('&'), 'enc', Array('helpers' => Array('a' => function ($i) {return "=$i=";}))
     * @expect '=&#x27;=' when input 'a', Array('\''), 'encq', Array('helpers' => Array('a' => function ($i) {return "=$i=";}))
     * @expect '=b=' when input 'a', Array('a' => 'b'), 'raw', Array('helpers' => Array('a' => function ($i) {return "={$i['a']}=";})), true
     * @expect '=&=' when input 'a', Array('&'), 'raw', Array('helpers' => Array('a' => function ($i) {return Array("=$i=");}))
     * @expect '=&amp;=' when input 'a', Array('&'), 'enc', Array('helpers' => Array('a' => function ($i) {return Array("=$i=");}))
     * @expect '=&=' when input 'a', Array('&'), 'raw', Array('helpers' => Array('a' => function ($i) {return Array("=$i=");}))
     * @expect '=&amp;&#039;&quot;=' when input 'a', Array('&\'"'), 'raw', Array('helpers' => Array('a' => function ($i) {return Array("=$i=", 'enc');}))
     * @expect '=&amp;&#x27;&quot;=' when input 'a', Array('&\'"'), 'raw', Array('helpers' => Array('a' => function ($i) {return Array("=$i=", 'encq');}))
     */
    public static function ch($ch, $vars, $op, &$cx, $named = false) {
        $args = Array();
        foreach ($vars as $i => $v) {
            $args[$i] = self::raw($v, $cx);
        }

        $r = call_user_func_array($cx['helpers'][$ch], $named ? Array($args) : $args);

        if (is_array($r)) {
            if (isset($r[1])) {
                if ($r[1]) {
                    $op = $r[1];
                } else {
                    return $r;
                }
            }
            $r = $r[0];
        }

        switch ($op) {
            case 'enc': 
                return htmlentities($r, ENT_QUOTES, 'UTF-8');
            case 'encq':
                return preg_replace('/&#039;/', '&#x27;', htmlentities($r, ENT_QUOTES, 'UTF-8'));
            case 'raw':
            default:
                return $r;
        }
    }

    /**
     * LightnCandy runtime method for block custom helpers.
     *
     * @param string $ch the name of custom helper to be executed
     * @param array $vars variables for the helper
     * @param array $cx render time context
     * @param array $in input data with current scope
     * @param Closure $cb callback function to render child context
     *
     * @return string The rendered string of the token
     */
    public static function bch($ch, $vars, &$cx, $in, $cb) {
        $args = Array();
        foreach ($vars as $i => $v) {
            $args[$i] = self::raw($v, $cx);
        }

        $r = call_user_func($cx['blockhelpers'][$ch], $in, $args);
        if (is_null($r)) {
            return '';
        }

        $cx['scopes'][] = $in;
        $ret = $cb($cx, $r);
        array_pop($cx['scopes']);
        return $ret;
    }
}
?>
