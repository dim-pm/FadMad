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

use pocketmine\network\mcpe\protocol\types\ScorePacketEntry;

class ScoreboardStore
{

    /** @var array */
    private $entries;

    /** @var array */
    private $scoreboards;

    /** @var array */
    private $displaySlots;

    /** @var array */
    private $sortOrders;

    /** @var array */
    private $ids;

    /** @var array */
    private $viewers;

    /**
     * @param string $objectiveName
     * @param int $line
     * @param ScorePacketEntry $entry
     */
    public function addEntry(string $objectiveName, int $line, ScorePacketEntry $entry)
    {
        $this->entries[$objectiveName][$line] = $entry;
    }

    /**
     * @param string $objectiveName The identification of the scoreboard
     * @param int $line The line of the scoreboard
     */
    public function removeEntry(string $objectiveName, int $line)
    {
        unset($this->entries[$objectiveName][$line]);
    }

    /**
     * @param string $objectiveName The identification of the scoreboard
     * @param string $displayName The scoreboard name on specific line
     * @param string $displaySlot The scoreboard line that the name will shown.
     * @param int $sortOrder The order of the scoreboard, 0: ascending/1: descending
     * @param int $scoreboardId The random scoreboard ID.
     */
    public function registerScoreboard(string $objectiveName, string $displayName, string $displaySlot, int $sortOrder, int $scoreboardId)
    {
        $this->entries[$objectiveName] = null;
        $this->scoreboards[$displayName] = $objectiveName;
        $this->displaySlots[$objectiveName] = $displaySlot;
        $this->sortOrders[$objectiveName] = $sortOrder;
        $this->ids[$objectiveName] = $scoreboardId;
        $this->viewers[$objectiveName] = [];
    }

    /**
     * @param string $objectiveName The scoreboard objective name.
     * @param string $displayName The display name of it
     */
    public function unregisterScoreboard(string $objectiveName, string $displayName)
    {
        unset($this->entries[$objectiveName]);
        unset($this->scoreboards[$displayName]);
        unset($this->displaySlots[$objectiveName]);
        unset($this->sortOrders[$objectiveName]);
        unset($this->ids[$objectiveName]);
        unset($this->viewers[$objectiveName]);
    }

    /**
     * @param string $objectiveName
     *
     * @return array
     */
    public function getEntries(string $objectiveName): array
    {
        return $this->entries[$objectiveName];
    }

    /**
     * @param string $objectiveName
     * @param int $line
     * @return bool
     */

    public function entryExist(string $objectiveName, int $line): bool
    {
        return isset($this->entries[$objectiveName][$line]);
    }

    /**
     * @param string $displayName
     * @return string|null
     */

    public function getId(string $displayName)
    {
        return $this->scoreboards[$displayName] ?? null;
    }

    /**
     * @param string $objectiveName
     * @return string
     */

    public function getDisplaySlot(string $objectiveName): string
    {
        return $this->displaySlots[$objectiveName];
    }

    /**
     * @param string $objectiveName
     * @return int
     */

    public function getSortOrder(string $objectiveName): int
    {
        return $this->sortOrders[$objectiveName];
    }

    /**
     * @param string $objectiveName
     * @return int
     */

    public function getScoreboardId(string $objectiveName): int
    {
        return $this->ids[$objectiveName];
    }

    /**
     * @param string $objectiveName
     * @param string $playerName
     */

    public function addViewer(string $objectiveName, string $playerName)
    {
        if (!in_array($playerName, $this->viewers[$objectiveName])) {
            array_push($this->viewers[$objectiveName], $playerName);
        }
    }

    /**
     * @param string $objectiveName
     * @param string $playerName
     */

    public function removeViewer(string $objectiveName, string $playerName)
    {
        if (in_array($playerName, $this->viewers[$objectiveName])) {
            if (($key = array_search($playerName, $this->viewers[$objectiveName])) !== false) {
                unset($this->viewers[$objectiveName][$key]);
            }
        }
    }

    /**
     * @param string $objectiveName
     * @return string[]|null
     */
    public function getViewers(string $objectiveName): ?array
    {
        return $this->viewers[$objectiveName] ?? null;
    }

    /**
     * @param string $oldName
     * @param string $newName
     */

    public function rename(string $oldName, string $newName)
    {
        $this->scoreboards[$newName] = $this->scoreboards[$oldName];
        unset($this->scoreboards[$oldName]);
    }

    /**
     * @param string $playerName
     */

    public function removePotentialViewer(string $playerName)
    {
        foreach ($this->viewers as $name => $data) {
            if (in_array($playerName, $data)) {
                if (($key = array_search($playerName, $data)) !== false) {
                    unset($this->viewers[$name][$key]);
                }
            }
        }
    }

    /**
     * @param string $displayName
     * @return string|null
     */

    public function getScoreboardName(string $displayName): ?string
    {
        return $this->scoreboards[$displayName] ?? null;
    }
}