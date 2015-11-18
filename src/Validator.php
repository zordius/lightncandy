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
 * file to keep LightnCandy Validator
 *
 * @package    LightnCandy
 * @author     Zordius <zordius@yahoo-inc.com>
 */

namespace LightnCandy;
use \LightnCandy\Token;
use \LightnCandy\Parser;
use \LightnCandy\Partial;

/**
 * LightnCandy Validator
 */
class Validator {
    /**
     * Verify template
     *
     * @param array<string,array|string|integer> $context Current context
     * @param string $template handlebars template
     */
    public static function verify(&$context, $template) {
        $template = String::escapeTemplate(String::stripExtendedComments($template));
        $context['level'] = 0;
        Parser::setDelimiter($context);

        while (preg_match($context['tokens']['search'], $template, $matches)) {
            // Skip a token when it is slash escaped
            if ($context['flags']['slash'] && ($matches[Token::POS_LSPACE] === '') && preg_match('/^(.*?)(\\\\+)$/s', $matches[Token::POS_LOTHER], $escmatch)) {
                if (strlen($escmatch[2]) % 4) {
                    static::pushToken($context, substr($matches[Token::POS_LOTHER], 0, -2) . $context['tokens']['startchar']);
                    $matches[Token::POS_BEGINTAG] = substr($matches[Token::POS_BEGINTAG], 1);
                    $template = implode('', array_slice($matches, Token::POS_BEGINTAG));
                    continue;
                } else {
                    $matches[Token::POS_LOTHER] = $escmatch[1] . str_repeat('\\', strlen($escmatch[2]) / 2);
                }
            }
            $context['tokens']['count']++;
            $V = static::token($matches, $context);
            static::pushToken($context, $matches[Token::POS_LOTHER]);
            static::pushToken($context, $matches[Token::POS_LSPACE]);
            if ($V) {
                if (is_array($V)) {
                    array_push($V, $matches, $context['tokens']['partialind']);
                }
                static::pushToken($context, $V);
            }
            $template = "{$matches[Token::POS_RSPACE]}{$matches[Token::POS_ROTHER]}";
        }
        static::pushToken($context, $template);

        if ($context['level'] > 0) {
            $token = array_pop($context['stack']);
            $context['error'][] = 'Unclosed token ' . ($context['rawblock'] ? "{{{{{$token}}}}}" : "{{#{$token}}}") . ' !!';
        }
    }

    /**
     * push a token into the stack when it is not empty string
     *
     * @param array<string,array|string|integer> $context Current context
     * @param string|array $token a parsed token or a string
     */
    protected static function pushToken(&$context, $token) {
        if ($token === '') {
            return;
        }
        if (is_string($token)) {
            if (is_string(end($context['parsed'][0]))) {
                $context['parsed'][0][key($context['parsed'][0])] .= $token;
                return;
            }
        }
        $context['parsed'][0][] = $token;
    }

    /**
     * push current token into the section stack
     *
     * @param array<string,array|string|integer> $context Current context
     */
    protected static function pushStack(&$context) {
        $context['stack'][] = $context['currentToken'];
        $context['level']++;
    }

    /**
     * Verify delimiters and operators
     *
     * @param string[] $token detected handlebars {{ }} token
     * @param array<string,array|string|integer> $context current compile context
     *
     * @return boolean|null Return true when invalid
     *
     * @expect null when input array_fill(0, 11, ''), array()
     * @expect null when input array(0, 0, 0, 0, 0, '{{', '#', '...', '}}'), array()
     * @expect true when input array(0, 0, 0, 0, 0, '{', '#', '...', '}'), array()
     */
    protected static function delimiter($token, &$context) {
        // {{ }}} or {{{ }} are invalid
        if (strlen($token[Token::POS_BEGINRAW]) !== strlen($token[Token::POS_ENDRAW])) {
            $context['error'][] = 'Bad token ' . Token::toString($token) . ' ! Do you mean ' . Token::toString($token, array(Token::POS_BEGINRAW => '', Token::POS_ENDRAW => '')) . ' or ' . Token::toString($token, array(Token::POS_BEGINRAW => '{', Token::POS_ENDRAW => '}')) . '?';
            return true;
        }
        // {{{# }}} or {{{! }}} or {{{/ }}} or {{{^ }}} are invalid.
        if ((strlen($token[Token::POS_BEGINRAW]) == 1) && $token[Token::POS_OP] && ($token[Token::POS_OP] !== '&')) {
            $context['error'][] = 'Bad token ' . Token::toString($token) . ' ! Do you mean ' . Token::toString($token, array(Token::POS_BEGINRAW => '', Token::POS_ENDRAW => '')) . ' ?';
            return true;
        }
    }

