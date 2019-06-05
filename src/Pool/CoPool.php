<?php

namespace Devar\MySQL\Pool;

use Swoole\Coroutine as co;
use Devar\MySQL\Exception\ConnectException;
use Devar\MySQL\Exception\ConnectFatalException;
use Devar\MySQL\Exception\PoolClosedException;
use Devar\MySQL\Connector\IConnectorBuilder;
use Devar\MySQL\Connector\IConnector;
use Devar\MySQL\Connector\ConnectorInfo;

/**
 * 协程版连接池
 * 注意：一旦连接池被销毁，连接池持有和分配出去的连接对象都会被关闭（哪怕该连接对象还在被外面使用）
 * Class CoPool
 * @package Devar\MySQL\Pool
 */
class CoPool implements IPool
{
    protected const STATUS_OK = 1;
    protected const STATUS_UNAVAILABLE = 2;
    protected const STATUS_CLOSED = 3;
    protected const MAX_WAIT_TIMEOUT_NUM = 200;

    protected static $container = [];

    /** @var IConnectorBuilder */
    protected $readPool;
    /** @var co\Channel */
    protected $writePool;
    /** @var co\Channel */
    protected $connectorBuilder;
    // 连接池大小
    protected $size;
    // 记录每个连接的相关信息
    protected $connectsInfo = [];
    // 当前存活的连接数（包括不在池中的）
    protected $connectNum;
    // 读连接数
    protected $readConnectNum;
    // 写连接数
    protected $writeConnectNum;
    protected $maxSleepTime;
    protected $maxExecCount;
    protected $status;
    // 连续等待连接对象失败次数（超过一定次数说明很可能数据库连接暂不可用）
    protected $waitTimeoutNum;

    /**
     * CoPool constructor.
     * @param IConnectorBuilder $connectorBuilder 数据库连接生成器，连接池使用此生成器创建连接对象
     * @param int $size
     * @param int $maxSleepTime
     * @param int $maxExecCount
     * @throws \Exception
     */
    protected function __construct(IConnectorBuilder $connectorBuilder, int $size = 25, int $maxSleepTime = 600, int $maxExecCount = 1000)
    {
        $this->connectorBuilder = $connectorBuilder;
        $this->readPool = new co\Channel($size);
        $this->writePool = new co\Channel($size);
        $this->size = $size;
        $this->maxSleepTime = $maxSleepTime;
        $this->maxExecCount = $maxExecCount;
        $this->connectNum = 0;
        $this->readConnectNum = 0;
        $this->writeConnectNum = 0;
        $this->waitTimeoutNum = 0;
        $this->status = self::STATUS_OK;
    }

    /**
     * @param IConnectorBuilder $connectorBuilder
     * @param int $size
     * @param int $maxSleepTime
     * @param int $maxExecCount
     * @return CoPool
     */
    public static function instance(IConnectorBuilder $connectorBuilder, int $size = 25, int $maxSleepTime = 600, int $maxExecCount = 1000): CoPool
    {
        if (!isset(static::$container[$connectorBuilder->getKey()])) {
            static::$container[$connectorBuilder->getKey()] = new static($connectorBuilder, $size, $maxSleepTime, $maxExecCount);
        }

        return static::$container[$connectorBuilder->getKey()];
    }

    /**
     * 从连接池中获取 MySQL 连接对象
     * @param string $type
     * @return IConnector|bool
     * @throws PoolClosedException
     * @throws ConnectException
     * @throws ConnectFatalException
     */
    public function getConnector(string $type = 'write'): IConnector
    {
        if (!$this->isOk()) {
            throw new PoolClosedException("连接池已经关闭，无法获取连接");
        }

        $pool = $this->getPool($type);

        if ($pool->isEmpty()) {
            // 超额，不能再创建，需等待
            if (($type == 'read' ? $this->readConnectNum : $this->writeConnectNum) > $this->size * 6) {
                // 放置数据库临时不可用，多次等待失败，则直接返回
                if ($this->waitTimeoutNum > self::MAX_WAIT_TIMEOUT_NUM) {
                    // 超出了等待失败次数限制，直接抛异常
                    throw new ConnectFatalException("多次获取连接超时，请检查数据库服务器状态");
                }

                $conn = $pool->pop(4);

                // 等待失败
                if (!$conn) {
                    switch ($pool->errCode) {
                        case SWOOLE_CHANNEL_TIMEOUT:
                            $this->waitTimeoutNum++;
                            $errMsg = "获取连接超时";
                            break;
                        case SWOOLE_CHANNEL_CLOSED:
                            $errMsg = "获取连接失败：连接池已关闭";
                            break;
                        default:
                            $errMsg = "获取连接失败";
                            break;
                    }

                    throw new ConnectException($errMsg);
                }
            } else {
                try {
                    // 创建新连接
                    $conn = $this->createConnector($type);
                } catch (ConnectException $exception) {
                    if ($exception->getCode() == 1040) {
                        // Too many connections,等待连接池
                        $conn = $pool->pop(4);
                    }

                    if (!$conn) {
                        if ($pool->errCode == SWOOLE_CHANNEL_TIMEOUT) {
                            $this->waitTimeoutNum++;
                        }

                        throw new ConnectException($exception->getMessage(), $exception->getCode());
                    }
                }
            }
        } else {
            // 从连接池获取
            $conn = $pool->pop(1);
        }

        $connectInfo = $this->connectInfo($conn);
        $connectInfo->popTime = time();
        $connectInfo->status = ConnectorInfo::STATUS_BUSY;
        // 等待次数清零
        $this->waitTimeoutNum = 0;

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

        if (!$this->isOk() || $pool->isFull() || !$this->isHealthy($connector)) {
            return $this->closeConnector($connector);
        }

        if ($connInfo) {
            $connInfo->status = ConnectorInfo::STATUS_IDLE;
            $connInfo->pushTime = time();
        }

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

        $objId = $this->getObjectId($connector);
        $connector->close();
        $this->untickConnectNum($this->connectsInfo[$objId]->type);
        unset($this->connectsInfo[$objId]);
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
     * 创建新连接对象
     * @param string $type
     * @return IConnector
     * @throws \Devar\MySQL\Exception\ConnectException
     */
    protected function createConnector($type = 'write'): IConnector
    {
        $conn = $this->connectorBuilder->build($type);

        if ($conn) {
            // 要在 connect 前 tick，否则无法阻止高并发协程打入
            $this->tickConnectNum($type);

            try {
                $conn->connect();
            } catch (ConnectException $exception) {
                // 撤销 tick
                $this->untickConnectNum($type);
                throw new ConnectException($exception->getMessage(), $exception->getCode());
            }

            $this->connectsInfo[$this->getObjectId($conn)] = new ConnectorInfo($conn, $type);
        }

        return $conn;
    }

    protected function tickConnectNum(string $type)
    {
        $this->changeConnectNum($type, 1);
    }

    protected function untickConnectNum(string $type)
    {
        $this->changeConnectNum($type, -1);
    }

    private function changeConnectNum(string $type, $num)
    {
        $this->connectNum = $this->connectNum + $num;
        if ($type == 'read') {
            $this->readConnectNum = $this->readConnectNum + $num;
        } else {
            $this->writeConnectNum = $this->writeConnectNum + $num;
        }
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
