<?php

namespace Linvanda\MySQL\Transaction;

use Linvanda\MySQL\Pool\IPool;
use Linvanda\MySQL\Connector\IConnector;

/**
 * 协程版事务管理器
 * 注意：事务开启直到提交/回滚的过程中会一直占用某个 IConnector 实例，如果有很多长事务，则会很快耗完连接池资源
 * Class Transaction
 * @package Linvanda\MySQL\Transaction
 */
class CoTransaction implements ITransaction
{
    private $pool;
    private $context;

    public function __construct(IPool $pool)
    {
        $this->pool = $pool;
        $this->context = new TContext();
    }

    /**
     * @throws \Exception
     */
    public function __destruct()
    {
        // 如果事务没有结束，则回滚
        if ($this->isRunning()) {
            $this->rollback();
        }
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

        // 事务模式
        $this->model($model);
        $this->isRunning(true);

        // 获取 Connector
        if (!($connector = $this->connector())) {
            $this->isRunning(false);
            return false;
        }

        $this->resetLastExecInfo();
        $this->clearSQL();

        return $isImplicit || $connector->begin();
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

        // 是否隐式事务：外界没有调用 begin 而是直接调用 command 则为隐式事务
        $isImplicit = !$this->isRunning();

        // 如果是隐式事务，则需要自动开启事务
        if ($isImplicit && !$this->begin($this->calcModelFromSQL($preSql), true)) {
            return false;
        }

        $result = $this->exec([$preSql, $params]);

        // 隐式事务需要及时提交
        if ($isImplicit && !$this->commit($isImplicit)) {
            return false;
        }

        return $result;
    }

    /**
     * 提交事务
     * @param bool $isImplicit 是否隐式事务，隐式事务不会向 MySQL 提交 commit
     * @return bool
     * @throws \Exception
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

    /**
     * @return bool
     * @throws \Exception
     */
    public function rollback(): bool
    {
        if (!$this->isRunning()) {
            return true;
        }

        $result = $this->connector()->rollback();
        $this->releaseTransResource();

        return $result;
    }

    /**
     * 获取或设置当前事务执行模式
     * @param string$model read/write
     * @return string
     */
    public function model(?string $model = null): string
    {
        // 事务处于开启状态时不允许切换运行模式
        if (!isset($model) || $this->isRunning()) {
            return $this->context['model'];
        }

        $this->context['model'] = $model === 'read' ? 'read' : 'write';

        return $model;
    }

    public function lastInsertId()
    {
        return $this->getLastExecInfo('insert_id');
    }

    public function affectedRows()
    {
        return $this->getLastExecInfo('affected_rows');
    }

    public function lastError()
    {
        return $this->getLastExecInfo('error');
    }

    public function lastErrorNo()
    {
        return $this->getLastExecInfo('error_no');
    }

    public function sql(): array
    {
        return $this->context['sql'] ?? [];
    }

    /**
     * 释放当前协程的事务资源
     * @throws \Exception
     */
    private function releaseTransResource()
    {
        // 保存本次事务相关执行结果
        $this->saveLastExecInfo();
        // 归还连接资源
        $this->giveBackConnector();

        unset($this->context['model']);

        $this->isRunning(false);
    }

    /**
     * @throws \Exception
     */
    private function saveLastExecInfo()
    {
        $conn = $this->connector();
        $this->context['last_exec_info'] = [
            'insert_id' => $conn->insertId(),
            'error' => $conn->lastError(),
            'error_no' => $conn->lastErrorNo(),
            'affected_rows' => $conn->affectedRows(),
        ];
    }

    private function resetLastExecInfo()
    {
        unset($this->context['last_exec_info']);
    }

    private function getLastExecInfo(string $key)
    {
        return isset($this->context['last_exec_info']) ? $this->context['last_exec_info'][$key] : '';
    }

    /**
     * 执行指令池中的指令
     * @param $sqlInfo
     * @return mixed
     * @throws
     */
    private function exec(array $sqlInfo)
    {
        if (!$sqlInfo || !$this->isRunning()) {
            return true;
        }

        // 保存 SQL
        $this->saveSQL($sqlInfo);

        return $this->connector()->query($sqlInfo[0], $sqlInfo[1]);
    }

    private function saveSQL(array $sqlInfo)
    {
        $sqlPool = $this->context['sql'] ?? [];
        $sqlPool[] = $sqlInfo;
        $this->context['sql'] = $sqlPool;
    }

    private function clearSQL()
    {
        unset($this->context['sql']);
    }

    private function calcModelFromSQL(string $sql): string
    {
        if (preg_match('/^(update|replace|delete|insert|drop|grant|truncate|alter|create)\s/i', $sql)) {
            return 'write';
        }

        return 'read';
    }

    /**
     * 获取连接资源
     * @return IConnector
     * @throws \Exception
     */
    private function connector()
    {
        if ($connector = $this->context['connector']) {
            return $connector;
        }

        $this->context['connector'] = $this->pool->getConnector($this->model());

        return $this->context['connector'];
    }

    /**
     * 归还连接资源
     */
    private function giveBackConnector()
    {
        if ($this->context['connector']) {
            $this->pool->pushConnector($this->context['connector']);
        }

        unset($this->context['connector']);
    }

    private function isRunning(?bool $val = null)
    {
        if (isset($val)) {
            $this->context['is_running'] = $val;
        } else {
            return $this->context['is_running'] ?? false;
        }
    }
}