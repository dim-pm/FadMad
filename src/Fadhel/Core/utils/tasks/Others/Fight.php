<?php

declare(strict_types=1);

namespace Fadhel\Core\utils\tasks\Others;

use Fadhel\Core\Main;
use pocketmine\Player;
use pocketmine\scheduler\Task;

class Fight extends Task
{
    protected $plugin;
    protected $player;
    private $secs = 10;

    public function __construct(Main $plugin, Player $player)
    {
        $this->plugin = $plugin;
        $this->player = $player;
    }

    public function onRun(int $currentTick)
    {
        if ($this->plugin->fighting[$this->player->getName()] !== "none" && $this->player->isOnline() && $this->secs !== 0) {
            $this->player->sendTip("§cYou're in combat for §f" . $this->secs . "§cs!");
            if ($this->secs === 1) {
                $this->plugin->getScheduler()->cancelTask($this->getTaskId());
                $this->plugin->fighting[$this->player->getName()] = "none";
            }
        } else {
            $this->plugin->getScheduler()->cancelTask($this->getTaskId());
            $this->plugin->fighting[$this->player->getName()] = "none";
        }
        $this->secs--;
    }
}