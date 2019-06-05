<?php

require '../vendor/autoload.php';

use Swoole\Coroutine as Co;
use Devar\MySQL\Test\TimeTick;

error_reporting(E_ERROR);

function randomWord($len = 10)
{
    $words = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    $s = '';
    for ($i = 0; $i < $len; $i++) {
        $s .= $words[mt_rand(0, strlen($words) - 1)];
    }

    return $s;
}

function randomNumber($len = 10)
{
    $words = '0123456789';
    $s = '';
    for ($i = 0; $i < $len; $i++) {
        $s .= $words[mt_rand(0, strlen($words) - 1)];
    }

    return $s;
}

function create_table(\Devar\MySQL\Query $query)
{
    $table_user_sql = "CREATE TABLE `users_test_abcdef`(`uid` int(11) NOT NULL AUTO_INCREMENT,`name` varchar(20) DEFAULT NULL,`nickname` varchar(30) NOT NULL DEFAULT '' COMMENT '昵称',`phone` varchar(20) DEFAULT NULL COMMENT '手机号，唯一',`email` varchar(50) DEFAULT NULL,`gender` tinyint(11) DEFAULT NULL COMMENT '性别，1男，0女',`birthday` date DEFAULT NULL,`id_number` varchar(30) NOT NULL DEFAULT '' COMMENT '证件号码',`id_number_type` tinyint(1) NOT NULL DEFAULT '0' COMMENT '证件类型：1身份证，2军官证，3港澳通行证，4护照',`headurl` varchar(400) DEFAULT NULL,`tinyheadurl` varchar(400) DEFAULT NULL,`invite_code` varchar(12) DEFAULT NULL,`update_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '修改时间',`channel` varchar(20) DEFAULT NULL,`birthday_change` tinyint(4) DEFAULT '0',`del_time` int(11) NOT NULL DEFAULT '0' COMMENT '删除时间',`regtime` datetime DEFAULT NULL,PRIMARY KEY (`uid`),UNIQUE KEY `IDX_PHONE` (`phone`)) ENGINE=InnoDB AUTO_INCREMENT=102002 DEFAULT CHARSET=utf8 COMMENT='用户主表'";

    $table_user_partner_sql = <<<ET
    CREATE TABLE `user_partner_ids_test_abcdef` (
      `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
      `uid` int(11) NOT NULL COMMENT '用户uid',
      `partner_id` varchar(40) NOT NULL COMMENT '第三方id（如openid、支付宝user_id等）',
      `partner_type` tinyint(4) NOT NULL DEFAULT '1' COMMENT '第三方类型（微信公众号、支付宝等），见配置表',
      `create_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '创建时间',
      PRIMARY KEY (`id`),
      KEY `partner_id` (`partner_id`,`partner_type`,`uid`)
    ) ENGINE=InnoDB AUTO_INCREMENT=20001 DEFAULT CHARSET=utf8 COMMENT='用户第三方标识映射表（如喂车大号openid、第三方uid等，用来对接第三方的用户系统）'
ET;

    $table_merchant_users_sql = <<<ET
    CREATE TABLE `merchant_users_test_abcdef` (
      `merchant_type` tinyint(4) NOT NULL COMMENT '商户类型：0平台，1油站，2集团，3区域',
      `merchant_id` int(11) NOT NULL COMMENT '商户id',
      `uid` int(11) NOT NULL COMMENT '用户uid',
      `channel` varchar(20) NOT NULL DEFAULT '' COMMENT '关联渠道',
      `create_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '关联时间'
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='商户用户列表'
ET;

    $query->execute("drop table IF EXISTS users_test_abcdef");
    $query->execute("drop table IF EXISTS merchant_users_test_abcdef");
    $query->execute("drop table IF EXISTS user_partner_ids_test_abcdef");
    $query->execute($table_user_sql);
    $query->execute($table_user_partner_sql);
    $query->execute($table_merchant_users_sql);
}

function create_query()
{
    // 请根据实际情况配置数据库信息，账号需要有 create table、drop table 以及增删改查的权限
    $config = new \Devar\MySQL\Connector\DBConfig('192.168.85.67', 'yanpinpin', 'yanpinpin@123', 'user_center');
    $connBuilder = new \Devar\MySQL\Connector\CoConnectorBuilder($config);
    $pool = \Devar\MySQL\Pool\CoPool::instance($connBuilder, 20);
    $trans = new \Devar\MySQL\Transaction\CoTransaction($pool);
    $query = new \Devar\MySQL\Query($trans);

    return $query;
}

/**
 * 多协程批量插入
 */
function multi_insert($use_trans = true)
{
    // 每个协程创建单独的 Query，这些 Query 共用 Pool
    $query = create_query();
    $cuid = Co::getuid();
    $id = 1;
    $now = date('Y-m-d H:i:s');
    for ($i = 0; $i < 2; $i++) {
        $users = [];
        $userPartnerIds = [];
        $merchantUsers = [];
        for ($j = 0; $j < 3; $j++) {
            $uid = $id . $cuid;
            $users[] = [
                'uid' => $uid,
                'name' => randomWord(),
                'nickname' => randomWord(7),
                'phone' => '189-' . $i . '-' . $j . '-' . $cuid,
                'gender' => mt_rand(0, 1),
            ];

            $userPartnerIds[] = [
                'uid' => $uid,
                'partner_id' => randomWord(20),
                'partner_type' => mt_rand(1, 4),
                'create_time' => $now
            ];

            $merchantUsers[] = [
                'merchant_type' => mt_rand(1, 2),
                'merchant_id' => mt_rand(500, 1000),
                'uid' => $uid,
                'channel' => randomWord(2),
                'create_time' => $now,
            ];

            $id++;
        }

        try {
            if ($use_trans) {
                $query->begin();
            }
            $r1 = $query->insert('users_test_abcdef')->values($users)->execute();
            $r2 = $query->insert('user_partner_ids_test_abcdef')->values($userPartnerIds)->execute();
            $r3 = $query->insert('merchant_users_test_abcdef')->values($merchantUsers)->execute();

            if ($use_trans) {
                if ($r1 && $r2 && $r3) {
                    $query->commit();
                } else {
                    $query->rollback();
                }
            }
        } catch (\Exception $exception) {
            // 打印出连接错误，继续后面的协程
            echo "exception:" . $exception->getMessage() . "===".$exception->getCode() ."\n";
            $query->rollback();
        }
    }
}

function request_insert($send_num = 2000, $use_trans = true)
{
    // 模拟高并发请求
    for ($i = 0; $i < $send_num; $i++) {
        // 模拟请求时延
        co::sleep(10/1000);
        go(function () use ($use_trans) {
            multi_insert($use_trans);
        });
    }
}

function multi_select()
{
    // 每个协程创建单独的 Query，这些 Query 共用 Pool
    $query = create_query();

    try {
        $result = $query->select(['users.name'])
            ->fields(['users.uid'])
            ->from('users_test_abcdef users')
            ->join('user_partner_ids_test_abcdef part', ["users.uid=part.uid"], 'left')
            ->join('merchant_users_test_abcdef merchant', 'merchant.uid=users.uid')
            ->limit(500, 3)
            ->execute();
    } catch (\Exception $exception) {
        echo "exception:" . $exception->getMessage() . "==".$exception->getCode()."\n";
    }

    if ($result === false) {
        echo $query->lastError();
        echo "query error\n";
    } else {
//        echo "cnt:".count($result)."\n";
    }
}

function request_select($send_num = 2000)
{
    // 模拟高并发请求
    for ($i = 0; $i < $send_num; $i++) {
        // 模拟请求时延
        co::sleep(5/1000);
        go(function () {
            multi_select();
        });
    }
}


// 测试脚本
go(function () {
    // 重建表
//    create_table(create_query());

    // 并发请求查询
    TimeTick::tick('start');
    request_select( 40000);
    TimeTick::memory();
});