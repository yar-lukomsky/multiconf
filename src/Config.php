<?php

namespace EveInUa\MultiConf;

class Config implements IConfig
{
    const ENV_KEY_IS_NOT_FOUND = 'Env key is not found.';
    const ENV_FILE_IS_NOT_FOUND = '.env file is not found.';
    const ENV_DEFAULT_FILE_IS_NOT_FOUND = '.env.default file is not found.';
    const CONFIG_KEY_IS_NOT_FOUND = 'Config key is not found.';
    const CONFIG_DIR_IS_NOT_FOUND = 'Config folder is not found.';

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

    /**
     * @throws \Exception
     */
    private function initEnv()
    {
        $this->env = [];

        // ENV
        $envFilePath = $this->clearPath(ENV_ROOT . '/.env');
        if (file_exists($envFilePath)) {
            $env = file($this->clearPath(ENV_ROOT . '/.env'));
            foreach ($env as $string) {
                $string = trim($string);
                if ($string == '' || substr($string, 0, 1) == '#') continue;
                $parts = explode('=', $string);
                $key = trim(array_shift($parts));
                $value = trim(implode('=', $parts));
                $this->env[$key] = $value;
            }
        } else {
            trigger_error(self::ENV_FILE_IS_NOT_FOUND);
        }

        // ENV DEFAULT
        $envDefaultFilePath = $this->clearPath(ENV_ROOT . '/.env.default');
        if (file_exists($envDefaultFilePath)) {
            $envDefault = file($this->clearPath(ENV_ROOT . '/.env.default'));
            foreach ($envDefault as $string) {
                $string = trim($string);
                if ($string == '' || substr($string, 0, 1) == '#') continue;
                $parts = explode('=', $string);
                $key = trim(array_shift($parts));
                if (isset($this->env[$key])) continue;
                $value = trim(implode('=', $parts));
                $this->env[$key] = $value;
            }
        } else {
            throw new \Exception(self::ENV_DEFAULT_FILE_IS_NOT_FOUND);
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
        $configFilesPath = $this->clearPath(CONFIG_ROOT . '/config');
        if (!file_exists($configFilesPath)) {
            throw new \Exception(self::CONFIG_DIR_IS_NOT_FOUND);
        }
        $configFiles = scandir($this->clearPath(CONFIG_ROOT . '/config'));
        // Fetch config.
        foreach ($configFiles as $configFile) {
            if (in_array($configFile, ['.', '..']) || substr_count($configFile, '.default.') > 0) continue;
            $configHere = include $this->clearPath(CONFIG_ROOT . '/config/' . $configFile);
            $keyHere = substr($configFile, 0, -4);
            $this->config[$keyHere] = $configHere;
        }
        // Merge with default config.
        foreach ($configFiles as $configFile) {
            if (in_array($configFile, ['.', '..']) || substr_count($configFile, '.default.') == 0) continue;
            $configDefaultHere = include $this->clearPath(CONFIG_ROOT . '/config/' . $configFile);
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

    private function clearPath($path)
    {
        return str_replace('//', '/', $path);
    }

}
