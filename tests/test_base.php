<?php

require '../vendor/autoload.php';

use Swoole\Coroutine as Co;
use Linvanda\MySQL\Test\TimeTick;

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

/**
 * 多协程批量插入
 * @param \Linvanda\MySQL\Query $query
 * @param \Linvanda\MySQL\Pool\CoPool $pool
 */
function multi_insert(\Linvanda\MySQL\Query $query, \Linvanda\MySQL\Pool\CoPool $pool, $reset = false)
{
    go(function () use ($query, $pool, $reset) {
        $cuid = Co::getuid();
        if ($reset) {
            $query->execute("truncate table users");
            $query->execute("truncate table user_partner_ids");
            $query->execute("truncate table merchant_users");
            $query->execute("truncate table user_car_numbers");
        }

        $id = 1;
        $now = date('Y-m-d H:i:s');
        for ($i = 0; $i < 10; $i++) {
            $users = [];
            $userPartnerIds = [];
            $merchantUsers = [];
            $userCarNumbers = [];
            for ($j = 0; $j < 10; $j++) {
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

                $userCarNumbers[] = [
                    'uid' => $uid,
                    'car_number' => '粤B' . randomNumber(5),
                    'create_time' => $now,
                ];

                $id++;
            }

            $query->insert('users')->values($users)->execute();
            $query->insert('user_partner_ids')->values($userPartnerIds)->execute();
            $query->insert('merchant_users')->values($merchantUsers)->execute();
            $query->insert('user_car_numbers')->values($userCarNumbers)->execute();

//            $num = $query->affectedRows();
//            echo "sql:".print_r($query->rawSql(), true)."\n";
//            echo "affected rows:$num\n";
//            echo "error:".$query->lastError()."\n";
        }

//        $pool->close();
    });
}

go(function () {
    $config = new \Linvanda\MySQL\Connector\DBConfig('192.168.85.135', 'root', 'weicheche', 'user_center');
    $connBuilder = new \Linvanda\MySQL\Connector\CoConnectorBuilder($config);
    $pool = \Linvanda\MySQL\Pool\CoPool::instance($connBuilder);
    $trans = new \Linvanda\MySQL\Transaction\CoTransaction($pool);
    $query = new \Linvanda\MySQL\Query($trans);

    for ($i = 0; $i < 1000; $i++) {
        multi_insert($query, $pool, false);
    }
});