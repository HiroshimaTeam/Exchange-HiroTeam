<?php

namespace exchange\Task;

use pocketmine\scheduler\Task;
use exchange\Main;

class exchangeTask extends Task{

    private $plugin;

    public function __construct(Main $plugin)
    {
        $this->plugin = $plugin;
    }


    public function onRun($tick){
        $this->plugin->TaskDo();
    }
}
