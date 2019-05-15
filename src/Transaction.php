<?php

namespace Linvanda\Fundation\MySQL;

use Linvanda\Fundation\MySQL\Connector\IConnector;
use \Swoole\Coroutine as co;
use Linvanda\Fundation\MySQL\Pool\IPool;

/**
 * 事务管理器
 * 注意：事务开启直到提交/回滚的过程中会一直占用某个 IConnector 实例，如果有很多长事务，则会很快耗完连接池资源
 * Class Transaction
 * @package Linvanda\Fundation\MySQL
 */
class Transaction
{
    private $pool;
    // 以下属性都需要针对每个协程单独记录
    private $isRunning = [];
    private $commandPool = [];
    private $model = [];// 读模式还是写模式 write/read
    private $connector = [];

    public function __construct(IPool $pool)
    {
        $this->pool = $pool;
    }

    /**
     * @param string $model write/read 读模式还是写模式，针对读写分离
     * @param bool $isImplicit 是否隐式事务，隐式事务不会向 MySQL 提交 begin 请求
     * @return bool
     * @throws \Exception
     */
    public function begin(string $model = 'write', bool $isImplicit = false): bool
    {
        // 如果事务已经开启了，则直接返回
        if ($this->isRunning()) {
            return true;
        }

        $this->isRunning(1);
        $this->model[co::getuid()] = $model;

        // 开启事务时需从连接池获取 Connector
        if (!$this->getConnector()) {
            return false;
        }

        return $isImplicit || $this->connector()->begin();
    }

    /**
     * 发送指令
     * @param string $preSql
     * @param array $params
     * @return bool|mixed
     * @throws
     */
    public function command(string $preSql, array $params = [])
    {
        if (!$preSql) {
            return false;
        }

        $isImplicit = !$this->isRunning();

        // 开启事务
        if ($isImplicit && !$this->begin($this->model([[$preSql, $params]]), $isImplicit)) {
            return false;
        }

        // 执行 SQL
        $this->commandPool([$preSql, $params]);
        $result = $this->exec();

        // 提交事务
        if ($isImplicit && !$this->commit($isImplicit)) {
            return false;
        }

        return $result;
    }

    /**
     * 提交事务
     * @param bool $isImplicit 是否隐式事务，隐式事务不会向 MySQL 提交 commit
     * @return bool
     */
    public function commit(bool $isImplicit = false): bool
    {
        if (!$this->isRunning()) {
            return true;
        }

        $result = true;
        if (!$isImplicit) {
            $result = $this->connector()->commit();

            if ($result === false) {
                // 执行失败，试图回滚
                $this->rollback();
                return false;
            }
        }

        // 释放事务占用的资源
        $this->releaseTransResource();

        return $result;
    }

    public function rollback(): bool
    {
        if (!$this->isRunning()) {
            return true;
        }

        // 回滚前指令池中的指令一律清空
        $this->commandPool[co::getuid()] = [];
        $result = $this->connector()->rollback();

        $this->releaseTransResource();

        return $result;
    }

    /**
     * 获取或设置当前事务执行模式
     * @param string|array $model string: read/write; array: 格式同 commandPool
     * @return string
     */
    public function model($model = null): string
    {
        // 事务处于开启状态时不允许切换运行模式
        if ($this->isRunning()) {
            return $this->model[co::getuid()];
        }

        if ($model === null || is_array($model)) {
            // 根据指令池内容计算运行模式(只要有一条写指令则是 write 模式)
            $mdl = 'read';
            foreach (($model ?: $this->commandPool()) as $command) {
                $sqls = array_map(
                    function ($item) {
                        return trim($item);
                    },
                    array_filter(explode(';', $command[0]))
                );

                foreach ($sqls as $sql) {
                    if (preg_match('/^(update|replace|delete|insert|drop|grant|truncate|alter|create)\s/i', $sql)) {
                        $mdl = 'write';
                        goto rtn;
                    }
                }
            }

            rtn:
            return $mdl;
        }

        return $this->model[co::getuid()] = $model === 'read' ? 'read' : 'write';
    }

    /**
     * @return IConnector
     */
    public function connector(): IConnector
    {
        return $this->connector[co::getuid()];
    }

    /**
     * 释放当前协程的事务资源
     */
    private function releaseTransResource()
    {
        $cid = co::getuid();
        $this->isRunning[$cid] = 0;
        $this->commandPool[$cid] = [];
        // 归还连接资源
        $this->pool->pushConnector($this->connector[$cid]);
        unset($this->connector[$cid]);
        unset($this->model[$cid]);
    }

    /**
     * 执行指令池中的指令
     * @return mixed
     * @throws
     */
    private function exec()
    {
        // 执行指令的前提是在事务开启模式下且指令池中有指令
        if (!$this->commandPool() || !$this->isRunning()) {
            return true;
        }

        return $this->getConnector()->query(...$this->commandPool(null, true));
    }

    /**
     * @return IConnector
     * @throws \Exception
     */
    private function getConnector()
    {
        $cid = co::getuid();

        if (isset($this->connector[$cid])) {
            return $this->connector[$cid];
        }

        return ($this->connector[$cid] = $this->pool->getConnector($this->model()));
    }

    private function isRunning($val = null)
    {
        $coUid = co::getuid();
        if (isset($val)) {
            $this->isRunning[$coUid] = $val;
        } else {
            return isset($this->isRunning[$coUid]) && $this->isRunning[$coUid];
        }
    }

    private function commandPool($sqlArr = null, $pop = false)
    {
        $cid = co::getuid();

        if (!isset($this->commandPool[$cid])) {
            $this->commandPool[$cid] = [];
        }

        if ($sqlArr) {
            $this->commandPool[$cid][] = $sqlArr;
        } else {
            if ($pop) {
                return array_shift($this->commandPool[$cid]);
            }

            return $this->commandPool[$cid];
        }
    }
}
