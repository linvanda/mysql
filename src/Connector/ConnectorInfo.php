<?php

namespace Linvanda\Fundation\MySQL\Connector;

/**
 * 连接对象的统计信息，供 Pool 使用
 * Class ConnectorInfo
 * @package Linvanda\Fundation\MySQL\Connector
 */
class ConnectorInfo
{
    public const STATUS_BUSY = 1;
    public const STATUS_IDLE = 2;

    // 创建时间
    public $createTime;
    // 最后取出时间
    public $popTime;
    // 最后归还时间
    public $pushTime;
    // 连接类型：只读连接、写连接
    public $type;
    // 状态：忙、闲
    public $status;

    /** @var IConnector */
    private $connector;

    public function __construct(IConnector $connector, string $type)
    {
        $this->connector = $connector;
        $this->createTime = time();
        $this->type = $type;
    }

    /**
     * 最后执行 SQL 时间
     * @return int
     */
    public function lastExecTime(): int
    {
        return $this->connector->lastExecTime();
    }

    /**
     * 执行了多少次 SQL
     * @return int
     */
    public function execCount(): int
    {
        return $this->connector->execCount();
    }

    /**
     * 峰值时长
     * @return int
     */
    public function peakExecTime(): int
    {
        return $this->connector->peakExpendTime();
    }
}
