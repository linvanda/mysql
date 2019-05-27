<?php

require '../vendor/autoload.php';
require 'TestConnectorBuilder.php';

//go(function () {
//    $connBuilder = new TestConnectorBuilder();
//    $pool = \Linvanda\MySQL\Pool\CoPool::instance($connBuilder);
//    $trans = new \Linvanda\MySQL\Transaction($pool);
//    $query = new \Linvanda\MySQL\Query($trans);
//
//    $result = $query->select('uid,phone')->from('wei_users')->where("uid=93")->one();
//    var_export($result);
//});

go(function () {
    $context = new \Linvanda\MySQL\Transaction\TContext();

    for ($i = 0; $i < 4; $i++) {
        go(function () use ($context, $i) {
            $context['name'] = 'name - ' . $i;
            if ($i == 3) {
                co::sleep(2);
            }
        });
    }

    co::sleep(1);
    var_export($context);
});