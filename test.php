<?php

require_once __DIR__ . '/vendor/autoload.php';
$multiConf = new EveInUa\MultiConf\Config();

var_dump($multiConf->config('a-first-config'));
