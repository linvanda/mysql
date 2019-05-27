<?php

namespace Linvanda\MySQL\Transaction;

use Swoole\Coroutine as Co;

/**
 * 协程事务上下文
 * Class TContext
 * @package Linvanda\MySQL\Transaction
 */
class TContext implements \ArrayAccess
{
    private $container = [];

    public function __construct()
    {
        Co::defer(function () {
            $this->clean();
        });
    }

    /**
     * 清除当前协程上下文
     * @param array $excludes 不能清除的
     */
    public function clean(array $excludes = [])
    {
        $cuid = Co::getuid();

        if (!$excludes) {
            unset($this->container[$cuid]);
            return;
        }

         foreach ($this->container[$cuid] as $key => $val) {
             if (!in_array($key, $excludes)) {
                 unset($this->container[$cuid]);
             }
         }
    }

    /**
     * 清除所有协程上下文
     */
    public function cleanAll()
    {
        $this->container = [];
    }

    public function offsetExists($offset)
    {
        return isset($this->container[Co::getuid()]) && array_key_exists($offset, $this->container[Co::getuid()]);
    }

    public function offsetGet($offset)
    {
        if (!isset($this->container[Co::getuid()])) {
            return null;
        }

        return $this->container[Co::getuid()][$offset] ?? null;
    }

    public function offsetSet($offset, $value)
    {
        $cuid = Co::getuid();
        if (!isset($this->container[$cuid])) {
            $this->container[$cuid] = [];
        }
        $this->container[$cuid][$offset] = $value;
    }

    public function offsetUnset($offset)
    {
        unset($this->container[Co::getuid()][$offset]);
    }

    public function __toString(): string
    {
        return print_r($this->container, true);
    }
}