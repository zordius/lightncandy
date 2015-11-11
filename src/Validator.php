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
        while (preg_match($context['tokens']['search'], $template, $matches)) {
            $context['tokens']['count']++;
            static::pushToken($context, $matches[Token::POS_LOTHER]);
            static::pushToken($context, $matches[Token::POS_LSPACE]);
            static::pushToken($context, static::token($matches, $context));
            $template = $matches[Token::POS_ROTHER];
        }
        static::pushToken($context, $template);
    }


    /**
     * push a token into the stack when it is not empty string
     *
     * @param array<string,array|string|integer> $context Current context
     * @param string|array $token a parsed token or a string
     */
    protected static function pushToken(&$context, $token) {
        if ($token !== '') {
            $context['parsed'][] = $token;
        }
    }

    /**
     * Verify delimiters and operators
     *
     * @param string[] $token detected handlebars {{ }} token
     * @param array<string,array|string|integer> $context current compile context
     *
     * @return boolean|null Return true when invalid
     *
     * @expect null when input array_fill(0, 9, ''), array()
     * @expect null when input array_fill(0, 9, '}}'), array()
     * @expect true when input array_fill(0, 9, '{{{'), array()
     */
    protected static function delimiter($token, &$context) {
        // {{ }}} or {{{ }} are invalid
        if (strlen($token[Token::POS_BEGINTAG]) !== strlen($token[Token::POS_ENDTAG])) {
            $context['error'][] = 'Bad token ' . token::toString($token) . ' ! Do you mean {{' . token::toString($token, 4) . '}} or {{{' . token::toString($token, 4) . '}}}?';
            return true;
        }
        // {{{# }}} or {{{! }}} or {{{/ }}} or {{{^ }}} are invalid.
        if ((strlen($token[Token::POS_BEGINTAG]) === 3) && $token[Token::POS_OP] && ($token[Token::POS_OP] !== '&')) {
            $context['error'][] = 'Bad token ' . token::toString($token) . ' ! Do you mean {{' . token::toString($token, 4) . '}} ?';
            return true;
        }
    }

    /**
     * Verify operators
     *
     * @param string[] $token detected handlebars {{ }} token
     * @param array<string,array|string|integer> $context current compile context
     * @param array<array> $vars parsed arguments list
     * @param boolean $named is named arguments
     *
     * @return boolean|integer|null Return true when invalid or detected
     *
     * @expect null when input array(0, 0, 0, 0, 0, ''), array(), array(), false
     * @expect 2 when input array(0, 0, 0, 0, 0, '^', '...'), array('usedFeature' => array('isec' => 1), 'level' => 0), array(array('foo')), false
     * @expect 3 when input array(0, 0, 0, 0, 0, '!', '...'), array('usedFeature' => array('comment' => 2)), array(), false
     * @expect true when input array(0, 0, 0, 0, 0, '/'), array('stack' => array(1), 'level' => 1), array(), false
     * @expect 4 when input array(0, 0, 0, 0, 0, '#', '...'), array('usedFeature' => array('sec' => 3), 'level' => 0), array(array('x')), false
     * @expect 5 when input array(0, 0, 0, 0, 0, '#', '...'), array('usedFeature' => array('if' => 4), 'level' => 0), array(array('if')), false
     * @expect 6 when input array(0, 0, 0, 0, 0, '#', '...'), array('usedFeature' => array('with' => 5), 'level' => 0, 'flags' => array('with' => 1)), array(array('with')), false
     * @expect 7 when input array(0, 0, 0, 0, 0, '#', '...'), array('usedFeature' => array('each' => 6), 'level' => 0), array(array('each')), false
     * @expect 8 when input array(0, 0, 0, 0, 0, '#', '...'), array('usedFeature' => array('unless' => 7), 'level' => 0), array(array('unless')), false
     * @expect 9 when input array(0, 0, 0, 0, 0, '#', '...'), array('blockhelpers' => array('abc' => ''), 'usedFeature' => array('bhelper' => 8), 'level' => 0), array(array('abc')), false
     * @expect 10 when input array(0, 0, 0, 0, 0, ' ', '...'), array('usedFeature' => array('delimiter' => 9), 'level' => 0), array(), false
     * @expect 11 when input array(0, 0, 0, 0, 0, '#', '...'), array('hbhelpers' => array('abc' => ''), 'usedFeature' => array('hbhelper' => 10), 'level' => 0), array(array('abc')), false
     * @expect true when input array(0, 0, 0, 0, 0, '>', '...'), array('basedir' => array('.'), 'fileext' => array('.tmpl'), 'usedFeature' => array('unless' => 7, 'partial' => 7), 'level' => 0, 'flags' => array('skippartial' => 0)), array('test'), false
     */
    protected static function operator(&$token, &$context, $vars, $named) {
        switch ($token[Token::POS_OP]) {
            case '>':
                static::partial($token, $context, $vars, $named);
                return true;

            case ' ':
                return ++$context['usedFeature']['delimiter'];

            case '^':
                if (isset($vars[0][0])) {
                    $context['stack'][] = $token[Token::POS_INNERTAG];
                    $context['level']++;
                    return ++$context['usedFeature']['isec'];
                }

                if (!$context['flags']['else']) {
                    $context['error'][] = 'Do not support {{^}}, you should do compile with LightnCandy::FLAG_ELSE flag';
                }
                return;

            case '/':
                array_pop($context['stack']);
                $context['level']--;
                return true;

            case '!':
                return ++$context['usedFeature']['comment'];

            case '#':
                $context['stack'][] = $token[Token::POS_INNERTAG];
                $context['level']++;

                if (!isset($vars[0][0])) {
                    return;
                }

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

                switch ($vars[0][0]) {
                    case 'with':
                        if ($context['flags']['with']) {
                            if (count($vars) < 2) {
                                $context['error'][] = 'No argument after {{#with}} !';
                            }
                        } else {
                            if (isset($vars[1][0])) {
                                $context['error'][] = 'Do not support {{#with var}}, you should do compile with LightnCandy::FLAG_WITH flag';
                            }
                        }
                        // Continue to add usage...
                    case 'each':
                    case 'unless':
                    case 'if':
                        return ++$context['usedFeature'][$vars[0][0]];

                    default:
                        return ++$context['usedFeature']['sec'];
                }
        }
    }

    /**
     * Collect handlebars usage information, detect template error.
     *
     * @param string[] $token detected handlebars {{ }} token
     * @param array<string,array|string|integer> $context current compile context
     */
    protected static function token($token, &$context) {
        list($raw, $vars) = Parser::parse($token, $context);

        if ($raw === -1) {
            return Token::toString($token);
        }

        $named = count(array_diff_key($vars, array_keys(array_keys($vars)))) > 0;

        // Handle spacing (standalone tags, partial indent)
        static::spacing($token, $vars, $context);

        // Handle space control.
        if ($token[Token::POS_LSPACECTL]) {
            $token[Token::POS_LSPACE] = '';
        }
        if ($token[Token::POS_RSPACECTL]) {
            $token[Token::POS_RSPACE] = '';
        }

        if (static::delimiter($token, $context)) {
            return;
        }

        if (static::operator($token, $context, $vars, $named)) {
            return;
        }

        if (($token[Token::POS_OP] === '^') && ($context['flags']['else'])) {
            return $context['usedFeature']['else']++;
        }

        if (count($vars) == 0) {
            return $context['error'][] = 'Wrong variable naming in ' . token::toString($token);
        }

        if (!isset($vars[0])) {
            return static::noNamedArguments($token, $context, true, ', you should use it after a custom helper.');
        }

        if ($vars[0] !== 'else') {
            $context['usedFeature'][$raw ? 'raw' : 'enc']++;
        }

        foreach ($vars as $var) {
            if (!isset($var[0])) {
                if ($context['level'] == 0) {
                    $context['usedFeature']['rootthis']++;
                }
                $context['usedFeature']['this']++;
            }
        }

        if (!isset($vars[0][0])) {
            return;
        }

        if (static::doElse($token, $context, $vars[0][0])) {
            return;
        }

        static::helper($token, $context, $vars[0][0]);
    }

    /**
     * Return 1 or larger number when else token detected
     *
     * @param array<string> $token detected handlebars {{ }} token
     * @param array<string,array|string|integer> $context current compile context
     * @param string $name token name
     *
     * @return integer|null Return 1 or larger number when else token detected
     */
    protected static function doElse($token, &$context, $name) {
        if ($name === 'else') {
            if ($context['flags']['else']) {
                return $context['usedFeature']['else']++;
            }
        }
    }

    /**
     * Return 1 or larger number when custom helper detected
     *
     * @param array<string> $token detected handlebars {{ }} token
     * @param array<string,array|string|integer> $context current compile context
     * @param string $name token name
     *
     * @return integer|null Return 1 or larger number when custom helper detected
     */
    protected static function helper($token, &$context, $name) {
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
     * Append error message when named arguments appear without helper.
     *
     * @param array<string> $token detected handlebars {{ }} token
     * @param array<string,array|string|integer> $context current compile context
     * @param boolean $named is named arguments
     * @param string $suggest extended hint for this no named argument error
     */
    public static function noNamedArguments($token, &$context, $named, $suggest = '!') {
        if ($named) {
            $context['error'][] = 'Do not support name=value in ' . token::toString($token) . $suggest;
        }
    }

    /**
     * Append error message when named arguments appear without helper.
     *
     * @param array<string> $token detected handlebars {{ }} token
     * @param array<string,array|string|integer> $context current compile context
     * @param boolean $named is named arguments
     * @param string $suggest extended hint for this no named argument error
     */
    public static function partial($token, &$context, $vars, $named) {
        Partial::readPartial($vars[0][0], $context);
    }

    /**
     * Modify $token when spacing rules matched.
     *
     * @param array<string> $token detected handlebars {{ }} token
     * @param array<array|string|integer> $vars parsed arguments list
     * @param array<string,array|string|integer> $context current compile context
     *
     * @return string|null Return compiled code segment for the token
     */
    protected static function spacing(&$token, $vars, &$context) {
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
        // Do not need standalone detection for these tags
        if (!$token[Token::POS_OP] || ($token[Token::POS_OP] === '&')) {
            if (!$context['flags']['else'] || !isset($vars[0][0]) || ($vars[0][0] !== 'else')) {
                $st = false;
            }
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
            || ($lsp && ($context['tokens']['current'] == $context['tokens']['count']) && !$token[Token::POS_ROTHER]) // final line
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

