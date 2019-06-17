<?php

namespace Dev\MySQL;

use Dev\MySQL\Exception\DBException;
use Dev\MySQL\Transaction\ITransaction;

/**
 * 查询器，对外暴露的 API
 * Class Query
 * @package Dev\MySQL
 */
class Query
{
    use Builder;

    public const MODEL_READ = 'read';
    public const MODEL_WRITE = 'write';

    private $transaction;

    /**
     * Query constructor.
     * @param ITransaction $transaction 事务管理器
     */
    public function __construct(ITransaction $transaction)
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
     * @throws \Exception
     */
    public function commit(): bool
    {
        return $this->transaction->commit();
    }

    /**
     * 回滚事务
     * @return bool
     * @throws \Exception
     */
    public function rollback(): bool
    {
        return $this->transaction->rollback();
    }

    /**
     * 强制设置使用读库还是写库
     * @param string $model read/write
     * @return string
     * @throws \Exception
     */
    public function setModel(string $model)
    {
        if (!in_array($model, [self::MODEL_READ, self::MODEL_WRITE])) {
            throw new \Exception("非法的 model 标识：{$model}。仅支持 read/write");
        }
        $this->transaction->model($model);
        return $this;
    }

    /**
     * 便捷方法：列表查询
     * @return array
     * @throws \Exception
     */
    public function list(): array
    {
        $list = $this->transaction->command(...$this->compile());
        if ($this->lastErrorNo()) {
            throw new DBException($this->lastError(), $this->lastErrorNo());
        }

        return $list;
    }

    /**
     * 便捷方法：查询一行记录
     * @return array|false
     * @throws \Exception
     */
    public function one(): array
    {
        $list = $this->transaction->command(...$this->limit(1)->compile());

        if ($this->lastErrorNo()) {
            throw new DBException($this->lastError(), $this->lastErrorNo());
        }

        if ($list) {
            return $list[0];
        }

        return [];
    }

    /**
     * 便捷方法：查询某个字段的值
     * @return mixed
     * @throws DBException
     */
    public function column()
    {
        $res = $this->transaction->command(...$this->compile());

        if ($this->lastErrorNo()) {
            throw new DBException($this->lastError(), $this->lastErrorNo());
        }

        return $res ? reset($res[0]) : '';
    }

    /**
     * 便捷方法：分页查询
     * @return array|false
     * @throws DBException
     */
    public function page(): array
    {
        $fields = $this->fields;
        $limit = $this->limit ?: 20;
        $offset = $this->offset ?? 0;

        $countRes = $this->transaction->command(...$this->fields('count(*) as cnt')->reset('limit')->compile(false));
        if ($this->lastErrorNo()) {
            throw new DBException($this->lastError(), $this->lastErrorNo());
        }

        if (!$countRes || !$countRes[0]['cnt']) {
            $this->reset();
            return ['total' => 0, 'data' => []];
        }

        $data = $this->transaction->command(...$this->fields($fields)->limit($limit, $offset)->compile());

        if ($this->lastErrorNo()) {
            throw new DBException($this->lastError(), $this->lastErrorNo());
        }

        return ['total' => $countRes[0]['cnt'], 'data' => $data];
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
            $result =  $this->transaction->command(...$this->compile());
        } else {
            $result = $this->transaction->command(...$this->prepareSQL($preSql, $params));
        }

        if ($this->lastErrorNo()) {
            throw new DBException($this->lastError(), $this->lastErrorNo());
        }

        return $result;
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

    public function affectedRows()
    {
        return $this->transaction->affectedRows();
    }
}
