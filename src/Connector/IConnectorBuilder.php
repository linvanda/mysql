<?php

namespace Linvanda\Fundation\MySQL\Connector;

/**
 * IConnector 生成器
 * Interface IConnectorBuilder
 * @package Linvanda\Fundation\MySQL\Connector
 */
interface IConnectorBuilder
{
    /**
     * 创建并返回 IConnector 对象
     * @param string $connType read/write
     * @return IConnector
     */
    public function build(string $connType = 'write'): IConnector;
}