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

namespace Fadhel\Core\listeners;

use pocketmine\item\Item;
use pocketmine\event\Listener;
use Fadhel\Core\Main;
use pocketmine\Player;
use pocketmine\utils\TextFormat as C;
use pocketmine\entity\projectile\EnderPearl;
use pocketmine\event\entity\ProjectileLaunchEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerMoveEvent;

class EnderPearls implements Listener
{
    private $plugin;
    private $pearl;

    public function __construct(Main $plugin)
    {
        $this->plugin = $plugin;
    }

    public function replaceVars($str, array $vars)
    {
        foreach ($vars as $key => $value) {
            $str = str_replace("{" . $key . "}", $value, $str);
        }
        return $str;
    }

    public function enderPearl(ProjectileLaunchEvent $event)
    {
        $pearl = $event->getEntity();
        if ($pearl instanceof EnderPearl) {
            $player = $pearl->getOwningEntity();
            $cooldown = 10;
            if ($player instanceof Player) {
                if (!isset($this->pearl[strtolower($player->getName())])) {
                    $this->pearl[strtolower($player->getName())] = time();
                } else {
                    if (time() - $this->pearl[strtolower($player->getName())] < $cooldown) {
                        $event->setCancelled(true);
                        $time = time() - $this->pearl[strtolower($player->getName())];
                        $player->sendMessage(C::colorize($this->replaceVars("§cPlease wait §7{TIME}§cs to use EnderPearl!", array("TIME" => $cooldown - $time))));
                        $player->getInventory()->addItem(Item::get(368));
                    } else {
                        $this->pearl[strtolower($player->getName())] = time();
                    }
                }
                if ($event->isCancelled()) {
                    $this->needToBeGivenEPearl[$player->getName()] = $player->getName();
                    return;
                }
            }
        }
    }

    public function onMove(PlayerMoveEvent $event): void
    {
        {
            $player = $event->getPlayer();
            if ($player instanceof Player) {
                if (isset($this->needToBeGivenEPearl[$player->getName()])) {
                    $player->getInventory()->addItem(Item::get(368));
                    unset($this->needToBeGivenEPearl[$player->getName()]);
                }
            }
        }
    }

    public function onInteract(PlayerInteractEvent $event): void
    {
        {
            $player = $event->getPlayer();
            if ($player instanceof Player) {
                if (isset($this->needToBeGivenEPearl[$player->getName()])) {
                    $player->getInventory()->addItem(Item::get(368));
                    unset($this->needToBeGivenEPearl[$player->getName()]);
                }
            }
        }
    }
}