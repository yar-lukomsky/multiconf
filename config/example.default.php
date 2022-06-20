<?php

return [
    0 => 'fuu-default',
    'foo' => 'bar-default',
    'zoo' => [
        'baz-default', // 0 => 'baz-default'
        'buzz' => 'buzz-default',
        // TODO: implement cascade merging (with ability to disable it in ENV)
    ],
    'default' => 'default'
];
