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
 * file to keep LightnCandy Validator
 *
 * @package    LightnCandy
 * @author     Zordius <zordius@gmail.com>
 */

namespace LightnCandy;
use \LightnCandy\Token;
use \LightnCandy\Parser;
use \LightnCandy\Partial;
use \LightnCandy\Expression;
use \LightnCandy\SafeString;

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
        $template = SafeString::stripExtendedComments($template);
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
            static::pushLeft($context);
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
            array_pop($context['stack']);
            array_pop($context['stack']);
            $token = array_pop($context['stack']);
            $context['error'][] = 'Unclosed token ' . ($context['rawblock'] ? "{{{{{$token}}}}}" : ( $context['partialblock'] ? "{{#>{$token}}}" : "{{#{$token}}}")) . ' !!';
        }
    }

    /**
     * push left string of current token and clear it
     *
     * @param array<string,array|string|integer> $context Current context
     */
    protected static function pushLeft(&$context) {
        $L = $context['currentToken'][Token::POS_LOTHER] . $context['currentToken'][Token::POS_LSPACE];

        if ($context['currentToken'][Token::POS_OP] === '!') {
            $appender = function (&$pb) use ($L) {
                $pb .= $L;
            };
            if (count($context['partialblock']) > 0) {
                array_walk($context['partialblock'], $appender);
            }
            if (count($context['inlinepartial']) > 0) {
                array_walk($context['inlinepartial'], $appender);
            }
        }

        static::pushToken($context, $L);
        $context['currentToken'][Token::POS_LOTHER] = $context['currentToken'][Token::POS_LSPACE] = '';
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
     * @param string $operation operation string
     * @param array<boolean|integer|string|array> $vars parsed arguments list
     */
    protected static function pushStack(&$context, $operation, $vars) {
        list($levels, $spvar, $var) = Expression::analyze($context, $vars[0]);
        $context['stack'][] = $context['currentToken'][Token::POS_INNERTAG];
        $context['stack'][] = Expression::toString($levels, $spvar, $var);
        $context['stack'][] = $operation;
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
     * @param string $operator the operator string
     * @param array<string,array|string|integer> $context current compile context
     * @param array<boolean|integer|string|array> $vars parsed arguments list
     *
     * @return boolean|integer|null Return true when invalid or detected
     *
     * @expect null when input '', array(), array()
     * @expect 2 when input '^', array('usedFeature' => array('isec' => 1), 'level' => 0, 'currentToken' => array(0,0,0,0,0,0,0,0), 'elselvl' => array(), 'flags' => array('spvar' => 0), 'elsechain' => false, 'helperresolver' => 0), array(array('foo'))
     * @expect true when input '/', array('stack' => array('[with]', '#'), 'level' => 1, 'currentToken' => array(0,0,0,0,0,0,0,'with'), 'flags' => array('nohbh' => 0)), array(array())
     * @expect 4 when input '#', array('usedFeature' => array('sec' => 3), 'level' => 0, 'currentToken' => array(0,0,0,0,0,0,0,0), 'flags' => array('spvar' => 0), 'elsechain' => false, 'elselvl' => array(), 'helperresolver' => 0), array(array('x'))
     * @expect 5 when input '#', array('usedFeature' => array('if' => 4), 'level' => 0, 'currentToken' => array(0,0,0,0,0,0,0,0), 'flags' => array('spvar' => 0, 'nohbh' => 0), 'elsechain' => false, 'elselvl' => array(), 'helperresolver' => 0), array(array('if'))
     * @expect 6 when input '#', array('usedFeature' => array('with' => 5), 'level' => 0, 'flags' => array('nohbh' => 0, 'runpart' => 0, 'spvar' => 0), 'currentToken' => array(0,0,0,0,0,0,0,0), 'elsechain' => false, 'elselvl' => array(), 'helperresolver' => 0), array(array('with'))
     * @expect 7 when input '#', array('usedFeature' => array('each' => 6), 'level' => 0, 'currentToken' => array(0,0,0,0,0,0,0,0), 'flags' => array('spvar' => 0, 'nohbh' => 0), 'elsechain' => false, 'elselvl' => array(), 'helperresolver' => 0), array(array('each'))
     * @expect 8 when input '#', array('usedFeature' => array('unless' => 7), 'level' => 0, 'currentToken' => array(0,0,0,0,0,0,0,0), 'flags' => array('spvar' => 0, 'nohbh' => 0), 'elsechain' => false, 'elselvl' => array(), 'helperresolver' => 0), array(array('unless'))
     * @expect 9 when input '#', array('helpers' => array('abc' => ''), 'usedFeature' => array('helper' => 8), 'level' => 0, 'currentToken' => array(0,0,0,0,0,0,0,0), 'flags' => array('spvar' => 0), 'elsechain' => false, 'elselvl' => array()), array(array('abc'))
     * @expect 11 when input '#', array('helpers' => array('abc' => ''), 'usedFeature' => array('helper' => 10), 'level' => 0, 'currentToken' => array(0,0,0,0,0,0,0,0), 'flags' => array('spvar' => 0), 'elsechain' => false, 'elselvl' => array()), array(array('abc'))
     * @expect true when input '>', array('partialresolver' => false, 'usedFeature' => array('partial' => 7), 'level' => 0, 'flags' => array('skippartial' => 0, 'runpart' => 0, 'spvar' => 0), 'currentToken' => array(0,0,0,0,0,0,0,0), 'elsechain' => false, 'elselvl' => array()), array('test')
     */
    protected static function operator($operator, &$context, &$vars) {
        switch ($operator) {
            case '#*':
                if (!$context['compile']) {
                    static::pushLeft($context);
                    $context['stack'][] = count($context['parsed'][0]);
                    static::pushStack($context, '#*', $vars);
                    array_unshift($context['inlinepartial'], '');
                }
                return static::inline($context, $vars);

            case '#>':
                if (!$context['compile']) {
                    static::pushLeft($context);
                    $context['stack'][] = count($context['parsed'][0]);
                    $vars[Parser::PARTIALBLOCK] = ++$context['usedFeature']['pblock'];
                    static::pushStack($context, '#>', $vars);
                    array_unshift($context['partialblock'], '');
                }
                return static::partial($context, $vars);

            case '>':
                return static::partial($context, $vars);

            case '^':
                if (!isset($vars[0][0])) {
                    if (!$context['flags']['else']) {
                        $context['error'][] = 'Do not support {{^}}, you should do compile with LightnCandy::FLAG_ELSE flag';
                        return;
                    } else {
                        return static::doElse($context, $vars);
                    }
                }

                static::doElseChain($context);

                if (static::isBlockHelper($context, $vars)) {
                    static::pushStack($context, '#', $vars);
                    return static::blockCustomHelper($context, $vars, true);
                }

                static::pushStack($context, '^', $vars);
                return static::invertedSection($context, $vars);

            case '/':
                $r = static::blockEnd($context, $vars);
                if ($r !== Token::POS_BACKFILL) {
                    array_pop($context['stack']);
                    array_pop($context['stack']);
                    array_pop($context['stack']);
                }
                return $r;

            case '#':
                static::doElseChain($context);
                static::pushStack($context, '#', $vars);

                if (static::isBlockHelper($context, $vars)) {
                    return static::blockCustomHelper($context, $vars);
                }

                return static::blockBegin($context, $vars);
        }
    }

    /**
     * validate inline partial begin token
     *
     * @param array<string,array|string|integer> $context current compile context
     * @param array<boolean|integer|string|array> $vars parsed arguments list
     *
     * @return boolean|null Return true when inline partial ends
     */
    protected static function inlinePartial(&$context, $vars) {
        if (count($context['inlinepartial']) > 0) {
            $ended = false;
            $append = $context['currentToken'][Token::POS_LOTHER] . $context['currentToken'][Token::POS_LSPACE];
            array_walk($context['inlinepartial'], function (&$pb) use ($context, $append) {
                $pb .= $append;
            });
            if ($context['currentToken'][Token::POS_OP] === '/') {
                if (static::blockEnd($context, $vars, '#*') !== null) {
                    $context['usedFeature']['inlpartial']++;
                    $tmpl = array_shift($context['inlinepartial']);
                    $c = $context['stack'][count($context['stack']) - 4];
                    $context['parsed'][0] = array_slice($context['parsed'][0], 0, $c + 1);
                    $P = &$context['parsed'][0][$c];
                    if (isset($P[1][1][0])) {
                        $context['usedPartial'][$P[1][1][0]] = $tmpl;
                        $P[1][0][0] = Partial::compileDynamic($context, $P[1][1][0]);
                    }
                    $ended = true;
                }
            }
            $append = Token::toString($context['currentToken']);
            array_walk($context['inlinepartial'], function (&$pb) use ($context, $append) {
                $pb .= $append;
            });
            return $ended;
        }
    }

    /**
     * validate partial block token
     *
     * @param array<string,array|string|integer> $context current compile context
     * @param array<boolean|integer|string|array> $vars parsed arguments list
     *
     * @return boolean|null Return true when partial block ends
     */
    protected static function partialBlock(&$context, $vars) {
        if (count($context['partialblock']) > 0) {
            $ended = false;
            $append = $context['currentToken'][Token::POS_LOTHER] . $context['currentToken'][Token::POS_LSPACE];
            array_walk($context['partialblock'], function (&$pb) use ($context, $append) {
                $pb .= $append;
            });
            if ($context['currentToken'][Token::POS_OP] === '/') {
                if (static::blockEnd($context, $vars, '#>') !== null) {
                    $c = $context['stack'][count($context['stack']) - 4];
                    $found = Partial::resolve($context, $vars[0][0]) !== null;
                    $v = $found ? "@partial-block{$context['parsed'][0][$c][1][Parser::PARTIALBLOCK]}" : "{$vars[0][0]}";
                    if ($found) {
                        $context['partials'][$v] = $context['partialblock'][0];
                    }
                    $context['usedPartial'][$v] = $context['partialblock'][0];
                    Partial::compileDynamic($context, $v);
                    if ($found) {
                        Partial::read($context, $vars[0][0]);
                    }
                    array_shift($context['partialblock']);
                    $context['parsed'][0] = array_slice($context['parsed'][0], 0, $c + 1);
                    $ended = true;
                }
            }
            $append = Token::toString($context['currentToken']);
            array_walk($context['partialblock'], function (&$pb) use ($context, $append) {
                $pb .= $append;
            });
            return $ended;
        }
    }

    /**
     * handle else chain
     *
     * @param array<string,array|string|integer> $context current compile context
     */
    protected static function doElseChain(&$context) {
        if ($context['elsechain']) {
            $context['elsechain'] = false;
        } else {
            array_unshift($context['elselvl'], array());
        }
    }

    /**
     * validate block begin token
     *
     * @param array<string,array|string|integer> $context current compile context
     * @param array<boolean|integer|string|array> $vars parsed arguments list
     *
     * @return boolean Return true always
     */
    protected static function blockBegin(&$context, $vars) {
        switch ((isset($vars[0][0]) && is_string($vars[0][0])) ? $vars[0][0] : null) {
            case 'with':
                return static::with($context, $vars);
            case 'each':
                return static::section($context, $vars, true);
            case 'unless':
                return static::unless($context, $vars);
            case 'if':
                return static::doIf($context, $vars);
            default:
                return static::section($context, $vars);
        }
    }

    /**
     * validate builtin helpers
     *
     * @param array<string,array|string|integer> $context current compile context
     * @param array<boolean|integer|string|array> $vars parsed arguments list
     */
    protected static function builtin(&$context, $vars) {
        if ($context['flags']['nohbh']) {
            if (isset($vars[1][0])) {
                $context['error'][] = "Do not support {{#{$vars[0][0]} var}} because you compile with LightnCandy::FLAG_NOHBHELPERS flag";
            }
        } else {
            if (count($vars) < 2) {
                $context['error'][] = "No argument after {{#{$vars[0][0]}}} !";
            }
        }
        $context['usedFeature'][$vars[0][0]]++;
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
        if ($isEach) {
            static::builtin($context, $vars);
        } else {
            if ((count($vars) > 1) && !$context['flags']['lambda']) {
                $context['error'][] = "Custom helper not found: {$vars[0][0]} in " . Token::toString($context['currentToken']) . ' !';
            }
            $context['usedFeature']['sec']++;
        }
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
        static::builtin($context, $vars);
        return true;
    }

    /**
     * validate unless token
     *
     * @param array<string,array|string|integer> $context current compile context
     * @param array<boolean|integer|string|array> $vars parsed arguments list
     *
     * @return boolean Return true always
     */
    protected static function unless(&$context, $vars) {
        static::builtin($context, $vars);
        return true;
    }

    /**
     * validate if token
     *
     * @param array<string,array|string|integer> $context current compile context
     * @param array<boolean|integer|string|array> $vars parsed arguments list
     *
     * @return boolean Return true always
     */
    protected static function doIf(&$context, $vars) {
        static::builtin($context, $vars);
        return true;
    }

    /**
     * validate block custom helper token
     *
     * @param array<string,array|string|integer> $context current compile context
     * @param array<boolean|integer|string|array> $vars parsed arguments list
     * @param boolean $inverted the logic will be inverted
     *
     * @return integer|null Return number of used custom helpers
     */
    protected static function blockCustomHelper(&$context, $vars, $inverted = false) {
        if (is_string($vars[0][0])) {
            if (static::resolveHelper($context, $vars)) {
                return ++$context['usedFeature']['helper'];
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
        return ++$context['usedFeature']['isec'];
    }

    /**
     * Return compiled PHP code for a handlebars block end token
     *
     * @param array<string,array|string|integer> $context current compile context
     * @param array<boolean|integer|string|array> $vars parsed arguments list
     * @param string|null $match should also match to this operator
     *
     * @return boolean Return true
     */
    protected static function blockEnd(&$context, &$vars, $match = null) {
        $context['level']--;
        $c = count($context['stack']) - 2;
        $pop = ($c >= 0) ? $context['stack'][$c + 1] : '';
        if (($match !== null) && ($match !== $pop)) {
            return;
        }
        $pop2 = ($c >= 0) ? $context['stack'][$c]: '';
        switch ($context['currentToken'][Token::POS_INNERTAG]) {
            case 'with':
                if (!$context['flags']['nohbh']) {
                    if ($pop2 !== '[with]') {
                        $context['error'][] = 'Unexpect token: {{/with}} !';
                        return;
                    }
                }
                return true;
        }

        switch($pop) {
            case '#':
            case '^':
                $elsechain = array_shift($context['elselvl']);
                if (isset($elsechain[0])) {
                    $context['level']++;
                    $context['currentToken'][Token::POS_RSPACE] = $context['currentToken'][Token::POS_BACKFILL] = '{{/' . implode('}}{{/', $elsechain) . '}}' . Token::toString($context['currentToken']) . $context['currentToken'][Token::POS_RSPACE];
                    return Token::POS_BACKFILL;
                }
            case '#>':
            case '#*':
                list($levels, $spvar, $var) = Expression::analyze($context, $vars[0]);
                $v = Expression::toString($levels, $spvar, $var);
                if ($pop2 !== $v) {
                    $context['error'][] = 'Unexpect token ' . Token::toString($context['currentToken']) . " ! Previous token {{{$pop}$pop2}} is not closed";
                    return;
                }
                return true;
            default:
                $context['error'][] = 'Unexpect token: ' . Token::toString($context['currentToken']) . ' !';
                return;
        }
    }

    /**
     * handle delimiter change
     *
     * @param array<string,array|string|integer> $context current compile context
     *
     * @return boolean|null Return true when delimiter changed
     */
    protected static function isDelimiter(&$context) {
        if (preg_match('/^=\s*([^ ]+)\s+([^ ]+)\s*=$/', $context['currentToken'][Token::POS_INNERTAG], $matched)) {
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
        $context['currentToken'] = &$token;

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

        $partials = static::partialBlock($context, $vars);
        $partials = static::inlinePartial($context, $vars) || $partials;

        if ($partials) {
            $context['stack'] = array_slice($context['stack'], 0, -4);
            static::spacing($token, $context);
            $context['currentToken'][Token::POS_LOTHER] = '';
            $context['currentToken'][Token::POS_LSPACE] = '';
            return;
        }

        // Handle spacing (standalone tags, partial indent)
        static::spacing($token, $context, (($token[Token::POS_OP] === '') || ($token[Token::POS_OP] === '&')) && (!$context['flags']['else'] || !isset($vars[0][0]) || ($vars[0][0] !== 'else')) || ($context['flags']['nostd'] > 0));

        if (static::operator($token[Token::POS_OP], $context, $vars)) {
            return isset($token[Token::POS_BACKFILL]) ? null : array($raw, $vars);
        }

        if (count($vars) == 0) {
            return $context['error'][] = 'Wrong variable naming in ' . Token::toString($token);
        }

        if (!isset($vars[0])) {
            return $context['error'][] = 'Do not support name=value in ' . Token::toString($token) . ', you should use it after a custom helper.';
        }

        $context['usedFeature'][$raw ? 'raw' : 'enc']++;

        foreach ($vars as $var) {
            if (!isset($var[0]) || ($var[0] === 0)) {
                if ($context['level'] == 0) {
                    $context['usedFeature']['rootthis']++;
                }
                $context['usedFeature']['this']++;
            }
        }

        if (!isset($vars[0][0])) {
            return array($raw, $vars);
        }

        if (($vars[0][0] === 'else') && $context['flags']['else']) {
            static::doElse($context, $vars);
            return array($raw, $vars);
        }

        if (!static::helper($context, $vars)) {
            static::lookup($context, $vars);
            static::log($context, $vars);
        }

        return array($raw, $vars);
    }

    /**
     * Return 1 or larger number when else token detected
     *
     * @param array<string,array|string|integer> $context current compile context
     * @param array<boolean|integer|string|array> $vars parsed arguments list
     *
     * @return integer Return 1 or larger number when else token detected
     */
    protected static function doElse(&$context, $vars) {
        if ($context['level'] == 0) {
            $context['error'][] = '{{else}} only valid in if, unless, each, and #section context';
        }

        if (isset($vars[1][0])) {
            $token = $context['currentToken'];
            $context['currentToken'][Token::POS_RSPACE] = "{{#{$vars[1][0]} " . preg_replace('/^\\s*else\\s+' . $vars[1][0] . '\\s*/', '', $token[Token::POS_INNERTAG]) . '}}' . $context['currentToken'][Token::POS_RSPACE];
            array_unshift($context['elselvl'][0], $vars[1][0]);
            $context['elsechain'] = true;
        }

        return ++$context['usedFeature']['else'];
    }

    /**
     * Return true when this is {{log ...}}
     *
     * @param array<string,array|string|integer> $context current compile context
     * @param array<boolean|integer|string|array> $vars parsed arguments list
     *
     * @return boolean|null Return true when it is custom helper
     */
    public static function log(&$context, $vars) {
        if (isset($vars[0][0]) && ($vars[0][0] === 'log')) {
            if (!$context['flags']['nohbh']) {
                if (count($vars) < 2) {
                    $context['error'][] = "No argument after {{log}} !";
                }
                $context['usedFeature']['log']++;
                return true;
            }
        }
    }

    /**
     * Return true when this is {{lookup ...}}
     *
     * @param array<string,array|string|integer> $context current compile context
     * @param array<boolean|integer|string|array> $vars parsed arguments list
     *
     * @return boolean|null Return true when it is custom helper
     */
    public static function lookup(&$context, $vars) {
        if (isset($vars[0][0]) && ($vars[0][0] === 'lookup')) {
            if (!$context['flags']['nohbh']) {
                if (count($vars) < 2) {
                    $context['error'][] = "No argument after {{lookup}} !";
                } else if (count($vars) < 3) {
                    $context['error'][] = "{{lookup}} requires 2 arguments !";
                }
                $context['usedFeature']['lookup']++;
                return true;
            }
        }
    }

    /**
     * Return true when the name is listed in helper table
     *
     * @param array<string,array|string|integer> $context current compile context
     * @param array<boolean|integer|string|array> $vars parsed arguments list
     * @param boolean $checkSubexp true when check for subexpression
     *
     * @return boolean Return true when it is custom helper
     */
    public static function helper(&$context, $vars, $checkSubexp = false) {
        if (static::resolveHelper($context, $vars)) {
            $context['usedFeature']['helper']++;
            return true;
        }

        if ($checkSubexp) {
            switch ($vars[0][0]) {
            case 'if':
            case 'unless':
            case 'with':
            case 'each':
            case 'lookup':
                return $context['flags']['nohbh'] ? false : true;
            }
        }

        return false;
    }

    /**
     * use helperresolver to resolve helper, return true when helper founded
     *
     * @param array<string,array|string|integer> $context Current context of compiler progress.
     * @param array<boolean|integer|string|array> $vars parsed arguments list
     *
     * @return boolean $found helper exists or not
     */
    public static function resolveHelper(&$context, &$vars) {
        if (count($vars[0]) !== 1) {
            return false;
        }
        if (isset($context['helpers'][$vars[0][0]])) {
            return true;
        }

        if ($context['helperresolver']) {
            $helper = $context['helperresolver']($context, $vars[0][0]);
            if ($helper) {
                $context['helpers'][$vars[0][0]] = $helper;
                return true;
            }
        }

        return false;
    }

    /**
     * detect for block custom helper
     *
     * @param array<string,array|string|integer> $context current compile context
     * @param array<boolean|integer|string|array> $vars parsed arguments list
     *
     * @return boolean|null Return true when this token is block custom helper
     */
    protected static function isBlockHelper($context, $vars) {
        if (!isset($vars[0][0])) {
            return;
        }

        if (!static::resolveHelper($context, $vars)) {
            return;
        }

        return true;
    }

    /**
     * validate inline partial
     *
     * @param array<string,array|string|integer> $context current compile context
     * @param array<boolean|integer|string|array> $vars parsed arguments list
     *
     * @return boolean Return true always
     */
    protected static function inline(&$context, $vars) {
        if (!$context['flags']['runpart']) {
            $context['error'][] = "Do not support {{#*{$context['currentToken'][Token::POS_INNERTAG]}}}, you should do compile with LightnCandy::FLAG_RUNTIMEPARTIAL flag";
        }
        if (!isset($vars[0][0]) || ($vars[0][0] !== 'inline')) {
            $context['error'][] = "Do not support {{#*{$context['currentToken'][Token::POS_INNERTAG]}}}, now we only support {{#*inline \"partialName\"}}template...{{/inline}}";
        }
        if (!isset($vars[1][0])) {
            $context['error'][] = "Error in {{#*{$context['currentToken'][Token::POS_INNERTAG]}}}: inline require 1 argument for partial name!";
        }
        return true;
    }

    /**
     * validate partial
     *
     * @param array<string,array|string|integer> $context current compile context
     * @param array<boolean|integer|string|array> $vars parsed arguments list
     *
     * @return integer|boolean Return 1 or larger number for runtime partial, return true for other case
     */
    protected static function partial(&$context, $vars) {
        if (Parser::isSubExp($vars[0])) {
            if ($context['flags']['runpart']) {
                return $context['usedFeature']['dynpartial']++;
            } else {
                $context['error'][] = "You use dynamic partial name as '{$vars[0][2]}', this only works with option FLAG_RUNTIMEPARTIAL enabled";
                return true;
            }
        } else {
            if ($context['currentToken'][Token::POS_OP] !== '#>') {
                Partial::read($context, $vars[0][0]);
            }
        }
        if (!$context['flags']['runpart']) {
        $named = count(array_diff_key($vars, array_keys(array_keys($vars)))) > 0;
            if ($named || (count($vars) > 1)) {
                $context['error'][] = "Do not support {{>{$context['currentToken'][Token::POS_INNERTAG]}}}, you should do compile with LightnCandy::FLAG_RUNTIMEPARTIAL flag";
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
            if ($token[Token::POS_OP] === '>') {
                if (!$context['flags']['noind']) {
                    $context['tokens']['partialind'] = $token[Token::POS_LSPACECTL] ? '' : $ind;
                    $token[Token::POS_LSPACE] = (isset($lmatch[2]) ? ($lmatch[1] . $lmatch[2]) : '');
                }
            } else {
                $token[Token::POS_LSPACE] = (isset($lmatch[2]) ? ($lmatch[1] . $lmatch[2]) : '');
            }
            $token[Token::POS_RSPACE] = isset($rmatch[3]) ? $rmatch[3] : '';
        }

        // Handle space control.
        if ($token[Token::POS_LSPACECTL]) {
            $token[Token::POS_LSPACE] = '';
        }
        if ($token[Token::POS_RSPACECTL]) {
            $token[Token::POS_RSPACE] = '';
        }
    }
}

