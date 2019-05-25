<?php

namespace Linvanda\Fundation\MySQL;

/**
 * 查询器，对外暴露的 API
 * Class Query
 * @package Linvanda\Fundation\MySQL
 */
class Query
{
    use Builder;

    private $transaction;

    /**
     * Query constructor.
     * @param Transaction $transaction 事务管理器
     */
    public function __construct(Transaction $transaction)
    {
        $this->transaction = $transaction;
    }

    /**
     * 开启事务
     * @param string $model
     * @return bool
     * @throws \Exception
     */
    public function begin($model = 'write'): bool
    {
        return $this->transaction->begin($model);
    }

    /**
     * 提交事务
     * @return bool
     */
    public function commit(): bool
    {
        return $this->transaction->commit();
    }

    /**
     * 回滚事务
     * @return bool
     */
    public function rollback(): bool
    {
        return $this->transaction->rollback();
    }

    /**
     * 便捷方法：列表查询
     * @return array
     * @throws \Exception
     */
    public function list(): array
    {
        return $this->transaction->command(...$this->compile());
    }

    /**
     * 便捷方法：查询一行记录
     * @return array|false
     * @throws \Exception
     */
    public function one(): array
    {
        $list = $this->transaction->command(...$this->limit(1)->compile());

        if ($list === false) {
            return false;
        }

        if ($list) {
            return $list[0];
        }

        return [];
    }

    /**
     * 便捷方法：查询某个字段的值
     * @return mixed
     */
    public function column()
    {
        $res = $this->transaction->command(...$this->compile());

        if ($res === false) {
            return false;
        }

        return $res ? reset($res[0]) : '';
    }

    /**
     * 便捷方法：分页查询
     * @return array|false
     */
    public function page(): array
    {
        $fields = $this->fields;
        $limit = $this->limit;

        $countRes = $this->transaction->command(...$this->fields('count(*) as cnt')->reset('limit')->compile(false));

        if ($countRes === false) {
            return false;
        }

        if (!$countRes || !$countRes[0]['cnt']) {
            $this->reset();
            return ['total' => 0, 'data' => []];
        }

        $data = $this->transaction->command(...$this->fields($fields)->limit($limit)->compile());

        if ($data === false) {
            return false;
        }

        return ['total' => $countRes['cnt'], 'data' => $data];
    }

    /**
     * 执行 SQL
     * 有两种方式：
     *  1. 调此方法时传入相关参数；
     *  2. 通过 Builder 提供的 Active Record 方法组装 SQL，调此方法（不传参数）执行并返回结果
     * @param string $preSql
     * @param array $params
     * @return int|array 影响的行数|数据集
     * @throws \Exception
     */
    public function execute(string $preSql = '', array $params = [])
    {
        if (!func_num_args()) {
            return $this->transaction->command(...$this->compile());
        }

        return $this->transaction->command(...$this->prepareSQL($preSql, $params));
    }

    /**
     * @return int
     */
    public function lastInsertId()
    {
        return $this->transaction->lastInsertId();
    }

    /**
     * @return string
     */
    public function lastError()
    {
        return $this->transaction->lastError();
    }

    /**
     * @return int
     */
    public function lastErrorNo()
    {
        return $this->transaction->lastErrorNo();
    }
}
