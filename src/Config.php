<?php

namespace EveInUa\MultiConf;

use Exception;

class Config extends SingletonAbstract implements IConfig
{
    const ENV_KEY_IS_NOT_FOUND = 'Env key `%s` is not found.';
    const ENV_FILE_IS_NOT_FOUND = '.env file is not found.';
    const ENV_DEFAULT_FILE_IS_NOT_FOUND = '.env.default file is not found.';
    const CONFIG_KEY_IS_NOT_FOUND = 'Config key `%s` of path `%s` is not found.';
    const CONFIG_KEY_IS_NOT_FOUND_SKIPPED = 'Config key `%s` of path `%s` is not found, default used instead.';
    const ERROR_UNSUPPORTED_CONFIG_TYPE = 'Unsupported config type.';
    const ERROR_TOO_MANY_NESTING = 'Too many config nesting.';

    const CONFIG_DEFAULT_VALUE = 'ecb902b728093cd0c652cfa78bdd8c97';

    const CONFIG_NESTING_THRESHOLD = 10;

    private static $init = false;

    private static $env = null;
    private static $config = null;

    private static $waitList = [];
    private static $loadedConfigNames = [];

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
     * @param bool $forceReload
     * @throws Exception
     */
    public function init($forceReload = false)
    {
        if (!$this->isEnvLoaded() || $forceReload) {
            $this->initEnv();
        }
        if (!$this->isConfigLoaded() || $forceReload) {
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

        return true;
    }

    /**
     * Returns config value from CONFIG_ROOT/config directory.
     *
     * @param string $keyPathDotNotation
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
            $this->initSpecificConfig(reset($keyPathDotNotationParts));
        }

        try {
            return $this->lodashGet(self::$config ?? [], $keyPathDotNotation, null, $default);
        } catch (Exception $exception) {
            if ($default !== self::CONFIG_DEFAULT_VALUE) {
                return $default;
            }
            throw $exception;
        }
    }

    /**
     * @param string $name = '{configName}' | 'env'
     * @return bool
     * @throws Exception
     */
    private function initSpecific($name)
    {
        if ($name === 'env') {
            return $this->isEnvLoaded() || self::initEnv();
        } else {
            return $this->isConfigLoaded($name) || self::initSpecificConfig($name);
        }
    }

    /**
     * TODO: allow to load configs from folder
     *
     * @param string $configName = 'websites' | 'locale/CUSTOMUSB/en'
     * @param int $reloadCount - allows to stop looped execution when there is broken config
     * @return bool
     * @throws Exception
     */
    private function initSpecificConfig($configName, $reloadCount = 0)
    {
        if ($this->isConfigLoaded($configName)) {
            return true;
        }
        /**
         * Handle using config|env in another config.
         * Stop looped execution when there is broken config.
         *
         * @see Config::waitFor
         */
        if ($reloadCount > $this->getThresholdNestingNumber()) {
            throw new \Exception(self::ERROR_TOO_MANY_NESTING);
        }
        $configsDirPath = $this->clearPath(CONFIG_ROOT . '/config');
        if (!file_exists($configsDirPath)) {
            return false;
        }

        $configBaseFile = $configsDirPath . '/' . trim($configName, '/');
        /** @var $configFiles - each next config file override previous values (recursively) */
        $configFiles = [
            $configBaseFile . '.default.php',
            $configBaseFile . '.php',
            $configBaseFile . '.default.json',
            $configBaseFile . '.json',
            # $configBaseFile , // TODO: allow to load configs from folder
        ];

        while (!empty($configFiles)) {
            $filePath = array_shift($configFiles);
            if (!file_exists($filePath)) continue;
            $isPhp = substr($filePath, -4) == '.php';
            $isJson = substr($filePath, -5) == '.json';
            if ($isPhp) {
                $configArray = include $filePath;
            } elseif ($isJson) {
                $configArray = file_get_contents($filePath);
                $configArray = json_decode($configArray, true);
            } else {
                throw new \Exception(self::ERROR_UNSUPPORTED_CONFIG_TYPE);
            }
            // INFO: config can return null if it waits for another config:
            if ($this->hasWaitList($configName)) {
                $waitList = $this->getWaitList($configName);
                foreach ($waitList as $waitName) {
                    $this->initSpecific($waitName);
                }
                $this->clearWaitList($configName);

                // INFO: rerun same code if dependecies were not loaded:
                return $this->initSpecificConfig($configName, $reloadCount + 1);
            }

            self::$config[$configName] = array_replace_recursive(self::$config[$configName] ?? [], $configArray ?? []);
        }
        self::$loadedConfigNames[] = $configName;

        return true;
    }

    private function initConfig()
    {
        $configFilesPath = $this->clearPath(CONFIG_ROOT . '/config');
        if (!file_exists($configFilesPath)) {
            self::$config = [];
            return;
        }
        $configFiles = scandir($this->clearPath(CONFIG_ROOT . '/config'));

        while (!empty($configFiles)) {
            $configFile = array_shift($configFiles);
            if (in_array($configFile, ['.', '..']) || is_dir($configFile)) continue; // TODO: allow to load configs from folder
            $configName = str_replace(['.php', '.json', '.default',], '', $configFile);
            if (in_array($configName, self::$loadedConfigNames)) continue;
            $this->initSpecificConfig($configName);
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
    private function lodashGet($array, $path, $fullPath = null, $default = self::CONFIG_DEFAULT_VALUE)
    {
        $pathParts = explode('.', $path);
        $key = array_shift($pathParts);

        if (!isset($array[$key])) {
            if ($default !== self::CONFIG_DEFAULT_VALUE) {
                throw new Exception(sprintf(self::CONFIG_KEY_IS_NOT_FOUND_SKIPPED, $key, $fullPath ?? $path));
            } else {
                throw new Exception(sprintf(self::CONFIG_KEY_IS_NOT_FOUND, $key, $fullPath ?? $path));
            }
        }

        if (count($pathParts) > 0) {
            return $this->lodashGet($array[$key], implode('.', $pathParts), $fullPath ?? $path);
        } else {
            return $array[$key];
        }
    }

    /**
     * @param string $path
     * @return string
     */
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
     * @param string $currentConfigKey
     * @param array|string $waitForKey
     * @return bool
     * @throws Exception
     */
    public function waitFor($currentConfigKey, $waitForKey = [])
    {
        $waitList = is_array($waitForKey) ? $waitForKey : [$waitForKey];

        self::$waitList[$currentConfigKey] = self::$waitList[$currentConfigKey] ?? [];
        self::$waitList[$currentConfigKey] = array_merge(self::$waitList[$currentConfigKey], $waitList);
        self::$waitList[$currentConfigKey] = array_unique(self::$waitList[$currentConfigKey]);

        return $this->needWait($currentConfigKey);
    }

    /**
     * @param string $configKey
     * @return bool
     */
    public function hasWaitList($configKey)
    {
        return !empty(self::$waitList[$configKey]);
    }

    /**
     * @param string $configKey
     * @return array
     */
    public function getWaitList($configKey)
    {
        return self::$waitList[$configKey];
    }

    /**
     * @param string $configKey
     * @return void
     */
    public function clearWaitList($configKey)
    {
        unset(self::$waitList[$configKey]);
    }

    /**
     * @param null|string $configKey
     * @return bool
     */
    public function isConfigLoaded($configKey = null)
    {
        return is_null($configKey) ? !is_null(self::$config) : in_array($configKey, self::$loadedConfigNames);
    }

    /**
     * @return bool
     * @throws \Exception
     */
    public function isEnvLoaded()
    {
        return !is_null(self::$env);
    }

    /**
     * @return string[]
     */
    public function getEnvKeys()
    {
        return array_keys(self::$env ?? []);
    }

    /**
     * @param string $currentConfigKey
     * @return bool
     * @throws Exception
     */
    private function needWait($currentConfigKey)
    {
        $needWait = false;
        foreach (self::$waitList[$currentConfigKey] as $waitFor) {
            if ($waitFor == 'env') {
                $needWait = $needWait || !$this->isEnvLoaded();
            } elseif (!$this->isConfigLoaded($waitFor)) {
                $needWait = true;
            }
        }
        if (!$needWait) {
            $this->clearWaitList($currentConfigKey);
        }

        return $needWait;
    }

    /**
     * @return int
     * @throws Exception
     */
    private function getThresholdNestingNumber()
    {
        return intval($this->env('CONFIG_NESTING_THRESHOLD', true, self::CONFIG_NESTING_THRESHOLD));
    }

}
