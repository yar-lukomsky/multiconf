<?php

namespace EveInUa\MultiConf;

use \Exception;

class Config extends SingletonAbstract implements IConfig
{
    const ENV_KEY_IS_NOT_FOUND = 'Env key `%s` is not found.';
    const ENV_FILE_IS_NOT_FOUND = '.env file is not found.';
    const ENV_DEFAULT_FILE_IS_NOT_FOUND = '.env.default file is not found.';
    const CONFIG_KEY_IS_NOT_FOUND = 'Config key `%s` of path `%s` is not found.';
    const CONFIG_DIR_IS_NOT_FOUND = 'Config folder is not found.';
    const ERROR_TOO_MANY_NESTING = 'Too many config nesting.';

    const CONFIG_DEFAULT_VALUE = 'ecb902b728093cd0c652cfa78bdd8c97';

    const CONFIG_NESTING_THRESHOLD = 10;

    private static $init = false;
    private static $env;
    private static $config;
    private static $waitList = [];
    private static $loadedDefaultConfigs = [];
    private static $loadedOverrideConfigs = [];

    /**
     * Config constructor.
     */
    public function boot()
    {
        if (!defined('CONFIG_ROOT')) {
            define('CONFIG_ROOT', !empty($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : $_SERVER['PWD']);
        }
        if (!defined('ENV_ROOT')) {
            define('ENV_ROOT', !empty($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : $_SERVER['PWD']);
        }
        if (!self::$init) {
            self::$init = true;
            $this->init();
        }
    }

    /**
     * Config root.
     *
     * @return string
     */
    public function configRoot()
    {
        return CONFIG_ROOT;
    }

    /**
     * ENV root.
     *
     * @return string
     */
    public function envRoot()
    {
        return ENV_ROOT;
    }

    /**
     * Returns env variable from ENV_ROOT/.env (or .env.default)
     *
     * @param string $key
     * @param bool $smartTransform - transform boolean values as strings to boolean type values
     * @param string $default
     * @return mixed
     * @throws Exception only if $key is not found (use $default to prevent throw)
     */
    public function env($key = 'ENV', $smartTransform = true, $default = self::CONFIG_DEFAULT_VALUE)
    {
        if (!self::$env) {
            $this->initEnv();
        }
        if (isset(self::$env[$key])) {
            return $smartTransform ? $this->transformStringValue(self::$env[$key]) : self::$env[$key];
        } elseif ($default !== self::CONFIG_DEFAULT_VALUE) {
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
        if (!self::$env || $forceReload) {
            $this->initEnv();
        }
        if (!self::$config || $forceReload) {
            $this->initConfig();
        }
    }

    /**
     * @throws Exception
     */
    private function initEnv()
    {
        self::$env = [];

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
                self::$env[$key] = $value;
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
                if (isset(self::$env[$key])) continue;
                $value = trim(implode('=', $parts));
                self::$env[$key] = $value;
            }
        } else {
            throw new Exception(self::ENV_DEFAULT_FILE_IS_NOT_FOUND);
        }
    }

    /**
     * Returns config value from CONFIG_ROOT/config directory.
     *
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
         *
         * @example if (self::$waitFor('current-config', ['env', 'another-config'])) { return null; }
         */
        $keyPathDotNotationParts = explode('.', $keyPathDotNotation);
        if (!self::$config || !(self::$config[reset($keyPathDotNotationParts)] ?? false)) {
            $this->initConfig();
        }

        try {
            return $this->lodashGet(self::$config, $keyPathDotNotation);
        } catch (Exception $exception) {
            if ($default !== self::CONFIG_DEFAULT_VALUE) {
                return $default;
            }
            throw $exception;
        }
    }

