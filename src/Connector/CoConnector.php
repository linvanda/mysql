<?php

namespace Linvanda\MySQL\Connector;

use Swoole\Coroutine\MySQL;

/**
 * 协程版连接器
 * Class CoConnector
 * @package Linvanda\MySQL\Connector
 */
class CoConnector implements IConnector
{
    /** @var MySQL */
    private $mysql;
    private $config;
    private $execCount = 0;
    private $lastExpendTime = 0;
    private $peakExpendTime = 0;
    private $lastExecTime = 0;

    public function __construct(
        string $host,
        string $user,
        string $password,
        string $database,
        int $port = 3306,
        int $timeout = 3,
        string $charset = 'utf8',
        bool $autoConnect = false
    ) {
        $this->config = [
            'host' => $host,
            'user' => $user,
            'password' => $password,
            'database' => $database,
            'port'    => $port,
            'timeout' => $timeout,
            'charset' => $charset,
            'strict_type' => false,
            'fetch_mode' => false,
        ];

        $this->mysql = new MySQL();

        if ($autoConnect) {
            $this->connect();
        }
    }

    public function __destruct()
    {
        $this->close();
    }

    public function connect(): bool
    {
        if ($this->mysql->connected) {
            return true;
        }

        return $this->mysql->connect($this->config);
    }

    /**
     * 关闭连接
     */
    public function close()
    {
        $this->mysql->close();

        $this->execCount = 0;
        $this->lastExecTime = 0;
        $this->lastExpendTime = 0;
    }

    /**
     * 执行 SQL 语句
     * 对于有 $params的 SQL，强制走 prepare
     * @param string $sql
     * @param array $params
     * @param int $timeout
     * @return mixed 成功返回相应值（true 或 结果集），失败返回 false
     * @throws \Exception
     */
    public function query(string $sql, array $params = [], int $timeout = 180)
    {
        $prepare = $params ? true : false;

        $this->execCount++;
        $this->lastExecTime = time();

        if ($prepare) {
            $statement = $this->mysql->prepare($sql, $timeout);

            // 失败，尝试重新连接数据库
            if ($statement === false && $this->tryReconnectForQueryFail()) {
                $statement = $this->mysql->prepare($sql, $timeout);
            }

            if ($statement === false) {
                $result = false;
                goto done;
            }

            // execute
            $result = $statement->execute($params, $timeout);

            if ($result === false && $this->tryReconnectForQueryFail()) {
                $result = $statement->execute($params, $timeout);
            }
        } else {
            $result = $this->mysql->query($sql, $timeout);

            if ($result === false && $this->tryReconnectForQueryFail()) {
                $result = $this->mysql->query($sql, $timeout);
            }
        }

        done:
        $this->lastExpendTime = time() - $this->lastExecTime;
        $this->peakExpendTime = max($this->lastExpendTime, $this->peakExpendTime);

        return $result;
    }

    public function begin(): bool
    {
        return $this->mysql->begin();
    }

    public function commit(): bool
    {
        return $this->mysql->commit();
    }

    public function rollback(): bool
    {
        return $this->mysql->rollback();
    }

    /**
     * SQL 执行影响的行数，针对命令型 SQL
     * @return int
     */
    public function affectedRows(): int
    {
        return $this->mysql->affected_rows;
    }

    /**
     * 最后插入的记录 id
     * @return int
     */
    public function insertId(): int
    {
        return $this->mysql->insert_id;
    }

    /**
     * 最后的错误码
     * @return int
     */
    public function lastErrorNo(): int
    {
        return $this->mysql->errno;
    }

    public function lastError(): string
    {
        return $this->mysql->error;
    }

    /**
     * 失败重连
     */
    private function tryReconnectForQueryFail()
    {
        if ($this->mysql->connected || !in_array($this->mysql->errno, [2006, 2013])) {
            return false;
        }

        // 尝试重新连接
        return $this->mysql->connect($this->config);
    }

    /**
     * 本次会话共执行了多少次 SQL
     * @return int
     */
    public function execCount(): int
    {
        return $this->execCount;
    }

    /**
     * 最近一次 SQL 执行时长（指发送 SQL 到接收 MySQL 返回的时长，不是那条 SQL 在 MySQL 服务器上执行时间）
     * @return int
     */
    public function lastExpendTime(): int
    {
        return $this->lastExpendTime;
    }

    public function peakExpendTime(): int
    {
        return $this->peakExpendTime;
    }

    /**
     * 最近一次执行 SQL 的时间
     * @return int
     */
    public function lastExecTime(): int
    {
        return $this->lastExecTime;
    }
}
