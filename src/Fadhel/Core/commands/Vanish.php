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
use pocketmine\Player;
use pocketmine\utils\TextFormat as C;

class Vanish extends Command
{
    private $plugin;

    public function __construct(Main $plugin)
    {
        parent::__construct("vanish", "Vanish's the players!");
        $this->plugin = $plugin;
        $this->setPermission("server.staff");
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): bool
    {
        if(!$this->testPermission($sender)){
            return true;
        }
        if (!$sender instanceof Player) {
            $sender->sendMessage(C::RED . "Run this command in-game!");
            return false;
        }
        if (!$sender->isInvisible()) {
            $sender->sendMessage(C::GREEN . "You have been vanished!");
            $sender->setInvisible(true);
            $sender->setGamemode(3);
        } elseif ($sender->isInvisible()) {
            $sender->sendMessage(C::RED . "You have been un-vanished!");
            $sender->setInvisible(false);
            $sender->setGamemode(2);
        }
        return true;
    }
}