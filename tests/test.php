<?php

require '../vendor/autoload.php';
require 'TestConnectorBuilder.php';

go(function () {
    $config = new \Linvanda\MySQL\Connector\DBConfig('192.168.85.135', 'root', 'weicheche', 'weicheche');
    $connBuilder = new \Linvanda\MySQL\Connector\CoConnectorBuilder($config);
    $pool = \Linvanda\MySQL\Pool\CoPool::instance($connBuilder);
    $trans = new \Linvanda\MySQL\Transaction\CoTransaction($pool);
    $query = new \Linvanda\MySQL\Query($trans);

    $result = $query->select('uid,phone')->from('wei_users')->where("uid=93")->execute();
    var_export($result);
});

//go(function () {
//    $context = new \Linvanda\MySQL\Transaction\TContext();
//
//    for ($i = 0; $i < 3; $i++) {
//        go(function () use ($context, $i) {
//            $context['name'] = 'name - ' . $i;
//            $context['age'] = 'age - ' . $i;
//            if ($i == 3) {
//                co::sleep(2);
//            }
//        });
//    }
//
//    co::sleep(1);
//    var_export($context);
//});