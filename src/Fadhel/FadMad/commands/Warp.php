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

namespace Fadhel\FadMad\commands;

use Fadhel\FadMad\form\SimpleForm;
use Fadhel\FadMad\Main;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\item\Item;
use pocketmine\Player;
use pocketmine\utils\TextFormat;

class Warp extends Command
{
    /**
     * @var string[]
     */
    public $data = [];
    /**
     * @var Main
     */
    protected $plugin;

    /**
     * Warp constructor.
     * @param Main $plugin
     */
    public function __construct(Main $plugin)
    {
        $this->plugin = $plugin;
        $this->setPermission("fadmad.command.warp");
        parent::__construct("warp", "Warps list", "", ["warps"]);
    }

    /**
     * @param CommandSender $sender
     * @param string $commandLabel
     * @param array $args
     * @return bool|mixed
     */
    public function execute(CommandSender $sender, string $commandLabel, array $args)
    {
        if (!$this->testPermission($sender)) {
            return false;
        }
        if ($sender instanceof Player) {
            $this->warpsForm($sender);
            return true;
        } else {
            $sender->sendMessage(TextFormat::RED . "Run this command in-game.");
            return false;
        }
    }

    /**
     * @param Player $player
     */
    protected function warpsForm(Player $player): void
    {
        $form = new SimpleForm(function (Player $player, $data) {
            if ($data === null) {
                return;
            }
            if (isset($this->data[(int)$data])) {
                $world = $this->plugin->getServer()->getLevelByName($this->data[(int)$data]);
                if ($world !== null) {
                    $player->sendMessage(TextFormat::colorize(str_replace("{warp}", $this->data[(int)$data], $this->plugin->getConfig()->get("warp-message"))));
                    $player->teleport($world->getSpawnLocation());
                    $this->sendItems($player, strtolower($this->data[(int)$data]));
                } else {
                    $player->sendMessage(TextFormat::RED . "There was an error while attempting to teleport you, please contact the server admins.");
                }
            }
        });
        $data = $this->plugin->getConfig()->getAll();
        $form->setTitle($data["form-title"]);
        $form->setContent($data["form-content"]);
        $i = -1;
        foreach ($data["warps"] as $warp) {
            $i++;
            $this->data[(int)$i] = $warp;
            $form->addButton($warp);
        }
        $form->addButton(TextFormat::colorize($data["form-exit"]));
        $form->sendToPlayer($player);
    }

    /**
     * @param Player $player
     * @param string $warp
     */
    protected function sendItems(Player $player, string $warp): void
    {
        if ($this->plugin->getConfig()->getNested("kits." . $warp)) {
            foreach ($this->plugin->getConfig()->getNested("kits." . $warp) as $item) {
                $data = explode(":", $item);
                $item = Item::get((int)$data[0], (int)$data[1], (int)$data[2]);
                if (isset($data[3])) {
                    $item->setCustomName((string)$data[3]);
                }
                $player->getInventory()->addItem(Item::get((int)$data[0], (int)$data[1], (int)$data[2]));
            }
        }
    }
}