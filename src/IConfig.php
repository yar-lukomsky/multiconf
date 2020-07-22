<?php

namespace EveInUa\MultiConf;

interface IConfig
{
    /**
     * Returns env variable from ENV_ROOT/.env (or .env.default)
     * @param string $key
     * @return mixed
     */
    public function env($key = 'ENV');

    /**
     * Returns config value from CONFIG_ROOT/config directory.
     * @param $keyPathDotNotation
     * @return mixed
     */
    public function config($keyPathDotNotation);
}
