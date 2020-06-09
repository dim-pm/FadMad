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

namespace Fadhel\Core\utils\scoreboard;

use pocketmine\network\mcpe\protocol\{RemoveObjectivePacket, SetDisplayObjectivePacket, SetScorePacket};
use pocketmine\network\mcpe\protocol\types\ScorePacketEntry;
use pocketmine\Player;
use pocketmine\Server;

class Scoreboard
{
    const MAX_LINES = 15;

    /** @var string */
    private $objectiveName;

    /** @var string */
    private $displayName;

    /** @var string */
    private $displaySlot;

    /** @var int */
    private $sortOrder;

    /** @var int */
    private $scoreboardId;

    /** @var ScoreboardStore */
    private static $store = null;

    public function __construct(string $title, int $action)
    {
        $this->displayName = $title;
        if ($action === Action::CREATE && is_null($this->getStore()->getId($title))) {
            $this->objectiveName = uniqid();

            return;
        }
        $this->objectiveName = $this->getStore()->getId($title);
        $this->displaySlot = $this->getStore()->getDisplaySlot($this->objectiveName);
        $this->sortOrder = $this->getStore()->getSortOrder($this->objectiveName);
        $this->scoreboardId = $this->getStore()->getScoreboardId($this->objectiveName);
    }

    /**
     * @param $player
     */
    public function addDisplay(Player $player)
    {
        $pk = new SetDisplayObjectivePacket();
        $pk->displaySlot = $this->displaySlot;
        $pk->objectiveName = $this->objectiveName;
        $pk->displayName = $this->displayName;
        $pk->criteriaName = "dummy";
        $pk->sortOrder = $this->sortOrder;
        $player->sendDataPacket($pk);
        $this->getStore()->addViewer($this->objectiveName, $player->getName());
        if ($this->displaySlot === DisplaySlot::BELOWNAME) {
            $player->setScoreTag($this->displayName);
        }
    }

    /**
     * @param $player
     */
    public function removeDisplay(Player $player)
    {
        $pk = new RemoveObjectivePacket();
        $pk->objectiveName = $this->objectiveName;
        $player->sendDataPacket($pk);

        $this->getStore()->removeViewer($this->objectiveName, $player->getName());
    }

    /**
     * @param Player $player
     * @param int $line
     * @param string $message
     */
    public function setLine(Player $player, int $line, string $message)
    {
        $pk = new SetScorePacket();
        $pk->type = SetScorePacket::TYPE_REMOVE;
        $entry = new ScorePacketEntry();
        $entry->objectiveName = $this->objectiveName;
        $entry->score = self::MAX_LINES - $line;
        $entry->scoreboardId = ($this->scoreboardId + $line);
        $pk->entries[] = $entry;
        $player->sendDataPacket($pk);
        $pk = new SetScorePacket();
        $pk->type = SetScorePacket::TYPE_CHANGE;
        if (!$this->getStore()->entryExist($this->objectiveName, ($line - 2)) && $line !== 1) {
            for ($i = 1; $i <= ($line - 1); $i++) {
                if (!$this->getStore()->entryExist($this->objectiveName, ($i - 1))) {
                    $entry = new ScorePacketEntry();
                    $entry->objectiveName = $this->objectiveName;
                    $entry->type = ScorePacketEntry::TYPE_FAKE_PLAYER;
                    $entry->customName = str_repeat(" ", $i); //You can't send two lines with the same message
                    $entry->score = self::MAX_LINES - $i;
                    $entry->scoreboardId = ($this->scoreboardId + $i - 1);
                    $pk->entries[] = $entry;
                    $this->getStore()->addEntry($this->objectiveName, ($i - 1), $entry);
                }
            }
        }
        $entry = new ScorePacketEntry();
        $entry->objectiveName = $this->objectiveName;
        $entry->type = ScorePacketEntry::TYPE_FAKE_PLAYER;
        $entry->customName = $message;
        $entry->score = self::MAX_LINES - $line;
        $entry->scoreboardId = ($this->scoreboardId + $line);
        $pk->entries[] = $entry;
        $this->getStore()->addEntry($this->objectiveName, ($line - 1), $entry);
        $player->sendDataPacket($pk);
    }

    /**
     * @param Player $player
     * @param int $line
     */
    public function removeLine(Player $player, int $line)
    {
        $pk = new SetScorePacket();
        $pk->type = SetScorePacket::TYPE_REMOVE;
        $entry = new ScorePacketEntry();
        $entry->objectiveName = $this->objectiveName;
        $entry->score = self::MAX_LINES - $line;
        $entry->scoreboardId = ($this->scoreboardId + $line);
        $pk->entries[] = $entry;
        $player->sendDataPacket($pk);
        $this->getStore()->removeEntry($this->objectiveName, $line);
    }

    /**
     * @param string $displaySlot
     * @param int $sortOrder
     */
    public function create(string $displaySlot, int $sortOrder)
    {
        $this->displaySlot = $displaySlot;
        $this->sortOrder = $sortOrder;
        $this->scoreboardId = mt_rand(1, 100000);
        $this->getStore()->registerScoreboard($this->objectiveName, $this->displayName, $this->displaySlot, $this->sortOrder, $this->scoreboardId);
    }

    public function delete()
    {
        $this->getStore()->unregisterScoreboard($this->objectiveName, $this->displayName);
    }

    /**
     * @param string $newName
     */
    public function rename(string $newName)
    {
        $this->getStore()->rename($this->displayName, $newName);
        $this->displayName = $newName;
        $pk = new RemoveObjectivePacket();
        $pk->objectiveName = $this->objectiveName;
        $pk2 = new SetDisplayObjectivePacket();
        $pk2->displaySlot = $this->displaySlot;
        $pk2->objectiveName = $this->objectiveName;
        $pk2->displayName = $this->displayName;
        $pk2->criteriaName = "dummy";
        $pk2->sortOrder = $this->sortOrder;
        $pk3 = new SetScorePacket();
        $pk3->type = SetScorePacket::TYPE_CHANGE;
        foreach ($this->getStore()->getEntries($this->objectiveName) as $index => $entry) {
            $pk3->entries[$index] = $entry;
        }
        foreach ($this->getStore()->getViewers($this->objectiveName) as $name) {
            $p = Server::getInstance()->getPlayer($name);
            $p->sendDataPacket($pk);
            $p->sendDataPacket($pk2);
            $p->sendDataPacket($pk3);
        }
    }

    /**
     * @return string[]
     */
    public static function getStore(): ScoreboardStore
    {
        if (!is_null(self::$store)) {
            return self::$store;
        }

        return self::$store = new ScoreboardStore();
    }

    public function getViewers(): array
    {
        return $this->getStore()->getViewers($this->objectiveName);
    }
}