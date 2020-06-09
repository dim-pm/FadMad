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

namespace Fadhel\Core\data;

use Fadhel\Core\listeners\Ranks;
use Fadhel\Core\Main;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerLoginEvent;

class Players implements Listener
{
    private $plugin;

    public function __construct(Main $plugin)
    {
        $this->plugin = $plugin;
    }

    public function onLogin(PlayerLoginEvent $event)
    {
        $ranks = new Ranks($this->plugin);
        $player = $event->getPlayer();
        if (!$player->hasPlayedBefore()) {
            $ranks->setPermission($player);
            $player->teleport($this->plugin->getServer()->getDefaultLevel()->getSafeSpawn());
            $db = new \SQLite3($this->plugin->getDataFolder() . "master.db");
            $db->exec("CREATE TABLE IF NOT EXISTS master (player TEXT PRIMARY KEY COLLATE NOCASE, kills INT , deaths INT , lvl INT , xp INT , streak INT , rank TEXT , tag TEXT);");
            $stmt = $db->prepare("INSERT OR REPLACE INTO master (player, kills, deaths, lvl, xp, streak, rank, tag) VALUES (:player, :kills, :deaths, :lvl, :xp, :streak, :rank, :tag)");
            $stmt->bindValue(":player", $player->getName());
            $stmt->bindValue(":kills", 0);
            $stmt->bindValue(":deaths", 0);
            $stmt->bindValue(":lvl", 1);
            $stmt->bindValue(":xp", 0);
            $stmt->bindValue(":streak", 0);
            $stmt->bindValue(":rank", "Default");
            $stmt->bindValue(":tag", "none");
            $stmt->execute();
        }
        if (!$this->plugin->coinExists(strtolower($player->getName()))) {
            $db = new \SQLite3($this->plugin->getDataFolder() . "coins.db");
            $db->exec("CREATE TABLE IF NOT EXISTS coins (player TEXT PRIMARY KEY COLLATE NOCASE, coins INT);");
            $stmt = $db->prepare("INSERT OR REPLACE INTO coins (player, coins) VALUES (:player, :coins)");
            $stmt->bindValue(":player", $player->getName());
            $stmt->bindValue(":coins", 100);
            $stmt->execute();

            $db = new \SQLite3($this->plugin->getDataFolder() . "tags.db");
            $db->exec("CREATE TABLE IF NOT EXISTS tags (player TEXT PRIMARY KEY COLLATE NOCASE, lit INT, enhanced INT, mvp INT, ultimate INT, crusader INT, legend INT, overlord INT, experienced INT, ez INT, pyro INT, elite INT, windows10 INT, fresh INT, emperor INT, salty INT, pancakes INT, uwu INT, zoomer INT, dirt INT, god INT, king INT, gangster INT, hitman INT, mobster INT, loner INT, horion INT, injected INT, bustdown INT, pro INT, hacker INT, gucci INT, troll INT, nou INT, killer INT, gangbang INT, chugnub INT, cornhub INT, androidgod INT, iosgod INT);");
            $stmt = $db->prepare("INSERT OR REPLACE INTO tags (player, lit, enhanced, mvp, ultimate, crusader, legend, overlord, experienced, ez, pyro, elite, windows10, fresh, emperor, salty, pancakes, uwu, zoomer, dirt, god, king, gangster, hitman, mobster, loner, horion, injected, bustdown, pro, hacker, gucci, troll, nou, killer, gangbang, chugnub, cornhub, androidgod, iosgod) VALUES (:player, :lit, :enhanced, :mvp, :ultimate, :crusader, :legend, :overlord, :experienced, :ez, :pyro, :elite, :windows10, :fresh, :emperor, :salty, :pancakes, :uwu, :zoomer, :dirt, :god, :king, :gangster, :hitman, :mobster, :loner, :horion, :injected, :bustdown, :pro, :hacker, :gucci, :troll, :nou, :killer, :gangbang, :chugnub, :cornhub, :androidgod, :iosgod)");
            $stmt->bindValue(":player", $player->getName());
            $stmt->bindValue(":lit", 0);
            $stmt->bindValue(":enhanced", 0);
            $stmt->bindValue(":mvp", 0);
            $stmt->bindValue(":ultimate", 0);
            $stmt->bindValue(":crusader", 0);
            $stmt->bindValue(":legend", 0);
            $stmt->bindValue(":overlord", 0);
            $stmt->bindValue(":experienced", 0);
            $stmt->bindValue(":ez", 0);
            $stmt->bindValue(":pyro", 0);
            $stmt->bindValue(":elite", 0);
            $stmt->bindValue(":windows10", 0);
            $stmt->bindValue(":fresh", 0);
            $stmt->bindValue(":emperor", 0);
            $stmt->bindValue(":salty", 0);
            $stmt->bindValue(":pancakes", 0);
            $stmt->bindValue(":uwu", 0);
            $stmt->bindValue(":zoomer", 0);
            $stmt->bindValue(":dirt", 0);
            $stmt->bindValue(":god", 0);
            $stmt->bindValue(":king", 0);
            $stmt->bindValue(":gangster", 0);
            $stmt->bindValue(":hitman", 0);
            $stmt->bindValue(":mobster", 0);
            $stmt->bindValue(":loner", 0);
            $stmt->bindValue(":horion", 0);
            $stmt->bindValue(":injected", 0);
            $stmt->bindValue(":bustdown", 0);
            $stmt->bindValue(":pro", 0);
            $stmt->bindValue(":hacker", 0);
            $stmt->bindValue(":gucci", 0);
            $stmt->bindValue(":troll", 0);
            $stmt->bindValue(":nou", 0);
            $stmt->bindValue(":killer", 0);
            $stmt->bindValue(":gangbang", 0);
            $stmt->bindValue(":chugnub", 0);
            $stmt->bindValue(":cornhub", 0);
            $stmt->bindValue(":androidgod", 0);
            $stmt->bindValue(":iosgod", 0);
            $stmt->execute();

        }
        $ranks->setPermission($player);
        $player->teleport($this->plugin->getServer()->getDefaultLevel()->getSafeSpawn());
    }
}