<?php

namespace Linvanda\MySQL\Test;

use Swoole\Coroutine as co;

class TimeTick
{
    static $timeTicks = [];
    static $memoryTicks = [];
    static $coFlag = [];
    static $num = [];

    public static function tick($flag = '', $echo = true)
    {
        $cuid = co::getuid();
        if (!self::$timeTicks[$cuid]) {
            self::$timeTicks[$cuid] = [];
            self::$memoryTicks[$cuid] = [];
        }

        $time = microtime(true);
        $memory = memory_get_usage();
        self::$timeTicks[$cuid][$flag] = $time;
        self::$memoryTicks[$cuid][$flag] = $memory;
        self::$coFlag[$cuid] = $flag;

        if ($echo) {
            echo "--- co:$cuid--tick--$flag(time:$time, memory:$memory)\n";
        }
    }

    public static function time($preFlag = '', $minSeconds = 0)
    {
        $cuid = co::getuid();
        $preFlag = $preFlag ?: self::$coFlag[$cuid];

        if (!self::$timeTicks[$cuid][$preFlag]) {
            echo "none time tick\n";
            return;
        }

        $sub = microtime(true) - self::$timeTicks[$cuid][$preFlag];
        if ($sub >= $minSeconds) {
            echo "--- co:$cuid--time--$preFlag:" . $sub . "\n";
        }
    }

    public static function count($flag = 'default')
    {
        $cuid = co::getuid();
        if (!isset(self::$num[$cuid][$flag])) {
            self::$num[$cuid][$flag] = 0;
        }
        self::$num[$cuid][$flag]++;

        echo "--- co:$cuid--num:$flag:".self::$num[$cuid][$flag]."\n";
    }

    public static function memory($preFlag = '', $minMemory = 0)
    {
        $cuid = co::getuid();
        $preFlag = $preFlag ?: self::$coFlag[$cuid];

        if (!self::$memoryTicks[$cuid][$preFlag]) {
            echo "none memory tick\n";
            return;
        }

        $sub = memory_get_usage() - self::$memoryTicks[$cuid][$preFlag];
        if ($sub >= $minMemory) {
            echo "--- co:$cuid--memory--$preFlag:" . ($sub/1024) . "K\n";
        }
    }

    public function reset()
    {
        self::$timeTicks = self::$coFlag = self::$num = self::$memoryTicks = [];

    }
}
