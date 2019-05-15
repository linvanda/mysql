<?php

namespace Linvanda\Fundation\MySQL\Connector;

/**
 * MySQL 数据库配置 DTO 对象
 * Class DBConfig
 * @package App\Foundation\DB
 */
class DBConfig
{
    public $host;
    public $port;
    public $user;
    public $password;
    public $database;
    public $charset;
    public $timeout;
    public $autoConnect;

    public function __construct(
        string $host,
        string $user,
        string $password,
        string $database,
        int $port = 3306,
        int $timeout = 3,
        string $charset = 'utf8',
        bool $autoConnect = false
    )
    {
        $this->host = $host;
        $this->port = $port;
        $this->user = $user;
        $this->password = $password;
        $this->database = $database;
        $this->charset = $charset;
        $this->timeout = $timeout;
        $this->autoConnect = $autoConnect;
    }
}