    /**
     * Verify operators
     *
     * @param string[] $token detected handlebars {{ }} token
     * @param array<string,array|string|integer> $context current compile context
     * @param array<boolean|integer|string|array> $vars parsed arguments list
     *
     * @return boolean|integer|null Return true when invalid or detected
     *
     * @expect null when input array(0, 0, 0, 0, 0, 0, ''), array(), array()
     * @expect 2 when input array(0, 0, 0, 0, 0, 0, '^', '...'), array('usedFeature' => array('isec' => 1), 'level' => 0, 'currentToken' => ''), array(array('foo'))
     * @expect true when input array(0, 0, 0, 0, 0, 0, '/'), array('stack' => array(1), 'level' => 1), array()
     * @expect 4 when input array(0, 0, 0, 0, 0, 0, '#', '...'), array('usedFeature' => array('sec' => 3), 'level' => 0, 'currentToken' => ''), array(array('x'))
     * @expect 5 when input array(0, 0, 0, 0, 0, 0, '#', '...'), array('usedFeature' => array('if' => 4), 'level' => 0, 'currentToken' => ''), array(array('if'))
     * @expect 6 when input array(0, 0, 0, 0, 0, 0, '#', '...'), array('usedFeature' => array('with' => 5), 'level' => 0, 'flags' => array('with' => 1, 'runpart' => 0), 'currentToken' => ''), array(array('with'))
     * @expect 7 when input array(0, 0, 0, 0, 0, 0, '#', '...'), array('usedFeature' => array('each' => 6), 'level' => 0, 'currentToken' => ''), array(array('each'))
     * @expect 8 when input array(0, 0, 0, 0, 0, 0, '#', '...'), array('usedFeature' => array('unless' => 7), 'level' => 0, 'currentToken' => ''), array(array('unless'))
     * @expect 9 when input array(0, 0, 0, 0, 0, 0, '#', '...'), array('blockhelpers' => array('abc' => ''), 'usedFeature' => array('bhelper' => 8), 'level' => 0, 'currentToken' => ''), array(array('abc'))
     * @expect 11 when input array(0, 0, 0, 0, 0, 0, '#', '...'), array('hbhelpers' => array('abc' => ''), 'usedFeature' => array('hbhelper' => 10), 'level' => 0, 'currentToken' => ''), array(array('abc'))
     * @expect true when input array(0, 0, 0, 0, 0, 0, '>', '...'), array('basedir' => array('.'), 'fileext' => array('.tmpl'), 'usedFeature' => array('unless' => 7, 'partial' => 7), 'level' => 0, 'flags' => array('skippartial' => 0, 'runpart' => 0), 'currentToken' => ''), array('test')
     */
    protected static function operator(&$token, &$context, &$vars) {
        switch ($token[Token::POS_OP]) {
            case '>':
                return static::partial($context, $vars);

            case '^':
                if (!isset($vars[0][0])) {
                    if (!$context['flags']['else']) {
                        $context['error'][] = 'Do not support {{^}}, you should do compile with LightnCandy::FLAG_ELSE flag';
                        return;
                    } else {
                        return static::doElse($context);
                    }
                }

                if (static::isBlockHelper($context, $vars)) {
                    return static::blockCustomHelper($context, $vars, true);
                }

                return static::invertedSection($context, $vars);

            case '/':
                return static::blockEnd($token, $context, $vars);

            case '#':
                if (static::isBlockHelper($context, $vars)) {
                    return static::blockCustomHelper($context, $vars);
                }

                return static::blockBegin($context, $vars);
        }
    }

