<?php

/**
 * 测试用生成器，返回 CoConnector 对象
 * Class TestConnectorBuilder
 */
class TestConnectorBuilder implements \Linvanda\MySQL\Connector\IConnectorBuilder
{
    /**
     * 创建并返回 IConnector 对象
     * @param string $connType read/write
     * @return \Linvanda\MySQL\Connector\IConnector
     */
    public function build(string $connType = 'write'): \Linvanda\MySQL\Connector\IConnector
    {
        return new \Linvanda\MySQL\Connector\CoConnector(
            '192.168.85.135',
            'root',
            'weicheche',
            'weicheche'
        );
    }
}