    private function initConfig($reloadCount = 0)
    {
        $configFilesPath = $this->clearPath(CONFIG_ROOT . '/config');
        if (!file_exists($configFilesPath)) {
            self::$config = [];
            return;
        }
        $configFiles = scandir($this->clearPath(CONFIG_ROOT . '/config'));
        $configFilesCount = count($configFiles);
        // Fetch config.
        $count = 0;
        while (!empty($configFiles)) {
            $configFile = array_shift($configFiles);
            if (in_array($configFile, self::$loadedDefaultConfigs)) continue;
            if (in_array($configFile, ['.', '..']) || substr_count($configFile, '.default.') > 0) continue;
            $isPhp = substr($configFile, -4) == '.php';
            $filePath = $this->clearPath(CONFIG_ROOT . '/config/' . $configFile);
            if (is_dir($filePath)) continue; // TODO: allow config from folders as nested.
            if ($isPhp) {
                $configHere = include $filePath;
            } else {
                $configHere = file_get_contents($filePath);
            }
            if (is_null($configHere)) {
                $count++;
                if ($count < 2 * $configFilesCount) {
                    $configFiles[] = $configFile;
                }
            }
            $partsHere = explode('.', $configFile);
            $ext = array_pop($partsHere);
            $configHere = $ext == 'json' ? json_decode($configHere, true) : $configHere;
            $keyHere = implode('.', $partsHere);
            self::$config[$keyHere] = $configHere;
            self::$loadedDefaultConfigs[] = $configFile;
        }

        $configFiles = scandir($this->clearPath(CONFIG_ROOT . '/config'));
        // Merge with default config.
        while (!empty($configFiles)) {
            $configFile = array_shift($configFiles);
            if (in_array($configFile, self::$loadedOverrideConfigs)) continue;
            if (in_array($configFile, ['.', '..']) || substr_count($configFile, '.default.') == 0) continue;
            $isPhp = substr($configFile, -4) == '.php';
            $filePath = $this->clearPath(CONFIG_ROOT . '/config/' . $configFile);
            if (is_dir($filePath)) continue; // TODO: allow config from folders as nested.
            if ($isPhp) {
                $configDefaultHere = include $filePath;
            } else {
                $configDefaultHere = file_get_contents($filePath);
            }
            if (is_null($configDefaultHere)) {
                $count++;
                if ($count < 2 * $configFilesCount) {
                    $configFiles[] = $configFile;
                }
            }
            $configFile = str_replace('.default.', '.', $configFile);
            $partsHere = explode('.', $configFile);
            $ext = array_pop($partsHere);
            $configDefaultHere = $ext == 'json' ? json_decode($configDefaultHere, true) : $configDefaultHere;
            $keyHere = implode('.', $partsHere);
            self::$config[$keyHere] = self::$config[$keyHere] ?? [];
            self::$config[$keyHere] = array_replace_recursive($configDefaultHere ?? [], self::$config[$keyHere]);
            self::$loadedOverrideConfigs[] = $configFile;
        }

        /**
         * Handle using config in another config.
         *
         * @see Config::waitFor
         */
        if ($reloadCount > $this->getThresholdNestingNumber()) {
            throw new \Exception(self::ERROR_TOO_MANY_NESTING);
        }
        $hasWaitingConfigs = false;
        foreach (self::$config as $configValue) {
            if (is_null($configValue)) $hasWaitingConfigs = true;
        }
        if ($hasWaitingConfigs) {
            $this->initConfig($reloadCount + 1);
        }
    }

    /**
     * @param array $array - nested array
     * @param string $path - dot notation path
     * @param string|null $fullPath - dot notation path: passed through all calls,
     *                                for "exception throw" when it will be thrown
     * @return mixed
     * @throws Exception
     */
    private function lodashGet($array, $path, $fullPath = null)
    {
        $pathParts = explode('.', $path);
        $key = array_shift($pathParts);

        if (!isset($array[$key])) {
            throw new Exception(sprintf(self::CONFIG_KEY_IS_NOT_FOUND, $key, $fullPath ?? $path));
        }

        if (count($pathParts) > 0) {
            return $this->lodashGet($array[$key], implode('.', $pathParts), $fullPath ?? $path);
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
     *
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
    public function isLoc()
    {
        return strtolower($this->env('ENV')) == 'loc';
    }

    /**
     * @return bool
     * @throws \Exception
     */
    public function isDev()
    {
        return strtolower($this->env('ENV')) == 'dev';
    }

    /**
     * @return bool
     * @throws \Exception
     */
    public function isTest()
    {
        return strtolower($this->env('ENV')) == 'test' || strtolower($this->env('ENV')) == 'testing';
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
        self::$waitList[$currentConfigKey] = self::$waitList[$currentConfigKey] ?? [];
        self::$waitList[$currentConfigKey] = array_merge(self::$waitList[$currentConfigKey], is_array($waitForKey) ? $waitForKey : [$waitForKey]);
        self::$waitList[$currentConfigKey] = array_unique(self::$waitList[$currentConfigKey]);

        return $this->needWait($currentConfigKey);
    }

    public function getEnvKeys()
    {
        return array_keys(self::$env);
    }

    /**
     * @return bool
     * @throws \Exception
     */
    private function needWait($currentConfigKey)
    {
        $needWait = false;
        foreach (self::$waitList[$currentConfigKey] as $waitFor) {
            if ($waitFor == 'env') {
                $needWait = $needWait || is_null(self::$env);
            } elseif (is_null(self::$config) || is_null(self::$config[$waitFor] ?? null)) {
                $needWait = true;
            }
        }

        return $needWait;
    }

    private function getThresholdNestingNumber()
    {
        return $this->env('CONFIG_NESTING_THRESHOLD', true, self::CONFIG_NESTING_THRESHOLD);
    }

}