    /**
     * validate block begin token
     *
     * @param array<string,array|string|integer> $context current compile context
     * @param array<boolean|integer|string|array> $vars parsed arguments list
     *
     * @return string|null Return compiled code segment for the token
     */
    protected static function blockBegin(&$context, $vars) {
        static::pushStack($context);
        switch (isset($vars[0][0]) ? $vars[0][0] : null) {
            case 'with':
                return static::with($context, $vars);
            case 'each':
                return static::section($context, $vars, true);
            case 'unless':
            case 'if':
                return ++$context['usedFeature'][$vars[0][0]];

            default:
                return ++$context['usedFeature']['sec'];
        }
    }

    /**
     * validate section token
     *
     * @param array<string,array|string|integer> $context current compile context
     * @param array<boolean|integer|string|array> $vars parsed arguments list
     * @param boolean $isEach the section is #each
     *
     * @return boolean Return true always
     */
    protected static function section(&$context, $vars, $isEach = false) {
        $context['usedFeature'][$vars[0][0]]++;
        return true;
    }

    /**
     * validate with token
     *
     * @param array<string,array|string|integer> $context current compile context
     * @param array<boolean|integer|string|array> $vars parsed arguments list
     *
     * @return boolean Return true always
     */
    protected static function with(&$context, $vars) {
        if ($context['flags']['with']) {
            if (count($vars) < 2) {
                $context['error'][] = 'No argument after {{#with}} !';
            }
        } else {
            if (isset($vars[1][0])) {
                $context['error'][] = 'Do not support {{#with var}}, you should do compile with LightnCandy::FLAG_WITH flag';
            }
        }
        $context['usedFeature'][$vars[0][0]]++;
        return true;
    }

    /**
     * validate block custom helper token
     *
     * @param array<string,array|string|integer> $context current compile context
     * @param array<boolean|integer|string|array> $vars parsed arguments list
     * @param boolean $inverted the logic will be inverted
     *
     * @return string|null Return compiled code segment for the token
     */
    protected static function blockCustomHelper(&$context, $vars, $inverted = false) {
        static::pushStack($context);
        if (is_string($vars[0][0])) {
            // detect handlebars custom helpers.
            if (isset($context['hbhelpers'][$vars[0][0]])) {
                return ++$context['usedFeature']['hbhelper'];
            }

            // detect block custom helpers.
            if (isset($context['blockhelpers'][$vars[0][0]])) {
                return ++$context['usedFeature']['bhelper'];
            }
        }
    }

    /**
     * validate inverted section
     *
     * @param array<string,array|string|integer> $context current compile context
     * @param array<boolean|integer|string|array> $vars parsed arguments list
     *
     * @return integer Return number of inverted sections
     */
    protected static function invertedSection(&$context, $vars) {
        static::pushStack($context);
        return ++$context['usedFeature']['isec'];
    }

    /**
     * Return compiled PHP code for a handlebars block end token
     *
     * @param array<string> $token detected handlebars {{ }} token
     * @param array<string,array|string|integer> $context current compile context
     * @param array<boolean|integer|string|array> $vars parsed arguments list
     *
     * @return boolean Return true
     */
    protected static function blockEnd(&$token, &$context, $vars) {
        array_pop($context['stack']);
        $context['level']--;
        return true;
    }

    /**
     * handle delimiter change
     *
     * @param array<string,array|string|integer> $context current compile context
     *
     * @return boolean|null Return true when delimiter changed
     */
    protected static function isDelimiter(&$context) {
        if (preg_match('/^=\s*([^ ]+)\s+([^ ]+)\s*=$/', $context['currentToken'], $matched)) {
            $context['usedFeature']['delimiter']++;
            Parser::setDelimiter($context, $matched[1], $matched[2]);
            return true;
        }
    }

