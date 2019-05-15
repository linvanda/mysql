<?php

namespace Linvanda\Fundation\MySQL\Pool;

use Swoole\Coroutine as co;
use Linvanda\Fundation\MySQL\Connector\IConnectorBuilder;
use Linvanda\Fundation\MySQL\Connector\IConnector;
use Linvanda\Fundation\MySQL\Connector\ConnectorInfo;

/**
 * 协程版连接池
 * 注意：一旦连接池被销毁，连接池持有和分配出去的连接对象都会被关闭（哪怕该连接对象还在被外面使用）
 * Class CoPool
 * @package Linvanda\Fundation\MySQL\Pool
 */
class CoPool implements IPool
{
    protected const STATUS_OK = 1;
    protected const STATUS_UNAVAILABLE = 2;
    protected const STATUS_CLOSED = 3;

    protected static $instance;

    /** @var IConnectorBuilder */
    protected $readPool;
    /** @var co\Channel */
    protected $connectorBuilder;
    /** @var co\Channel */
    protected $writePool;
    // 记录每个连接的相关信息
    protected $connectsInfo = [];
    protected $connectNum;
    protected $maxSleepTime;
    protected $maxExecCount;
    protected $status;

    /**
     * CoPool constructor.
     * @param IConnectorBuilder $connectorBuilder 数据库连接生成器，连接池使用此生成器创建连接对象
     * @param int $size
     * @param int $maxSleepTime
     * @param int $maxExecCount
     * @throws \Exception
     */
    protected function __construct(IConnectorBuilder $connectorBuilder, int $size, int $maxSleepTime = 600, int $maxExecCount = 1000)
    {
        if (co::getuid() == -1) {
            throw new \Exception("只能在协程环境使用CoPool");
        }
        $this->connectorBuilder = $connectorBuilder;
        $this->readPool = new co\Channel($size);
        $this->writePool = new co\Channel($size);
        $this->maxSleepTime = $maxSleepTime;
        $this->maxExecCount = $maxExecCount;
        $this->connectNum = 0;
        $this->status = self::STATUS_OK;
    }

    public static function instance(IConnectorBuilder $connectorBuilder, int $size = 20, int $maxSleepTime = 600, int $maxExecCount = 1000): IPool
    {
        if (!static::$instance) {
            static::$instance = new static($connectorBuilder, $size, $maxSleepTime, $maxExecCount);
        }

        return static::$instance;
    }

    /**
     * 从连接池中获取 MySQL 连接对象
     * @param string $type
     * @return IConnector|bool
     * @throws \Exception
     */
    public function getConnector(string $type = 'write'): IConnector
    {
        if (!$this->isOk()) {
            return false;
        }

        $pool = $this->getPool($type);
        if ($pool->isEmpty()) {
            // 创建新连接
            $conn = $this->createConnector($type);

            if ($conn) {
                goto done;
            }

            return $conn;
        }

        $conn = $pool->pop(2);

        done:
        $connectInfo = $this->connectInfo($conn);
        $connectInfo->popTime = time();
        $connectInfo->status = ConnectorInfo::STATUS_BUSY;

        return $conn;
    }

    /**
     * 归还连接
     * @param IConnector $connector
     * @return bool
     */
    public function pushConnector(IConnector $connector): bool
    {
        if (!$connector) {
            return true;
        }

        $connInfo = $this->connectInfo($connector);
        $pool = $this->getPool($connInfo->type);

        // 先改变状态
        $connInfo && $connInfo->status = ConnectorInfo::STATUS_IDLE;

        if (!$this->isOk() || $pool->isFull() || !$this->isHealthy($connector)) {
            return $this->closeConnector($connector);
        }

        $connInfo->pushTime = time();
        return $pool->push($connector);
    }

    /**
     * 关闭连接池
     * @return bool
     */
    public function close(): bool
    {
        $this->status = self::STATUS_CLOSED;

        // 关闭通道中所有的连接。等待5ms为的是防止还有等待push的排队协程
        while ($conn = $this->readPool->pop(0.005)) {
            $this->closeConnector($conn);
        }
        while ($conn = $this->writePool->pop(0.005)) {
            $this->closeConnector($conn);
        }
        $this->readPool->close();
        $this->writePool->close();

        return true;
    }

    public function count(): array
    {
        return [
            'read' => $this->readPool->length(),
            'write' => $this->writePool->length()
        ];
    }

    protected function closeConnector(IConnector $connector)
    {
        if (!$connector) {
            return true;
        }

        $connector->close();
        $this->connectNum--;
        unset($this->connectsInfo[$this->getObjectId($connector)]);

        return true;
    }

    protected function isOk()
    {
        return $this->status == self::STATUS_OK;
    }

    /**
     * @param string $type
     * @return co\Channel
     */
    protected function getPool($type = 'write'): co\Channel
    {
        if (!$type || !in_array($type, ['read', 'write'])) {
            $type = 'write';
        }

        return $type === 'write' ? $this->writePool : $this->readPool;
    }

    /**
     * @param string $type
     * @return IConnector
     * @throws \Exception
     */
    protected function createConnector($type = 'write'): IConnector
    {
        $conn = $this->connectorBuilder->build($type);

        if ($conn) {
            $this->connectNum++;
            $this->connectsInfo[$this->getObjectId($conn)] = new ConnectorInfo($conn, $type);

            // 建立数据库连接
            $conn->connect();
        }

        return $conn;
    }

    /**
     * 检查连接对象的健康情况，以下情况视为不健康：
     * 1. SQL 执行次数超过阈值；
     * 2. 连接对象距最后使用时间超过阈值；
     * 3. 连接对象不是连接池创建的
     * @param IConnector $connector
     * @return bool
     */
    protected function isHealthy(IConnector $connector): bool
    {
        $connectorInfo = $this->connectInfo($connector);
        if (!$connectorInfo) {
            return false;
        }

        // 如果连接处于忙态（一般是还处于事务未提交状态），则一律返回 ok
        if ($connectorInfo->status === ConnectorInfo::STATUS_BUSY) {
            return true;
        }

        if (
            $connectorInfo->execCount() >= $this->maxExecCount ||
            time() - $connectorInfo->lastExecTime() >= $this->maxSleepTime
        ) {
            return false;
        }

        return true;
    }

    protected function connectInfo(IConnector $connector): ConnectorInfo
    {
        return $this->connectsInfo[$this->getObjectId($connector)];
    }

    protected function getObjectId($object): string
    {
        return spl_object_hash($object);
    }
}
