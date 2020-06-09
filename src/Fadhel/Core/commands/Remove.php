<?php

/**
 * Copyright 2020 Fadhel
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

use Fadhel\Core\listeners\Ranks;
use Fadhel\Core\Main;
use Fadhel\Core\utils\form\CustomForm;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\Player;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat as C;
use pocketmine\utils\TextFormat;

class Remove extends Command
{
    private $plugin;

    public function __construct(Main $plugin)
    {
        $this->plugin = $plugin;
        parent::__construct("remove", "Coins command!");
        $this->setPermission("server.admin");
    }

    public function sendStaffAlert($msg)
    {
        foreach ($this->plugin->getServer()->getOnlinePlayers() as $staff) {
            if ($staff->hasPermission("server.staff")) {
                $staff->sendMessage($msg);
            }
        }
    }

    public function getCoins($player): int
    {
        $players = strtolower($player);
        $deaths = $this->plugin->coins->query("SELECT coins FROM coins WHERE player = '$players';");
        $array = $deaths->fetchArray(SQLITE3_ASSOC);
        return (int)$array["coins"];
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): bool
    {
        if (!$this->testPermission($sender)) {
            return true;
        }
        if (count($args) === 1) {
            $sender->sendMessage(C::RED . "Usage /remove <player> <amount>");
        } elseif (count($args) === 2) {
            $player = strtolower($args[0]);
            if(!$this->plugin->coinExists($player)){
                $sender->sendMessage(TextFormat::RED . "Player not found on the records");
                return false;
            }else{
                $stmt = $this->plugin->coins->prepare("INSERT OR REPLACE INTO coins (player, coins) VALUES (:player, :coins)");
                $stmt->bindValue(":player", $args[0]);
                $stmt->bindValue(":coins", $this->getCoins($player) - $args[1]);
                $stmt->execute();
                $this->sendStaffAlert(C::YELLOW . $args[0] . C::GOLD . " has reduced " . C::GRAY . $args[1]);
            }
        }
        return false;
    }
}
