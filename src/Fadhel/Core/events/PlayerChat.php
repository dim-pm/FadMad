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

namespace Fadhel\Core\events;

use Fadhel\Core\listeners\Ranks;
use Fadhel\Core\Main;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\Listener;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat as C;
use pocketmine\Player;

class PlayerChat implements Listener
{

    private $plugin;
    private $spam;

    public function __construct(Main $plugin)
    {
        $this->plugin = $plugin;
    }

    public function sendStaffAlert($msg)
    {
        foreach ($this->plugin->getServer()->getOnlinePlayers() as $staff) {
            if ($staff->hasPermission("server.staff")) {
                $staff->sendMessage($msg);
            }
        }
    }

    public function replaceVars($str, array $vars)
    {
        foreach ($vars as $key => $value) {
            $str = str_replace("{" . $key . "}", $value, $str);
        }
        return $str;
    }

    public function getLevelFormat(Player $player)
    {
        if ($this->plugin->getLevel($player) <= 10) {
            return "§a";
        } elseif ($this->plugin->getLevel($player) >= 10 && $this->plugin->getLevel($player) < 20) {
            return "§2";
        } elseif ($this->plugin->getLevel($player) >= 20 && $this->plugin->getLevel($player) < 30) {
            return "§3";
        } elseif ($this->plugin->getLevel($player) >= 30 && $this->plugin->getLevel($player) < 40) {
            return "§6";
        } elseif ($this->plugin->getLevel($player) >= 40 && $this->plugin->getLevel($player) < 50) {
            return "§5";
        } elseif ($this->plugin->getLevel($player) >= 50 && $this->plugin->getLevel($player) < 60) {
            return "§c";
        } elseif ($this->plugin->getLevel($player) >= 60) {
            return "§4";
        }
        return true;
    }

    public function contains($string, array $contains): bool
    {
        foreach ($contains as $contain) {
            if (strpos(strtolower($string), $contain) !== false) {
                return true;
            }
        }
        return false;
    }

    public function onChat(PlayerChatEvent $event)
    {
        $player = $event->getPlayer();
        $message = $event->getMessage();
        $ranks = new Ranks($this->plugin);
        $rank = $this->plugin->getRank($player);
        $format = $ranks->getFormat($rank);
        $tag = $this->plugin->getTag($player);
        if ($tag !== "none") {
            $type = $ranks->getType($tag);
            $event->setFormat(C::GRAY . "[" . $this->getLevelFormat($player) . $this->plugin->getLevel($player) . C::RESET . C::GRAY . "] " . $type . $format . $player->getName() . C::GRAY . ": " . C::WHITE . $event->getMessage());
        } else {
            $event->setFormat(C::GRAY . "[" . $this->getLevelFormat($player) . $this->plugin->getLevel($player) . C::RESET . C::GRAY . "] " . $format . $player->getName() . C::GRAY . ": " . C::WHITE . $event->getMessage());
        }
        if (substr($message, 0, 1) === "#") {
            if ($player->hasPermission("server.staff")) {
                $this->sendStaffAlert(C::AQUA . "STAFF CHAT: " . $format . $player->getName() . C::GRAY . ": " . C::WHITE . $message);
                $event->setCancelled(true);
            }
        } else {
            if (!$player->hasPermission("chat.bypass")) {
                if (!isset($this->spam[strtolower($player->getName())])) {
                    if ($message !== $this->plugin->msg[$player->getName()]) {
                        $config = new Config($this->plugin->getDataFolder() . "words.txt", Config::ENUM);
                        $msg = str_replace(" ", "", $message);
                        if (!$this->contains($msg, $config->getAll(true)) && !$event->isCancelled()) {
                            $this->spam[strtolower($player->getName())] = time();
                            $event->setCancelled(false);
                            $this->plugin->msg[$player->getName()] = $message;
                        } else {
                            $event->setCancelled(true);
                            $player->sendMessage(C::RED . "You can't send this message!");
                        }
                    } else {
                        $event->setCancelled(true);
                        $player->sendMessage(C::RED . "You can't spam the message!");
                    }
                } else {
                    $cooldown = 3;
                    if (time() - $this->spam[strtolower($player->getName())] < $cooldown) {
                        $event->setCancelled(true);
                        $time = time() - $this->spam[strtolower($player->getName())];
                        $player->sendMessage(C::colorize($this->replaceVars("§cPlease wait §7{TIME}§cs to send another message!", array("TIME" => $cooldown - $time))));
                    } else {
                        $config = new Config($this->plugin->getDataFolder() . "words.txt", Config::ENUM);
                        $msg = str_replace(" ", "", $message);
                        if ($message !== $this->plugin->msg[$player->getName()]) {
                            if (!$this->contains($msg, $config->getAll(true)) && !$event->isCancelled()) {
                                $event->setCancelled(false);
                                $this->plugin->msg[$player->getName()] = $message;
                                $this->spam[strtolower($player->getName())] = time();
                            } else {
                                $event->setCancelled(true);
                                $player->sendMessage(C::RED . "You can't send this message!");
                            }
                        } else {
                            $event->setCancelled(true);
                            $player->sendMessage(C::RED . "You can't spam the message!");
                        }
                    }
                }
            } else {
                $config = new Config($this->plugin->getDataFolder() . "words.txt", Config::ENUM);
                $msg = str_replace(" ", "", $message);
                if ($message !== $this->plugin->msg[$player->getName()]) {
                    if (!$this->contains($msg, $config->getAll(true))) {
                        if($event->isCancelled() === true) return;
                        $event->setCancelled(false);
                    } else {
                        $player->sendMessage(C::RED . "You can't send this message!");
                    }
                } else {
                    $event->setCancelled(true);
                    $player->sendMessage(C::RED . "You can't spam the message!");
                }
            }
        }
    }
}