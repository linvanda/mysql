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

    /**
     * 清除当前协程上下文
     */
    public function clean()
    {
        unset($this->container[Co::getuid()]);
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
            $this->init();
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

    /**
     * 第一次设置当前协程上下文信息时初始化
     */
    private function init()
    {
        $this->container[Co::getuid()] = [];
        // 协程退出时需要清理当前协程上下文
        Co::defer(function () {
            unset($this->container[Co::getuid()]);
        });
    }
}