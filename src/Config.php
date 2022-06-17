<?php

namespace EveInUa\MultiConf;

use \Exception;

class Config implements IConfig
{
    const ENV_KEY_IS_NOT_FOUND = 'Env key `%s` is not found.';
    const ENV_FILE_IS_NOT_FOUND = '.env file is not found.';
    const ENV_DEFAULT_FILE_IS_NOT_FOUND = '.env.default file is not found.';
    const CONFIG_KEY_IS_NOT_FOUND = 'Config key `%s` of path `%s` is not found.';
    const CONFIG_DIR_IS_NOT_FOUND = 'Config folder is not found.';

    const CONFIG_DEFAULT_VALUE = 'ecb902b728093cd0c652cfa78bdd8c97';

    private $env;
    private $config;
    private $waitList = [];

    /**
     * Config constructor.
     */
    public function __construct()
    {
        if (!defined('CONFIG_ROOT')) {
            define('CONFIG_ROOT', !empty($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : $_SERVER['PWD']);
        }
        if (!defined('ENV_ROOT')) {
            define('ENV_ROOT', !empty($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : $_SERVER['PWD']);
        }
    }

    /**
     * Config root.
     * @return string
     */
    public function configRoot()
    {
        return CONFIG_ROOT;
    }

    /**
     * ENV root.
     * @return string
     */
    public function envRoot()
    {
        return ENV_ROOT;
    }

    /**
     * Returns env variable from ENV_ROOT/.env (or .env.default)
     * @param string $key
     * @param bool $smartTransform - transform boolean values as strings to boolean type values
     * @param string $default
     * @return mixed
     * @throws Exception only if $key is not found (use $default to prevent throw)
     */
    public function env($key = 'ENV', $smartTransform = true, $default = self::CONFIG_DEFAULT_VALUE)
    {
        if (!$this->env) {
            $this->initEnv();
        }
        if (isset($this->env[$key])) {
            return $smartTransform ? $this->transformStringValue($this->env[$key]) : $this->env[$key];
        } elseif ($default != self::CONFIG_DEFAULT_VALUE) {
            return $default;
        } else {
            throw new Exception(sprintf(self::ENV_KEY_IS_NOT_FOUND, $key));
        }
    }

    /**
     * @throws Exception
     */
    public function init($forceReload = false)
    {
        if (!$this->env || $forceReload) {
            $this->initEnv();
        }
        if (!$this->config || $forceReload) {
            $this->initConfig();
        }
    }

    /**
     * @throws Exception
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
            throw new Exception(self::ENV_DEFAULT_FILE_IS_NOT_FOUND);
        }
    }

    /**
     * Returns config value from CONFIG_ROOT/config directory.
     * @param $keyPathDotNotation
     * @param string $default
     * @return mixed
     * @throws Exception only if $keyPathDotNotation is not found (use $default to prevent throw)
     */
    public function config($keyPathDotNotation, $default = self::CONFIG_DEFAULT_VALUE)
    {
        /**
         * If your `current-config` want to use `another-config` - you need to wait for it and return null while waiting.
         *
         * Add the next example at the beginning of the config.
         * @example if (self::waitFor('current-config', ['env', 'another-config'])) { return null; }
         */
        $keyPathDotNotationParts = explode('.', $keyPathDotNotation);
        if (!$this->config || !$this->config[reset($keyPathDotNotationParts)]) {
            $this->initConfig();
        }

        try {
            return $this->lodashGet($this->config, $keyPathDotNotation);
        } catch (Exception $exception) {
            if ($default != self::CONFIG_DEFAULT_VALUE) {
                return $default;
            }
            throw $exception;
        }
    }

    private function initConfig()
    {
        $configFilesPath = $this->clearPath(CONFIG_ROOT . '/config');
        if (!file_exists($configFilesPath)) {
            throw new Exception(self::CONFIG_DIR_IS_NOT_FOUND);
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
            $this->config[$keyHere] = $this->config[$keyHere] ?? [];
            $this->config[$keyHere] = array_replace_recursive($configDefaultHere, $this->config[$keyHere]);
        }
    }

    /**
     * @param $array - nested array
     * @param $path - dot notation path
     * @return mixed
     * @throws Exception
     */
    private function lodashGet($array, $path)
    {
        $pathParts = explode('.', $path);
        $key = array_shift($pathParts);

        if (!isset($array[$key])) {
            throw new Exception(sprintf(self::CONFIG_KEY_IS_NOT_FOUND, $key, $path));
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

    /**
     * Transform boolean values as strings to boolean type values.
     * @param $value
     * @return bool
     */
    public function transformStringValue($value)
    {
        // JSON
        $newValue = @json_decode($value, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $newValue;
        }

        // BOOL
        switch ($value) {
            case 'true':
                return true;
            case 'false':
                return false;
        }

        // DEFAULT
        return $value;
    }

    /**
     * @return bool
     * @throws \Exception
     */
    public function isDev()
    {
        return strtolower($this->env('ENV')) == 'dev' || strtolower($this->env('ENV')) == 'loc';
    }

    /**
     * @return bool
     * @throws \Exception
     */
    public function isTest()
    {
        return strtolower($this->env('ENV')) == 'test';
    }

    /**
     * @return bool
     * @throws \Exception
     */
    public function isStage()
    {
        return strtolower($this->env('ENV')) == 'stage' || strtolower($this->env('ENV')) == 'staging';
    }

    /**
     * @return bool
     * @throws \Exception
     */
    public function isProd()
    {
        return strtolower($this->env('ENV')) == 'prod' || strtolower($this->env('ENV')) == 'production' || strtolower($this->env('ENV')) == 'live';
    }

    /**
     * @return bool
     * @throws \Exception
     */
    public function waitFor($currentConfigKey, $waitForKey = [])
    {
        $this->waitList[$currentConfigKey] = $this->waitList[$currentConfigKey] ?? [];
        $this->waitList[$currentConfigKey] = array_merge($this->waitList[$currentConfigKey], is_array($waitForKey) ? $waitForKey : [$waitForKey]);
        $this->waitList[$currentConfigKey] = array_unique($this->waitList[$currentConfigKey]);

        return $this->needWait($currentConfigKey);
    }

    /**
     * @return bool
     * @throws \Exception
     */
    private function needWait($currentConfigKey)
    {
        $needWait = false;
        foreach ($this->waitList[$currentConfigKey] as $waitFor) {
            if ($waitFor == 'env') {
                $needWait = $needWait || is_null($this->env);
            } elseif (is_null($this->config[$waitFor])) {
                $needWait = true;
            }
        }

        return $needWait;
    }

}
