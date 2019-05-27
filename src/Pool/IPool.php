<?php

namespace Linvanda\MySQL\Pool;

use Linvanda\MySQL\Connector\IConnector;

/**
 * 连接池
 * Interface IPool
 * @package Linvanda\MySQL\Pool
 */
 interface IPool
 {
     /**
      * 从连接池中获取 MySQL 连接对象
      * @param string $type
      * @return IConnector
      * @throws \Exception
      */
    public function getConnector(string $type = 'write'): IConnector;
     /**
      * 归还连接
      * @param IConnector $connector
      * @return bool
      */
     public function pushConnector(IConnector $connector): bool;

     /**
      * 连接池中连接数
      * @return array ['read' => 3, 'write' => 3]
      */
     public function count(): array;

     /**
      * 关闭连接池
      * @return bool
      */
     public function close(): bool;
 }
