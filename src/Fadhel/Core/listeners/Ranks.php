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

use pocketmine\Player;
use Fadhel\Core\Main;
use pocketmine\utils\TextFormat as C;


class Ranks
{
    private $plugin;

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

    public function setRank(Player $player, $rank)
    {
        $ranks = array("Default", "YouTuber", "VIP", "MVP", "Manager", "Trainee", "Moderator", "SrMod", "Admin", "Investor", "Owner", "SrAdmin", "Developer", "Builder", "Modmvp", "Traineemvp", "YT", "JrMod", "Events Manager", "TraineeYT", "ModYT", "BuilderYouTuber");
        if (in_array($rank, $ranks)) {
            $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, kills, deaths, lvl, xp, streak, rank, tag) VALUES (:player, :kills, :deaths, :lvl, :xp, :streak, :rank, :tag)");
            $stmt->bindValue(":player", $player->getName());
            $stmt->bindValue(":kills", $this->plugin->getKills($player));
            $stmt->bindValue(":deaths", $this->plugin->getDeaths($player));
            $stmt->bindValue(":lvl", $this->plugin->getLevel($player));
            $stmt->bindValue(":xp", $this->plugin->getXP($player));
            $stmt->bindValue(":streak", $this->plugin->getStreak($player));
            $stmt->bindValue(":rank", $rank);
            $stmt->bindValue(":tag", $this->plugin->getTag($player));
            $stmt->execute();
            $player->sendMessage(C::GOLD . "Your new rank is " . C::YELLOW . $rank . "!");
            $this->sendStaffAlert(C::YELLOW . $player->getName() . C::GOLD . " has been granted " . C::GRAY . $rank);
            $rank = $this->plugin->getRank($player);
            $format = $this->getFormat($rank);
            $tag = $this->plugin->getTag($player);
            $type = $this->getType($tag);
            $this->setPermission($player);
            if ($tag !== "none") {
                $player->setNameTag(C::GRAY . "[" . C::AQUA . $this->getLevelFormat($player) . C::GRAY . "] " . $type . $format . $player->getName());
            } else {
                $player->setNameTag(C::GRAY . "[" . C::AQUA . $this->getLevelFormat($player) . C::GRAY . "] " . $format . $player->getName());
            }
        }
    }

    public function setTag(Player $player, $tag)
    {
        $tags = array("Amazing", "Toast", "EBoy", "EGirl", "OOF", "Idot", "Savage", "Triggered", "PvPGod", "TryHard", "Owner", "Nerd", "XD", "God", "Turtle", "Cookie", "Pancakes", "Boomer", "Reklaze", "Raider", "Zeus", "Toxic", "noob", "Lit", "Fadhel", "Creeper", "EnderMan", "Ps4", "Gay", "UwU", "Pyro", "Ez", "Zoomer", "Salty", "Windows10", "Dirt", "Fresh", "Enhanced", "MVP", "Ultimate", "Crusader", "Legend", "Overlord", "Experienced", "Elite", "Emperor", "King", "Gangster", "HitMan", "Mobster", "Loner", "Horion", "Injected", "Bustdown", "Pro", "Hacker", "Gucci", "Troll", "NoU", "Killer", "Gangbang", "ChugNub", "CornHub", "AndroidGod", "IOSGOD", "Cliqnt");
        if (in_array($tag, $tags)) {
            $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, kills, deaths, lvl, xp, streak, rank, tag) VALUES (:player, :kills, :deaths, :lvl, :xp, :streak, :rank, :tag)");
            $stmt->bindValue(":player", $player->getName());
            $stmt->bindValue(":kills", $this->plugin->getKills($player));
            $stmt->bindValue(":deaths", $this->plugin->getDeaths($player));
            $stmt->bindValue(":lvl", $this->plugin->getLevel($player));
            $stmt->bindValue(":xp", $this->plugin->getXP($player));
            $stmt->bindValue(":streak", $this->plugin->getStreak($player));
            $stmt->bindValue(":rank", $this->plugin->getRank($player));
            $stmt->bindValue(":tag", $tag);
            $stmt->execute();
            $player->sendMessage(C::GREEN . "You have changed your tag to " . C::GRAY . $tag . C::GREEN . "!");
            $tag = $this->plugin->getTag($player);
            $type = $this->getType($tag);
            $rank = $this->plugin->getRank($player);
            $format = $this->getFormat($rank);
            $this->sendStaffAlert(C::YELLOW . $player->getName() . C::GOLD . " has been given " . C::GRAY . $tag . C::GOLD . " tag!");
            $player->setNameTag(C::GRAY . "[" . C::AQUA . $this->getLevelFormat($player) . $this->plugin->getLevel($player) . C::GRAY . "] " . $type . $format . $player->getName());
        } elseif ($tag === "none") {
            $rank = $this->plugin->getRank($player);
            $format = $this->getFormat($rank);
            $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, kills, deaths, lvl, xp, streak, rank, tag) VALUES (:player, :kills, :deaths, :lvl, :xp, :streak, :rank, :tag)");
            $stmt->bindValue(":player", $player->getName());
            $stmt->bindValue(":kills", $this->plugin->getKills($player));
            $stmt->bindValue(":deaths", $this->plugin->getDeaths($player));
            $stmt->bindValue(":lvl", $this->plugin->getLevel($player));
            $stmt->bindValue(":xp", $this->plugin->getXP($player));
            $stmt->bindValue(":streak", $this->plugin->getStreak($player));
            $stmt->bindValue(":rank", $this->plugin->getRank($player));
            $stmt->bindValue(":tag", "none");
            $stmt->execute();
            $this->sendStaffAlert(C::YELLOW . $player->getName() . C::GOLD . " hided their tag!");
            $player->setNameTag(C::GRAY . "[" . C::AQUA . $this->getLevelFormat($player) . $this->plugin->getLevel($player) . C::GRAY . "] " . $format . $player->getName());
            $player->sendMessage(C::GREEN . "You have hided your tag!");
        }
    }

    public function getFormat($rank)
    {
        switch ($rank):
            case "Owner":
                $format = "§8[§4Owner§8]§4 ";
                return $format;
                break;
            case "Admin":
                $format = "§8[§cAdmin§8]§c ";
                return $format;
                break;
            case "Moderator":
                $format = "§8[§5Moderator§8] ";
                return $format;
                break;
            case "Modmvp":
                $format = "§8[§5Moderator+§l§bMVP§b]§5 ";
                return $format;
                break;
            case "ModYT":
                $format = "§8[§5Moderator+§l§fY§cT§8] ";
                return $format;
                break;
            case "Traineemvp":
                $format = "§8[§eTrainee+§l§bMVP§b]§5 ";
                return $format;
                break;
            case "TraineeYT":
                $format = "§8[§eTrainee+§l§fY§cT§8] ";
                return $format;
                break;
            case "Trainee":
                $format = "§8[§eTrainee§8]§e ";
                return $format;
                break;
            case "BuilderYouTuber":
                $format = "§c[§eTrainee+§l§fYOUTUBE§c]§c ";
                return $format;
                break;
            case "Builder":
                $format = "§8[§6Builder§8] ";
                return $format;
                break;
            case "Manager":
                $format = "§8[§cManager§8]§c ";
                return $format;
                break;
            case "SrAdmin":
                $format = "§8[§4Senior§cAdmin§8]§c ";
                return $format;
                break;
            case "Developer":
                $format = "§8[§1Developer§8]§1 ";
                return $format;
                break;
            case "Investor":
                $format = "§8[§bInvestor§8]§b ";
                return $format;
                break;
            case "MVP":
                $format = "§l§b[MVP]§b ";
                return $format;
                break;
            case "SrMod":
                $format = "§8[§dSenior§5Mod§8]§d ";
                return $format;
                break;
            case "JrMod":
                $format = "§8[§dJunior§5Mod§8]§d ";
                return $format;
                break;
            case "VIP":
                $format = "§8[§aVIP§8]§a ";
                return $format;
                break;
            case "YouTuber":
                $format = "§c[§fYOUTUBE§c]§c ";
                return $format;
                break;
            case "YT":
                $format = "§c[§fY§cT§c] ";
                return $format;
                break;
            case "Events Manager":
                $format = "§c[§1Events Manager§c] ";
                return $format;
                break;
            case "Default":
                $format = "§7[Player§7]§8 ";
                return $format;
                break;
        endswitch;
        return true;
    }

    public function getType($tag)
    {
        switch ($tag):
            case "EBoy":
                $format = "§7[§bEBOY§7] ";
                return $format;
                break;
            case "EGirl":
                $format = "§7[§l§dE§r§l-§dGirl§7] ";
                return $format;
                break;
            case "Idot":
                $format = "§7[§3IDOT§7] ";
                return $format;
                break;
            case "OOF":
                $format = "§7[§4OOF§7] ";
                return $format;
                break;
            case "Toast":
                $format = "§7[§6Toast§7] ";
                return $format;
                break;
            case "Amazing":
                $format = "§7[§aAMAZING§7] ";
                return $format;
                break;
            case "Raider":
                $format = "§7[§bRaider§7] ";
                return $format;
                break;
            case "Zeus":
                $format = "§7[§9Zeus§7] ";
                return $format;
                break;
            case "Owner":
                $format = "§7[§4Owner§7§7] ";
                return $format;
                break;
            case "Savage":
                $format = "§7[§5SAVAGE§7] ";
                return $format;
                break;
            case "Toxic":
                $format = "§7[§aT§7o§ax§7i§ac§7] ";
                return $format;
                break;
            case "Nerd":
                $format = "§7[§eNERD§7] ";
                return $format;
                break;
            case "TryHard":
                $format = "§7[§1TryHard§7] ";
                return $format;
                break;
            case "Triggered":
                $format = "§7[§6TRIGGERED§7] ";
                return $format;
                break;
            case "PvPGod":
                $format = "§7[§2PvPGod§7] ";
                return $format;
                break;
            case "XD":
                $format = "§7[§aXD§7] ";
                return $format;
                break;
            case "God":
                $format = "§7[§6I§eAm§a2§9Gud§54§cYou§7] ";
                return $format;
                break;
            case "Turtle":
                $format = "§7[§2TURTLE§7] ";
                return $format;
                break;
            case "Pancakes":
                $format = "§7[§6Pan§dcake§7] ";
                return $format;
                break;
            case "Boomer":
                $format = "§7[§eBoomer§7] ";
                return $format;
                break;
            case "Cookie":
                $format = "§7[§6ImCookie§7] ";
                return $format;
                break;
            case "Reklaze":
                $format = "§7[§bWindows10 §4God§0+§1Birthday §cboy§7] ";
                return $format;
                break;
            case "noob":
                $format = "§7[§bNoob§7] ";
                return $format;
                break;
            case "Fadhel":
                $format = "§7[§cHoes Mad§7] ";
                return $format;
                break;
            case "Lit":
                $format = "§7[§1L§4I§aT§7] ";
                return $format;
                break;
            case "Creeper":
                $format = "§7[§l§2C§8R§2E§8E§2P§8E§2R§7] ";
                return $format;
                break;
            case "EnderMan":
                $format = "§7[§dEnder§0Man§7] ";
                return $format;
                break;
            case "Ps4":
                $format = "§7[§c§lA Custom Tag§7] ";
                return $format;
                break;
            case "Gay":
                $format = "§7[§fSUB§fSCRIBE§7] ";
                return $format;
                break;
            case "UwU":
                $format = "§7[§6U§ew§6U§7] ";
                return $format;
                break;
            case "Pyro":
                $format = "§7[§2P§ry§2r§ro§7] ";
                return $format;
                break;
            case "Ez":
                $format = "§7[§1E§9z§7] ";
                return $format;
                break;
            case "Salty":
                $format = "§7[§1S§3al§9ty§7] ";
                return $format;
                break;
            case "Zoomer":
                $format = "§7[§6Zoo§amer§7] ";
                return $format;
                break;
            case "Windows10":
                $format = "§7[§bWindows10§7] ";
                return $format;
                break;
             case "Dirt":
                $format = "§7[§l§0Dirt§7] ";
                return $format;
                break;
            case "Fresh":
                $format = "§7[§4F§cH§6R§eE§2S§aH§7] ";
                return $format;
                break;
            case "Enhanced":
                $format = "§7[§1En§9han§bced§7] ";
                return $format;
                break;
            case "MVP":
                $format = "§7[§2M§aV§2P§7] ";
                return $format;
                break;
            case "Ultimate":
                $format = "§7[§4U§6l§2t§3i§1m§5a§ft§7e§7] ";
                return $format;
                break;
            case "Crusader":
                $format = "§7[§6Crusader§7] ";
                return $format;
                break;
            case "Legend":
                $format = "§7[§2Legend§7] ";
                return $format;
                break;
            case "Overlord":
                $format = "§7[§5Over§dlord§7] ";
                return $format;
                break;
            case "Experienced":
                $format = "§7[§2Experienced§7] ";
                return $format;
                break;
            case "Elite":
                $format = "§7[§2El§aite§7] ";
                return $format;
                break;
            case "Emperor":
                $format = "§7[§l§4Emperor§r§7] ";
                return $format;
                break;
            case "King":
                $format = "§7[§l§aKing§7] ";
                return $format;
                break;
            case "Gangster":
                $format = "§7[§l§4Gangster§7] ";
                return $format;
                break;
            case "HitMan":
                $format = "§7[§l§4Hit§cman§r§7] ";
                return $format;
                break;
            case "Mobster":
                $format = "§7[§l§2Mobster§7] ";
                return $format;
                break;
            case "Loner":
                $format = "§7[§l§3Loner§7] ";
                return $format;
                break;
            case "Horion":
                $format = "§7[§l§3Horion§bUser§7] ";
                return $format;
                break;
            case "Injected":
                $format = "§7[§l§5Injected§7] ";
                return $format;
                break;
            case "Bustdown":
                $format = "§7[§l§cBustdown§7] ";
                return $format;
                break;
            case "Pro":
                $format = "§7[§l§bPro§r§7] ";
                return $format;
                break;
            case "Hacker":
                $format = "§7[§l§4Hacker§r§7] ";
                return $format;
                break;
            case "Gucci":
                $format = "§7[§l§4G§2u§4c§2c§4i§r§7] ";
                return $format;
                break;
            case "Troll":
                $format = "§7[§l§2Troll§r§7] ";
                return $format;
                break;
            case "NoU":
                $format = "§7[§l§3No§9U§r§7] ";
                return $format;
                break;
            case "Killer":
                $format = "§7[§l§4Kill§cer§r§l.exe§r§7] ";
                return $format;
                break;
            case "Gangbang":
                $format = "§7[§l§4Gang§cBang§r§7] ";
                return $format;
                break;
            case "ChugNub":
                $format = "§7[§l§2Chug§aNub§r§7] ";
                return $format;
                break;
            case "CornHub":
                $format = "§7[§l§6Corn§0Hub§r§7] ";
                return $format;
                break;
            case "AndroidGod":
                $format = "§7[§l§8Android§7God§r§7] ";
                return $format;
                break;
            case "IOSGOD":
                $format = "§7[§l§3IOS§9GOD§r§7] ";
                return $format;
                break;
            case "Cliqnt":
                $format = "§7[§l§5Cli§dqnt§r§l.exe§r§7] ";
                return $format;
                break;
        endswitch;
        return true;
    }

    public function addPermission(Player $player, $permission)
    {
        $player->addAttachment($this->plugin, $permission, true);
    }

    public function setPermission(Player $player)
    {
        switch ($this->plugin->getRank($player)) {
            case "Default":
                $this->addPermission($player, "server.kitpvp");
                break;
            case "Voter":
                $this->addPermission($player, "chat.bypass");
                break;
            case "Trainee":
                $this->addPermission($player, "chat.bypass");
                $this->addPermission($player, "cucumber.command.vanish");
                $this->addPermission($player, "blazinfly.command");
                $this->addPermission($player, "pocketmine.command.kick");
                $this->addPermission($player, "server.staff");
                $this->addPermission($player, "freeze.command");
                break;
            case "TraineeYT":
                $this->addPermission($player, "chat.bypass");
                $this->addPermission($player, "cucumber.command.vanish");
                $this->addPermission($player, "blazinfly.command");
                $this->addPermission($player, "pocketmine.command.kick");
                $this->addPermission($player, "server.staff");
                $this->addPermission($player, "freeze.command");
                $this->addPermission($player, "server.kitpvp");
                $this->addPermission($player, "youtube.kit");
                $this->addPermission($player, "chat.bypass");
                $this->addPermission($player, "blazinfly.command");
                $this->addPermission($player, "tags.command");
                break;
            case "Traineemvp":
                $this->addPermission($player, "server.kitpvp");
                $this->addPermission($player, "vip.kit");
                $this->addPermission($player, "chat.bypass");
                $this->addPermission($player, "cucumber.command.vanish");
                $this->addPermission($player, "blazinfly.command");
                $this->addPermission($player, "pocketmine.command.kick");
                $this->addPermission($player, "server.staff");
                $this->addPermission($player, "server.kitpvp");
                $this->addPermission($player, "blazinfly.command");
                $this->addPermission($player, "chat.bypass");
                $this->addPermission($player, "tags.command");
                $this->addPermission($player, "freeze.command");
                $this->addPermission($player, "p.ui.command");
                break;
            case "MVP":
                $this->addPermission($player, "server.kitpvp");
                $this->addPermission($player, "blazinfly.command");
                $this->addPermission($player, "chat.bypass");
                $this->addPermission($player, "tags.command");
                $this->addPermission($player, "p.ui.command");
                break;
            case "SrMod":
                $this->addPermission($player, "server.kitpvp");
                $this->addPermission($player, "server.staff");
                $this->addPermission($player, "server.mod");
                $this->addPermission($player, "chat.bypass");
                $this->addPermission($player, "pocketmine.command.ban.player");
                $this->addPermission($player, "pocketmine.command.ban.list");
                $this->addPermission($player, "pocketmine.command.kick");
                $this->addPermission($player, "pocketmine.command.teleport");
                $this->addPermission($player, "cucumber.command.ipbanlist");
                $this->addPermission($player, "cucumber.command.banlist");
                $this->addPermission($player, "cucumber.command.pardon");
                $this->addPermission($player, "cucumber.ban");
                $this->addPermission($player, "cucumber.mute");
                $this->addPermission($player, "blazinfly.command");
                $this->addPermission($player, "BanWarn.command.warn");
                $this->addPermission($player, "BanWarn.command.warninfo");
                $this->addPermission($player, "BanWarn.command.warnpardon");
                $this->addPermission($player, "cucumber.command.vanish");
                $this->addPermission($player, "freeze.command");
                break;
            case "JrMod":
                $this->addPermission($player, "server.kitpvp");
                $this->addPermission($player, "server.staff");
                $this->addPermission($player, "server.mod");
                $this->addPermission($player, "chat.bypass");
                $this->addPermission($player, "pocketmine.command.ban.player");
                $this->addPermission($player, "pocketmine.command.ban.list");
                $this->addPermission($player, "pocketmine.command.kick");
                $this->addPermission($player, "pocketmine.command.teleport");
                $this->addPermission($player, "cucumber.command.ipbanlist");
                $this->addPermission($player, "cucumber.command.banlist");
                $this->addPermission($player, "cucumber.command.pardon");
                $this->addPermission($player, "cucumber.ban");
                $this->addPermission($player, "cucumber.mute");
                $this->addPermission($player, "blazinfly.command");
                $this->addPermission($player, "BanWarn.command.warn");
                $this->addPermission($player, "BanWarn.command.warninfo");
                $this->addPermission($player, "BanWarn.command.warnpardon");
                $this->addPermission($player, "cucumber.command.vanish");
                $this->addPermission($player, "freeze.command");
                break;
            case "Events Manager":
                $this->addPermission($player, "server.kitpvp");
                $this->addPermission($player, "server.staff");
                $this->addPermission($player, "server.mod");
                $this->addPermission($player, "chat.bypass");
                $this->addPermission($player, "pocketmine.command.ban.player");
                $this->addPermission($player, "pocketmine.command.ban.list");
                $this->addPermission($player, "pocketmine.command.kick");
                $this->addPermission($player, "pocketmine.command.teleport");
                $this->addPermission($player, "cucumber.command.ipbanlist");
                $this->addPermission($player, "cucumber.command.banlist");
                $this->addPermission($player, "cucumber.command.pardon");
                $this->addPermission($player, "cucumber.ban");
                $this->addPermission($player, "cucumber.mute");
                $this->addPermission($player, "blazinfly.command");
                $this->addPermission($player, "BanWarn.command.warn");
                $this->addPermission($player, "BanWarn.command.warninfo");
                $this->addPermission($player, "BanWarn.command.warnpardon");
                $this->addPermission($player, "cucumber.command.vanish");
                $this->addPermission($player, "freeze.command");
                break;
            case "YouTuber":
                $this->addPermission($player, "server.kitpvp");
                $this->addPermission($player, "youtube.kit");
                $this->addPermission($player, "chat.bypass");
                $this->addPermission($player, "blazinfly.command");
                $this->addPermission($player, "tags.command");
                break;
            case "BuilderYouTuber":
                $this->addPermission($player, "server.kitpvp");
                $this->addPermission($player, "youtube.kit");
                $this->addPermission($player, "chat.bypass");
                $this->addPermission($player, "blazinfly.command");
                $this->addPermission($player, "tags.command");
                break;
            case "YT":
                $this->addPermission($player, "server.kitpvp");
                $this->addPermission($player, "youtube.kit");
                $this->addPermission($player, "chat.bypass");
                $this->addPermission($player, "blazinfly.command");
                $this->addPermission($player, "tags.command");
                break;
            case "Manager":
                $this->addPermission($player, "server.kitpvp");
                $this->addPermission($player, "server.staff");
                $this->addPermission($player, "chat.bypass");
                $this->addPermission($player, "pocketmine.command.kick");
                $this->addPermission($player, "pocketmine.command.teleport");
                $this->addPermission($player, "tags.command");
                $this->addPermission($player, "cucumber.command.ipbanlist");
                $this->addPermission($player, "cucumber.command.banlist");
                $this->addPermission($player, "cucumber.command.pardon");
                $this->addPermission($player, "cucumber.ban");
                $this->addPermission($player, "cucumber.mute");
                $this->addPermission($player, "blazinfly.command");
                $this->addPermission($player, "BanWarn.command.warn");
                $this->addPermission($player, "BanWarn.command.warninfo");
                $this->addPermission($player, "BanWarn.command.warnpardon");
                $this->addPermission($player, "cucumber.command.vanish");
                $this->addPermission($player, "freeze.command");
                break;
            case "Moderator":
                $this->addPermission($player, "server.kitpvp");
                $this->addPermission($player, "server.staff");
                $this->addPermission($player, "server.mod");
                $this->addPermission($player, "chat.bypass");
                $this->addPermission($player, "pocketmine.command.ban.player");
                $this->addPermission($player, "pocketmine.command.ban.list");
                $this->addPermission($player, "pocketmine.command.kick");
                $this->addPermission($player, "pocketmine.command.teleport");
                $this->addPermission($player, "cucumber.command.ipbanlist");
                $this->addPermission($player, "cucumber.command.banlist");
                $this->addPermission($player, "cucumber.command.pardon");
                $this->addPermission($player, "cucumber.ban");
                $this->addPermission($player, "cucumber.mute");
                $this->addPermission($player, "blazinfly.command");
                $this->addPermission($player, "BanWarn.command.warn");
                $this->addPermission($player, "BanWarn.command.warninfo");
                $this->addPermission($player, "BanWarn.command.warnpardon");
                $this->addPermission($player, "cucumber.command.vanish");
                $this->addPermission($player, "freeze.command");
                break;
            case "ModYT":
                $this->addPermission($player, "server.kitpvp");
                $this->addPermission($player, "server.staff");
                $this->addPermission($player, "server.mod");
                $this->addPermission($player, "chat.bypass");
                $this->addPermission($player, "pocketmine.command.ban.player");
                $this->addPermission($player, "pocketmine.command.ban.list");
                $this->addPermission($player, "pocketmine.command.kick");
                $this->addPermission($player, "pocketmine.command.teleport");
                $this->addPermission($player, "cucumber.command.ipbanlist");
                $this->addPermission($player, "cucumber.command.banlist");
                $this->addPermission($player, "cucumber.command.pardon");
                $this->addPermission($player, "cucumber.ban");
                $this->addPermission($player, "cucumber.mute");
                $this->addPermission($player, "blazinfly.command");
                $this->addPermission($player, "BanWarn.command.warn");
                $this->addPermission($player, "BanWarn.command.warninfo");
                $this->addPermission($player, "BanWarn.command.warnpardon");
                $this->addPermission($player, "cucumber.command.vanish");
                $this->addPermission($player, "freeze.command");
                $this->addPermission($player, "server.kitpvp");
                $this->addPermission($player, "youtube.kit");
                $this->addPermission($player, "chat.bypass");
                $this->addPermission($player, "blazinfly.command");
                $this->addPermission($player, "tags.command");
                break;
            case "Modmvp":
                $this->addPermission($player, "server.kitpvp");
                $this->addPermission($player, "server.staff");
                $this->addPermission($player, "server.mod");
                $this->addPermission($player, "chat.bypass");
                $this->addPermission($player, "pocketmine.command.ban.player");
                $this->addPermission($player, "pocketmine.command.ban.list");
                $this->addPermission($player, "pocketmine.command.kick");
                $this->addPermission($player, "pocketmine.command.teleport");
                $this->addPermission($player, "cucumber.command.ipbanlist");
                $this->addPermission($player, "cucumber.command.banlist");
                $this->addPermission($player, "cucumber.command.pardon");
                $this->addPermission($player, "cucumber.ban");
                $this->addPermission($player, "cucumber.mute");
                $this->addPermission($player, "blazinfly.command");
                $this->addPermission($player, "BanWarn.command.warn");
                $this->addPermission($player, "BanWarn.command.warninfo");
                $this->addPermission($player, "BanWarn.command.warnpardon");
                $this->addPermission($player, "cucumber.command.vanish");
                $this->addPermission($player, "server.kitpvp");
                $this->addPermission($player, "blazinfly.command");
                $this->addPermission($player, "chat.bypass");
                $this->addPermission($player, "tags.command");
                $this->addPermission($player, "freeze.command");
                $this->addPermission($player, "p.ui.command");
                break;
            case "Admin":
                $this->addPermission($player, "chat.bypass");
                $this->addPermission($player, "cucumber.command.ipbanlist");
                $this->addPermission($player, "cucumber.command.banlist");
                $this->addPermission($player, "cucumber.command.pardon");
                $this->addPermission($player, "cucumber.ban");
                $this->addPermission($player, "cucumber.mute");
                $this->addPermission($player, "blazinfly.command");
                $this->addPermission($player, "BanWarn.command.warn");
                $this->addPermission($player, "BanWarn.command.warninfo");
                $this->addPermission($player, "BanWarn.command.warnpardon");
                $this->addPermission($player, "cucumber.command.vanish");
                $this->addPermission($player, "pocketmine.command.kick");
                $this->addPermission($player, "pocketmine.command.teleport");
                $this->addPermission($player, "server.staff");
                break;
            case "SrAdmin":
                $this->addPermission($player, "server.kitpvp");
                $this->addPermission($player, "server.staff");
                $this->addPermission($player, "core.admin");
                $this->addPermission($player, "chat.bypass");
                $this->addPermission($player, "cucumber.command.ipbanlist");
                $this->addPermission($player, "cucumber.command.banlist");
                $this->addPermission($player, "cucumber.command.pardon");
                $this->addPermission($player, "cucumber.ban");
                $this->addPermission($player, "cucumber.mute");
                $this->addPermission($player, "blazinfly.command");
                $this->addPermission($player, "BanWarn.command.warn");
                $this->addPermission($player, "BanWarn.command.warninfo");
                $this->addPermission($player, "BanWarn.command.warnpardon");
                $this->addPermission($player, "cucumber.command.vanish");
                $this->addPermission($player, "freeze.command");
                $this->addPermission($player, "server.staff");
                break;
            case "Investor":
                $this->addPermission($player, "server.kitpvp");
                $this->addPermission($player, "server.staff");
                $this->addPermission($player, "core.admin");
                $this->addPermission($player, "chat.bypass");
                $this->addPermission($player, "cucumber.command.ipbanlist");
                $this->addPermission($player, "cucumber.command.banlist");
                $this->addPermission($player, "cucumber.command.pardon");
                $this->addPermission($player, "cucumber.ban");
                $this->addPermission($player, "cucumber.mute");
                $this->addPermission($player, "blazinfly.command");
                $this->addPermission($player, "BanWarn.command.warn");
                $this->addPermission($player, "BanWarn.command.warninfo");
                $this->addPermission($player, "BanWarn.command.warnpardon");
                $this->addPermission($player, "cucumber.command.vanish");
                $this->addPermission($player, "freeze.command");
                break;  
            case "Developer":
                $this->addPermission($player, "server.kitpvp");
                $this->addPermission($player, "server.staff");
                $this->addPermission($player, "core.admin");
                $this->addPermission($player, "chat.bypass");
                $this->addPermission($player, "cucumber.command.ipbanlist");
                $this->addPermission($player, "cucumber.command.banlist");
                $this->addPermission($player, "cucumber.command.pardon");
                $this->addPermission($player, "cucumber.ban");
                $this->addPermission($player, "cucumber.mute");
                $this->addPermission($player, "blazinfly.command");
                $this->addPermission($player, "BanWarn.command.warn");
                $this->addPermission($player, "BanWarn.command.warninfo");
                $this->addPermission($player, "BanWarn.command.warnpardon");
                $this->addPermission($player, "cucumber.command.vanish");
                $this->addPermission($player, "freeze.command");
                break;    
            case "Owner":
                $this->addPermission($player, "tags.command");
                $this->addPermission($player, "server.staff");
                $this->addPermission($player, "core.admin");
                $this->addPermission($player, "chat.bypass");
                $this->addPermission($player, "cucumber.command.ipbanlist");
                $this->addPermission($player, "cucumber.command.banlist");
                $this->addPermission($player, "cucumber.command.pardon");
                $this->addPermission($player, "cucumber.ban");
                $this->addPermission($player, "BanWarn.command.warn");
                $this->addPermission($player, "cucumber.command.vanish");
                $this->addPermission($player, "BanWarn.command.warninfo");
                $this->addPermission($player, "BanWarn.command.warnpardon");
                $this->addPermission($player, "blazinfly.command");
                $this->addPermission($player, "cucumber.mute");
                $this->addPermission($player, "freeze.command");
        }
    }
}
