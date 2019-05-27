<?php

namespace Linvanda\MySQL\Connector;

/**
 * 连接器接口
 * Interface IConnector
 * @package Linvanda\MySQL\Connector
 */
interface IConnector
{
    /**
     * @param string $host
     * @param string $user
     * @param string $password
     * @param string $database
     * @param int $port
     * @param int $timeout
     * @param string $charset
     * @param bool @authConnect 是否自动连接
     */
    public function __construct(
        string $host,
        string $user,
        string $password,
        string $database,
        int $port = 3306,
        int $timeout = 3,
        string $charset = 'utf8',
        bool $autoConnect = false
    );

    /**
     * 连接数据库
     * @return bool 成功 true，失败 false
     */
    public function connect(): bool;

    /**
     * 关闭连接
     */
    public function close();

    /**
     * 执行 SQL
     * $sql 格式：select * from t_name where uid=?
     * @param string $sql 预处理 SQL
     * @param array $params 参数
     * @param int $timeout 查询超时时间，默认 3 分钟
     * @return mixed 失败返回 false；成功：查询返回数组，否则返回 true
     */
    public function query(string $sql, array $params, int $timeout = 180);

    /**
     * 开启事务
     * @return bool
     */
    public function begin(): bool;

    /**
     * 提交事务
     * @return bool
     */
    public function commit(): bool;

    /**
     * 回滚事务
     * @return bool
     */
    public function rollback(): bool;

    /**
     * SQL 执行影响的行数，针对命令型 SQL
     * @return int
     */
    public function affectedRows(): int;

    /**
     * 最后插入的记录 id
     * @return int
     */
    public function insertId(): int;

    /**
     * 最后的错误码
     * @return int
     */
    public function lastErrorNo(): int;

    /**
     * 错误信息
     * @return string
     */
    public function lastError(): string;

    /**
     * 本次会话共执行了多少次 SQL
     * @return int
     */
    public function execCount(): int;

    /**
     * 最近一次 SQL 执行时长（指发送 SQL 到接收 MySQL 返回的时长，不是那条 SQL 在 MySQL 服务器上执行时间）
     * @return int
     */
    public function lastExpendTime(): int;

    /**
     * 执行时间峰值
     * @return int
     */
    public function peakExpendTime(): int;

    /**
     * 最近一次执行 SQL 的时间
     * @return int
     */
    public function lastExecTime(): int;
}
