<?php

namespace Linvanda\MySQL\Connector;

class CoConnectorBuilder implements IConnectorBuilder
{
    protected static $container = [];

    protected $writeConfig;
    protected $readConfigs;
    protected $key;

    /**
     * CoConnectorBuilder constructor.
     * @param DBConfig $writeConfig
     * @param array $readConfigs
     * @throws \Exception
     */
    protected function __construct(DBConfig $writeConfig = null, array $readConfigs = [])
    {
        $this->writeConfig = $writeConfig;
        $this->readConfigs = $readConfigs;
    }

    public static function instance(DBConfig $writeConfig = null, array $readConfigs = []): CoConnectorBuilder
    {
        if ($writeConfig && !$readConfigs) {
            $readConfigs = [$writeConfig];
        }

        $key = self::calcKey($writeConfig, $readConfigs);
        if (!self::$container[$key]) {
            $builder = new static($writeConfig, $readConfigs);
            $builder->key = $key;
            self::$container[$key] = $builder;
        }

        return self::$container[$key];
    }

    /**
     * 创建并返回 IConnector 对象
     * @param string $connType read/write
     * @return IConnector
     */
    public function build(string $connType = 'write'): IConnector
    {
        /** @var DBConfig */
        $config = $connType == 'read' ? $this->getReadConfig() : $this->writeConfig;

        if (!($config instanceof DBConfig)) {
            return null;
        }

        return new CoConnector($config->host, $config->user, $config->password, $config->database, $config->port, $config->timeout, $config->charset);
    }

    public function getKey(): string
    {
        return $this->key;
    }

    private function getReadConfig()
    {
        return $this->readConfigs[mt_rand(0, count($this->readConfigs) - 1)];
    }

    /**
     * 根据配置计算 key，完全相同的配置对应同样的 key
     * @param DBConfig|null $writeConfig
     * @param array $readConfigs
     * @return string
     */
    private static function calcKey(DBConfig $writeConfig = null, array $readConfigs = []): string
    {
        $joinStr = function ($conf)
        {
            $arr = [
                $conf->host,
                $conf->port,
                $conf->user,
                $conf->password,
                $conf->database,
                $conf->charset,
                $conf->timeout,
            ];
            sort($arr);
            return implode('-', $arr);
        };

        $readArr = [];
        foreach ($readConfigs as $readConfig) {
            $readArr[] = $joinStr($readConfig);
        }

        sort($readArr);

        return md5($joinStr($writeConfig) . implode('$', $readArr));
    }
}