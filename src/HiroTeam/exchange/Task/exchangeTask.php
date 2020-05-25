<?php

namespace HiroTeam\exchange\Task;

use pocketmine\scheduler\Task;
use HiroTeam\exchange\ExChange;

class exchangeTask extends Task{

    private $plugin;

    public function __construct(ExChange $plugin){
        $this->plugin = $plugin;
    }


    public function onRun($tick){
        $this->plugin->TaskDo();
    }
}
