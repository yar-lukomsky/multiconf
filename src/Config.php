<?php

namespace EveInUa\MultiConf;

class Config implements IConfig
{
    const ENV_KEY_IS_NOT_FOUND = 'Env key is not found.';
    const CONFIG_KEY_IS_NOT_FOUND = 'Config key is not found.';

    private $env;
    private $config;

    public function __construct()
    {
        if (!defined('CONFIG_ROOT')) {
            define('CONFIG_ROOT', $_SERVER['DOCUMENT_ROOT']);
        }
        if (!defined('ENV_ROOT')) {
            define('ENV_ROOT', $_SERVER['DOCUMENT_ROOT']);
        }
    }

    /**
     * Returns env variable from ENV_ROOT/.env (or .env.default)
     * @param string $key
     * @return mixed
     * @throws \Exception
     */
    public function env($key = 'ENV')
    {
        if (!$this->env) {
            $this->initEnv();
        }
        if (isset($this->env[$key])) {
            return $this->env[$key];
        } else {
            throw new \Exception(self::ENV_KEY_IS_NOT_FOUND);
        }
    }

    private function initEnv()
    {
        $env = file(ENV_ROOT . '/.env');
        $this->env = [];
        foreach ($env as $string) {
            if (trim($string) == '') continue;
            $parts = explode('=', $string);
            $key = trim(array_shift($parts));
            $value = trim(implode('=', $parts));
            $this->env[$key] = $value;
        }
        $envDefault = file(ENV_ROOT . '/.env.default');
        foreach ($envDefault as $string) {
            if (trim($string) == '') continue;
            $parts = explode('=', $string);
            $key = trim(array_shift($parts));
            if (isset($this->env[$key])) continue;
            $value = trim(implode('=', $parts));
            $this->env[$key] = $value;
        }
    }

    /**
     * Returns config value from CONFIG_ROOT/config directory.
     * @param $keyPathDotNotation
     * @return mixed
     * @throws \Exception
     */
    public function config($keyPathDotNotation)
    {
        if (!$this->config) {
            $this->initConfig();
        }

        return $this->lodashGet($this->config, $keyPathDotNotation);
    }

    private function initConfig()
    {
        $configFiles = scandir(CONFIG_ROOT . '/config');
        // Fetch config.
        foreach ($configFiles as $configFile) {
            if (in_array($configFile, ['.', '..']) || substr_count($configFile, '.default.') > 0) continue;
            $configHere = include CONFIG_ROOT . '/config/' . $configFile;
            $keyHere = substr($configFile, 0, -4);
            $this->config[$keyHere] = $configHere;
        }
        // Merge with default config.
        foreach ($configFiles as $configFile) {
            if (in_array($configFile, ['.', '..']) || substr_count($configFile, '.default.') == 0) continue;
            $configDefaultHere = include CONFIG_ROOT . '/config/' . $configFile;
            $keyHere = substr($configFile, 0, -12);
            $this->config[$keyHere] = array_replace_recursive($configDefaultHere, $this->config[$keyHere]);
        }
    }

    /**
     * @param $array - nested array
     * @param $path - dot notation path
     * @return mixed
     * @throws \Exception
     */
    private function lodashGet($array, $path)
    {
        $pathParts = explode('.', $path);
        $key = array_shift($pathParts);

        if (!isset($array[$key])) {
            throw new \Exception(self::CONFIG_KEY_IS_NOT_FOUND);
        }

        if (count($pathParts) > 0) {
            return $this->lodashGet($array[$key], implode('.', $pathParts));
        } else {
            return $array[$key];
        }
    }

}
