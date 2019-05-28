<?php

namespace Linvanda\MySQL\Test;

class TimeTick
{
    static $num = 0;
    private $ticks = [];
    private $flag;

    public function tick($flag = '', $echo = true)
    {
        $time = microtime(true);
        $this->ticks[$flag] = $time;
        $this->flag = $flag;
        if ($echo) {
            echo "--tick--$flag: $time\n";
        }
    }

    public function time($preFlag = '')
    {
        $preFlag = $preFlag ?: $this->flag;

        if (!$this->ticks[$preFlag]) {
            echo "none time\n";
            return;
        }

        echo "--tick time to $preFlag:" . (microtime(true) - $this->ticks[$preFlag]) . "\n";
    }

    public static function count()
    {
        self::$num++;
        echo "-tick num:".self::$num."\n";
    }

    public function reset()
    {
        $this->ticks = [];
        $this->flag = null;
    }
}