    /**
     * handle raw block
     *
     * @param string[] $token detected handlebars {{ }} token
     * @param array<string,array|string|integer> $context current compile context
     *
     * @return boolean|null Return true when in rawblock mode
     */
    protected static function rawblock(&$token, &$context) {
        $inner = $token[Token::POS_INNERTAG];
        trim($inner);

        // skip parse when inside raw block
        if ($context['rawblock'] && !(($token[Token::POS_BEGINRAW] === '{{') && ($token[Token::POS_OP] === '/') && ($context['rawblock'] === $inner))) {
            return true;
        }

        $token[Token::POS_INNERTAG] = $inner;

        // Handle raw block
        if ($token[Token::POS_BEGINRAW] === '{{') {
            if ($token[Token::POS_ENDRAW] !== '}}') {
                $context['error'][] = 'Bad token ' . Token::toString($token) . ' ! Do you mean ' . Token::toString($token, array(Token::POS_ENDRAW => '}}')) . ' ?';
            }
            if ($context['rawblock']) {
                Parser::setDelimiter($context);
                $context['rawblock'] = false;
            } else {
                if ($token[Token::POS_OP]) {
                    $context['error'][] = "Wrong raw block begin with " . Token::toString($token) . ' ! Remove "' . $token[Token::POS_OP] . '" to fix this issue.';
                }
                $context['rawblock'] = $token[Token::POS_INNERTAG];
                Parser::setDelimiter($context);
                $token[Token::POS_OP] = '#';
            }
            $token[Token::POS_ENDRAW] = '}}';
        }
    }

    /**
     * handle comment
     *
     * @param string[] $token detected handlebars {{ }} token
     * @param array<string,array|string|integer> $context current compile context
     *
     * @return boolean|null Return true when is comment
     */
    protected static function comment(&$token, &$context) {
        if ($token[Token::POS_OP] === '!') {
            $context['usedFeature']['comment']++;
            return true;
        }
    }

    /**
     * Collect handlebars usage information, detect template error.
     *
     * @param string[] $token detected handlebars {{ }} token
     * @param array<string,array|string|integer> $context current compile context
     */
    protected static function token(&$token, &$context) {
        $context['currentToken'] = $token[Token::POS_INNERTAG];
        if (static::rawblock($token, $context)) {
            return Token::toString($token);
        }

        if (static::delimiter($token, $context)) {
            return;
        }

        if (static::isDelimiter($context)) {
            static::spacing($token, $context);
            return;
        }

        if (static::comment($token, $context)) {
            static::spacing($token, $context);
            return;
        }

        list($raw, $vars) = Parser::parse($token, $context);

        // Handle spacing (standalone tags, partial indent)
        static::spacing($token, $context, (!$token[Token::POS_OP] || ($token[Token::POS_OP] === '&')) && (!$context['flags']['else'] || !isset($vars[0][0]) || ($vars[0][0] !== 'else')));

        if (static::operator($token, $context, $vars)) {
            return array($raw, $vars);
        }

        if (count($vars) == 0) {
            return $context['error'][] = 'Wrong variable naming in ' . Token::toString($token);
        }

        if (!isset($vars[0])) {
            return $context['error'][] = 'Do not support name=value in ' . Token::toString($token) . ', you should use it after a custom helper.';
        }

        $context['usedFeature'][$raw ? 'raw' : 'enc']++;

        foreach ($vars as $var) {
            if (!isset($var[0])) {
                if ($context['level'] == 0) {
                    $context['usedFeature']['rootthis']++;
                }
                $context['usedFeature']['this']++;
            }
        }

        if (!isset($vars[0][0])) {
            return array($raw, $vars);
        }

        static::helper($context, $vars[0][0]);

        return array($raw, $vars);
    }

    /**
     * Return 1 or larger number when else token detected
     *
     * @param array<string,array|string|integer> $context current compile context
     *
     * @return integer Return 1 or larger number when else token detected
     */
    protected static function doElse(&$context) {
        return ++$context['usedFeature']['else'];
    }

    /**
     * Return 1 or larger number when custom helper detected
     *
     * @param array<string,array|string|integer> $context current compile context
     * @param string $name token name
     *
     * @return integer|null Return 1 or larger number when custom helper detected
     */
    protected static function helper(&$context, $name) {
        // detect handlebars custom helpers.
        if (isset($context['hbhelpers'][$name])) {
            return $context['usedFeature']['hbhelper']++;
        }

        // detect custom helpers.
        if (isset($context['helpers'][$name])) {
            return $context['usedFeature']['helper']++;
        }
    }

