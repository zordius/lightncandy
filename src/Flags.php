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
 * file to keep LightnCandy flags
 *
 * @package    LightnCandy
 * @author     Zordius <zordius@gmail.com>
 */

namespace LightnCandy;

/**
 * LightnCandy class to keep flag consts
 */
class Flags
{
    // Compile time error handling flags
    const FLAG_ERROR_LOG = 1;
    const FLAG_ERROR_EXCEPTION = 2;

    // JavaScript compatibility
    const FLAG_JSTRUE = 8;
    const FLAG_JSOBJECT = 16;
    const FLAG_JSLENGTH = 33554432;

    // Handlebars.js compatibility
    const FLAG_THIS = 32;
    const FLAG_PARENT = 128;
    const FLAG_HBESCAPE = 256;
    const FLAG_ADVARNAME = 512;
    const FLAG_NAMEDARG = 2048;
    const FLAG_SPVARS = 4096;
    const FLAG_PREVENTINDENT = 131072;
    const FLAG_SLASH = 8388608;
    const FLAG_ELSE = 16777216;
    const FLAG_RAWBLOCK = 134217728;
    const FLAG_HANDLEBARSLAMBDA = 268435456;
    const FLAG_PARTIALNEWCONTEXT = 536870912;
    const FLAG_IGNORESTANDALONE = 1073741824;
    const FLAG_STRINGPARAMS = 2147483648;
    const FLAG_KNOWNHELPERSONLY = 4294967296;

    // PHP behavior flags
    const FLAG_STANDALONEPHP = 4;
    const FLAG_EXTHELPER = 8192;
    const FLAG_ECHO = 16384;
    const FLAG_PROPERTY = 32768;
    const FLAG_METHOD = 65536;
    const FLAG_RUNTIMEPARTIAL = 1048576;
    const FLAG_NOESCAPE = 67108864;

    // Mustache compatibility
    const FLAG_MUSTACHELOOKUP = 262144;
    const FLAG_ERROR_SKIPPARTIAL = 4194304;
    const FLAG_MUSTACHELAMBDA = 2097152;
    const FLAG_NOHBHELPERS = 64;
    const FLAG_MUSTACHESECTION = 8589934592;

    // Template rendering time debug flags
    const FLAG_RENDER_DEBUG = 524288;

    // alias flags
    const FLAG_BESTPERFORMANCE = 16388; // FLAG_ECHO + FLAG_STANDALONEPHP
    const FLAG_JS = 33554456; // FLAG_JSTRUE + FLAG_JSOBJECT + FLAG_JSLENGTH
    const FLAG_INSTANCE = 98304; // FLAG_PROPERTY + FLAG_METHOD
    const FLAG_MUSTACHE = 8597536856; // FLAG_ERROR_SKIPPARTIAL + FLAG_MUSTACHELOOKUP + FLAG_MUSTACHELAMBDA + FLAG_NOHBHELPERS + FLAG_MUSTACHESECTION + FLAG_RUNTIMEPARTIAL + FLAG_JSTRUE + FLAG_JSOBJECT
    const FLAG_HANDLEBARS = 159390624; // FLAG_THIS + FLAG_PARENT + FLAG_HBESCAPE + FLAG_ADVARNAME + FLAG_SPACECTL + FLAG_NAMEDARG + FLAG_SPVARS + FLAG_SLASH + FLAG_ELSE + FLAG_RAWBLOCK
    const FLAG_HANDLEBARSJS = 192945080; // FLAG_JS + FLAG_HANDLEBARS
    const FLAG_HANDLEBARSJS_FULL = 429235128; // FLAG_HANDLEBARSJS + FLAG_INSTANCE + FLAG_RUNTIMEPARTIAL + FLAG_MUSTACHELOOKUP
}

