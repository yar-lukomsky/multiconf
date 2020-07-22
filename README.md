[TOC]

# MultiConf

## Composer

```json
{
    // ... 
    "repositories": [
        // ...
        {
            "type": "vcs",
            "url": "https://github.com/yar-lukomsky/multiconf"
        }
    ],
    "require": {
        // ...
        "eve-in-ua/multiconf": "dev-master"
    }
}
```

## Test

`composer test`

## Examples

### Env

`{ENV_ROOT}/.env`|`{ENV_ROOT}/.env.default`
```dotenv
ENV=PROD
DB_HOST=localhost
DB_PORT=3306
DB_USER=user_name
DB_PASS=password
DB_NAME=database_name
TABLE_PREFIX=
```

### Config

`{CONFIG_ROOT}/config/example.php`|`{CONFIG_ROOT}/config/example.default.php`
```php
<?php

return [
    0 => 'fuu',
    'foo' => 'bar',
    'zoo' => [
        'baz', // 0 => 'baz'

    ],

];

```

### Usage

```php
<?php
// You can set directories manually or library with use $_SERVER['DOCUMENT_ROOT'] as CONFIG_ROOT & ENV_ROOT 
define('CONFIG_ROOT', __DIR__);
define('ENV_ROOT', __DIR__);
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