    /**
     * detect for block custom helper
     *
     * @param array<string,array|string|integer> $context current compile context
     * @param array<boolean|integer|string|array> $vars parsed arguments list
     *
     * @return boolean|null Return true when this token is block :w
custom helper
     */
    protected static function isBlockHelper($context, $vars) {
        if (!isset($vars[0][0])) {
            return;
        }

        if (!isset($context['blockhelpers'][$vars[0][0]]) && !isset($context['hbhelpers'][$vars[0][0]])) {
            return;
        }

        return true;
    }

    /**
     * validate partial
     *
     * @param array<string,array|string|integer> $context current compile context
     * @param array<boolean|integer|string|array> $vars parsed arguments list
     *
     * @return integer|true Return 1 or larger number for runtime partial, return true for other case
     */
    protected static function partial(&$context, $vars) {
        if (Parser::isSubexp($vars[0])) {
            if ($context['flags']['runpart']) {
                return $context['usedFeature']['dynpartial']++;
            } else {
                $context['error'][] = "You use dynamic partial name as '{$vars[0][2]}', this only works with option FLAG_RUNTIMEPARTIAL enabled";
                return true;
            }
        } else {
            Partial::readPartial($vars[0][0], $context);
        }
        if (!$context['flags']['runpart']) {
        $named = count(array_diff_key($vars, array_keys(array_keys($vars)))) > 0;
            if ($named || (count($vars) > 1)) {
                $context['error'][] = "Do not support {{>{$context['currentToken']}}}, you should do compile with LightnCandy::FLAG_RUNTIMEPARTIAL flag";
            }
        }

        return true;
    }

    /**
     * Modify $token when spacing rules matched.
     *
     * @param array<string> $token detected handlebars {{ }} token
     * @param array<string,array|string|integer> $context current compile context
     * @param boolean $nost do not do stand alone logic
     *
     * @return string|null Return compiled code segment for the token
     */
    protected static function spacing(&$token, &$context, $nost = false) {
        // Handle space control.
        if ($token[Token::POS_LSPACECTL]) {
            $token[Token::POS_LSPACE] = '';
        }
        if ($token[Token::POS_RSPACECTL]) {
            $token[Token::POS_RSPACE] = '';
        }

        if ($context['flags']['noind']) {
            return;
        }
        // left line change detection
        $lsp = preg_match('/^(.*)(\\r?\\n)([ \\t]*?)$/s', $token[Token::POS_LSPACE], $lmatch);
        $ind = $lsp ? $lmatch[3] : $token[Token::POS_LSPACE];
        // right line change detection
        $rsp = preg_match('/^([ \\t]*?)(\\r?\\n)(.*)$/s', $token[Token::POS_RSPACE], $rmatch);
        $st = true;
        // setup ahead flag
        $ahead = $context['tokens']['ahead'];
        $context['tokens']['ahead'] = preg_match('/^[^\n]*{{/s', $token[Token::POS_RSPACE] . $token[Token::POS_ROTHER]);
        // reset partial indent
        $context['tokens']['partialind'] = '';
        // same tags in the same line , not standalone
        if (!$lsp && $ahead) {
            $st = false;
        }
        if ($nost) {
            $st = false;
        }
        // not standalone because other things in the same line ahead
        if ($token[Token::POS_LOTHER] && !$token[Token::POS_LSPACE]) {
            $st = false;
        }
        // not standalone because other things in the same line behind
        if ($token[Token::POS_ROTHER] && !$token[Token::POS_RSPACE]) {
            $st = false;
        }
        if ($st && (($lsp && $rsp) // both side cr
            || ($rsp && !$token[Token::POS_LOTHER]) // first line without left
            || ($lsp && !$token[Token::POS_ROTHER]) // final line
           )) {
            // handle partial
            if ((!$context['flags']['noind']) && ($token[Token::POS_OP] === '>')) {
                $context['tokens']['partialind'] = $ind;
            }
            $token[Token::POS_LSPACE] = (isset($lmatch[2]) ? ($lmatch[1] . $lmatch[2]) : '');
            $token[Token::POS_RSPACE] = isset($rmatch[3]) ? $rmatch[3] : '';
        }
    }
}

