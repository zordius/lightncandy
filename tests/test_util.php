<?php
function array_by_ref($array) {
    $ret = array();
    foreach ($array as $k => &$V) {
        $ret[$k] = &$V;
    }
    return $ret;
}

