<?php

// Classes for inputs or helpers
class myClass {
    function test() {
        return 'testMethod OK!';
    }

    function helper2($arg) {
        return "=$arg=";
    }

    function __call($method, $args) {
        return "-- $method:" . print_r($args, true);
    }
}

class foo {
    public $prop = 'Yes!';

    function bar() {
        return 'OK!';
    }
}

// Custom helpers
function helper1($arg) {
    return "-$arg-";
}                                                                                                                                          
function alink($u, $t) {
    return "<a href=\"$u\">$t</a>";
}

 function meetup_date_format() {
    return "OKOK~1";
}

function  meetup_date_format2() {
    return "OKOK~2";
}

function        meetup_date_format3 () {
    return "OKOK~3";
}

function	meetup_date_format4(){
    return "OKOK~4";};


function test_array ($input) {
   return is_array($input[0]) ? 'IS_ARRAY' : 'NOT_ARRAY';
}

function test_join ($input) {
   return join('.', $input[0]);
}

// Custom helpers for handlebars (should be used in hbhelpers)
function myif ($conditional, $options) {
    if ($conditional) {
        return $options['fn']();
    } else {
        return $options['inverse']();
    }
}

function mywith ($context, $options) {
    return $options['fn']($context);
}

function myeach ($context, $options) {
    $ret = '';
    foreach ($context as $cx) {
        $ret .= $options['fn']($cx);
    }
    return $ret;
}

function mylogic ($input, $yes, $no, $options) {
    if ($input === true) {
        return $options['fn']($yes);
    } else {
        return $options['inverse']($no);
    }
}

function mydash ($a, $b) {
    return "$a-$b";
}

function myjoin ($a, $b) {
    return "$a$b";
}

?>
