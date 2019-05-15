<?php

require '../vendor/autoload.php';
require 'TestConnectorBuilder.php';

go(function () {
    $connBuilder = new TestConnectorBuilder();
    $pool = \Linvanda\Fundation\MySQL\Pool\CoPool::instance($connBuilder);
    $trans = new \Linvanda\Fundation\MySQL\Transaction($pool);
    $query = new \Linvanda\Fundation\MySQL\Query($trans);

    $result = $query->select('uid,phone')->from('wei_users')->where("uid=93")->one();
    var_export($result);
});