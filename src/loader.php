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
 * PHP loader for LightnCandy
 *
 * @package    LightnCandy
 * @author     Zordius <zordius@gmail.com>
 */

require_once(__DIR__ . '/Flags.php');
require_once(__DIR__ . '/Context.php');
require_once(__DIR__ . '/Token.php');
require_once(__DIR__ . '/Encoder.php');
require_once(__DIR__ . '/SafeString.php');
require_once(__DIR__ . '/Parser.php');
require_once(__DIR__ . '/Expression.php');
require_once(__DIR__ . '/Validator.php');
require_once(__DIR__ . '/Partial.php');
require_once(__DIR__ . '/Exporter.php');
require_once(__DIR__ . '/Runtime.php');
require_once(__DIR__ . '/Compiler.php');
require_once(__DIR__ . '/LightnCandy.php');
