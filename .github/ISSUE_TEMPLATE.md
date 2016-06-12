The PHP Code:
-------------

```php
require('./vendor/autoload.php');
use LightnCandy\LightnCandy;

// The Template:
$template = <<<VAREND
put template with issues here...
VAREND;

// Helpers:
$helpers = array(
  'foo' => function () {
    return 'Hello World';
  },
);

// Partials:
$partials = array(
    'bar' => 'Yes, partial',
);

$phpStr = LightnCandy::compile($template, array(
  // Used compile flags
  'flags' => LightnCandy::FLAG_ERROR_EXCEPTION | LightnCandy::FLAG_HANDLEBARS,
  'helpers' => $helpers,
  'partials' => $partials,
));

echo "Generated PHP Code:\n$phpStr\n";

// Input Data:
$data = array(
  'qoo' => 'moo'
);

// Save the compiled PHP code into a php file
file_put_contents('render.php', '<?php ' . $phpStr . '?>');

// Get the render function from the php file
$renderer = include('render.php');

echo "Result:\n" . $renderer($data);
```

The Issue:
----------

Describe your issue or question here...
