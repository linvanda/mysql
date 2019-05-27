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
    go(function () use ($context) {
        $sql = $context['sql'] ?? [];
        $sql[] = "sql 1";
        $sql[] = "sql 2";
        $context['sql'] = $sql;

        var_export($context['sql']);
    });
});