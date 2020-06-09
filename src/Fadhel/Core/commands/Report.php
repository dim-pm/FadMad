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

namespace Fadhel\Core\commands;

use Fadhel\Core\Main;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat as C;

class Report extends Command
{
    private $plugin;
    public function __construct(Main $plugin)
    {
        parent::__construct("report", "Report a player");
        $this->plugin = $plugin;
    }
    public function sendStaffAlert($msg){
        foreach ($this->plugin->getServer()->getOnlinePlayers() as $staff){
            if($staff->hasPermission("server.staff")){
                $staff->sendMessage($msg);
            }
        }
    }
    public function execute(CommandSender $sender, string $commandLabel, array $args): bool
    {
        if (count($args) === 0 || count($args) === 1) {
            $sender->sendMessage(C::RED . "Usage: /report <player> <reason>");
            return false;
        } else {
            $player = $this->plugin->getServer()->getPlayer($args[0]);
            unset($args[0]);
            $reason = implode(" ", $args);
            if ($player !== null) {
                $sender->sendMessage(C::AQUA . "You have reported " . $player->getName() . " for " . $reason);
                $this->sendStaffAlert(C::YELLOW . $sender->getName() . " reported " . $player->getName() . " for " . $reason);
                return true;
            } else {
                $sender->sendMessage(C::RED . "Player not found!");
            }
        }
        return false;
    }
}