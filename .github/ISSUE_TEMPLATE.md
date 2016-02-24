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

$php = LightnCandy::compile($template, array(
  // Used compile flags
  'flags' => LightnCandy::FLAG_ERROR_EXCEPTION | LightnCandy::FLAG_HANDLEBARS,
  'helpers' => $helpers,
  'partials' => $partials,
));

echo "Generated PHP Code:\n$php\n";

// Input Data:
$data = array(
  'qoo' => 'moo'
);

$render = LightnCandy::prepare($php);
echo "Result:\n" . $render($data);
```

The Issue:
----------

Describe your issue or question here...
