<?php

namespace Dev\MySQL\Connector;

/**
 * IConnector 生成器
 * Interface IConnectorBuilder
 * @package Dev\MySQL\Connector
 */
interface IConnectorBuilder
{
    /**
     * 创建并返回 IConnector 对象
     * @param string $connType read/write
     * @return IConnector
     */
    public function build(string $connType = 'write'): IConnector;
    public function getKey(): string;
}