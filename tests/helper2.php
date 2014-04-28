<?php
require('src/lightncandy.php');

$template = '{{helper name="ernie" blah=true}}';

function myhelper() {
  print_r(func_get_args());
  exit();
}

$php = LightnCandy::compile($template, array(
  "flags" => LightnCandy::FLAG_NAMEDARG , //| LightnCandy::FLAG_ADVARNAME,
  "helpers" => array(
    "helper" => "myhelper"
  )
));

echo "Compiled code: $php";

$tpl = LightnCandy::prepare($php);

echo $tpl(array()); 
?>
