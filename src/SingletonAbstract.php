<?php

namespace EveInUa\MultiConf;

abstract class SingletonAbstract
{
    /**
     * @var self
     */
    protected static $instances = [];

    final public function __construct()
    {
        // Allow to use the `new` syntax along with static::instance().
        $class = get_called_class();
        if (empty(static::$instances[$class])) { // Check if something is filled to forbid self-call in a loop.
            return static::instance();
        }
    }

    final public function __clone()
    {
    }

    final public function __wakeup()
    {
    }

    /**
     * @return static
     */
    public static function instance()
    {
        $class = get_called_class();

        if (empty(static::$instances[$class])) {
            static::$instances[$class] = true; // Fill with something to forbid self-call in a loop.
            static::$instances[$class] = new static();
            static::$instances[$class]->boot();
        }

        return static::$instances[$class];
    }

    protected function boot()
    {
    }

}
