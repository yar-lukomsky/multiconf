<?php
$multiConf = new EveInUa\MultiConf\Config();

// Wait
if ($multiConf->waitFor('a-first-config', ['env', 'example'])) return null;

return [
    'foo' => 'baz',
    'def' => 'def',
    'example2-data' => $multiConf->config('example', ''),
];
