<?php

namespace Devar\MySQL\Pool;

use Devar\MySQL\Connector\IConnector;

/**
 * 连接池
 * Interface IPool
 * @package Devar\MySQL\Pool
 */
 interface IPool
 {
     /**
      * 从连接池中获取 MySQL 连接对象
      * @param string $type
      * @return IConnector
      * @throws \Devar\MySQL\Exception\PoolClosedException
      * @throws \Devar\MySQL\Exception\ConnectException
      * @throws \Devar\MySQL\Exception\ConnectFatalException
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
