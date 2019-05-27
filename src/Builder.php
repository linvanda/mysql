<?php

namespace Linvanda\MySQL;

/**
 * 简单的查询构造器
 * 复杂的 SQL 建议直接写原生 SQL
 * 目前只支持构造 select,update,insert,replace,delete
 * Trait Builder
 * @package Linvanda\MySQL
 */
Trait Builder
{
    private $type;
    private $fields;
    private $table;
    private $where;
    private $join = '';
    private $limit;
    private $orderBy;
    private $groupBy;
    private $having;
    private $values;
    private $set;
    private $forceIndex;
    private $whereParams = [];
    private $joinParams = [];
    private $havingParams = [];
    private $valuesParams = [];
    private $setParams = [];
    private $rawSqlInfo = [];

    /**
     * 只能调用一次
     * @param null $fields
     * @return Builder
     */
    public function select($fields = null)
    {
        if ($this->type) {
            return $this;
        }

        $this->type = 'select';

        if ($fields) {
            $this->fields($fields);
        }

        return $this;
    }

    /**
     * 多次调用会覆盖前面的
     * @param string|array $fields
     * @return Builder
     */
    public function fields($fields)
    {
        if (!$fields) {
            $this->fields = '*';
            return $this;
        }

        if (is_string($fields)) {
            $this->fields = $this->plainText($fields);
            return $this;
        }

        $this->fields = $this->plainText(implode(',', $fields));

        return $this;
    }

    /**
     * 只能调用一次
     * @param string $table
     * @return Builder
     */
    public function update(string $table)
    {
        if ($this->type) {
            return $this;
        }

        $this->type = 'update';
        $this->table = $this->plainText($table);

        return $this;
    }

    /**
     * 只能调用一次
     * @param string $table
     * @return Builder
     */
    public function insert(string $table)
    {
        if ($this->type) {
            return $this;
        }

        $this->type = 'insert';
        $this->table = $this->plainText($table);

        return $this;
    }

    /**
     * 只能调用一次
     * @param string $table
     * @return Builder
     */
    public function replace(string $table)
    {
        if ($this->type) {
            return $this;
        }

        $this->type = 'replace';
        $this->table = $this->plainText($table);

        return $this;
    }

    /**
     * 只能调用一次
     * @param string $table
     * @return Builder
     */
    public function delete(string $table)
    {
        if ($this->type) {
            return $this;
        }

        $this->type = 'delete';
        $this->table = $this->plainText($table);

        return $this;
    }

    /**
     * @param string $table 如 'users'，'users as u'
     * @return Builder
     */
    public function from(string $table)
    {
        $this->table = $this->plainText($table);

        return $this;
    }

    public function forceIndex(string $index)
    {
        $this->forceIndex = 'force index(' . $this->plainText($index) . ')';
    }

    /**
     * 可多次调用，执行拼接
     * 其中：type 可能的值：inner、left、right，默认是 inner；condition 符合 where 格式
     * 参数：可以整个传如下格式的数组：['table' => 'tbname', 'type' => 'inner', 'on' => $conditions]
     * @param string|array $table
     * @param $condition
     * @param string $type
     * @return Builder
     * @throws \Exception
     */
    public function join($table, $condition = '', $type = 'inner')
    {
        if (is_array($args = func_get_arg(0))) {
            $table = $args['table'];
            $condition = $args['on'];
            $type = $args['type'];
        }

        if (!in_array($type, ['inner', 'left', 'right'])) {
            $type = 'inner';
        }

        list($sql, $params) = $this->condition($condition);

        $this->join .= " $type join " . $this->plainText($table) . " on $sql";
        $this->joinParams = array_merge($this->joinParams, $params);

        return $this;
    }

    /**
     * 可多次调用，执行 and 拼接
     * @param $conditions
     * @return $this
     * @throws \Exception
     */
    public function where($conditions)
    {
        list($sql, $params) = $this->condition($conditions);

        if (!$this->where) {
            $this->where = 'where 1=1';
        }

        $this->where .= " and $sql";
        $this->whereParams = array_merge($this->whereParams, $params);

        return $this;
    }

    /**
     * @param int $limit
     * @param int $offset 从 0 开始
     * @return Builder
     */
    public function limit(int $limit, int $offset = 0)
    {
        $limit = intval($limit);
        $offset = intval($offset);
        $this->limit = "limit $offset,$limit";

        return $this;
    }

    /**
     * 多次调用会覆盖前面的
     * @param string $fields 如 'uid,age'
     * @return Builder
     */
    public function groupBy(string $fields)
    {
        $this->groupBy = 'group by ' . $this->plainText($fields);

        return $this;
    }

    /**
     * 可多次调用，执行 and 拼接
     * @param $conditions
     * @return $this
     * @throws \Exception
     */
    public function having($conditions)
    {
        list($sql, $params) = $this->condition($conditions);

        if (!$this->having) {
            $this->having = 'having 1=1';
        }
        $this->having .= " and $sql";
        $this->havingParams = array_merge($this->havingParams, $params);

        return $this;
    }

    public function orderBy(string $orderStr)
    {
        $this->orderBy = 'order by ' . $this->plainText($orderStr);

        return $this;
    }

    /**
     * 和 update 配合使用，对应 SQL 的 set
     * 多次调用会覆盖前面的
     * @param array $data ['name' => '里斯', 'utime' => new Expression('unix_timestamp()')]
     * @return Builder
     */
    public function set(array $data)
    {
        if (!$data) {
            return $this;
        }

        $sql = [];
        $params = [];

        foreach ($data as $field => $value) {
            if ($this->isExpression($value)) {
                $sql[] = $this->plainText($field) . "=$value";
            } else {
                $sql[] = $this->plainText($field) . '=?';
                $params[] = $value;
            }
        }

        $this->set = 'set ' . implode(',', $sql);
        $this->setParams = $params;

        return $this;
    }

    /**
     * insert 和 replace 配合使用
     * 重复调用会覆盖前面的
     * 批量插入请传入二维数组
     * @param array $insertData ['name' => '张三', 'time' => new Expression('unix_timestamp()')]
     * @return $this
     */
    public function values(array $insertData)
    {
        // 一维转二维
        if (!is_array(reset($insertData))) {
            $insertData = [$insertData];
        }

        $columns = '(' . $this->plainText(implode(',', array_keys(reset($insertData)))) . ') values ';
        $values = [];
        $params = [];
        foreach ($insertData as $data) {
            $values[] = '(';
            foreach ($data as $field => $value) {
                if ($this->isExpression($value)) {
                    $values[] = $value;
                } else {
                    $values[] = "?";
                    $params[] = $value;
                }
            }
            $values[] = ')';
        }

        $this->values = $columns . str_replace(['(,', ',)'], ['(', ')'], implode(',', $values));
        $this->valuesParams = $params;

        return $this;
    }

    /**
     * 重置某些或整个 SQL 子句
     * 不提供 $type，则整个重置，否则重置指定的子句
     * 可 reset 的子句有：fields,where,join,limit,orderBy,groupBy,having,forceIndex
     * @param string $type
     * @return Builder
     */
    public function reset(string $type = '')
    {
        if ($type) {
            $nCasetype = strtolower(str_replace('_', '', $type));

            foreach (array_keys(get_object_vars($this)) as $attr) {
                $nCaseAttr = strtolower($attr);
                if ($nCaseAttr == $nCasetype || $nCaseAttr == "{$nCasetype}params") {
                    $this->$attr = is_array($this->$attr) ? [] : '';
                }
            }
        } else {
            $this->fields = $this->join = $this->limit = $this->orderBy = $this->groupBy = $this->set = '';
            $this->forceIndex = $this->table = $this->type = $this->values = $this->where = $this->having = '';
            $this->whereParams = $this->joinParams = $this->havingParams = $this->valuesParams = $this->setParams = [];
        }

        return $this;
    }

    /**
     * 最后一次编译出的原始 SQL
     * @return array
     */
    public function rawSql(): array
    {
        return $this->rawSqlInfo;
    }

    /**
     * 预处理 SQL
     * @param string $sql 格式：select * from t_name where uid=:uid
     * @param array $params 格式：['uid' => $uid]
     * @return array 输出格式：sql: select * from t_name where uid=?，params: [$uid]
     * @throws \Exception
     */
    private function prepareSQL(string $sql, array $params)
    {
        if (!$params) {
            return [$sql, []];
        }

        preg_match_all('/:([^\s;]+)/', $sql, $matches);

        if (!($matches = $matches[1])) {
            return [$sql, []];
        }

        if (count($matches) !== count($params)) {
            throw new \Exception("SQL 占位数与参数个数不符。SQL:$sql,参数：" . print_r($params, true));
        }

        $p = [];
        foreach ($matches as $flag) {
            if (!array_key_exists($flag, $params)) {
                throw new \Exception("SQL 占位符与参数不符。SQL:$sql,参数：" . print_r($params, true));
            }

            $value = $params[$flag];

            if ($this->isExpression($value)) {
                $sql = preg_replace("/:$flag(?=\s|$)/", $value, $sql);
            } else {
                $p[] = $value;
            }
        }

        $sql = preg_replace('/:[-a-zA-Z0-9_]+/', '?', $sql);

        return [$sql, $p];
    }

    /**
     * 编译
     * 目前仅支持 select,update,insert,replace,delete
     * @param bool $reset 编译后是否重置构造器
     * @return array [$preSql, $params]
     */
    private function compile(bool $reset = true)
    {
        if (!$this->type) {
            return ['', []];
        }

        $method = 'compile' . ucfirst($this->type);
        if (method_exists($this, $method)) {
            $this->rawSqlInfo = $this->$method();
            if ($reset) {
                $this->reset();
            }
            return $this->rawSqlInfo;
        }

        return ['', []];
    }

    private function compileSelect()
    {
        $sql = "select $this->fields ";
        $params = [];

        if ($this->table) {
            $sql .= implode(
                ' ',
                [
                    'from',
                    $this->table,
                    $this->join,
                    $this->forceIndex,
                    $this->where,
                    $this->groupBy,
                    $this->having,
                    $this->orderBy,
                    $this->limit
                ]
            );
            $params = array_merge($this->joinParams, $this->whereParams, $this->havingParams);
        }

        return [$this->trimSpace($sql), $params];
    }

    private function compileUpdate()
    {
        return [
            $this->trimSpace("update $this->table $this->join $this->set $this->where"),
            array_merge($this->joinParams, $this->setParams, $this->whereParams)
        ];
    }

    private function compileInsert($type = 'insert')
    {
        $type = in_array($type, ['insert', 'replace']) ? $type : 'insert';
        return [
            $this->trimSpace("$type into $this->table $this->values"),
            $this->valuesParams
        ];
    }

    private function compileReplace()
    {
        return $this->compileInsert('replace');
    }

    private function compileDelete()
    {
        return [
            $this->trimSpace("delete from $this->table $this->where $this->orderBy $this->limit"),
            $this->whereParams
        ];
    }

    /**
     * 条件（where、on、having 等）
     * 为了记忆和使用方便，目前只提供了最基本的一些形式，复杂的条件请使用原生写法
     * $conditions 数组格式：
     * // 基本的 and 查询
     * [
     *      'uid' => 232,
     *      'name' => '里斯',
     *      'b.age' => 34,
     *      'level_id' => [1,2,3], // in
     *      'count' => new Expression('count + 1'),
     * ]
     *
     * [
     *      "(uid=:uid1 or uid=:uid2) and  count=:count", // 原生预处理 SQL
     *      ['uid1' => 12, 'uid2' => 13, 'count' => new Expression('count+1')]
     * ]
     * @param string|array $conditions
     * @return array [$preSql, $params]，$preSql: 用 ? 占位的预处理 SQL
     * @throws \Exception
     */
    private function condition($conditions)
    {
        if (is_string($conditions)) {
            return [$conditions, []];
        }

        if (!$conditions || !is_array($conditions)) {
            return [];
        }

        if (is_int(key($conditions)) && count($conditions) <= 2) {
            if (count($conditions) == 1) {
                $conditions[1] = [];
            }

            return $this->prepareSQL($conditions[0], $conditions[1]);
        }

        $where = '1=1';
        $params = [];
        foreach ($conditions as $key => $condition) {
            $key = $this->plainText($key);
            if (is_array($condition)) {
                // in 查询
                $where .= " and $key in(" . implode(',', array_fill(0, count($condition), '?')) . ')';
                $params = array_merge($params, $condition);
            } else {
                // = 查询
                if ($this->isExpression($condition)) {
                    $where .= " and $key = $condition";
                } else {
                    $where .= " and $key = ?";
                    $params[] = $condition;
                }
            }
        }

        return [str_replace('1=1 and ', '', $where), $params];
    }

    /**
     * 去掉文本中的非常规字符，使得文本中只包含 字母、数字、下划线、中划线、点、逗号、空格、星号
     * @param $text
     * @return string
     */
    private function plainText(string $text): string
    {
        return preg_replace('/[^-a-zA-Z0-9_.,\s*]+/', '', $text);
    }

    private function isExpression($value)
    {
        return $value instanceof Expression;
    }

    private function trimSpace(string $str)
    {
        return preg_replace('/\s{2,}/', ' ', trim($str));
    }
}
