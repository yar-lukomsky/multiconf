# MultiConf

## Composer

```
composer require eve-in-ua/multiconf
```

OR

```json
{
    "require": {
        "eve-in-ua/multiconf": "^v1.0.0"
    }
}
```

## Test

`composer test`

## Examples

### Env

`{ENV_ROOT}/.env`
```dotenv
ENV=DEV
DB_HOST=localhost
DB_USER=user_name
DB_PASS=password
DB_NAME=database_name

```

`{ENV_ROOT}/.env.default`
```dotenv
ENV=PROD
DB_HOST=
DB_PORT=3306
DB_USER=
DB_PASS=
DB_NAME=
TABLE_PREFIX=
```

### Config

`{CONFIG_ROOT}/config/example.php`
```php
<?php

return [
    'foo' => 'bar',
    'zoo' => [
        'baz', // 0 => 'baz'

    ],

];
```


`{CONFIG_ROOT}/config/example.default.php`
```php
<?php

return [
    'foo' => 'baz',
    'def' => 'def',
];
```

`{CONFIG_ROOT}/config/a-first-config.php`
```php
<?php
$multiConf = new EveInUa\MultiConf\Config();

// Wait for `example2` to load:
if ($multiConf->waitFor('a-first-config', ['env', 'example2'])) { return null; }

return [
    'foo' => 'baz',
    'def' => 'def',
    'example2-data' => $multiConf->config('example2'),
];
```

### Usage

```php
<?php
// You can set directories manually for library using $_SERVER['DOCUMENT_ROOT'] as CONFIG_ROOT and ENV_ROOT 
define('CONFIG_ROOT', __DIR__); // optional, will use DOCUMENT_ROOT instead
define('ENV_ROOT', __DIR__); // optional, will use DOCUMENT_ROOT instead
require_once __DIR__ . '/vendor/autoload.php';
$multiConf = new EveInUa\MultiConf\Config();
$result = [
    $multiConf->env(),                  // DEV       - from ENV_ROOT/.env
    $multiConf->env('DB_HOST'),         // localhost - from ENV_ROOT/.env
    $multiConf->env('DB_PORT'),         // 3306      - from ENV_ROOT/.env.default
    $multiConf->config('example.foo'),  // bar       - from CONFIG_ROOT/config/example.php
    $multiConf->config('example.def'),  // def       - from CONFIG_ROOT/config/example.default.php
];
var_dump($result);
```
Output:
```
array(5) {
  [0]=> string(3) "DEV"
  [1]=> string(9) "localhost"
  [2]=> int(3306)
  [3]=> string(3) "bar"
  [4]=> string(3) "def"
}
```
