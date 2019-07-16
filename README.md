协程版 MySQL 查询器
----

#### 使用
- 入口：`Dev\MySQL\Query`
- 请在实际项目中使用工厂或者 IoC 容器创建/注入 Query 对象，如：
```php
class MySQLFactory
{
    public static function build(string $dbAlias): Query
    {
				...
        $mySQLBuilder = CoConnectorBuilder::instance($writeConfObj, $readConfObjs);
        $pool = CoPool::instance($mySQLBuilder, $dbConf['pool']['size'] ?? 30);
        $transaction = new CoTransaction($pool);

        return new Query($transaction);
    }
  ...
```

#### 例子

查询：

```php
$query->select(['uid', 'name'])
  ->from('wei_users u')
  ->join('wei_auth_users au', "u.uid=au.uid")
  ->where(['uid' => $uid])
  ->groupBy("u.phone")
  ->having("count(u.phone)>1")
  ->orderBy("u.uid desc")
  ->limit(10, 0)
  ->list();
```

插入：

```php
$query->insert('users')
  ->values(
  [
    [
      'name' => 'linvanda',
      'phone' => '18687664562',
      'nickname' => '林子',
    ],
    [
      'name' => 'xiake',
      'phone' => '18989876543',
      'nickname' => '侠客',
    ],
  ]
)->execute();

// 延迟插入
$query->insert('users')
  ->delayed()
  ->values(
  [
    'name' => 'linvanda',
    'phone' => '18687664562',
    'nickname' => '林子',
  ]
)->execute();
```

更新：

```php
$query->update('wei_users u')
  ->join('wei_auth_users au', "u.uid=au.uid", 'left')
  ->set(['u.name' => '粽子'])
  ->where("u.uid=:uid", ['uid' => 123])
  ->execute();
```

删除：

```php
$query->delete('wei_users')
  ->where("uid=:uid", ['uid' => 123])
  ->execute();
```



#### API

分为执行型、和构造型 API。

**执行型：**

- `list(): array` 查询列表（不带分页）
- `page(): array` 分页查询，返回结构：['total' => 343, 'data' => [...]]
- `one(): array` 返回一行记录
- `column()` 返回查到的第一行某个字段的值
- `execute(string \$preSql = '', array $params = [])` 不带参数执行命令型查询（update、delete等）。也可以直接传参数执行原生 SQL
- `lastInsertId(): int` 最后插入的 id
- `lastError(): string` 最后一次执行错误信息
- `lastErrorNo(): int` 最后一次执行错误码
- `affectedRows(): int` 影响行数

**构造型：**

所有构造型方法返回的都是 $this，因而可以链式调用。

- `select($fields = null)`：构造 select 子句。可以传入字段数组或者字符串，如 ['name', 'age']，"name,age"。
- `update(string $table)`：构造 update 子句。
- `insert(string $table)`：构造 insert 子句。
- `replace(string $table)`：构造 replace 子句。
- `delete(string $table)`：构造 delete 子句。
- `from(string $table)`：构造 from 子句。
- `join($table, $condition = '', $type = 'inner')`：构造 join 子句。\$condition 见后面说明，​\$type 可以是 innser、left、join、outer。
- `where($conditions, ...$args)`：构造 where 子句。如 where("uid=:uid", ['uid' => 123])。$conditions 见后面说明。
- `limit(int $limit, int $offset = 0)`：构造 limit 子句。
- `groupBy(string $fields)`：构造 group by 子句。
- `having($conditions)`：构造 having 子句。
- `orderBy(string $orderStr)`：构造 order by 子句。如 orderBy("uid desc,phone asc")。
- `set(array $data)`：构造 update set 子句。如 set(['name'=>'李四'])。其中值可以是 `Dev\MySQL\Expression`（用来设置表达式，普通输入会进行防注入处理）。
- `values(array $insertData)`：构造 insert 的 value 子句。可以是一维或者二维数组（批量插入）。可以使用 Expression 表达式。
- `reset(string $type = '')`：重置指定的（$type）或者全部子句，这将清空前面构造的内容（一般很少用到）。
- `rawSql(): array`：最后一次编译出的 SQL 信息，格式：[preSQL, params]。

**$conditions 格式：**

所有涉及到条件的地方（where、join、having）都可使用此格式：

- 基本的 and 构造：

  ```php
  [
      'uid' => 232,
      'name' => '里斯',
      'b.age' => 34,
      'level_id' => [1,2,3], // in
      'count' => new Expression('count + 1'), // 表达式
  ]
  ```

- 使用原生预处理 SQL：

  ```php
  ["(uid=:uid1 or uid=:uid2) and  count=:count", ['uid1' => 12, 'uid2' => 13, 'count' => new Expression('count+1')]]
  ```

> 之所以没有提供特别复杂的构造结构，原因是复杂的结构增加学习和记忆负担，且 SQL 不够直观，后面维护者也很难搞得明白。对于简单的 and 构造（限于等于比较和 in 监测），可以使用第一种，稍复杂的请使用第二种方式，简单明了。
>
> 不要在字符串中直接拼接变量，这样会造成 SQL 注入漏洞，一定要采用上面两种的一种。
>
> 对于 where() 构造有一种优化的使用方式：where("uid=:uid or phone=:phone", ["uid"=>233,"phone"=> 18989877777])。

