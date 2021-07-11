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
 * file to keep LightnCandy string utilities
 *
 * @package    LightnCandy
 * @author     Zordius <zordius@gmail.com>
 */

namespace LightnCandy;

/**
 * LightnCandy SafeString class
 */
class SafeString extends Encoder
{
    const EXTENDED_COMMENT_SEARCH = '/{{!--.*?--}}/s';
    const IS_SUBEXP_SEARCH = '/^\(.+\)$/s';
    const IS_BLOCKPARAM_SEARCH = '/^ +\|(.+)\|$/s';

    private $string;

    public static $jsContext = array(
        'flags' => array(
            'jstrue' => 1,
            'jsobj' => 1,
        )
    );

    /**
     * Constructor
     *
     * @param string $str input string
     * @param bool|string $escape false to not escape, true to escape, 'encq' to escape as handlebars.js
     */
    public function __construct($str, $escape = false)
    {
        $this->string = $escape ? (($escape === 'encq') ? static::encq(static::$jsContext, $str) : static::enc(static::$jsContext, $str)) : $str;
    }

    public function __toString()
    {
        return $this->string;
    }

    /**
     * Strip extended comments {{!-- .... --}}
     *
     * @param string $template handlebars template string
     *
     * @return string Stripped template
     *
     * @expect 'abc' when input 'abc'
     * @expect 'abc{{!}}cde' when input 'abc{{!}}cde'
     * @expect 'abc{{! }}cde' when input 'abc{{!----}}cde'
     */
    public static function stripExtendedComments($template)
    {
        return preg_replace(static::EXTENDED_COMMENT_SEARCH, '{{! }}', $template);
    }

    /**
     * Escape template
     *
     * @param string $template handlebars template string
     *
     * @return string Escaped template
     *
     * @expect 'abc' when input 'abc'
     * @expect 'a\\\\bc' when input 'a\bc'
     * @expect 'a\\\'bc' when input 'a\'bc'
     */
    public static function escapeTemplate($template)
    {
        return addcslashes(addcslashes($template, '\\'), "'");
    }
}
