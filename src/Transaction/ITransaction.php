<?php

namespace Linvanda\MySQL\Transaction;

/**
 * 事务接口
 * Interface ITransaction
 * @package Linvanda\MySQL\Transaction
 */
interface ITransaction
{
    /**
     * @param string $model write/read 读模式还是写模式，针对读写分离
     * @param bool $isImplicit 是否隐式事务，隐式事务不会向 MySQL 提交 begin 请求
     * @return bool
     * @throws \Exception
     */
    public function begin(string $model = 'write', bool $isImplicit = false): bool;

    /**
     * 发送指令
     * @param string $preSql
     * @param array $params
     * @return bool|mixed
     * @throws
     */
    public function command(string $preSql, array $params = []);

    /**
     * 提交事务
     * @param bool $isImplicit 是否隐式事务，隐式事务不会向 MySQL 提交 commit (要求数据库服务器开启了自动提交的配置)
     * @return bool
     * @throws \Exception
     */
    public function commit(bool $isImplicit = false): bool;

    /**
     * @return bool
     * @throws \Exception
     */
    public function rollback(): bool;

    /**
     * 获取或设置当前事务执行模式
     * @param string 读/写模式 read/write
     * @return string 当前事务执行模式
     */
    public function model(?string $model = null): string;

    public function lastInsertId();

    public function affectedRows();

    public function lastError();

    public function lastErrorNo();
}