<?php

/**
 * Copyright 2019 Fadhel
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

declare(strict_types=1);

namespace Fadhel\Core\utils\tasks\Others;

use Fadhel\Core\listeners\Ranks;
use pocketmine\scheduler\Task;
use Fadhel\Core\utils\scoreboard\{Action, DisplaySlot, Scoreboard, Sort};
use pocketmine\Player;
use Fadhel\Core\Main;
use pocketmine\utils\TextFormat as C;

class Status extends Task
{
    private $plugin;
    private $player;
    private $score;

    public function __construct(Main $plugin, Player $player)
    {
        $this->plugin = $plugin;
        $this->score = new Scoreboard($this->plugin->getConfig()->get("scoreboard-title"), Action::CREATE);
        $this->score->create(DisplaySlot::SIDEBAR, Sort::ASCENDING);
        $this->player = $player;
    }

    public function onRun($tick)
    {
        if ($this->player->isOnline()) {
            $this->score->setLine($this->player, 14, " ");
            $this->score->setLine($this->player, 13, "§7Kills:§f " . $this->plugin->getKills($this->player));
            $this->score->setLine($this->player, 12, "§7Deaths:§f " . $this->plugin->getDeaths($this->player));
            $this->score->setLine($this->player, 11, "§7Coins:§f " . $this->plugin->getCoins($this->player));
            $this->score->setLine($this->player, 10, "§7Streaks:§f " . $this->plugin->getStreak($this->player));
            $this->score->setLine($this->player, 9, "§7Ping:§f " . $this->player->getPing());
            $this->score->setLine($this->player, 8, "  ");
            $this->score->setLine($this->player, 7, $this->plugin->getConfig()->get("server-website"));
            $this->plugin->LevelUP($this->player);
            $this->score->addDisplay($this->player);
            $players = $this->player;
            $ranks = new Ranks($this->plugin);
            $rank = $this->plugin->getRank($players);
            $format = $ranks->getFormat($rank);
            $tag = $this->plugin->getTag($players);
            $type = $ranks->getType($tag);
            if ($tag !== "none") {
                $players->setNameTag(C::GRAY . "[" . $this->plugin->getLevelFormat($players) . $this->plugin->getLevel($players) . C::GRAY . "] " . $type . $format . $players->getName());
            } else {
                $players->setNameTag(C::GRAY . "[" . $this->plugin->getLevelFormat($players) . $this->plugin->getLevel($players) . C::GRAY . "] " . $format . $players->getName());
            }
        if ($players->getLevel()->getName() !== $this->plugin->getServer()->getDefaultLevel()->getFolderName()) {
                $this->player->setScoreTag("§fHealth: §c" . $this->player->getHealth() . "\n§fPing: §6" . $this->player->getPing() . "\n§fDevice: §e" . $this->plugin->getDevice($this->plugin->os[$this->player->getName()]));
            } else {
                $players->setScoreTag("");
            }
        } else {
            $this->score->removeDisplay($this->player);
            $this->plugin->getScheduler()->cancelTask($this->getTaskId());
        }
    }
}