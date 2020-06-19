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

namespace Fadhel\Core;

use Fadhel\Core\commands\Add;
use Fadhel\Core\commands\Remove;
use Fadhel\Core\commands\Shop;
use Fadhel\Core\commands\tag;
use Fadhel\Core\listeners\EnderPearls;
use Fadhel\Core\listeners\Ranks;
use Fadhel\Core\utils\form\ModalForm;
use Fadhel\Core\utils\tasks\Others\Fight;
use Fadhel\Core\utils\tasks\Others\Status;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\entity\EntityIds;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerCommandPreprocessEvent;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerExhaustEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\event\server\QueryRegenerateEvent;
use pocketmine\item\enchantment\Enchantment;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\Item;
use pocketmine\level\sound\AnvilBreakSound;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\AddActorPacket;
use pocketmine\network\mcpe\protocol\LoginPacket;
use pocketmine\Player;
use pocketmine\utils\TextFormat as C;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use Fadhel\Core\commands\Vanish;
use Fadhel\Core\commands\Report;
use Fadhel\Core\commands\setRank;
use Fadhel\Core\events\PlayerChat;
use Fadhel\Core\data\Players;
use Fadhel\Core\utils\form\SimpleForm;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use pocketmine\utils\TextFormat;
use Fadhel\Core\commands\Tags;
use pocketmine\entity\Entity;

class Main extends PluginBase implements Listener
{
    private $commands = [];
    private $prefix = "Core";
    private $compass;
    private $stat;
    public $tag;
    public $db;
    public $coins;
    public $clicks;
    public $tags;
    public $msg;
    public $fighting;

    public function onLoad()
    {
        $this->getServer()->getCommandMap()->unregister($this->getServer()->getCommandMap()->getCommand("version"));
    }

    public function onEnable(): void
    {
        $this->db = new \SQLite3($this->getDataFolder() . "master.db");
        $this->db->exec("CREATE TABLE IF NOT EXISTS master (player TEXT PRIMARY KEY COLLATE NOCASE, kills INT , deaths INT , lvl INT , xp INT , streak INT , rank TEXT , tag TEXT);");
        $this->coins = new \SQLite3($this->getDataFolder() . "coins.db");
        $this->coins->exec("CREATE TABLE IF NOT EXISTS coins (player TEXT PRIMARY KEY COLLATE NOCASE, coins INT);");

        $this->tag = new \SQLite3($this->getDataFolder() . "tags.db");
        $this->tag->exec("CREATE TABLE IF NOT EXISTS tags (player TEXT PRIMARY KEY COLLATE NOCASE, lit INT, enhanced INT, mvp INT, ultimate INT, crusader INT, legend INT, overlord INT, experienced INT, ez INT, pyro INT, elite INT, windows10 INT, fresh INT, emperor INT, salty INT, pancakes INT, uwu INT, zoomer INT, dirt INT, god INT, king INT, gangster INT, hitman INT, mobster INT, loner INT, horion INT, injected INT, bustdown INT, pro INT, hacker INT, gucci INT, troll INT, nou INT, killer INT, gangbang INT, chugnub INT, cornhub INT, androidgod INT, iosgod INT);");
        $this->tags = $this->tag;

        @mkdir($this->getDataFolder());
        $this->saveConfig();

        $this->getServer()->getCommandMap()->register("report", $this->commands[] = new Report($this));
        $this->getServer()->getCommandMap()->register("settag", $this->commands[] = new Tags($this));

        $this->getServer()->getCommandMap()->register("vanish", $this->commands[] = new Vanish($this));
        $this->getServer()->getCommandMap()->register("rank", $this->commands[] = new setRank($this));
        $this->getServer()->getCommandMap()->register("add", $this->commands[] = new Add($this));
        $this->getServer()->getCommandMap()->register("remove", $this->commands[] = new Remove($this));
        $this->getServer()->getCommandMap()->register("tag", $this->commands[] = new tag($this));
        $this->getServer()->getCommandMap()->register("shop", $this->commands[] = new Shop($this));
        $this->getServer()->getCommandMap()->unregister($this->getServer()->getCommandMap()->getCommand("me"));
        $this->getServer()->getCommandMap()->unregister($this->getServer()->getCommandMap()->getCommand("kill"));

        $this->getServer()->getPluginManager()->registerEvents(new PlayerChat($this), $this);
        $this->getServer()->getPluginManager()->registerEvents(new EnderPearls($this), $this);

        $this->getServer()->getPluginManager()->registerEvents(new Players($this), $this);
        $this->getServer()->getPluginManager()->registerEvents($this, $this);

        $this->getServer()->getLogger()->notice(C::GREEN . $this->prefix . " Enabled");

        $this->getServer()->loadLevel("Gapple");
        $this->getServer()->loadLevel("PE");
        $this->getServer()->loadLevel("PvP");
        $this->getServer()->loadLevel("Soup");
        $this->getServer()->loadLevel("Combo");

    }

    /**
     * @param DataPacketReceiveEvent $event
     */
    public function onLogin(DataPacketReceiveEvent $event): void
    {
        $packet = $event->getPacket();
        if ($packet instanceof LoginPacket) {
            $this->os[$event->getPacket()->username] = $event->getPacket()->clientData["DeviceOS"];
        }
        if ($packet instanceof InventoryTransactionPacket) {
            $transactionType = $packet->transactionType;
            if ($transactionType === InventoryTransactionPacket::TYPE_USE_ITEM || $transactionType === InventoryTransactionPacket::TYPE_USE_ITEM_ON_ENTITY) {
                $player = $event->getPlayer();
                $this->addClick($player);
            }
        }
    }

    /**
     * @param Player $player
     * @return int
     */
    public function getClicks(Player $player): int
    {
        if (!isset($this->clicks[$player->getLowerCaseName()])) {
            return 0;
        }
        $time = $this->clicks[$player->getLowerCaseName()][0];
        $clicks = $this->clicks[$player->getLowerCaseName()][1];
        if ($time !== time()) {
            unset($this->clicks[$player->getLowerCaseName()]);
            return 0;
        }
        return $clicks;
    }

    /**
     * @param Player $player
     */
    public function addClick(Player $player): void
    {
        if (!isset($this->clicks[$player->getLowerCaseName()])) {
            $this->clicks[$player->getLowerCaseName()] = [time(), 0];
        }
        $time = $this->clicks[$player->getLowerCaseName()][0];
        $clicks = $this->clicks[$player->getLowerCaseName()][1];
        if ($time !== time()) {
            $time = time();
            $clicks = 0;
        }
        $clicks++;
        $this->clicks[$player->getLowerCaseName()] = [$time, $clicks];
    }

    /**
     * @param $os
     * @return string
     */
    public function getDevice($os)
    {
        switch ($os) {
            case 1:
                $device = "Android";
                break;
            case 2:
                $device = "iOS";
                break;
            case 3:
                $device = "Mac";
                break;
            case 4:
                $device = "FireOS";
                break;
            case 5:
                $device = "GearVR";
                break;
            case 6:
                $device = "Hololens";
                break;
            case 7:
                $device = "Win10";
                break;
            case 8:
                $device = "Win32";
                break;
            case 9:
                $device = "Unknown";
                break;
            case 10:
                $device = "PS4";
                break;
            case 11:
                $device = "NX";
                break;
            default:
                $device = "Xbox";
                break;
        }
        return $device;
    }

    /**
     * @param PlayerQuitEvent $event
     */
    public function onQuit(PlayerQuitEvent $event)
    {
        $event->setQuitMessage("§8[§c-§8] §c" . $event->getPlayer()->getName());
    }

    /**
     * @param CommandSender $sender
     * @param Command $command
     * @param string $label
     * @param array $args
     * @return bool
     */
    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool
    {
        switch ($command->getName()) {
            case "warp":
                if ($sender instanceof Player) {
                    $this->gui($sender);
                }
                break;
            case "ping":
                if ($sender instanceof Player) {
                    $sender->sendMessage(C::WHITE . "Your ping is " . C::GREEN . $sender->getPing() . "ms");
                }
                break;
            case "leaderboard":
                if ($sender instanceof Player) {
                    $this->LeaderBoard($sender);
                }
                break;
            case "version":
                $sender->sendMessage("§6================================\n§7GitHub: github.com/DimBis/FadMad\n§3Twitter: twitter.com/DevDim_\n§cYouTube: youtube.com/c/FadhelFS\n§6================================");
        }
        return true;
    }

    /**
     * @return int
     */
    public function counter(): int
    {
        return count($this->getServer()->getLevelByName("PvP")->getPlayers()) + count($this->getServer()->getLevelByName("PE")->getPlayers());
    }

    /**
     * @param $player
     */
    public function gui($player)
    {
        $form = new SimpleForm(function (Player $event, $data) {
            $player = $event->getPlayer();
            if ($data === null) {
                return;
            }
            switch ($data) {
                case 0:
                    $player->getInventory()->clearAll();
                    $player->getArmorInventory()->clearAll();
                    $player->setHealth(20);
                    $player->setFood(20);
                    $player->removeAllEffects();
                    $player->sendMessage("§9You warped to §dCombo Arena");
                    $player->teleport($this->getServer()->getLevelByName("Combo")->getSafeSpawn());
                    $player->getInventory()->setItem(0, Item::get(399, 0, 1)->setCustomName("§r§l§9Combo Kit"));
                    $player->getInventory()->setItem(8, Item::get(351, 1, 1)->setCustomName("§r§l§9Hub"));
                    break;
                case 1:
                    $player->getInventory()->clearAll();
                    $player->getArmorInventory()->clearAll();
                    $player->setHealth(20);
                    $player->setFood(20);
                    $player->removeAllEffects();
                    $player->sendMessage("§9You warped to §6Gapple");
                    $player->teleport($this->getServer()->getLevelByName("Gapple")->getSafeSpawn());
                    $player->getInventory()->setItem(0, Item::get(399, 0, 1)->setCustomName("§r§l§9Gapple Kit"));
                    $player->getInventory()->setItem(8, Item::get(351, 1, 1)->setCustomName("§r§l§9Hub"));
                    break;
                case 2:
                    $player->getInventory()->clearAll();
                    $player->getArmorInventory()->clearAll();
                    $player->setHealth(20);
                    $player->setFood(20);
                    $player->removeAllEffects();
                    $player->sendMessage("§9You warped to §4Soup");
                    $player->teleport($this->getServer()->getLevelByName("Soup")->getSafeSpawn());
                    $player->getInventory()->setItem(0, Item::get(399, 0, 1)->setCustomName("§r§l§9Soup Kit"));
                    $player->getInventory()->setItem(8, Item::get(351, 1, 1)->setCustomName("§r§l§9Hub"));
                    break;
                case 3:
                    $this->NodeBuff($player);
                    break;
                case 4:
                    $player->setHealth(20);
                    $player->setFood(20);
                    $player->removeAllEffects();
                    $player->getInventory()->clearAll();
                    $player->getArmorInventory()->clearAll();
                    $player->sendMessage("§9You warped to §cHub");
                    $transfer = Item::get(345, 0, 1)->setCustomName(C::RESET . C::BOLD . C::AQUA . "Transfer");
                    $stats = Item::get(340, 0, 1)->setCustomName(C::RESET . C::BOLD . C::GREEN . "My stats");
                    $gads = Item::get(54, 0, 1)->setCustomName(C::RESET . C::BOLD . C::YELLOW . "Tags Collection");
                    $leaderboard = Item::get(339, 0, 1)->setCustomName(C::RESET . C::BOLD . C::RED . "Leaderboard");
                    $shop = Item::get(399, 0, 1)->setCustomName(C::RESET . C::BOLD . C::GOLD . "Black Market");
                    $transfer->setLore(["§r§bThis item transfers you to the Warps instead of running /warp"]);
                    $stats->setLore(["§aShows your current status"]);
                    $gads->setLore(["§r§eThis item shows the Tags list instead of running /tags"]);
                    $leaderboard->setLore(["§r§cLeaderboards"]);
                    $shop->setLore(["§r§6Shop"]);
                    $player->getInventory()->setItem(4, $transfer);
                    $player->getInventory()->setItem(6, $stats);
                    $player->getInventory()->setItem(2, $gads);
                    $player->getInventory()->setItem(8, $leaderboard);
                    $player->getInventory()->setItem(0, $shop);
                    $player->teleport($this->getServer()->getDefaultLevel()->getSafeSpawn());
            }
        });
        $form->setTitle("Transfer");
        $form->setContent("Select warp");
        $form->addButton("Combo\n§l§3» §r§bCurrently playing: §9" . count($this->getServer()->getLevelByName("Combo")->getPlayers()), 0, "textures/items/iron_sword");
        $form->addButton("Gapple\n§l§3» §r§bCurrently playing: §9" . count($this->getServer()->getLevelByName("Gapple")->getPlayers()), 0, "textures/items/apple_golden");
        $form->addButton("Soup\n§l§3» §r§bCurrently playing: §9" . count($this->getServer()->getLevelByName("Soup")->getPlayers()), 0, "textures/items/mushroom_stew");
        $form->addButton("Pot PvP / Pot PE\n§l§3» §r§bCurrently playing: §9" . $this->counter(), 0, "textures/items/potion_bottle_splash_heal");
        $form->addButton("Lobby", 0, "textures/blocks/barrier");
        $form->sendToPlayer($player);
    }

    /**
     * @param Player $player
     */
    public function stats(Player $player): void
    {
        $form = new SimpleForm(function (Player $event, $data) {
            $player = $event->getPlayer();
            if ($data === null) {
                return;
            }
            switch ($data) {
                case 0:
                    $player->sendMessage("§9Your total Deaths is: §b" . $this->getDeaths($player));
                    break;
                case 1:
                    $player->sendMessage("§9Your total Kills is: §b" . $this->getKills($player));
                    break;
                case 2:
                    $player->sendMessage("§9Your Level is: §b" . $this->getLevel($player));
                    break;
                case 3:
                    $player->sendMessage("§9Your current Streaks is: §b" . $this->getStreak($player));
                    break;
                case 4:
                    $player->sendMessage("§9Your total Coins is: §b" . $this->getCoins($player));
                    break;
                case 5:
                    $player->sendMessage("§9Your current XP is: §b" . $this->getXP($player));
            }
        });
        $form->setTitle("Your stats");
        $form->addButton("Deaths\n§l§3» §r§bTotal: §9" . $this->getDeaths($player), 0, "textures/items/redstone_dust");
        $form->addButton("Kills\n§l§3» §r§bTotal: §9" . $this->getKills($player), 0, "textures/items/wood_sword");
        $form->addButton("Level\n§l§3» §r§9" . $this->getLevel($player), 0, "textures/items/diamond");
        $form->addButton("Streaks\n§l§3» §r§bCurrent: §9" . $this->getStreak($player), 0, "textures/items/book_writable");
        $form->addButton("Coins\n§l§3» §r§bTotal: §e" . $this->getCoins($player), 0, "textures/items/gold_ingot");
        $form->addButton("XP\n§l§3» §r§bCurrent: §9" . $this->getXP($player) . "/5000", 0, "textures/items/diamond");
        $form->sendToPlayer($player);
    }

    /**
     * @param PlayerInteractEvent $event
     */
    public function onClick(PlayerInteractEvent $event): void
    {
        $player = $event->getPlayer();
        $itemname = $event->getPlayer()->getInventory()->getItemInHand()->getCustomName();
        $cooldown = 1;
        if ($itemname === "§r§l§bTransfer") {
            if (!isset($this->compass[strtolower($player->getName())])) {
                $this->compass[strtolower($player->getName())] = time();
                $this->gui($player);
            } else {
                if (time() - $this->compass[strtolower($player->getName())] < $cooldown) {
                    $event->setCancelled(true);
                } else {
                    $this->gui($player);
                    $this->compass[strtolower($player->getName())] = time();
                }
            }
        } elseif ($itemname === "§r§l§aMy stats") {
            $cooldown = 1;
            if (!isset($this->stat[strtolower($player->getName())])) {
                $this->stat[strtolower($player->getName())] = time();
                $this->stats($player);
            } else {
                if (time() - $this->stat[strtolower($player->getName())] < $cooldown) {
                    $event->setCancelled(true);
                } else {
                    $this->stat[strtolower($player->getName())] = time();
                    $this->stats($player);
                }
            }
        } elseif ($itemname === "§r§l§eTags Collection") {
            $cooldown = 1;
            if (!isset($this->tagz[strtolower($player->getName())])) {
                $this->tagz[strtolower($player->getName())] = time();
                $this->getServer()->dispatchCommand($player, "st");
            } else {
                if (time() - $this->tagz[strtolower($player->getName())] < $cooldown) {
                    $event->setCancelled(true);
                } else {
                    $this->tagz[strtolower($player->getName())] = time();
                    $this->getServer()->dispatchCommand($player, "st");
                }
            }
        } elseif ($itemname === "§r§l§cLeaderboard") {
            $cooldown = 1;
            if (!isset($this->leaderboard[strtolower($player->getName())])) {
                $this->leaderboard[strtolower($player->getName())] = time();
                $this->getServer()->dispatchCommand($player, "leaderboard");
            } else {
                if (time() - $this->leaderboard[strtolower($player->getName())] < $cooldown) {
                    $event->setCancelled(true);
                } else {
                    $this->leaderboard[strtolower($player->getName())] = time();
                    $this->getServer()->dispatchCommand($player, "leaderboard");
                }
            }
        } elseif ($itemname === "§r§l§6Black Market") {
            $cooldown = 1;
            if (!isset($this->shop[strtolower($player->getName())])) {
                $this->shop[strtolower($player->getName())] = time();
                $this->getServer()->dispatchCommand($player, "shop");
            } else {
                if (time() - $this->shop[strtolower($player->getName())] < $cooldown) {
                    $event->setCancelled(true);
                } else {
                    $this->shop[strtolower($player->getName())] = time();
                    $this->getServer()->dispatchCommand($player, "shop");
                }
            }
        } elseif ($itemname === "§r§l§9Hub") {
            $player->getInventory()->clearAll();
            $player->getArmorInventory()->clearAll();
            $player->setHealth(20);
            $player->setFood(20);
            $player->sendMessage("§9You warped to §bHub");
            $transfer = Item::get(345, 0, 1)->setCustomName(C::RESET . C::BOLD . C::AQUA . "Transfer");
            $stats = Item::get(340, 0, 1)->setCustomName(C::RESET . C::BOLD . C::GREEN . "My stats");
            $gads = Item::get(54, 0, 1)->setCustomName(C::RESET . C::BOLD . C::YELLOW . "Tags Collection");
            $leaderboard = Item::get(339, 0, 1)->setCustomName(C::RESET . C::BOLD . C::RED . "Leaderboard");
            $shop = Item::get(399, 0, 1)->setCustomName(C::RESET . C::BOLD . C::GOLD . "Black Market");
            $transfer->setLore(["§r§bThis item transfers you to the Warps instead of running /warp"]);
            $stats->setLore(["§aShows your current status"]);
            $gads->setLore(["§r§eThis item shows the Tags list instead of running /tags"]);
            $leaderboard->setLore(["§r§cLeaderboards"]);
            $shop->setLore(["§r§6Shop"]);
            $player->getInventory()->setItem(4, $transfer);
            $player->getInventory()->setItem(6, $stats);
            $player->getInventory()->setItem(2, $gads);
            $player->getInventory()->setItem(8, $leaderboard);
            $player->getInventory()->setItem(0, $shop);
            $player->teleport($this->getServer()->getDefaultLevel()->getSafeSpawn());
        } elseif ($itemname === "§r§l§9Combo Kit") {
            $player->getInventory()->clearAll();
            $player->getArmorInventory()->clearAll();
            $player->setFood(20);
            $enchantment = Enchantment::getEnchantment(0);
            $enchantment2 = Enchantment::getEnchantment(9);
            $enchantment3 = Enchantment::getEnchantment(17);
            $unbreaking = new EnchantmentInstance($enchantment3, 3);
            $helmet = Item::get(310);
            $chestplate = Item::get(311);
            $leggings = Item::get(312);
            $boots = Item::get(313);
            $helmet->addEnchantment($unbreaking);
            $chestplate->addEnchantment($unbreaking);
            $leggings->addEnchantment($unbreaking);
            $boots->addEnchantment($unbreaking);
            $player->getArmorInventory()->setHelmet($helmet);
            $player->getArmorInventory()->setChestplate($chestplate);
            $player->getArmorInventory()->setLeggings($leggings);
            $player->getArmorInventory()->setBoots($boots);
            $player->getInventory()->setItem(0, Item::get(364, 0, 64));
        } elseif ($itemname === "§r§aSoup") {
            $event->setCancelled(true);
            $item = $event->getItem();
            $item->pop();
            $player->getInventory()->setItemInHand($item);
            $heal = mt_rand(2, 5);
            $player->setHealth($player->getHealth() + $heal);
            $player->setFood(20);
        } elseif ($itemname === "§r§l§9Pot PvP Kit") {
            $player->getInventory()->clearAll();
            $player->getArmorInventory()->clearAll();
            $player->setFood(20);
            $enchantment = Enchantment::getEnchantment(0);
            $enchantment2 = Enchantment::getEnchantment(9);
            $enchantment3 = Enchantment::getEnchantment(17);
            $protection = new EnchantmentInstance($enchantment, 1);
            $unbreaking = new EnchantmentInstance($enchantment3, 3);
            $sharp = new EnchantmentInstance($enchantment2, 2);
            $sword = Item::get(276);
            $helmet = Item::get(310);
            $chestplate = Item::get(311);
            $leggings = Item::get(312);
            $boots = Item::get(313);
            $helmet->addEnchantment($protection);
            $chestplate->addEnchantment($protection);
            $leggings->addEnchantment($protection);
            $boots->addEnchantment($protection);
            $helmet->addEnchantment($unbreaking);
            $chestplate->addEnchantment($unbreaking);
            $leggings->addEnchantment($unbreaking);
            $boots->addEnchantment($unbreaking);
            $sword->addEnchantment($unbreaking);
            $sword->addEnchantment($sharp);
            $player->getArmorInventory()->setHelmet($helmet);
            $player->getArmorInventory()->setChestplate($chestplate);
            $player->getArmorInventory()->setLeggings($leggings);
            $player->getArmorInventory()->setBoots($boots);
            $player->getInventory()->addItem($sword);
            $player->getInventory()->addItem(Item::get(438, 22, 35));
            $player->getInventory()->setItem(1, Item::get(368, 0, 12));
            $player->getInventory()->setItem(2, Item::get(364, 0, 32));
        } elseif ($itemname === "§r§l§9Soup Kit") {
            $player->getInventory()->clearAll();
            $player->getArmorInventory()->clearAll();
            $player->setFood(20);
            $enchantment = Enchantment::getEnchantment(0);
            $enchantment2 = Enchantment::getEnchantment(9);
            $enchantment3 = Enchantment::getEnchantment(17);
            $protection = new EnchantmentInstance($enchantment, 1);
            $unbreaking = new EnchantmentInstance($enchantment3, 3);
            $sharp = new EnchantmentInstance($enchantment2, 2);
            $sword = Item::get(276);
            $helmet = Item::get(310);
            $chestplate = Item::get(311);
            $leggings = Item::get(312);
            $boots = Item::get(313);
            $helmet->addEnchantment($protection);
            $chestplate->addEnchantment($protection);
            $leggings->addEnchantment($protection);
            $boots->addEnchantment($protection);
            $helmet->addEnchantment($unbreaking);
            $chestplate->addEnchantment($unbreaking);
            $leggings->addEnchantment($unbreaking);
            $boots->addEnchantment($unbreaking);
            $sword->addEnchantment($unbreaking);
            $sword->addEnchantment($sharp);
            $player->getArmorInventory()->setHelmet($helmet);
            $player->getArmorInventory()->setChestplate($chestplate);
            $player->getArmorInventory()->setLeggings($leggings);
            $player->getArmorInventory()->setBoots($boots);
            $player->getInventory()->addItem($sword);
            $player->getInventory()->addItem(Item::get(282, 0, 35));
            $player->getInventory()->setItem(1, Item::get(368, 0, 12));
            $player->getInventory()->setItem(2, Item::get(364, 0, 32)->setCustomName("§r§aSoup"));
            $player->setFood(20);
        } elseif ($itemname === "§r§l§9Gapple Kit") {
            $player->getInventory()->clearAll();
            $player->getArmorInventory()->clearAll();
            $player->setFood(20);
            $enchantment = Enchantment::getEnchantment(0);
            $enchantment2 = Enchantment::getEnchantment(9);
            $enchantment3 = Enchantment::getEnchantment(17);
            $protection = new EnchantmentInstance($enchantment, 1);
            $unbreaking = new EnchantmentInstance($enchantment3, 3);
            $sharp = new EnchantmentInstance($enchantment2, 2);
            $sword = Item::get(276);
            $helmet = Item::get(310);
            $chestplate = Item::get(311);
            $leggings = Item::get(312);
            $boots = Item::get(313);
            $helmet->addEnchantment($protection);
            $chestplate->addEnchantment($protection);
            $leggings->addEnchantment($protection);
            $boots->addEnchantment($protection);
            $helmet->addEnchantment($unbreaking);
            $chestplate->addEnchantment($unbreaking);
            $leggings->addEnchantment($unbreaking);
            $boots->addEnchantment($unbreaking);
            $sword->addEnchantment($unbreaking);
            $sword->addEnchantment($sharp);
            $player->getArmorInventory()->setHelmet($helmet);
            $player->getArmorInventory()->setChestplate($chestplate);
            $player->getArmorInventory()->setLeggings($leggings);
            $player->getArmorInventory()->setBoots($boots);
            $player->getInventory()->addItem($sword);
            $player->getInventory()->addItem(Item::get(322, 0, 32));
            $player->getInventory()->addItem(Item::get(364, 0, 32));
        }
    }

    /**
     * @param Player $player
     */
    public function NodeBuff(Player $player): void
    {
        $form = new ModalForm(function (Player $event, $data) {
            $player = $event->getPlayer();
            if ($data === null) {
                return;
            }
            switch ($data) {
                case 1:
                    $player->getInventory()->clearAll();
                    $player->getArmorInventory()->clearAll();
                    $player->sendMessage("§9You warped to §bPot PvP");
                    $player->teleport($this->getServer()->getLevelByName("PvP")->getSafeSpawn());
                    $player->getInventory()->setItem(0, Item::get(399, 0, 1)->setCustomName("§r§l§9Pot PvP Kit"));
                    $player->getInventory()->setItem(8, Item::get(351, 1, 1)->setCustomName("§r§l§9Hub"));
                    break;
                case 0:
                    $device = array("Android", "FireOS", "iOS");
                    if (in_array($this->getDevice($this->os[$player->getName()]), $device)) {
                        $player->getInventory()->clearAll();
                        $player->getArmorInventory()->clearAll();
                        $player->sendMessage("§9You warped to §bPot PE");
                        $player->teleport($this->getServer()->getLevelByName("PE")->getSafeSpawn());
                        $player->getInventory()->setItem(0, Item::get(399, 0, 1)->setCustomName("§r§l§9Pot PvP Kit"));
                        $player->getInventory()->setItem(8, Item::get(351, 1, 1)->setCustomName("§r§l§9Hub"));
                    } else {
                        $player->sendMessage(C::RED . "You're not Minecraft Pocket Edition player, Your device is " . $this->getDevice($this->os[$player->getName()]) . "!");
                    }
            }
        });
        $form->setTitle("Pot PvP");
        $form->setContent("You have to select an mode to resume playing!\n\nNormal:\n§l§3» §r§bCurrently playing: §9" . count($this->getServer()->getLevelByName("PvP")->getPlayers()) . "\n\n§rPE Only:\n§l§3» §r§bCurrently playing: §9" . count($this->getServer()->getLevelByName("PE")->getPlayers()));
        $form->setButton1("Normal");
        $form->setButton2("PE Only");
        $form->sendToPlayer($player);
    }

    /**
     * @param PlayerRespawnEvent $event
     */
    public function onRespawn(PlayerRespawnEvent $event): void
    {
        $player = $event->getPlayer();
        $transfer = Item::get(345, 0, 1)->setCustomName(C::RESET . C::BOLD . C::AQUA . "Transfer");
        $stats = Item::get(340, 0, 1)->setCustomName(C::RESET . C::BOLD . C::GREEN . "My stats");
        $gads = Item::get(54, 0, 1)->setCustomName(C::RESET . C::BOLD . C::YELLOW . "Tags Collection");
        $leaderboard = Item::get(339, 0, 1)->setCustomName(C::RESET . C::BOLD . C::RED . "Leaderboard");
        $shop = Item::get(399, 0, 1)->setCustomName(C::RESET . C::BOLD . C::GOLD . "Black Market");
        $transfer->setLore(["§r§bThis item transfers you to the Warps instead of running /warp"]);
        $stats->setLore(["§aShows your current status"]);
        $gads->setLore(["§r§eThis item shows the Tags list instead of running /tags"]);
        $leaderboard->setLore(["§r§cLeaderboards"]);
        $shop->setLore(["§r§6Shop"]);
        $player->getInventory()->setItem(4, $transfer);
        $player->getInventory()->setItem(6, $stats);
        $player->getInventory()->setItem(2, $gads);
        $player->getInventory()->setItem(8, $leaderboard);
        $player->getInventory()->setItem(0, $shop);
        $this->addDeath($player);
        $this->resetStreak($player);
    }

    /**
     * @param Player $player
     * @return bool|string
     */
    public function getLevelFormat(Player $player)
    {
        if ($this->getLevel($player) <= 10) {
            return "§a";
        } elseif ($this->getLevel($player) >= 10 && $this->getLevel($player) < 20) {
            return "§2";
        } elseif ($this->getLevel($player) >= 20 && $this->getLevel($player) < 30) {
            return "§3";
        } elseif ($this->getLevel($player) >= 30 && $this->getLevel($player) < 40) {
            return "§6";
        } elseif ($this->getLevel($player) >= 40 && $this->getLevel($player) < 50) {
            return "§5";
        } elseif ($this->getLevel($player) >= 50 && $this->getLevel($player) < 60) {
            return "§c";
        } elseif ($this->getLevel($player) >= 60) {
            return "§4";
        }
        return true;
    }

    /**
     * @param PlayerJoinEvent $event
     */
    public function onJoin(PlayerJoinEvent $event): void
    {
        $player = $event->getPlayer();
        $player->setHealth(20);
        $player->setFood(20);
        $event->setJoinMessage("§8[§a+§8] §a" . $player->getName());
        $player->getInventory()->clearAll();
        $player->getArmorInventory()->clearAll();
        $transfer = Item::get(345, 0, 1)->setCustomName(C::RESET . C::BOLD . C::AQUA . "Transfer");
        $stats = Item::get(340, 0, 1)->setCustomName(C::RESET . C::BOLD . C::GREEN . "My stats");
        $gads = Item::get(54, 0, 1)->setCustomName(C::RESET . C::BOLD . C::YELLOW . "Tags Collection");
        $leaderboard = Item::get(339, 0, 1)->setCustomName(C::RESET . C::BOLD . C::RED . "Leaderboard");
        $shop = Item::get(399, 0, 1)->setCustomName(C::RESET . C::BOLD . C::GOLD . "Black Market");
        $transfer->setLore(["§r§bThis item transfers you to the Warps instead of running /warp"]);
        $stats->setLore(["§aShows your current status"]);
        $gads->setLore(["§r§eThis item shows the Tags list instead of running /tags"]);
        $leaderboard->setLore(["§r§cLeaderboards"]);
        $shop->setLore(["§r§6Shop"]);
        $player->getInventory()->setItem(4, $transfer);
        $player->getInventory()->setItem(6, $stats);
        $player->getInventory()->setItem(2, $gads);
        $player->getInventory()->setItem(8, $leaderboard);
        $player->getInventory()->setItem(0, $shop);
        $player->removeAllEffects();
        $player->addTitle($this->getConfig()->get("join-title"));
        $player->setGamemode(2);
        $ranks = new Ranks($this);
        $rank = $this->getRank($player);
        $format = $ranks->getFormat($rank);
        $tag = $this->getTag($player);
        $type = $ranks->getType($tag);
        $this->msg[$player->getName()] = "";
        $this->getScheduler()->scheduleRepeatingTask(new Status($this, $player), 20);
        $ranks->setPermission($player);
        $this->fighting[$player->getName()] = "none";
        if ($tag !== "none") {
            $player->setNameTag(C::GRAY . "[" . $this->getLevelFormat($player) . $this->getLevel($player) . C::GRAY . "] " . $type . $format . $player->getName());
        } else {
            $player->setNameTag(C::GRAY . "[" . $this->getLevelFormat($player) . $this->getLevel($player) . C::GRAY . "] " . $format . $player->getName());
        }
    }

    /**
     * @param PlayerDeathEvent $event
     */
    public function onDeath(PlayerDeathEvent $event): void
    {
        $event->setDrops([]);
        $event->setDeathMessage("");
    }

    /**
     * @param EntityDamageEvent $ev
     */
    public function onDamage(EntityDamageEvent $ev): void
    {
        if ($ev->getEntity() instanceof Player) {
            $p = $ev->getEntity();
            $player = $p;
            if ($p->getHealth() - $ev->getFinalDamage() < 0) {
                if ($player instanceof Player) {
                    if ($ev instanceof EntityDamageByEntityEvent) {
                        if ($player->getLevel()->getName() !== "Hub") {
                            if (!$player->isCreative()) {
                                $this->fighting[$player->getName()] = "none";
                                $lightning = new AddActorPacket();
                                $lightning->type = AddActorPacket::LEGACY_ID_MAP_BC[EntityIds::LIGHTNING_BOLT];
                                $lightning->entityRuntimeId = Entity::$entityCount++;
                                $lightning->metadata = [];
                                $lightning->position = $player->asVector3()->add(0, $height = 0);
                                $lightning->yaw = $player->getYaw();
                                $lightning->pitch = $player->getPitch();
                                $player->getServer()->broadcastPacket($player->getLevel()->getPlayers(), $lightning);
                                $player->teleport($this->getServer()->getDefaultLevel()->getSafeSpawn());
                                $player->getInventory()->clearAll();
                                $player->getArmorInventory()->clearAll();
                                $player->setHealth(20);
                                $player->setFood(20);
                                $player->removeAllEffects();
                                $this->addDeath($player);
                                $ev->setCancelled(true);
                                $this->resetStreak($player);
                                $killer = $ev->getDamager();
                                $transfer = Item::get(345, 0, 1)->setCustomName(C::RESET . C::BOLD . C::AQUA . "Transfer");
                                $stats = Item::get(340, 0, 1)->setCustomName(C::RESET . C::BOLD . C::GREEN . "My stats");
                                $gads = Item::get(54, 0, 1)->setCustomName(C::RESET . C::BOLD . C::YELLOW . "Tags Collection");
                                $leaderboard = Item::get(339, 0, 1)->setCustomName(C::RESET . C::BOLD . C::RED . "Leaderboard");
                                $shop = Item::get(399, 0, 1)->setCustomName(C::RESET . C::BOLD . C::GOLD . "Black Market");
                                $transfer->setLore(["§r§bThis item transfers you to the Warps instead of running /warp"]);
                                $stats->setLore(["§aShows your current status"]);
                                $gads->setLore(["§r§eThis item shows the Tags list instead of running /tags"]);
                                $leaderboard->setLore(["§r§cLeaderboards"]);
                                $shop->setLore(["§r§6Shop"]);
                                $player->getInventory()->setItem(4, $transfer);
                                $player->getInventory()->setItem(6, $stats);
                                $player->getInventory()->setItem(2, $gads);
                                $player->getInventory()->setItem(8, $leaderboard);
                                $player->getInventory()->setItem(0, $shop);
                                $ev->setCancelled();
                                if ($killer instanceof Player) {
                                    $this->fighting[$player->getName()] = "none";
                                    $xp = mt_rand(1, 600);
                                    $this->addXP($killer, $xp);
                                    $this->addStreak($killer);
                                    $this->addKill($killer);
                                    $killer->setHealth(20);
                                    $coins = mt_rand(50, 100);
                                    $this->addCoins($killer, $coins);
                                    $killer->sendTip("§9+" . $xp . " §bXP§9!");
                                    $words = array(" §eOOFED by§c ", " §etook the L by§c ", " §ewas killed by§c ", " §ewas slain by§c ", " §eroasted by §c");
                                    $msg = array_rand($words, 2);
                                    $this->getServer()->broadcastMessage("§9" . $player->getName() . $words[$msg[1]] . $killer->getName());
                                    $killer->getLevel()->addSound(new AnvilBreakSound(new Vector3($killer->getX(), $killer->getY(), $player->getZ())));
                                }
                            }
                        }
                    }
                }
            }
            switch ($ev->getCause()) {
                case EntityDamageEvent::CAUSE_FALL:
                    $ev->setCancelled(true);
                    break;
                case EntityDamageEvent::CAUSE_VOID:
                    $ev->setCancelled(true);
                    $player->teleport($player->getLevel()->getSafeSpawn());
                    break;
                case EntityDamageEvent::CAUSE_ENTITY_ATTACK:
                    $damager = $ev->getDamager();
                    if ($damager instanceof Player) {
                        if ($player->getLevel()->getName() !== "Hub") {
                            if ($player->getLevel()->getName() === "Combo" or $player->getLevel()->getName() === "Hub") {
                                $ev->setKnockBack(0.4);
                                $ev->setAttackCooldown(0.5);
                            } else {
                                if ($this->fighting[$player->getName()] === "none") {
                                    $levels = array("Combo", "PE", "PvP", "Gapple", "Soup");
                                    if (in_array($player->getLevel()->getFolderName(), $levels)) {
                                        $this->fighting[$player->getName()] = "fight";
                                        $this->getScheduler()->scheduleRepeatingTask(new Fight($this, $player), 20);
                                    }
                                }
                                $levels = array("Combo", "PE", "PvP", "Gapple", "Soup");
                                if (in_array($damager->getLevel()->getFolderName(), $levels)) {
                                    if ($this->fighting[$damager->getName()] === "none") {
                                        $this->fighting[$damager->getName()] = "fight";
                                        $this->getScheduler()->scheduleRepeatingTask(new Fight($this, $damager), 20);
                                    }
                                }
                            }
                        }
                    }
            }
        }
    }

    /**
     * @param Player $player
     */
    public function LeaderBoard(Player $player): void
    {
        $form = new SimpleForm(function (Player $event, $data) {
            $player = $event->getPlayer();
            if ($data === null) {
                return;
            }
            switch ($data) {
                case 0:
                    $this->deaths($player);
                    break;
                case 1:
                    $this->kills($player);
                    break;
                case 2:
                    $this->levels($player);
                    break;
                case 3:
                    $this->streaks($player);
                    break;
                case 4:
                    $this->topcoins($player);
            }
        });
        $form->setTitle("LeaderBoards");
        $form->addButton("Deaths", 0, "textures/items/redstone_dust");
        $form->addButton("Kills", 0, "textures/items/wood_sword");
        $form->addButton("Levels", 1, "textures/items/diamond");
        $form->addButton("Streaks", 0, "textures/items/book_writable");
        $form->addButton("Coins", 0, "textures/items/gold_ingot");
        $form->addButton("§l§cExit", 0, "textures/blocks/barrier");
        $form->sendToPlayer($player);
    }

    /**
     * @param Player $player
     */
    public function kills(Player $player): void
    {
        $form = new SimpleForm(function (Player $event, $data) {
            if ($data === null) {
                return;
            }
        });
        $config = new Config($this->getDataFolder() . "Kills.yml", Config::YAML);
        $cfg = $config->getAll();
        count($cfg);
        arsort($cfg);
        $leader = "";
        $i = 1;
        foreach ($cfg as $name => $amount) {
            $leader .= "         §b" . $i . " - §9" . $name . " §b" . $amount . "\n\n";
            if ($i > 9) {
                break;
            }
            ++$i;
        }
        $form->setTitle("Top Kills");
        $form->setContent("\n" . $leader);
        $form->addButton("§l§cExit", 1, "http://i63.tinypic.com/332021h_th.png");
        $form->sendToPlayer($player);
    }

    /**
     * @param $player
     * @return int
     */
    public function getCoin($player): int
    {
        $players = strtolower($player);
        $deaths = $this->coins->query("SELECT coins FROM coins WHERE player = '$players';");
        $array = $deaths->fetchArray(SQLITE3_ASSOC);
        return (int)$array["coins"];
    }

    /**
     * @param Player $player
     */
    public function topcoins(Player $player): void
    {
        $result = $this->coins->query("SELECT player FROM coins ORDER BY coins DESC LIMIT 10;");
        $i = 0;
        $player->sendMessage(TextFormat::AQUA . "Richest player");
        while ($resultArr = $result->fetchArray(SQLITE3_ASSOC)) {
            $j = $i + 1;
            $cf = $resultArr["player"];
            $pf = $this->getCoin($cf);
            $player->sendMessage(TextFormat::ITALIC . TextFormat::GOLD . "$j -> " . TextFormat::GREEN . "$cf" . TextFormat::GOLD . " with " . TextFormat::RED . "$pf coins");
            $i = $i + 1;
        }
    }

    /**
     * @param Player $player
     */
    public function deaths(Player $player)
    {
        $form = new SimpleForm(function (Player $event, $data) {
            if ($data === null) {
                return;
            }
        });
        $config = new Config($this->getDataFolder() . "Deaths.yml", Config::YAML);
        $cfg = $config->getAll();
        count($cfg);
        arsort($cfg);
        $leader = "";
        $i = 1;
        foreach ($cfg as $name => $amount) {
            $leader .= "         §b" . $i . " - §9" . $name . " §b" . $amount . "\n\n";
            if ($i > 9) {
                break;
            }
            ++$i;
        }
        $form->setTitle("Top Deaths");
        $form->setContent("\n" . $leader);
        $form->addButton("§l§cExit", 1, "http://i63.tinypic.com/332021h_th.png");
        $form->sendToPlayer($player);
    }

    /**
     * @param Player $player
     */
    public function streaks(Player $player): void
    {
        $form = new SimpleForm(function (Player $event, $data) {
            if ($data === null) {
                return;
            }
        });
        $config = new Config($this->getDataFolder() . "Streaks.yml", Config::YAML);
        $cfg = $config->getAll();
        count($cfg);
        arsort($cfg);
        $leader = "";
        $i = 1;
        foreach ($cfg as $name => $amount) {
            $leader .= "         §b" . $i . " - §9" . $name . " §b" . $amount . "\n\n";
            if ($i > 9) {
                break;
            }
            ++$i;
        }
        $form->setTitle("Top Streaks");
        $form->setContent("\n" . $leader);
        $form->addButton("§l§cExit", 1, "http://i63.tinypic.com/332021h_th.png");
        $form->sendToPlayer($player);
    }

    /**
     * @param Player $player
     */
    public function levels(Player $player): void
    {
        $form = new SimpleForm(function (Player $event, $data) {
            if ($data === null) {
                return;
            }
        });
        $config = new Config($this->getDataFolder() . "Levels.yml", Config::YAML);
        $cfg = $config->getAll();
        count($cfg);
        arsort($cfg);
        $leader = "";
        $i = 1;
        foreach ($cfg as $name => $amount) {
            $leader .= "         §b" . $i . " - §9" . $name . " §b" . $amount . "\n\n";
            if ($i > 9) {
                break;
            }
            ++$i;
        }
        $form->setTitle("Top Levels");
        $form->setContent("\n" . $leader);
        $form->addButton("§l§cExit", 1, "http://i63.tinypic.com/332021h_th.png");
        $form->sendToPlayer($player);
    }

    /**
     * @param BlockBreakEvent $event
     */
    public function onBreak(BlockBreakEvent $event): void
    {
        if (!$event->getPlayer()->isCreative()) {
            $event->setCancelled(true);
        }
    }

    /**
     * @param BlockPlaceEvent $event
     */
    public function onPlace(BlockPlaceEvent $event): void
    {
        if (!$event->getPlayer()->isCreative()) {
            $event->setCancelled(true);
        }
    }

    /**
     * @param PlayerExhaustEvent $event
     */
    public function onHunger(PlayerExhaustEvent $event): void
    {
        if ($event->getPlayer()->getLevel()->getName() === "Hub") {
            $event->setCancelled(true);
        }
    }

    /**
     * @param PlayerDropItemEvent $event
     */
    public function onDrop(PlayerDropItemEvent $event): void
    {
        if (!$event->getPlayer()->isCreative()) {
            $event->setCancelled(true);
        }
    }

    /**
     * @param PlayerCommandPreprocessEvent $event
     */
    public function onCMD(PlayerCommandPreprocessEvent $event): void
    {
        $command = $event->getMessage();
        if ($command{0} === "/") {
            $player = $event->getPlayer();
            if ($this->fighting[$player->getName()] !== "none") {
                $command = str_replace("/", "", explode(" ", strtolower($event->getMessage()))[0]);
                if ($command === "warp") {
                    $player->sendMessage(C::RED . "You can't warp while you're in Fight!");
                    $event->setCancelled();
                }
            }
        }
    }

    /**
     * @param Player $player
     * @param int $points
     */
    public function addKill(Player $player, int $points = 1): void
    {
        $stmt = $this->db->prepare("INSERT OR REPLACE INTO master (player, kills, deaths, lvl, xp, streak, rank, tag) VALUES (:player, :kills, :deaths, :lvl, :xp, :streak, :rank, :tag)");
        $stmt->bindValue(":player", $player->getName());
        $stmt->bindValue(":kills", $this->getKills($player) + $points);
        $stmt->bindValue(":deaths", $this->getDeaths($player));
        $stmt->bindValue(":lvl", $this->getLevel($player));
        $stmt->bindValue(":xp", $this->getXP($player));
        $stmt->bindValue(":streak", $this->getStreak($player));
        $stmt->bindValue(":rank", $this->getRank($player));
        $stmt->bindValue(":tag", $this->getTag($player));
        $stmt->execute();
        $cfg = new Config($this->getDataFolder() . "Kills.yml");
        $cfg->set($player->getName(), $cfg->get($player->getName()) + $points);
        $cfg->save();
    }

    /**
     * @param Player $player
     * @param int $points
     */
    public function addDeath(Player $player, int $points = 1): void
    {
        $stmt = $this->db->prepare("INSERT OR REPLACE INTO master (player, kills, deaths, lvl, xp, streak, rank, tag) VALUES (:player, :kills, :deaths, :lvl, :xp, :streak, :rank, :tag)");
        $stmt->bindValue(":player", $player->getName());
        $stmt->bindValue(":kills", $this->getKills($player));
        $stmt->bindValue(":deaths", $this->getDeaths($player) + $points);
        $stmt->bindValue(":lvl", $this->getLevel($player));
        $stmt->bindValue(":xp", $this->getXP($player));
        $stmt->bindValue(":streak", $this->getStreak($player));
        $stmt->bindValue(":rank", $this->getRank($player));
        $stmt->bindValue(":tag", $this->getTag($player));
        $stmt->execute();
        $cfg = new Config($this->getDataFolder() . "Deaths.yml");
        $cfg->set($player->getName(), $cfg->get($player->getName()) + $points);
        $cfg->save();
    }

    /**
     * @param Player $player
     * @param int $points
     */
    public function addCoins(Player $player, int $points): void
    {
        $stmt = $this->coins->prepare("INSERT OR REPLACE INTO coins (player, coins) VALUES (:player, :coins)");
        $stmt->bindValue(":player", $player->getName());
        $stmt->bindValue(":coins", $this->getCoins($player) + $points);
        $stmt->execute();
    }

    /**
     * @param Player $player
     * @param int $points
     */
    public function reduceCoins(Player $player, int $points): void
    {
        $stmt = $this->coins->prepare("INSERT OR REPLACE INTO coins (player, coins) VALUES (:player, :coins)");
        $stmt->bindValue(":player", $player->getName());
        $stmt->bindValue(":coins", $this->getCoins($player) - $points);
        $stmt->execute();
    }

    /**
     * @param Player $player
     * @param int $points
     */
    public function addStreak(Player $player, int $points = 1): void
    {
        $stmt = $this->db->prepare("INSERT OR REPLACE INTO master (player, kills, deaths, lvl, xp, streak, rank, tag) VALUES (:player, :kills, :deaths, :lvl, :xp, :streak, :rank, :tag)");
        $stmt->bindValue(":player", $player->getName());
        $stmt->bindValue(":kills", $this->getKills($player));
        $stmt->bindValue(":deaths", $this->getDeaths($player));
        $stmt->bindValue(":lvl", $this->getLevel($player));
        $stmt->bindValue(":xp", $this->getXP($player));
        $stmt->bindValue(":streak", $this->getStreak($player) + $points);
        $stmt->bindValue(":rank", $this->getRank($player));
        $stmt->bindValue(":tag", $this->getTag($player));
        $stmt->execute();
        $cfg = new Config($this->getDataFolder() . "Streaks.yml");
        $cfg->set($player->getName(), $this->getStreak($player));
        $cfg->save();
    }

    /**
     * @param Player $player
     * @return int
     */
    public function getKills(Player $player): int
    {
        $players = $player->getName();
        $kills = $this->db->query("SELECT kills FROM master WHERE player = '$players';");
        $array = $kills->fetchArray(SQLITE3_ASSOC);
        return (int)$array["kills"];
    }

    /**
     * @param Player $player
     * @return int
     */
    public function getDeaths(Player $player): int
    {
        $players = $player->getName();
        $deaths = $this->db->query("SELECT deaths FROM master WHERE player = '$players';");
        $array = $deaths->fetchArray(SQLITE3_ASSOC);
        return (int)$array["deaths"];
    }

    /**
     * @param Player $player
     * @return int
     */
    public function getCoins(Player $player): int
    {
        $players = $player->getName();
        $deaths = $this->coins->query("SELECT coins FROM coins WHERE player = '$players';");
        $array = $deaths->fetchArray(SQLITE3_ASSOC);
        return (int)$array["coins"];
    }

    /**
     * @param Player $player
     * @return int
     */
    public function getStreak(Player $player): int
    {
        $players = $player->getName();
        $streaks = $this->db->query("SELECT streak FROM master WHERE player = '$players';");
        $array = $streaks->fetchArray(SQLITE3_ASSOC);
        return (int)$array["streak"];
    }

    /**
     * @param Player $player
     * @param string $kit
     * @return bool
     */
    public function hasTag(Player $player, string $kit): bool
    {
        $players = $player->getName();
        $db = $this->tag->query("SELECT " . $kit . " FROM tags WHERE player = '$players';");
        $array = $db->fetchArray(SQLITE3_ASSOC);
        $result = (int)$array[strtolower($kit)];
        if ($this->coinTag($player)) {
            if ($result === 0) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param Player $player
     * @param string $tag
     * @return int
     */
    public function getInfo(Player $player, string $tag): int
    {
        $players = $player->getName();
        $db = $this->tag->query("SELECT " . $tag . " FROM tags WHERE player = '$players';");
        $array = $db->fetchArray(SQLITE3_ASSOC);
        $result = (int)$array[$tag];
        if ($this->coinTag($player)) {
            if ($result === 0) {
                return 0;
            } else {
                return 1;
            }
        }
        return false;
    }


    public function unlockTag(Player $player, $tag)
    {
        switch ($tag) {
            case "lit":
                $stmt = $this->tags->prepare("INSERT OR REPLACE INTO tags (player, lit, enhanced, mvp, ultimate, crusader, legend, overlord, experienced, ez, pyro, elite, windows10, fresh, emperor, salty, pancakes, uwu, zoomer, dirt, god, king, gangster, hitman, mobster, loner, horion, injected, bustdown, pro, hacker, gucci, troll, nou, killer, gangbang, chugnub, cornhub, androidgod, iosgod) VALUES (:player, :lit, :enhanced, :mvp, :ultimate, :crusader, :legend, :overlord, :experienced, :ez, :pyro, :elite, :windows10, :fresh, :emperor, :salty, :pancakes, :uwu, :zoomer, :dirt, :god, :king, :gangster, :hitman, :mobster, :loner, :horion, :injected, :bustdown, :pro, :hacker, :gucci, :troll, :nou, :killer, :gangbang, :chugnub, :cornhub, :androidgod, :iosgod)");
                $stmt->bindValue(":player", $player->getName());
                $stmt->bindValue(":lit", 1);
                $stmt->bindValue(":enhanced", $this->getInfo($player, "enhanced"));
                $stmt->bindValue(":mvp", $this->getInfo($player, "mvp"));
                $stmt->bindValue(":ultimate", $this->getInfo($player, "ultimate"));
                $stmt->bindValue(":crusader", $this->getInfo($player, "crusader"));
                $stmt->bindValue(":legend", $this->getInfo($player, "legend"));
                $stmt->bindValue(":overlord", $this->getInfo($player, "overlord"));
                $stmt->bindValue(":experienced", $this->getInfo($player, "experienced"));
                $stmt->bindValue(":ez", $this->getInfo($player, "ez"));
                $stmt->bindValue(":pyro", $this->getInfo($player, "pyro"));
                $stmt->bindValue(":elite", $this->getInfo($player, "elite"));
                $stmt->bindValue(":windows10", $this->getInfo($player, "windows10"));
                $stmt->bindValue(":fresh", $this->getInfo($player, "fresh"));
                $stmt->bindValue(":emperor", $this->getInfo($player, "emperor"));
                $stmt->bindValue(":salty", $this->getInfo($player, "salty"));
                $stmt->bindValue(":pancakes", $this->getInfo($player, "pancakes"));
                $stmt->bindValue(":uwu", $this->getInfo($player, "uwu"));
                $stmt->bindValue(":zoomer", $this->getInfo($player, "zoomer"));
                $stmt->bindValue(":dirt", $this->getInfo($player, "dirt"));
                $stmt->bindValue(":god", $this->getInfo($player, "god"));
                $stmt->bindValue(":king", $this->getInfo($player, "king"));
                $stmt->bindValue(":gangster", $this->getInfo($player, "gangster"));
                $stmt->bindValue(":hitman", $this->getInfo($player, "hitman"));
                $stmt->bindValue(":mobster", $this->getInfo($player, "mobster"));
                $stmt->bindValue(":loner", $this->getInfo($player, "loner"));
                $stmt->bindValue(":horion", $this->getInfo($player, "horion"));
                $stmt->bindValue(":injected", $this->getInfo($player, "injected"));
                $stmt->bindValue(":bustdown", $this->getInfo($player, "bustdown"));
                $stmt->bindValue(":pro", $this->getInfo($player, "pro"));
                $stmt->bindValue(":hacker", $this->getInfo($player, "hacker"));
                $stmt->bindValue(":gucci", $this->getInfo($player, "gucci"));
                $stmt->bindValue(":troll", $this->getInfo($player, "troll"));
                $stmt->bindValue(":nou", $this->getInfo($player, "nou"));
                $stmt->bindValue(":killer", $this->getInfo($player, "killer"));
                $stmt->bindValue(":gangbang", $this->getInfo($player, "gangbang"));
                $stmt->bindValue(":chugnub", $this->getInfo($player, "chugnub"));
                $stmt->bindValue(":cornhub", $this->getInfo($player, "cornhub"));
                $stmt->bindValue(":androidgod", $this->getInfo($player, "androidgod"));
                $stmt->bindValue(":iosgod", $this->getInfo($player, "iosgod"));
                $stmt->execute();
                break;
            case "enhanced":
                $stmt = $this->tags->prepare("INSERT OR REPLACE INTO tags (player, lit, enhanced, mvp, ultimate, crusader, legend, overlord, experienced, ez, pyro, elite, windows10, fresh, emperor, salty, pancakes, uwu, zoomer, dirt, god, king, gangster, hitman, mobster, loner, horion, injected, bustdown, pro, hacker, gucci, troll, nou, killer, gangbang, chugnub, cornhub, androidgod, iosgod) VALUES (:player, :lit, :enhanced, :mvp, :ultimate, :crusader, :legend, :overlord, :experienced, :ez, :pyro, :elite, :windows10, :fresh, :emperor, :salty, :pancakes, :uwu, :zoomer, :dirt, :god, :king, :gangster, :hitman, :mobster, :loner, :horion, :injected, :bustdown, :pro, :hacker, :gucci, :troll, :nou, :killer, :gangbang, :chugnub, :cornhub, :androidgod, :iosgod)");
                $stmt->bindValue(":player", $player->getName());
                $stmt->bindValue(":lit", $this->getInfo($player, "lit"));
                $stmt->bindValue(":enhanced", 1);
                $stmt->bindValue(":mvp", $this->getInfo($player, "mvp"));
                $stmt->bindValue(":ultimate", $this->getInfo($player, "ultimate"));
                $stmt->bindValue(":crusader", $this->getInfo($player, "crusader"));
                $stmt->bindValue(":legend", $this->getInfo($player, "legend"));
                $stmt->bindValue(":overlord", $this->getInfo($player, "overlord"));
                $stmt->bindValue(":experienced", $this->getInfo($player, "experienced"));
                $stmt->bindValue(":ez", $this->getInfo($player, "ez"));
                $stmt->bindValue(":pyro", $this->getInfo($player, "pyro"));
                $stmt->bindValue(":elite", $this->getInfo($player, "elite"));
                $stmt->bindValue(":windows10", $this->getInfo($player, "windows10"));
                $stmt->bindValue(":fresh", $this->getInfo($player, "fresh"));
                $stmt->bindValue(":emperor", $this->getInfo($player, "emperor"));
                $stmt->bindValue(":salty", $this->getInfo($player, "salty"));
                $stmt->bindValue(":pancakes", $this->getInfo($player, "pancakes"));
                $stmt->bindValue(":uwu", $this->getInfo($player, "uwu"));
                $stmt->bindValue(":zoomer", $this->getInfo($player, "zoomer"));
                $stmt->bindValue(":dirt", $this->getInfo($player, "dirt"));
                $stmt->bindValue(":god", $this->getInfo($player, "god"));
                $stmt->bindValue(":king", $this->getInfo($player, "king"));
                $stmt->bindValue(":gangster", $this->getInfo($player, "gangster"));
                $stmt->bindValue(":hitman", $this->getInfo($player, "hitman"));
                $stmt->bindValue(":mobster", $this->getInfo($player, "mobster"));
                $stmt->bindValue(":loner", $this->getInfo($player, "loner"));
                $stmt->bindValue(":horion", $this->getInfo($player, "horion"));
                $stmt->bindValue(":injected", $this->getInfo($player, "injected"));
                $stmt->bindValue(":bustdown", $this->getInfo($player, "bustdown"));
                $stmt->bindValue(":pro", $this->getInfo($player, "pro"));
                $stmt->bindValue(":hacker", $this->getInfo($player, "hacker"));
                $stmt->bindValue(":gucci", $this->getInfo($player, "gucci"));
                $stmt->bindValue(":troll", $this->getInfo($player, "troll"));
                $stmt->bindValue(":nou", $this->getInfo($player, "nou"));
                $stmt->bindValue(":killer", $this->getInfo($player, "killer"));
                $stmt->bindValue(":gangbang", $this->getInfo($player, "gangbang"));
                $stmt->bindValue(":chugnub", $this->getInfo($player, "chugnub"));
                $stmt->bindValue(":cornhub", $this->getInfo($player, "cornhub"));
                $stmt->bindValue(":androidgod", $this->getInfo($player, "androidgod"));
                $stmt->bindValue(":iosgod", $this->getInfo($player, "iosgod"));
                $stmt->execute();
                break;
            case "mvp":
                $stmt = $this->tags->prepare("INSERT OR REPLACE INTO tags (player, lit, enhanced, mvp, ultimate, crusader, legend, overlord, experienced, ez, pyro, elite, windows10, fresh, emperor, salty, pancakes, uwu, zoomer, dirt, god, king, gangster, hitman, mobster, loner, horion, injected, bustdown, pro, hacker, gucci, troll, nou, killer, gangbang, chugnub, cornhub, androidgod, iosgod) VALUES (:player, :lit, :enhanced, :mvp, :ultimate, :crusader, :legend, :overlord, :experienced, :ez, :pyro, :elite, :windows10, :fresh, :emperor, :salty, :pancakes, :uwu, :zoomer, :dirt, :god, :king, :gangster, :hitman, :mobster, :loner, :horion, :injected, :bustdown, :pro, :hacker, :gucci, :troll, :nou, :killer, :gangbang, :chugnub, :cornhub, :androidgod, :iosgod)");
                $stmt->bindValue(":player", $player->getName());
                $stmt->bindValue(":lit", $this->getInfo($player, "lit"));
                $stmt->bindValue(":enhanced", $this->getInfo($player, "enhanced"));
                $stmt->bindValue(":mvp", 1);
                $stmt->bindValue(":ultimate", $this->getInfo($player, "ultimate"));
                $stmt->bindValue(":crusader", $this->getInfo($player, "crusader"));
                $stmt->bindValue(":legend", $this->getInfo($player, "legend"));
                $stmt->bindValue(":overlord", $this->getInfo($player, "overlord"));
                $stmt->bindValue(":experienced", $this->getInfo($player, "experienced"));
                $stmt->bindValue(":ez", $this->getInfo($player, "ez"));
                $stmt->bindValue(":pyro", $this->getInfo($player, "pyro"));
                $stmt->bindValue(":elite", $this->getInfo($player, "elite"));
                $stmt->bindValue(":windows10", $this->getInfo($player, "windows10"));
                $stmt->bindValue(":fresh", $this->getInfo($player, "fresh"));
                $stmt->bindValue(":emperor", $this->getInfo($player, "emperor"));
                $stmt->bindValue(":salty", $this->getInfo($player, "salty"));
                $stmt->bindValue(":pancakes", $this->getInfo($player, "pancakes"));
                $stmt->bindValue(":uwu", $this->getInfo($player, "uwu"));
                $stmt->bindValue(":zoomer", $this->getInfo($player, "zoomer"));
                $stmt->bindValue(":dirt", $this->getInfo($player, "dirt"));
                $stmt->bindValue(":god", $this->getInfo($player, "god"));
                $stmt->bindValue(":king", $this->getInfo($player, "king"));
                $stmt->bindValue(":gangster", $this->getInfo($player, "gangster"));
                $stmt->bindValue(":hitman", $this->getInfo($player, "hitman"));
                $stmt->bindValue(":mobster", $this->getInfo($player, "mobster"));
                $stmt->bindValue(":loner", $this->getInfo($player, "loner"));
                $stmt->bindValue(":horion", $this->getInfo($player, "horion"));
                $stmt->bindValue(":injected", $this->getInfo($player, "injected"));
                $stmt->bindValue(":bustdown", $this->getInfo($player, "bustdown"));
                $stmt->bindValue(":pro", $this->getInfo($player, "pro"));
                $stmt->bindValue(":hacker", $this->getInfo($player, "hacker"));
                $stmt->bindValue(":gucci", $this->getInfo($player, "gucci"));
                $stmt->bindValue(":troll", $this->getInfo($player, "troll"));
                $stmt->bindValue(":nou", $this->getInfo($player, "nou"));
                $stmt->bindValue(":killer", $this->getInfo($player, "killer"));
                $stmt->bindValue(":gangbang", $this->getInfo($player, "gangbang"));
                $stmt->bindValue(":chugnub", $this->getInfo($player, "chugnub"));
                $stmt->bindValue(":cornhub", $this->getInfo($player, "cornhub"));
                $stmt->bindValue(":androidgod", $this->getInfo($player, "androidgod"));
                $stmt->bindValue(":iosgod", $this->getInfo($player, "iosgod"));
                $stmt->execute();
                break;
            case "ultimate":
                $stmt = $this->tags->prepare("INSERT OR REPLACE INTO tags (player, lit, enhanced, mvp, ultimate, crusader, legend, overlord, experienced, ez, pyro, elite, windows10, fresh, emperor, salty, pancakes, uwu, zoomer, dirt, god, king, gangster, hitman, mobster, loner, horion, injected, bustdown, pro, hacker, gucci, troll, nou, killer, gangbang, chugnub, cornhub, androidgod, iosgod) VALUES (:player, :lit, :enhanced, :mvp, :ultimate, :crusader, :legend, :overlord, :experienced, :ez, :pyro, :elite, :windows10, :fresh, :emperor, :salty, :pancakes, :uwu, :zoomer, :dirt, :god, :king, :gangster, :hitman, :mobster, :loner, :horion, :injected, :bustdown, :pro, :hacker, :gucci, :troll, :nou, :killer, :gangbang, :chugnub, :cornhub, :androidgod, :iosgod)");
                $stmt->bindValue(":player", $player->getName());
                $stmt->bindValue(":lit", $this->getInfo($player, "lit"));
                $stmt->bindValue(":enhanced", $this->getInfo($player, "enhanced"));
                $stmt->bindValue(":mvp", $this->getInfo($player, "mvp"));
                $stmt->bindValue(":ultimate", 1);
                $stmt->bindValue(":crusader", $this->getInfo($player, "crusader"));
                $stmt->bindValue(":legend", $this->getInfo($player, "legend"));
                $stmt->bindValue(":overlord", $this->getInfo($player, "overlord"));
                $stmt->bindValue(":experienced", $this->getInfo($player, "experienced"));
                $stmt->bindValue(":ez", $this->getInfo($player, "ez"));
                $stmt->bindValue(":pyro", $this->getInfo($player, "pyro"));
                $stmt->bindValue(":elite", $this->getInfo($player, "elite"));
                $stmt->bindValue(":windows10", $this->getInfo($player, "windows10"));
                $stmt->bindValue(":fresh", $this->getInfo($player, "fresh"));
                $stmt->bindValue(":emperor", $this->getInfo($player, "emperor"));
                $stmt->bindValue(":salty", $this->getInfo($player, "salty"));
                $stmt->bindValue(":pancakes", $this->getInfo($player, "pancakes"));
                $stmt->bindValue(":uwu", $this->getInfo($player, "uwu"));
                $stmt->bindValue(":zoomer", $this->getInfo($player, "zoomer"));
                $stmt->bindValue(":dirt", $this->getInfo($player, "dirt"));
                $stmt->bindValue(":god", $this->getInfo($player, "god"));
                $stmt->bindValue(":king", $this->getInfo($player, "king"));
                $stmt->bindValue(":gangster", $this->getInfo($player, "gangster"));
                $stmt->bindValue(":hitman", $this->getInfo($player, "hitman"));
                $stmt->bindValue(":mobster", $this->getInfo($player, "mobster"));
                $stmt->bindValue(":loner", $this->getInfo($player, "loner"));
                $stmt->bindValue(":horion", $this->getInfo($player, "horion"));
                $stmt->bindValue(":injected", $this->getInfo($player, "injected"));
                $stmt->bindValue(":bustdown", $this->getInfo($player, "bustdown"));
                $stmt->bindValue(":pro", $this->getInfo($player, "pro"));
                $stmt->bindValue(":hacker", $this->getInfo($player, "hacker"));
                $stmt->bindValue(":gucci", $this->getInfo($player, "gucci"));
                $stmt->bindValue(":troll", $this->getInfo($player, "troll"));
                $stmt->bindValue(":nou", $this->getInfo($player, "nou"));
                $stmt->bindValue(":killer", $this->getInfo($player, "killer"));
                $stmt->bindValue(":gangbang", $this->getInfo($player, "gangbang"));
                $stmt->bindValue(":chugnub", $this->getInfo($player, "chugnub"));
                $stmt->bindValue(":cornhub", $this->getInfo($player, "cornhub"));
                $stmt->bindValue(":androidgod", $this->getInfo($player, "androidgod"));
                $stmt->bindValue(":iosgod", $this->getInfo($player, "iosgod"));
                $stmt->execute();
                break;
            case "crusader":
                $stmt = $this->tags->prepare("INSERT OR REPLACE INTO tags (player, lit, enhanced, mvp, ultimate, crusader, legend, overlord, experienced, ez, pyro, elite, windows10, fresh, emperor, salty, pancakes, uwu, zoomer, dirt, god, king, gangster, hitman, mobster, loner, horion, injected, bustdown, pro, hacker, gucci, troll, nou, killer, gangbang, chugnub, cornhub, androidgod, iosgod) VALUES (:player, :lit, :enhanced, :mvp, :ultimate, :crusader, :legend, :overlord, :experienced, :ez, :pyro, :elite, :windows10, :fresh, :emperor, :salty, :pancakes, :uwu, :zoomer, :dirt, :god, :king, :gangster, :hitman, :mobster, :loner, :horion, :injected, :bustdown, :pro, :hacker, :gucci, :troll, :nou, :killer, :gangbang, :chugnub, :cornhub, :androidgod, :iosgod)");
                $stmt->bindValue(":player", $player->getName());
                $stmt->bindValue(":lit", $this->getInfo($player, "lit"));
                $stmt->bindValue(":enhanced", $this->getInfo($player, "enhanced"));
                $stmt->bindValue(":mvp", $this->getInfo($player, "mvp"));
                $stmt->bindValue(":ultimate", $this->getInfo($player, "ultimate"));
                $stmt->bindValue(":crusader", 1);
                $stmt->bindValue(":legend", $this->getInfo($player, "legend"));
                $stmt->bindValue(":overlord", $this->getInfo($player, "overlord"));
                $stmt->bindValue(":experienced", $this->getInfo($player, "experienced"));
                $stmt->bindValue(":ez", $this->getInfo($player, "ez"));
                $stmt->bindValue(":pyro", $this->getInfo($player, "pyro"));
                $stmt->bindValue(":elite", $this->getInfo($player, "elite"));
                $stmt->bindValue(":windows10", $this->getInfo($player, "windows10"));
                $stmt->bindValue(":fresh", $this->getInfo($player, "fresh"));
                $stmt->bindValue(":emperor", $this->getInfo($player, "emperor"));
                $stmt->bindValue(":salty", $this->getInfo($player, "salty"));
                $stmt->bindValue(":pancakes", $this->getInfo($player, "pancakes"));
                $stmt->bindValue(":uwu", $this->getInfo($player, "uwu"));
                $stmt->bindValue(":zoomer", $this->getInfo($player, "zoomer"));
                $stmt->bindValue(":dirt", $this->getInfo($player, "dirt"));
                $stmt->bindValue(":god", $this->getInfo($player, "god"));
                $stmt->bindValue(":king", $this->getInfo($player, "king"));
                $stmt->bindValue(":gangster", $this->getInfo($player, "gangster"));
                $stmt->bindValue(":hitman", $this->getInfo($player, "hitman"));
                $stmt->bindValue(":mobster", $this->getInfo($player, "mobster"));
                $stmt->bindValue(":loner", $this->getInfo($player, "loner"));
                $stmt->bindValue(":horion", $this->getInfo($player, "horion"));
                $stmt->bindValue(":injected", $this->getInfo($player, "injected"));
                $stmt->bindValue(":bustdown", $this->getInfo($player, "bustdown"));
                $stmt->bindValue(":pro", $this->getInfo($player, "pro"));
                $stmt->bindValue(":hacker", $this->getInfo($player, "hacker"));
                $stmt->bindValue(":gucci", $this->getInfo($player, "gucci"));
                $stmt->bindValue(":troll", $this->getInfo($player, "troll"));
                $stmt->bindValue(":nou", $this->getInfo($player, "nou"));
                $stmt->bindValue(":killer", $this->getInfo($player, "killer"));
                $stmt->bindValue(":gangbang", $this->getInfo($player, "gangbang"));
                $stmt->bindValue(":chugnub", $this->getInfo($player, "chugnub"));
                $stmt->bindValue(":cornhub", $this->getInfo($player, "cornhub"));
                $stmt->bindValue(":androidgod", $this->getInfo($player, "androidgod"));
                $stmt->bindValue(":iosgod", $this->getInfo($player, "iosgod"));
                $stmt->execute();
                break;
            case "legend":
                $stmt = $this->tags->prepare("INSERT OR REPLACE INTO tags (player, lit, enhanced, mvp, ultimate, crusader, legend, overlord, experienced, ez, pyro, elite, windows10, fresh, emperor, salty, pancakes, uwu, zoomer, dirt, god, king, gangster, hitman, mobster, loner, horion, injected, bustdown, pro, hacker, gucci, troll, nou, killer, gangbang, chugnub, cornhub, androidgod, iosgod) VALUES (:player, :lit, :enhanced, :mvp, :ultimate, :crusader, :legend, :overlord, :experienced, :ez, :pyro, :elite, :windows10, :fresh, :emperor, :salty, :pancakes, :uwu, :zoomer, :dirt, :god, :king, :gangster, :hitman, :mobster, :loner, :horion, :injected, :bustdown, :pro, :hacker, :gucci, :troll, :nou, :killer, :gangbang, :chugnub, :cornhub, :androidgod, :iosgod)");
                $stmt->bindValue(":player", $player->getName());
                $stmt->bindValue(":lit", $this->getInfo($player, "lit"));
                $stmt->bindValue(":enhanced", $this->getInfo($player, "enhanced"));
                $stmt->bindValue(":mvp", $this->getInfo($player, "mvp"));
                $stmt->bindValue(":ultimate", $this->getInfo($player, "ultimate"));
                $stmt->bindValue(":crusader", $this->getInfo($player, "crusader"));
                $stmt->bindValue(":legend", 1);
                $stmt->bindValue(":overlord", $this->getInfo($player, "overlord"));
                $stmt->bindValue(":experienced", $this->getInfo($player, "experienced"));
                $stmt->bindValue(":ez", $this->getInfo($player, "ez"));
                $stmt->bindValue(":pyro", $this->getInfo($player, "pyro"));
                $stmt->bindValue(":elite", $this->getInfo($player, "elite"));
                $stmt->bindValue(":windows10", $this->getInfo($player, "windows10"));
                $stmt->bindValue(":fresh", $this->getInfo($player, "fresh"));
                $stmt->bindValue(":emperor", $this->getInfo($player, "emperor"));
                $stmt->bindValue(":salty", $this->getInfo($player, "salty"));
                $stmt->bindValue(":pancakes", $this->getInfo($player, "pancakes"));
                $stmt->bindValue(":uwu", $this->getInfo($player, "uwu"));
                $stmt->bindValue(":zoomer", $this->getInfo($player, "zoomer"));
                $stmt->bindValue(":dirt", $this->getInfo($player, "dirt"));
                $stmt->bindValue(":god", $this->getInfo($player, "god"));
                $stmt->bindValue(":king", $this->getInfo($player, "king"));
                $stmt->bindValue(":gangster", $this->getInfo($player, "gangster"));
                $stmt->bindValue(":hitman", $this->getInfo($player, "hitman"));
                $stmt->bindValue(":mobster", $this->getInfo($player, "mobster"));
                $stmt->bindValue(":loner", $this->getInfo($player, "loner"));
                $stmt->bindValue(":horion", $this->getInfo($player, "horion"));
                $stmt->bindValue(":injected", $this->getInfo($player, "injected"));
                $stmt->bindValue(":bustdown", $this->getInfo($player, "bustdown"));
                $stmt->bindValue(":pro", $this->getInfo($player, "pro"));
                $stmt->bindValue(":hacker", $this->getInfo($player, "hacker"));
                $stmt->bindValue(":gucci", $this->getInfo($player, "gucci"));
                $stmt->bindValue(":troll", $this->getInfo($player, "troll"));
                $stmt->bindValue(":nou", $this->getInfo($player, "nou"));
                $stmt->bindValue(":killer", $this->getInfo($player, "killer"));
                $stmt->bindValue(":gangbang", $this->getInfo($player, "gangbang"));
                $stmt->bindValue(":chugnub", $this->getInfo($player, "chugnub"));
                $stmt->bindValue(":cornhub", $this->getInfo($player, "cornhub"));
                $stmt->bindValue(":androidgod", $this->getInfo($player, "androidgod"));
                $stmt->bindValue(":iosgod", $this->getInfo($player, "iosgod"));
                $stmt->execute();
                break;
            case "overlord":
                $stmt = $this->tags->prepare("INSERT OR REPLACE INTO tags (player, lit, enhanced, mvp, ultimate, crusader, legend, overlord, experienced, ez, pyro, elite, windows10, fresh, emperor, salty, pancakes, uwu, zoomer, dirt, god, king, gangster, hitman, mobster, loner, horion, injected, bustdown, pro, hacker, gucci, troll, nou, killer, gangbang, chugnub, cornhub, androidgod, iosgod) VALUES (:player, :lit, :enhanced, :mvp, :ultimate, :crusader, :legend, :overlord, :experienced, :ez, :pyro, :elite, :windows10, :fresh, :emperor, :salty, :pancakes, :uwu, :zoomer, :dirt, :god, :king, :gangster, :hitman, :mobster, :loner, :horion, :injected, :bustdown, :pro, :hacker, :gucci, :troll, :nou, :killer, :gangbang, :chugnub, :cornhub, :androidgod, :iosgod)");
                $stmt->bindValue(":player", $player->getName());
                $stmt->bindValue(":lit", $this->getInfo($player, "lit"));
                $stmt->bindValue(":enhanced", $this->getInfo($player, "enhanced"));
                $stmt->bindValue(":mvp", $this->getInfo($player, "mvp"));
                $stmt->bindValue(":ultimate", $this->getInfo($player, "ultimate"));
                $stmt->bindValue(":crusader", $this->getInfo($player, "crusader"));
                $stmt->bindValue(":legend", $this->getInfo($player, "legend"));
                $stmt->bindValue(":overlord", 1);
                $stmt->bindValue(":experienced", $this->getInfo($player, "experienced"));
                $stmt->bindValue(":ez", $this->getInfo($player, "ez"));
                $stmt->bindValue(":pyro", $this->getInfo($player, "pyro"));
                $stmt->bindValue(":elite", $this->getInfo($player, "elite"));
                $stmt->bindValue(":windows10", $this->getInfo($player, "windows10"));
                $stmt->bindValue(":fresh", $this->getInfo($player, "fresh"));
                $stmt->bindValue(":emperor", $this->getInfo($player, "emperor"));
                $stmt->bindValue(":salty", $this->getInfo($player, "salty"));
                $stmt->bindValue(":pancakes", $this->getInfo($player, "pancakes"));
                $stmt->bindValue(":uwu", $this->getInfo($player, "uwu"));
                $stmt->bindValue(":zoomer", $this->getInfo($player, "zoomer"));
                $stmt->bindValue(":dirt", $this->getInfo($player, "dirt"));
                $stmt->bindValue(":god", $this->getInfo($player, "god"));
                $stmt->bindValue(":king", $this->getInfo($player, "king"));
                $stmt->bindValue(":gangster", $this->getInfo($player, "gangster"));
                $stmt->bindValue(":hitman", $this->getInfo($player, "hitman"));
                $stmt->bindValue(":mobster", $this->getInfo($player, "mobster"));
                $stmt->bindValue(":loner", $this->getInfo($player, "loner"));
                $stmt->bindValue(":horion", $this->getInfo($player, "horion"));
                $stmt->bindValue(":injected", $this->getInfo($player, "injected"));
                $stmt->bindValue(":bustdown", $this->getInfo($player, "bustdown"));
                $stmt->bindValue(":pro", $this->getInfo($player, "pro"));
                $stmt->bindValue(":hacker", $this->getInfo($player, "hacker"));
                $stmt->bindValue(":gucci", $this->getInfo($player, "gucci"));
                $stmt->bindValue(":troll", $this->getInfo($player, "troll"));
                $stmt->bindValue(":nou", $this->getInfo($player, "nou"));
                $stmt->bindValue(":killer", $this->getInfo($player, "killer"));
                $stmt->bindValue(":gangbang", $this->getInfo($player, "gangbang"));
                $stmt->bindValue(":chugnub", $this->getInfo($player, "chugnub"));
                $stmt->bindValue(":cornhub", $this->getInfo($player, "cornhub"));
                $stmt->bindValue(":androidgod", $this->getInfo($player, "androidgod"));
                $stmt->bindValue(":iosgod", $this->getInfo($player, "iosgod"));
                $stmt->execute();
                break;
            case "experienced":
                $stmt = $this->tags->prepare("INSERT OR REPLACE INTO tags (player, lit, enhanced, mvp, ultimate, crusader, legend, overlord, experienced, ez, pyro, elite, windows10, fresh, emperor, salty, pancakes, uwu, zoomer, dirt, god, king, gangster, hitman, mobster, loner, horion, injected, bustdown, pro, hacker, gucci, troll, nou, killer, gangbang, chugnub, cornhub, androidgod, iosgod) VALUES (:player, :lit, :enhanced, :mvp, :ultimate, :crusader, :legend, :overlord, :experienced, :ez, :pyro, :elite, :windows10, :fresh, :emperor, :salty, :pancakes, :uwu, :zoomer, :dirt, :god, :king, :gangster, :hitman, :mobster, :loner, :horion, :injected, :bustdown, :pro, :hacker, :gucci, :troll, :nou, :killer, :gangbang, :chugnub, :cornhub, :androidgod, :iosgod)");
                $stmt->bindValue(":player", $player->getName());
                $stmt->bindValue(":lit", $this->getInfo($player, "lit"));
                $stmt->bindValue(":enhanced", $this->getInfo($player, "enhanced"));
                $stmt->bindValue(":mvp", $this->getInfo($player, "mvp"));
                $stmt->bindValue(":ultimate", $this->getInfo($player, "ultimate"));
                $stmt->bindValue(":crusader", $this->getInfo($player, "crusader"));
                $stmt->bindValue(":legend", $this->getInfo($player, "legend"));
                $stmt->bindValue(":overlord", $this->getInfo($player, "overlord"));
                $stmt->bindValue(":experienced", 1);
                $stmt->bindValue(":ez", $this->getInfo($player, "ez"));
                $stmt->bindValue(":pyro", $this->getInfo($player, "pyro"));
                $stmt->bindValue(":elite", $this->getInfo($player, "elite"));
                $stmt->bindValue(":windows10", $this->getInfo($player, "windows10"));
                $stmt->bindValue(":fresh", $this->getInfo($player, "fresh"));
                $stmt->bindValue(":emperor", $this->getInfo($player, "emperor"));
                $stmt->bindValue(":salty", $this->getInfo($player, "salty"));
                $stmt->bindValue(":pancakes", $this->getInfo($player, "pancakes"));
                $stmt->bindValue(":uwu", $this->getInfo($player, "uwu"));
                $stmt->bindValue(":zoomer", $this->getInfo($player, "zoomer"));
                $stmt->bindValue(":dirt", $this->getInfo($player, "dirt"));
                $stmt->bindValue(":god", $this->getInfo($player, "god"));
                $stmt->bindValue(":king", $this->getInfo($player, "king"));
                $stmt->bindValue(":gangster", $this->getInfo($player, "gangster"));
                $stmt->bindValue(":hitman", $this->getInfo($player, "hitman"));
                $stmt->bindValue(":mobster", $this->getInfo($player, "mobster"));
                $stmt->bindValue(":loner", $this->getInfo($player, "loner"));
                $stmt->bindValue(":horion", $this->getInfo($player, "horion"));
                $stmt->bindValue(":injected", $this->getInfo($player, "injected"));
                $stmt->bindValue(":bustdown", $this->getInfo($player, "bustdown"));
                $stmt->bindValue(":pro", $this->getInfo($player, "pro"));
                $stmt->bindValue(":hacker", $this->getInfo($player, "hacker"));
                $stmt->bindValue(":gucci", $this->getInfo($player, "gucci"));
                $stmt->bindValue(":troll", $this->getInfo($player, "troll"));
                $stmt->bindValue(":nou", $this->getInfo($player, "nou"));
                $stmt->bindValue(":killer", $this->getInfo($player, "killer"));
                $stmt->bindValue(":gangbang", $this->getInfo($player, "gangbang"));
                $stmt->bindValue(":chugnub", $this->getInfo($player, "chugnub"));
                $stmt->bindValue(":cornhub", $this->getInfo($player, "cornhub"));
                $stmt->bindValue(":androidgod", $this->getInfo($player, "androidgod"));
                $stmt->bindValue(":iosgod", $this->getInfo($player, "iosgod"));
                $stmt->execute();
                break;
            case "ez":
                $stmt = $this->tags->prepare("INSERT OR REPLACE INTO tags (player, lit, enhanced, mvp, ultimate, crusader, legend, overlord, experienced, ez, pyro, elite, windows10, fresh, emperor, salty, pancakes, uwu, zoomer, dirt, god, king, gangster, hitman, mobster, loner, horion, injected, bustdown, pro, hacker, gucci, troll, nou, killer, gangbang, chugnub, cornhub, androidgod, iosgod) VALUES (:player, :lit, :enhanced, :mvp, :ultimate, :crusader, :legend, :overlord, :experienced, :ez, :pyro, :elite, :windows10, :fresh, :emperor, :salty, :pancakes, :uwu, :zoomer, :dirt, :god, :king, :gangster, :hitman, :mobster, :loner, :horion, :injected, :bustdown, :pro, :hacker, :gucci, :troll, :nou, :killer, :gangbang, :chugnub, :cornhub, :androidgod, :iosgod)");
                $stmt->bindValue(":player", $player->getName());
                $stmt->bindValue(":lit", $this->getInfo($player, "lit"));
                $stmt->bindValue(":enhanced", $this->getInfo($player, "enhanced"));
                $stmt->bindValue(":mvp", $this->getInfo($player, "mvp"));
                $stmt->bindValue(":ultimate", $this->getInfo($player, "ultimate"));
                $stmt->bindValue(":crusader", $this->getInfo($player, "crusader"));
                $stmt->bindValue(":legend", $this->getInfo($player, "legend"));
                $stmt->bindValue(":overlord", $this->getInfo($player, "overlord"));
                $stmt->bindValue(":experienced", $this->getInfo($player, "experienced"));
                $stmt->bindValue(":ez", 1);
                $stmt->bindValue(":pyro", $this->getInfo($player, "pyro"));
                $stmt->bindValue(":elite", $this->getInfo($player, "elite"));
                $stmt->bindValue(":windows10", $this->getInfo($player, "windows10"));
                $stmt->bindValue(":fresh", $this->getInfo($player, "fresh"));
                $stmt->bindValue(":emperor", $this->getInfo($player, "emperor"));
                $stmt->bindValue(":salty", $this->getInfo($player, "salty"));
                $stmt->bindValue(":pancakes", $this->getInfo($player, "pancakes"));
                $stmt->bindValue(":uwu", $this->getInfo($player, "uwu"));
                $stmt->bindValue(":zoomer", $this->getInfo($player, "zoomer"));
                $stmt->bindValue(":dirt", $this->getInfo($player, "dirt"));
                $stmt->bindValue(":god", $this->getInfo($player, "god"));
                $stmt->bindValue(":king", $this->getInfo($player, "king"));
                $stmt->bindValue(":gangster", $this->getInfo($player, "gangster"));
                $stmt->bindValue(":hitman", $this->getInfo($player, "hitman"));
                $stmt->bindValue(":mobster", $this->getInfo($player, "mobster"));
                $stmt->bindValue(":loner", $this->getInfo($player, "loner"));
                $stmt->bindValue(":horion", $this->getInfo($player, "horion"));
                $stmt->bindValue(":injected", $this->getInfo($player, "injected"));
                $stmt->bindValue(":bustdown", $this->getInfo($player, "bustdown"));
                $stmt->bindValue(":pro", $this->getInfo($player, "pro"));
                $stmt->bindValue(":hacker", $this->getInfo($player, "hacker"));
                $stmt->bindValue(":gucci", $this->getInfo($player, "gucci"));
                $stmt->bindValue(":troll", $this->getInfo($player, "troll"));
                $stmt->bindValue(":nou", $this->getInfo($player, "nou"));
                $stmt->bindValue(":killer", $this->getInfo($player, "killer"));
                $stmt->bindValue(":gangbang", $this->getInfo($player, "gangbang"));
                $stmt->bindValue(":chugnub", $this->getInfo($player, "chugnub"));
                $stmt->bindValue(":cornhub", $this->getInfo($player, "cornhub"));
                $stmt->bindValue(":androidgod", $this->getInfo($player, "androidgod"));
                $stmt->bindValue(":iosgod", $this->getInfo($player, "iosgod"));
                $stmt->execute();
                break;
            case "pyro":
                $stmt = $this->tags->prepare("INSERT OR REPLACE INTO tags (player, lit, enhanced, mvp, ultimate, crusader, legend, overlord, experienced, ez, pyro, elite, windows10, fresh, emperor, salty, pancakes, uwu, zoomer, dirt, god, king, gangster, hitman, mobster, loner, horion, injected, bustdown, pro, hacker, gucci, troll, nou, killer, gangbang, chugnub, cornhub, androidgod, iosgod) VALUES (:player, :lit, :enhanced, :mvp, :ultimate, :crusader, :legend, :overlord, :experienced, :ez, :pyro, :elite, :windows10, :fresh, :emperor, :salty, :pancakes, :uwu, :zoomer, :dirt, :god, :king, :gangster, :hitman, :mobster, :loner, :horion, :injected, :bustdown, :pro, :hacker, :gucci, :troll, :nou, :killer, :gangbang, :chugnub, :cornhub, :androidgod, :iosgod)");
                $stmt->bindValue(":player", $player->getName());
                $stmt->bindValue(":lit", $this->getInfo($player, "lit"));
                $stmt->bindValue(":enhanced", $this->getInfo($player, "enhanced"));
                $stmt->bindValue(":mvp", $this->getInfo($player, "mvp"));
                $stmt->bindValue(":ultimate", $this->getInfo($player, "ultimate"));
                $stmt->bindValue(":crusader", $this->getInfo($player, "crusader"));
                $stmt->bindValue(":legend", $this->getInfo($player, "legend"));
                $stmt->bindValue(":overlord", $this->getInfo($player, "overlord"));
                $stmt->bindValue(":experienced", $this->getInfo($player, "experienced"));
                $stmt->bindValue(":ez", $this->getInfo($player, "ez"));
                $stmt->bindValue(":pyro", 1);
                $stmt->bindValue(":elite", $this->getInfo($player, "elite"));
                $stmt->bindValue(":windows10", $this->getInfo($player, "windows10"));
                $stmt->bindValue(":fresh", $this->getInfo($player, "fresh"));
                $stmt->bindValue(":emperor", $this->getInfo($player, "emperor"));
                $stmt->bindValue(":salty", $this->getInfo($player, "salty"));
                $stmt->bindValue(":pancakes", $this->getInfo($player, "pancakes"));
                $stmt->bindValue(":uwu", $this->getInfo($player, "uwu"));
                $stmt->bindValue(":zoomer", $this->getInfo($player, "zoomer"));
                $stmt->bindValue(":dirt", $this->getInfo($player, "dirt"));
                $stmt->bindValue(":god", $this->getInfo($player, "god"));
                $stmt->bindValue(":king", $this->getInfo($player, "king"));
                $stmt->bindValue(":gangster", $this->getInfo($player, "gangster"));
                $stmt->bindValue(":hitman", $this->getInfo($player, "hitman"));
                $stmt->bindValue(":mobster", $this->getInfo($player, "mobster"));
                $stmt->bindValue(":loner", $this->getInfo($player, "loner"));
                $stmt->bindValue(":horion", $this->getInfo($player, "horion"));
                $stmt->bindValue(":injected", $this->getInfo($player, "injected"));
                $stmt->bindValue(":bustdown", $this->getInfo($player, "bustdown"));
                $stmt->bindValue(":pro", $this->getInfo($player, "pro"));
                $stmt->bindValue(":hacker", $this->getInfo($player, "hacker"));
                $stmt->bindValue(":gucci", $this->getInfo($player, "gucci"));
                $stmt->bindValue(":troll", $this->getInfo($player, "troll"));
                $stmt->bindValue(":nou", $this->getInfo($player, "nou"));
                $stmt->bindValue(":killer", $this->getInfo($player, "killer"));
                $stmt->bindValue(":gangbang", $this->getInfo($player, "gangbang"));
                $stmt->bindValue(":chugnub", $this->getInfo($player, "chugnub"));
                $stmt->bindValue(":cornhub", $this->getInfo($player, "cornhub"));
                $stmt->bindValue(":androidgod", $this->getInfo($player, "androidgod"));
                $stmt->bindValue(":iosgod", $this->getInfo($player, "iosgod"));
                $stmt->execute();
                break;
            case "elite":
                $stmt = $this->tags->prepare("INSERT OR REPLACE INTO tags (player, lit, enhanced, mvp, ultimate, crusader, legend, overlord, experienced, ez, pyro, elite, windows10, fresh, emperor, salty, pancakes, uwu, zoomer, dirt, god, king, gangster, hitman, mobster, loner, horion, injected, bustdown, pro, hacker, gucci, troll, nou, killer, gangbang, chugnub, cornhub, androidgod, iosgod) VALUES (:player, :lit, :enhanced, :mvp, :ultimate, :crusader, :legend, :overlord, :experienced, :ez, :pyro, :elite, :windows10, :fresh, :emperor, :salty, :pancakes, :uwu, :zoomer, :dirt, :god, :king, :gangster, :hitman, :mobster, :loner, :horion, :injected, :bustdown, :pro, :hacker, :gucci, :troll, :nou, :killer, :gangbang, :chugnub, :cornhub, :androidgod, :iosgod)");
                $stmt->bindValue(":player", $player->getName());
                $stmt->bindValue(":lit", $this->getInfo($player, "lit"));
                $stmt->bindValue(":enhanced", $this->getInfo($player, "enhanced"));
                $stmt->bindValue(":mvp", $this->getInfo($player, "mvp"));
                $stmt->bindValue(":ultimate", $this->getInfo($player, "ultimate"));
                $stmt->bindValue(":crusader", $this->getInfo($player, "crusader"));
                $stmt->bindValue(":legend", $this->getInfo($player, "legend"));
                $stmt->bindValue(":overlord", $this->getInfo($player, "overlord"));
                $stmt->bindValue(":experienced", $this->getInfo($player, "experienced"));
                $stmt->bindValue(":ez", $this->getInfo($player, "ez"));
                $stmt->bindValue(":pyro", $this->getInfo($player, "pyro"));
                $stmt->bindValue(":elite", 1);
                $stmt->bindValue(":windows10", $this->getInfo($player, "windows10"));
                $stmt->bindValue(":fresh", $this->getInfo($player, "fresh"));
                $stmt->bindValue(":emperor", $this->getInfo($player, "emperor"));
                $stmt->bindValue(":salty", $this->getInfo($player, "salty"));
                $stmt->bindValue(":pancakes", $this->getInfo($player, "pancakes"));
                $stmt->bindValue(":uwu", $this->getInfo($player, "uwu"));
                $stmt->bindValue(":zoomer", $this->getInfo($player, "zoomer"));
                $stmt->bindValue(":dirt", $this->getInfo($player, "dirt"));
                $stmt->bindValue(":god", $this->getInfo($player, "god"));
                $stmt->bindValue(":king", $this->getInfo($player, "king"));
                $stmt->bindValue(":gangster", $this->getInfo($player, "gangster"));
                $stmt->bindValue(":hitman", $this->getInfo($player, "hitman"));
                $stmt->bindValue(":mobster", $this->getInfo($player, "mobster"));
                $stmt->bindValue(":loner", $this->getInfo($player, "loner"));
                $stmt->bindValue(":horion", $this->getInfo($player, "horion"));
                $stmt->bindValue(":injected", $this->getInfo($player, "injected"));
                $stmt->bindValue(":bustdown", $this->getInfo($player, "bustdown"));
                $stmt->bindValue(":pro", $this->getInfo($player, "pro"));
                $stmt->bindValue(":hacker", $this->getInfo($player, "hacker"));
                $stmt->bindValue(":gucci", $this->getInfo($player, "gucci"));
                $stmt->bindValue(":troll", $this->getInfo($player, "troll"));
                $stmt->bindValue(":nou", $this->getInfo($player, "nou"));
                $stmt->bindValue(":killer", $this->getInfo($player, "killer"));
                $stmt->bindValue(":gangbang", $this->getInfo($player, "gangbang"));
                $stmt->bindValue(":chugnub", $this->getInfo($player, "chugnub"));
                $stmt->bindValue(":cornhub", $this->getInfo($player, "cornhub"));
                $stmt->bindValue(":androidgod", $this->getInfo($player, "androidgod"));
                $stmt->bindValue(":iosgod", $this->getInfo($player, "iosgod"));
                $stmt->execute();
                break;
            case "windows10":
                $stmt = $this->tags->prepare("INSERT OR REPLACE INTO tags (player, lit, enhanced, mvp, ultimate, crusader, legend, overlord, experienced, ez, pyro, elite, windows10, fresh, emperor, salty, pancakes, uwu, zoomer, dirt, god, king, gangster, hitman, mobster, loner, horion, injected, bustdown, pro, hacker, gucci, troll, nou, killer, gangbang, chugnub, cornhub, androidgod, iosgod) VALUES (:player, :lit, :enhanced, :mvp, :ultimate, :crusader, :legend, :overlord, :experienced, :ez, :pyro, :elite, :windows10, :fresh, :emperor, :salty, :pancakes, :uwu, :zoomer, :dirt, :god, :king, :gangster, :hitman, :mobster, :loner, :horion, :injected, :bustdown, :pro, :hacker, :gucci, :troll, :nou, :killer, :gangbang, :chugnub, :cornhub, :androidgod, :iosgod)");
                $stmt->bindValue(":player", $player->getName());
                $stmt->bindValue(":lit", $this->getInfo($player, "lit"));
                $stmt->bindValue(":enhanced", $this->getInfo($player, "enhanced"));
                $stmt->bindValue(":mvp", $this->getInfo($player, "mvp"));
                $stmt->bindValue(":ultimate", $this->getInfo($player, "ultimate"));
                $stmt->bindValue(":crusader", $this->getInfo($player, "crusader"));
                $stmt->bindValue(":legend", $this->getInfo($player, "legend"));
                $stmt->bindValue(":overlord", $this->getInfo($player, "overlord"));
                $stmt->bindValue(":experienced", $this->getInfo($player, "experienced"));
                $stmt->bindValue(":ez", $this->getInfo($player, "ez"));
                $stmt->bindValue(":pyro", $this->getInfo($player, "pyro"));
                $stmt->bindValue(":elite", $this->getInfo($player, "pyro"));
                $stmt->bindValue(":windows10", 1);
                $stmt->bindValue(":fresh", $this->getInfo($player, "fresh"));
                $stmt->bindValue(":emperor", $this->getInfo($player, "emperor"));
                $stmt->bindValue(":salty", $this->getInfo($player, "salty"));
                $stmt->bindValue(":pancakes", $this->getInfo($player, "pancakes"));
                $stmt->bindValue(":uwu", $this->getInfo($player, "uwu"));
                $stmt->bindValue(":zoomer", $this->getInfo($player, "zoomer"));
                $stmt->bindValue(":dirt", $this->getInfo($player, "dirt"));
                $stmt->bindValue(":god", $this->getInfo($player, "god"));
                $stmt->bindValue(":king", $this->getInfo($player, "king"));
                $stmt->bindValue(":gangster", $this->getInfo($player, "gangster"));
                $stmt->bindValue(":hitman", $this->getInfo($player, "hitman"));
                $stmt->bindValue(":mobster", $this->getInfo($player, "mobster"));
                $stmt->bindValue(":loner", $this->getInfo($player, "loner"));
                $stmt->bindValue(":horion", $this->getInfo($player, "horion"));
                $stmt->bindValue(":injected", $this->getInfo($player, "injected"));
                $stmt->bindValue(":bustdown", $this->getInfo($player, "bustdown"));
                $stmt->bindValue(":pro", $this->getInfo($player, "pro"));
                $stmt->bindValue(":hacker", $this->getInfo($player, "hacker"));
                $stmt->bindValue(":gucci", $this->getInfo($player, "gucci"));
                $stmt->bindValue(":troll", $this->getInfo($player, "troll"));
                $stmt->bindValue(":nou", $this->getInfo($player, "nou"));
                $stmt->bindValue(":killer", $this->getInfo($player, "killer"));
                $stmt->bindValue(":gangbang", $this->getInfo($player, "gangbang"));
                $stmt->bindValue(":chugnub", $this->getInfo($player, "chugnub"));
                $stmt->bindValue(":cornhub", $this->getInfo($player, "cornhub"));
                $stmt->bindValue(":androidgod", $this->getInfo($player, "androidgod"));
                $stmt->bindValue(":iosgod", $this->getInfo($player, "iosgod"));
                $stmt->execute();
                break;
            case "fresh":
                $stmt = $this->tags->prepare("INSERT OR REPLACE INTO tags (player, lit, enhanced, mvp, ultimate, crusader, legend, overlord, experienced, ez, pyro, elite, windows10, fresh, emperor, salty, pancakes, uwu, zoomer, dirt, god, king, gangster, hitman, mobster, loner, horion, injected, bustdown, pro, hacker, gucci, troll, nou, killer, gangbang, chugnub, cornhub, androidgod, iosgod) VALUES (:player, :lit, :enhanced, :mvp, :ultimate, :crusader, :legend, :overlord, :experienced, :ez, :pyro, :elite, :windows10, :fresh, :emperor, :salty, :pancakes, :uwu, :zoomer, :dirt, :god, :king, :gangster, :hitman, :mobster, :loner, :horion, :injected, :bustdown, :pro, :hacker, :gucci, :troll, :nou, :killer, :gangbang, :chugnub, :cornhub, :androidgod, :iosgod)");
                $stmt->bindValue(":player", $player->getName());
                $stmt->bindValue(":lit", $this->getInfo($player, "lit"));
                $stmt->bindValue(":enhanced", $this->getInfo($player, "enhanced"));
                $stmt->bindValue(":mvp", $this->getInfo($player, "mvp"));
                $stmt->bindValue(":ultimate", $this->getInfo($player, "ultimate"));
                $stmt->bindValue(":crusader", $this->getInfo($player, "crusader"));
                $stmt->bindValue(":legend", $this->getInfo($player, "legend"));
                $stmt->bindValue(":overlord", $this->getInfo($player, "overlord"));
                $stmt->bindValue(":experienced", $this->getInfo($player, "experienced"));
                $stmt->bindValue(":ez", $this->getInfo($player, "ez"));
                $stmt->bindValue(":pyro", $this->getInfo($player, "pyro"));
                $stmt->bindValue(":elite", $this->getInfo($player, "elite"));
                $stmt->bindValue(":windows10", $this->getInfo($player, "windows10"));
                $stmt->bindValue(":fresh", 1);
                $stmt->bindValue(":emperor", $this->getInfo($player, "emperor"));
                $stmt->bindValue(":salty", $this->getInfo($player, "salty"));
                $stmt->bindValue(":pancakes", $this->getInfo($player, "pancakes"));
                $stmt->bindValue(":uwu", $this->getInfo($player, "uwu"));
                $stmt->bindValue(":zoomer", $this->getInfo($player, "zoomer"));
                $stmt->bindValue(":dirt", $this->getInfo($player, "dirt"));
                $stmt->bindValue(":god", $this->getInfo($player, "god"));
                $stmt->bindValue(":king", $this->getInfo($player, "king"));
                $stmt->bindValue(":gangster", $this->getInfo($player, "gangster"));
                $stmt->bindValue(":hitman", $this->getInfo($player, "hitman"));
                $stmt->bindValue(":mobster", $this->getInfo($player, "mobster"));
                $stmt->bindValue(":loner", $this->getInfo($player, "loner"));
                $stmt->bindValue(":horion", $this->getInfo($player, "horion"));
                $stmt->bindValue(":injected", $this->getInfo($player, "injected"));
                $stmt->bindValue(":bustdown", $this->getInfo($player, "bustdown"));
                $stmt->bindValue(":pro", $this->getInfo($player, "pro"));
                $stmt->bindValue(":hacker", $this->getInfo($player, "hacker"));
                $stmt->bindValue(":gucci", $this->getInfo($player, "gucci"));
                $stmt->bindValue(":troll", $this->getInfo($player, "troll"));
                $stmt->bindValue(":nou", $this->getInfo($player, "nou"));
                $stmt->bindValue(":killer", $this->getInfo($player, "killer"));
                $stmt->bindValue(":gangbang", $this->getInfo($player, "gangbang"));
                $stmt->bindValue(":chugnub", $this->getInfo($player, "chugnub"));
                $stmt->bindValue(":cornhub", $this->getInfo($player, "cornhub"));
                $stmt->bindValue(":androidgod", $this->getInfo($player, "androidgod"));
                $stmt->bindValue(":iosgod", $this->getInfo($player, "iosgod"));
                $stmt->execute();
                break;
            case "emperor":
                $stmt = $this->tags->prepare("INSERT OR REPLACE INTO tags (player, lit, enhanced, mvp, ultimate, crusader, legend, overlord, experienced, ez, pyro, elite, windows10, fresh, emperor, salty, pancakes, uwu, zoomer, dirt, god, king, gangster, hitman, mobster, loner, horion, injected, bustdown, pro, hacker, gucci, troll, nou, killer, gangbang, chugnub, cornhub, androidgod, iosgod) VALUES (:player, :lit, :enhanced, :mvp, :ultimate, :crusader, :legend, :overlord, :experienced, :ez, :pyro, :elite, :windows10, :fresh, :emperor, :salty, :pancakes, :uwu, :zoomer, :dirt, :god, :king, :gangster, :hitman, :mobster, :loner, :horion, :injected, :bustdown, :pro, :hacker, :gucci, :troll, :nou, :killer, :gangbang, :chugnub, :cornhub, :androidgod, :iosgod)");
                $stmt->bindValue(":player", $player->getName());
                $stmt->bindValue(":lit", $this->getInfo($player, "lit"));
                $stmt->bindValue(":enhanced", $this->getInfo($player, "enhanced"));
                $stmt->bindValue(":mvp", $this->getInfo($player, "mvp"));
                $stmt->bindValue(":ultimate", $this->getInfo($player, "ultimate"));
                $stmt->bindValue(":crusader", $this->getInfo($player, "crusader"));
                $stmt->bindValue(":legend", $this->getInfo($player, "legend"));
                $stmt->bindValue(":overlord", $this->getInfo($player, "overlord"));
                $stmt->bindValue(":experienced", $this->getInfo($player, "experienced"));
                $stmt->bindValue(":ez", $this->getInfo($player, "ez"));
                $stmt->bindValue(":pyro", $this->getInfo($player, "pyro"));
                $stmt->bindValue(":elite", $this->getInfo($player, "elite"));
                $stmt->bindValue(":windows10", $this->getInfo($player, "windows10"));
                $stmt->bindValue(":fresh", $this->getInfo($player, "fresh"));
                $stmt->bindValue(":emperor", 1);
                $stmt->bindValue(":salty", $this->getInfo($player, "salty"));
                $stmt->bindValue(":pancakes", $this->getInfo($player, "pancakes"));
                $stmt->bindValue(":uwu", $this->getInfo($player, "uwu"));
                $stmt->bindValue(":zoomer", $this->getInfo($player, "zoomer"));
                $stmt->bindValue(":dirt", $this->getInfo($player, "dirt"));
                $stmt->bindValue(":god", $this->getInfo($player, "god"));
                $stmt->bindValue(":king", $this->getInfo($player, "king"));
                $stmt->bindValue(":gangster", $this->getInfo($player, "gangster"));
                $stmt->bindValue(":hitman", $this->getInfo($player, "hitman"));
                $stmt->bindValue(":mobster", $this->getInfo($player, "mobster"));
                $stmt->bindValue(":loner", $this->getInfo($player, "loner"));
                $stmt->bindValue(":horion", $this->getInfo($player, "horion"));
                $stmt->bindValue(":injected", $this->getInfo($player, "injected"));
                $stmt->bindValue(":bustdown", $this->getInfo($player, "bustdown"));
                $stmt->bindValue(":pro", $this->getInfo($player, "pro"));
                $stmt->bindValue(":hacker", $this->getInfo($player, "hacker"));
                $stmt->bindValue(":gucci", $this->getInfo($player, "gucci"));
                $stmt->bindValue(":troll", $this->getInfo($player, "troll"));
                $stmt->bindValue(":nou", $this->getInfo($player, "nou"));
                $stmt->bindValue(":killer", $this->getInfo($player, "killer"));
                $stmt->bindValue(":gangbang", $this->getInfo($player, "gangbang"));
                $stmt->bindValue(":chugnub", $this->getInfo($player, "chugnub"));
                $stmt->bindValue(":cornhub", $this->getInfo($player, "cornhub"));
                $stmt->bindValue(":androidgod", $this->getInfo($player, "androidgod"));
                $stmt->bindValue(":iosgod", $this->getInfo($player, "iosgod"));
                $stmt->execute();
                break;
            case "salty":
                $stmt = $this->tags->prepare("INSERT OR REPLACE INTO tags (player, lit, enhanced, mvp, ultimate, crusader, legend, overlord, experienced, ez, pyro, elite, windows10, fresh, emperor, salty, pancakes, uwu, zoomer, dirt, god, king, gangster, hitman, mobster, loner, horion, injected, bustdown, pro, hacker, gucci, troll, nou, killer, gangbang, chugnub, cornhub, androidgod, iosgod) VALUES (:player, :lit, :enhanced, :mvp, :ultimate, :crusader, :legend, :overlord, :experienced, :ez, :pyro, :elite, :windows10, :fresh, :emperor, :salty, :pancakes, :uwu, :zoomer, :dirt, :god, :king, :gangster, :hitman, :mobster, :loner, :horion, :injected, :bustdown, :pro, :hacker, :gucci, :troll, :nou, :killer, :gangbang, :chugnub, :cornhub, :androidgod, :iosgod)");
                $stmt->bindValue(":player", $player->getName());
                $stmt->bindValue(":lit", $this->getInfo($player, "lit"));
                $stmt->bindValue(":enhanced", $this->getInfo($player, "enhanced"));
                $stmt->bindValue(":mvp", $this->getInfo($player, "mvp"));
                $stmt->bindValue(":ultimate", $this->getInfo($player, "ultimate"));
                $stmt->bindValue(":crusader", $this->getInfo($player, "crusader"));
                $stmt->bindValue(":legend", $this->getInfo($player, "legend"));
                $stmt->bindValue(":overlord", $this->getInfo($player, "overlord"));
                $stmt->bindValue(":experienced", $this->getInfo($player, "experienced"));
                $stmt->bindValue(":ez", $this->getInfo($player, "ez"));
                $stmt->bindValue(":pyro", $this->getInfo($player, "pyro"));
                $stmt->bindValue(":elite", $this->getInfo($player, "elite"));
                $stmt->bindValue(":windows10", $this->getInfo($player, "windows10"));
                $stmt->bindValue(":fresh", $this->getInfo($player, "fresh"));
                $stmt->bindValue(":emperor", $this->getInfo($player, "emperor"));
                $stmt->bindValue(":salty", 1);
                $stmt->bindValue(":pancakes", $this->getInfo($player, "pancakes"));
                $stmt->bindValue(":uwu", $this->getInfo($player, "uwu"));
                $stmt->bindValue(":zoomer", $this->getInfo($player, "zoomer"));
                $stmt->bindValue(":dirt", $this->getInfo($player, "dirt"));
                $stmt->bindValue(":god", $this->getInfo($player, "god"));
                $stmt->bindValue(":king", $this->getInfo($player, "king"));
                $stmt->bindValue(":gangster", $this->getInfo($player, "gangster"));
                $stmt->bindValue(":hitman", $this->getInfo($player, "hitman"));
                $stmt->bindValue(":mobster", $this->getInfo($player, "mobster"));
                $stmt->bindValue(":loner", $this->getInfo($player, "loner"));
                $stmt->bindValue(":horion", $this->getInfo($player, "horion"));
                $stmt->bindValue(":injected", $this->getInfo($player, "injected"));
                $stmt->bindValue(":bustdown", $this->getInfo($player, "bustdown"));
                $stmt->bindValue(":pro", $this->getInfo($player, "pro"));
                $stmt->bindValue(":hacker", $this->getInfo($player, "hacker"));
                $stmt->bindValue(":gucci", $this->getInfo($player, "gucci"));
                $stmt->bindValue(":troll", $this->getInfo($player, "troll"));
                $stmt->bindValue(":nou", $this->getInfo($player, "nou"));
                $stmt->bindValue(":killer", $this->getInfo($player, "killer"));
                $stmt->bindValue(":gangbang", $this->getInfo($player, "gangbang"));
                $stmt->bindValue(":chugnub", $this->getInfo($player, "chugnub"));
                $stmt->bindValue(":cornhub", $this->getInfo($player, "cornhub"));
                $stmt->bindValue(":androidgod", $this->getInfo($player, "androidgod"));
                $stmt->bindValue(":iosgod", $this->getInfo($player, "iosgod"));
                $stmt->execute();
                break;
            case "pancakes":
                $stmt = $this->tags->prepare("INSERT OR REPLACE INTO tags (player, lit, enhanced, mvp, ultimate, crusader, legend, overlord, experienced, ez, pyro, elite, windows10, fresh, emperor, salty, pancakes, uwu, zoomer, dirt, god, king, gangster, hitman, mobster, loner, horion, injected, bustdown, pro, hacker, gucci, troll, nou, killer, gangbang, chugnub, cornhub, androidgod, iosgod) VALUES (:player, :lit, :enhanced, :mvp, :ultimate, :crusader, :legend, :overlord, :experienced, :ez, :pyro, :elite, :windows10, :fresh, :emperor, :salty, :pancakes, :uwu, :zoomer, :dirt, :god, :king, :gangster, :hitman, :mobster, :loner, :horion, :injected, :bustdown, :pro, :hacker, :gucci, :troll, :nou, :killer, :gangbang, :chugnub, :cornhub, :androidgod, :iosgod)");
                $stmt->bindValue(":player", $player->getName());
                $stmt->bindValue(":lit", $this->getInfo($player, "lit"));
                $stmt->bindValue(":enhanced", $this->getInfo($player, "enhanced"));
                $stmt->bindValue(":mvp", $this->getInfo($player, "mvp"));
                $stmt->bindValue(":ultimate", $this->getInfo($player, "ultimate"));
                $stmt->bindValue(":crusader", $this->getInfo($player, "crusader"));
                $stmt->bindValue(":legend", $this->getInfo($player, "legend"));
                $stmt->bindValue(":overlord", $this->getInfo($player, "overlord"));
                $stmt->bindValue(":experienced", $this->getInfo($player, "experienced"));
                $stmt->bindValue(":ez", $this->getInfo($player, "ez"));
                $stmt->bindValue(":pyro", $this->getInfo($player, "pyro"));
                $stmt->bindValue(":elite", $this->getInfo($player, "elite"));
                $stmt->bindValue(":windows10", $this->getInfo($player, "windows10"));
                $stmt->bindValue(":fresh", $this->getInfo($player, "fresh"));
                $stmt->bindValue(":emperor", $this->getInfo($player, "emperor"));
                $stmt->bindValue(":salty", $this->getInfo($player, "salty"));
                $stmt->bindValue(":pancakes", 1);
                $stmt->bindValue(":uwu", $this->getInfo($player, "uwu"));
                $stmt->bindValue(":zoomer", $this->getInfo($player, "zoomer"));
                $stmt->bindValue(":dirt", $this->getInfo($player, "dirt"));
                $stmt->bindValue(":god", $this->getInfo($player, "god"));
                $stmt->bindValue(":king", $this->getInfo($player, "king"));
                $stmt->bindValue(":gangster", $this->getInfo($player, "gangster"));
                $stmt->bindValue(":hitman", $this->getInfo($player, "hitman"));
                $stmt->bindValue(":mobster", $this->getInfo($player, "mobster"));
                $stmt->bindValue(":loner", $this->getInfo($player, "loner"));
                $stmt->bindValue(":horion", $this->getInfo($player, "horion"));
                $stmt->bindValue(":injected", $this->getInfo($player, "injected"));
                $stmt->bindValue(":bustdown", $this->getInfo($player, "bustdown"));
                $stmt->bindValue(":pro", $this->getInfo($player, "pro"));
                $stmt->bindValue(":hacker", $this->getInfo($player, "hacker"));
                $stmt->bindValue(":gucci", $this->getInfo($player, "gucci"));
                $stmt->bindValue(":troll", $this->getInfo($player, "troll"));
                $stmt->bindValue(":nou", $this->getInfo($player, "nou"));
                $stmt->bindValue(":killer", $this->getInfo($player, "killer"));
                $stmt->bindValue(":gangbang", $this->getInfo($player, "gangbang"));
                $stmt->bindValue(":chugnub", $this->getInfo($player, "chugnub"));
                $stmt->bindValue(":cornhub", $this->getInfo($player, "cornhub"));
                $stmt->bindValue(":androidgod", $this->getInfo($player, "androidgod"));
                $stmt->bindValue(":iosgod", $this->getInfo($player, "iosgod"));
                $stmt->execute();
                break;
            case "uwu":
                $stmt = $this->tags->prepare("INSERT OR REPLACE INTO tags (player, lit, enhanced, mvp, ultimate, crusader, legend, overlord, experienced, ez, pyro, elite, windows10, fresh, emperor, salty, pancakes, uwu, zoomer, dirt, god, king, gangster, hitman, mobster, loner, horion, injected, bustdown, pro, hacker, gucci, troll, nou, killer, gangbang, chugnub, cornhub, androidgod, iosgod) VALUES (:player, :lit, :enhanced, :mvp, :ultimate, :crusader, :legend, :overlord, :experienced, :ez, :pyro, :elite, :windows10, :fresh, :emperor, :salty, :pancakes, :uwu, :zoomer, :dirt, :god, :king, :gangster, :hitman, :mobster, :loner, :horion, :injected, :bustdown, :pro, :hacker, :gucci, :troll, :nou, :killer, :gangbang, :chugnub, :cornhub, :androidgod, :iosgod)");
                $stmt->bindValue(":player", $player->getName());
                $stmt->bindValue(":lit", $this->getInfo($player, "lit"));
                $stmt->bindValue(":enhanced", $this->getInfo($player, "enhanced"));
                $stmt->bindValue(":mvp", $this->getInfo($player, "mvp"));
                $stmt->bindValue(":ultimate", $this->getInfo($player, "ultimate"));
                $stmt->bindValue(":crusader", $this->getInfo($player, "crusader"));
                $stmt->bindValue(":legend", $this->getInfo($player, "legend"));
                $stmt->bindValue(":overlord", $this->getInfo($player, "overlord"));
                $stmt->bindValue(":experienced", $this->getInfo($player, "experienced"));
                $stmt->bindValue(":ez", $this->getInfo($player, "ez"));
                $stmt->bindValue(":pyro", $this->getInfo($player, "pyro"));
                $stmt->bindValue(":elite", $this->getInfo($player, "elite"));
                $stmt->bindValue(":windows10", $this->getInfo($player, "windows10"));
                $stmt->bindValue(":fresh", $this->getInfo($player, "fresh"));
                $stmt->bindValue(":emperor", $this->getInfo($player, "emperor"));
                $stmt->bindValue(":salty", $this->getInfo($player, "salty"));
                $stmt->bindValue(":pancakes", $this->getInfo($player, "salty"));
                $stmt->bindValue(":uwu", 1);
                $stmt->bindValue(":zoomer", $this->getInfo($player, "zoomer"));
                $stmt->bindValue(":dirt", $this->getInfo($player, "dirt"));
                $stmt->bindValue(":god", $this->getInfo($player, "god"));
                $stmt->bindValue(":king", $this->getInfo($player, "king"));
                $stmt->bindValue(":gangster", $this->getInfo($player, "gangster"));
                $stmt->bindValue(":hitman", $this->getInfo($player, "hitman"));
                $stmt->bindValue(":mobster", $this->getInfo($player, "mobster"));
                $stmt->bindValue(":loner", $this->getInfo($player, "loner"));
                $stmt->bindValue(":horion", $this->getInfo($player, "horion"));
                $stmt->bindValue(":injected", $this->getInfo($player, "injected"));
                $stmt->bindValue(":bustdown", $this->getInfo($player, "bustdown"));
                $stmt->bindValue(":pro", $this->getInfo($player, "pro"));
                $stmt->bindValue(":hacker", $this->getInfo($player, "hacker"));
                $stmt->bindValue(":gucci", $this->getInfo($player, "gucci"));
                $stmt->bindValue(":troll", $this->getInfo($player, "troll"));
                $stmt->bindValue(":nou", $this->getInfo($player, "nou"));
                $stmt->bindValue(":killer", $this->getInfo($player, "killer"));
                $stmt->bindValue(":gangbang", $this->getInfo($player, "gangbang"));
                $stmt->bindValue(":chugnub", $this->getInfo($player, "chugnub"));
                $stmt->bindValue(":cornhub", $this->getInfo($player, "cornhub"));
                $stmt->bindValue(":androidgod", $this->getInfo($player, "androidgod"));
                $stmt->bindValue(":iosgod", $this->getInfo($player, "iosgod"));
                $stmt->execute();
                break;
                break;
            case "zoomer":
                $stmt = $this->tags->prepare("INSERT OR REPLACE INTO tags (player, lit, enhanced, mvp, ultimate, crusader, legend, overlord, experienced, ez, pyro, elite, windows10, fresh, emperor, salty, pancakes, uwu, zoomer, dirt, god, king, gangster, hitman, mobster, loner, horion, injected, bustdown, pro, hacker, gucci, troll, nou, killer, gangbang, chugnub, cornhub, androidgod, iosgod) VALUES (:player, :lit, :enhanced, :mvp, :ultimate, :crusader, :legend, :overlord, :experienced, :ez, :pyro, :elite, :windows10, :fresh, :emperor, :salty, :pancakes, :uwu, :zoomer, :dirt, :god, :king, :gangster, :hitman, :mobster, :loner, :horion, :injected, :bustdown, :pro, :hacker, :gucci, :troll, :nou, :killer, :gangbang, :chugnub, :cornhub, :androidgod, :iosgod)");
                $stmt->bindValue(":player", $player->getName());
                $stmt->bindValue(":lit", $this->getInfo($player, "lit"));
                $stmt->bindValue(":enhanced", $this->getInfo($player, "enhanced"));
                $stmt->bindValue(":mvp", $this->getInfo($player, "mvp"));
                $stmt->bindValue(":ultimate", $this->getInfo($player, "ultimate"));
                $stmt->bindValue(":crusader", $this->getInfo($player, "crusader"));
                $stmt->bindValue(":legend", $this->getInfo($player, "legend"));
                $stmt->bindValue(":overlord", $this->getInfo($player, "overlord"));
                $stmt->bindValue(":experienced", $this->getInfo($player, "experienced"));
                $stmt->bindValue(":ez", $this->getInfo($player, "ez"));
                $stmt->bindValue(":pyro", $this->getInfo($player, "pyro"));
                $stmt->bindValue(":elite", $this->getInfo($player, "elite"));
                $stmt->bindValue(":windows10", $this->getInfo($player, "windows10"));
                $stmt->bindValue(":fresh", $this->getInfo($player, "fresh"));
                $stmt->bindValue(":emperor", $this->getInfo($player, "emperor"));
                $stmt->bindValue(":salty", $this->getInfo($player, "salty"));
                $stmt->bindValue(":pancakes", $this->getInfo($player, "pancakes"));
                $stmt->bindValue(":uwu", $this->getInfo($player, "uwu"));
                $stmt->bindValue(":zoomer", 1);
                $stmt->bindValue(":dirt", $this->getInfo($player, "dirt"));
                $stmt->bindValue(":god", $this->getInfo($player, "god"));
                $stmt->bindValue(":king", $this->getInfo($player, "king"));
                $stmt->bindValue(":gangster", $this->getInfo($player, "gangster"));
                $stmt->bindValue(":hitman", $this->getInfo($player, "hitman"));
                $stmt->bindValue(":mobster", $this->getInfo($player, "mobster"));
                $stmt->bindValue(":loner", $this->getInfo($player, "loner"));
                $stmt->bindValue(":horion", $this->getInfo($player, "horion"));
                $stmt->bindValue(":injected", $this->getInfo($player, "injected"));
                $stmt->bindValue(":bustdown", $this->getInfo($player, "bustdown"));
                $stmt->bindValue(":pro", $this->getInfo($player, "pro"));
                $stmt->bindValue(":hacker", $this->getInfo($player, "hacker"));
                $stmt->bindValue(":gucci", $this->getInfo($player, "gucci"));
                $stmt->bindValue(":troll", $this->getInfo($player, "troll"));
                $stmt->bindValue(":nou", $this->getInfo($player, "nou"));
                $stmt->bindValue(":killer", $this->getInfo($player, "killer"));
                $stmt->bindValue(":gangbang", $this->getInfo($player, "gangbang"));
                $stmt->bindValue(":chugnub", $this->getInfo($player, "chugnub"));
                $stmt->bindValue(":cornhub", $this->getInfo($player, "cornhub"));
                $stmt->bindValue(":androidgod", $this->getInfo($player, "androidgod"));
                $stmt->bindValue(":iosgod", $this->getInfo($player, "iosgod"));
                $stmt->execute();
                break;
            case "dirt":
                $stmt = $this->tags->prepare("INSERT OR REPLACE INTO tags (player, lit, enhanced, mvp, ultimate, crusader, legend, overlord, experienced, ez, pyro, elite, windows10, fresh, emperor, salty, pancakes, uwu, zoomer, dirt, god, king, gangster, hitman, mobster, loner, horion, injected, bustdown, pro, hacker, gucci, troll, nou, killer, gangbang, chugnub, cornhub, androidgod, iosgod) VALUES (:player, :lit, :enhanced, :mvp, :ultimate, :crusader, :legend, :overlord, :experienced, :ez, :pyro, :elite, :windows10, :fresh, :emperor, :salty, :pancakes, :uwu, :zoomer, :dirt, :god, :king, :gangster, :hitman, :mobster, :loner, :horion, :injected, :bustdown, :pro, :hacker, :gucci, :troll, :nou, :killer, :gangbang, :chugnub, :cornhub, :androidgod, :iosgod)");
                $stmt->bindValue(":player", $player->getName());
                $stmt->bindValue(":lit", $this->getInfo($player, "lit"));
                $stmt->bindValue(":enhanced", $this->getInfo($player, "enhanced"));
                $stmt->bindValue(":mvp", $this->getInfo($player, "mvp"));
                $stmt->bindValue(":ultimate", $this->getInfo($player, "ultimate"));
                $stmt->bindValue(":crusader", $this->getInfo($player, "crusader"));
                $stmt->bindValue(":legend", $this->getInfo($player, "legend"));
                $stmt->bindValue(":overlord", $this->getInfo($player, "overlord"));
                $stmt->bindValue(":experienced", $this->getInfo($player, "experienced"));
                $stmt->bindValue(":ez", $this->getInfo($player, "ez"));
                $stmt->bindValue(":pyro", $this->getInfo($player, "pyro"));
                $stmt->bindValue(":elite", $this->getInfo($player, "elite"));
                $stmt->bindValue(":windows10", $this->getInfo($player, "windows10"));
                $stmt->bindValue(":fresh", $this->getInfo($player, "fresh"));
                $stmt->bindValue(":emperor", $this->getInfo($player, "emperor"));
                $stmt->bindValue(":salty", $this->getInfo($player, "salty"));
                $stmt->bindValue(":pancakes", $this->getInfo($player, "pancakes"));
                $stmt->bindValue(":uwu", $this->getInfo($player, "uwu"));
                $stmt->bindValue(":zoomer", $this->getInfo($player, "zoomer"));
                $stmt->bindValue(":dirt", 1);
                $stmt->bindValue(":god", $this->getInfo($player, "god"));
                $stmt->bindValue(":king", $this->getInfo($player, "king"));
                $stmt->bindValue(":gangster", $this->getInfo($player, "gangster"));
                $stmt->bindValue(":hitman", $this->getInfo($player, "hitman"));
                $stmt->bindValue(":mobster", $this->getInfo($player, "mobster"));
                $stmt->bindValue(":loner", $this->getInfo($player, "loner"));
                $stmt->bindValue(":horion", $this->getInfo($player, "horion"));
                $stmt->bindValue(":injected", $this->getInfo($player, "injected"));
                $stmt->bindValue(":bustdown", $this->getInfo($player, "bustdown"));
                $stmt->bindValue(":pro", $this->getInfo($player, "pro"));
                $stmt->bindValue(":hacker", $this->getInfo($player, "hacker"));
                $stmt->bindValue(":gucci", $this->getInfo($player, "gucci"));
                $stmt->bindValue(":troll", $this->getInfo($player, "troll"));
                $stmt->bindValue(":nou", $this->getInfo($player, "nou"));
                $stmt->bindValue(":killer", $this->getInfo($player, "killer"));
                $stmt->bindValue(":gangbang", $this->getInfo($player, "gangbang"));
                $stmt->bindValue(":chugnub", $this->getInfo($player, "chugnub"));
                $stmt->bindValue(":cornhub", $this->getInfo($player, "cornhub"));
                $stmt->bindValue(":androidgod", $this->getInfo($player, "androidgod"));
                $stmt->bindValue(":iosgod", $this->getInfo($player, "iosgod"));
                $stmt->execute();
                break;
            case "god":
                $stmt = $this->tags->prepare("INSERT OR REPLACE INTO tags (player, lit, enhanced, mvp, ultimate, crusader, legend, overlord, experienced, ez, pyro, elite, windows10, fresh, emperor, salty, pancakes, uwu, zoomer, dirt, god, king, gangster, hitman, mobster, loner, horion, injected, bustdown, pro, hacker, gucci, troll, nou, killer, gangbang, chugnub, cornhub, androidgod, iosgod) VALUES (:player, :lit, :enhanced, :mvp, :ultimate, :crusader, :legend, :overlord, :experienced, :ez, :pyro, :elite, :windows10, :fresh, :emperor, :salty, :pancakes, :uwu, :zoomer, :dirt, :god, :king, :gangster, :hitman, :mobster, :loner, :horion, :injected, :bustdown, :pro, :hacker, :gucci, :troll, :nou, :killer, :gangbang, :chugnub, :cornhub, :androidgod, :iosgod)");
                $stmt->bindValue(":player", $player->getName());
                $stmt->bindValue(":lit", $this->getInfo($player, "lit"));
                $stmt->bindValue(":enhanced", $this->getInfo($player, "enhanced"));
                $stmt->bindValue(":mvp", $this->getInfo($player, "mvp"));
                $stmt->bindValue(":ultimate", $this->getInfo($player, "ultimate"));
                $stmt->bindValue(":crusader", $this->getInfo($player, "crusader"));
                $stmt->bindValue(":legend", $this->getInfo($player, "legend"));
                $stmt->bindValue(":overlord", $this->getInfo($player, "overlord"));
                $stmt->bindValue(":experienced", $this->getInfo($player, "experienced"));
                $stmt->bindValue(":ez", $this->getInfo($player, "ez"));
                $stmt->bindValue(":pyro", $this->getInfo($player, "pyro"));
                $stmt->bindValue(":elite", $this->getInfo($player, "elite"));
                $stmt->bindValue(":windows10", $this->getInfo($player, "windows10"));
                $stmt->bindValue(":fresh", $this->getInfo($player, "fresh"));
                $stmt->bindValue(":emperor", $this->getInfo($player, "emperor"));
                $stmt->bindValue(":salty", $this->getInfo($player, "salty"));
                $stmt->bindValue(":pancakes", $this->getInfo($player, "pancakes"));
                $stmt->bindValue(":uwu", $this->getInfo($player, "uwu"));
                $stmt->bindValue(":zoomer", $this->getInfo($player, "zoomer"));
                $stmt->bindValue(":dirt", $this->getInfo($player, "dirt"));
                $stmt->bindValue(":god", 1);
                $stmt->bindValue(":king", $this->getInfo($player, "king"));
                $stmt->bindValue(":gangster", $this->getInfo($player, "gangster"));
                $stmt->bindValue(":hitman", $this->getInfo($player, "hitman"));
                $stmt->bindValue(":mobster", $this->getInfo($player, "mobster"));
                $stmt->bindValue(":loner", $this->getInfo($player, "loner"));
                $stmt->bindValue(":horion", $this->getInfo($player, "horion"));
                $stmt->bindValue(":injected", $this->getInfo($player, "injected"));
                $stmt->bindValue(":bustdown", $this->getInfo($player, "bustdown"));
                $stmt->bindValue(":pro", $this->getInfo($player, "pro"));
                $stmt->bindValue(":hacker", $this->getInfo($player, "hacker"));
                $stmt->bindValue(":gucci", $this->getInfo($player, "gucci"));
                $stmt->bindValue(":troll", $this->getInfo($player, "troll"));
                $stmt->bindValue(":nou", $this->getInfo($player, "nou"));
                $stmt->bindValue(":killer", $this->getInfo($player, "killer"));
                $stmt->bindValue(":gangbang", $this->getInfo($player, "gangbang"));
                $stmt->bindValue(":chugnub", $this->getInfo($player, "chugnub"));
                $stmt->bindValue(":cornhub", $this->getInfo($player, "cornhub"));
                $stmt->bindValue(":androidgod", $this->getInfo($player, "androidgod"));
                $stmt->bindValue(":iosgod", $this->getInfo($player, "iosgod"));
                $stmt->execute();
                break;
            case "king":
                $stmt = $this->tags->prepare("INSERT OR REPLACE INTO tags (player, lit, enhanced, mvp, ultimate, crusader, legend, overlord, experienced, ez, pyro, elite, windows10, fresh, emperor, salty, pancakes, uwu, zoomer, dirt, god, king, gangster, hitman, mobster, loner, horion, injected, bustdown, pro, hacker, gucci, troll, nou, killer, gangbang, chugnub, cornhub, androidgod, iosgod) VALUES (:player, :lit, :enhanced, :mvp, :ultimate, :crusader, :legend, :overlord, :experienced, :ez, :pyro, :elite, :windows10, :fresh, :emperor, :salty, :pancakes, :uwu, :zoomer, :dirt, :god, :king, :gangster, :hitman, :mobster, :loner, :horion, :injected, :bustdown, :pro, :hacker, :gucci, :troll, :nou, :killer, :gangbang, :chugnub, :cornhub, :androidgod, :iosgod)");
                $stmt->bindValue(":player", $player->getName());
                $stmt->bindValue(":lit", $this->getInfo($player, "lit"));
                $stmt->bindValue(":enhanced", $this->getInfo($player, "enhanced"));
                $stmt->bindValue(":mvp", $this->getInfo($player, "mvp"));
                $stmt->bindValue(":ultimate", $this->getInfo($player, "ultimate"));
                $stmt->bindValue(":crusader", $this->getInfo($player, "crusader"));
                $stmt->bindValue(":legend", $this->getInfo($player, "legend"));
                $stmt->bindValue(":overlord", $this->getInfo($player, "overlord"));
                $stmt->bindValue(":experienced", $this->getInfo($player, "experienced"));
                $stmt->bindValue(":ez", $this->getInfo($player, "ez"));
                $stmt->bindValue(":pyro", $this->getInfo($player, "pyro"));
                $stmt->bindValue(":elite", $this->getInfo($player, "elite"));
                $stmt->bindValue(":windows10", $this->getInfo($player, "windows10"));
                $stmt->bindValue(":fresh", $this->getInfo($player, "fresh"));
                $stmt->bindValue(":emperor", $this->getInfo($player, "emperor"));
                $stmt->bindValue(":salty", $this->getInfo($player, "salty"));
                $stmt->bindValue(":pancakes", $this->getInfo($player, "pancakes"));
                $stmt->bindValue(":uwu", $this->getInfo($player, "uwu"));
                $stmt->bindValue(":zoomer", $this->getInfo($player, "zoomer"));
                $stmt->bindValue(":dirt", $this->getInfo($player, "dirt"));
                $stmt->bindValue(":god", $this->getInfo($player, "god"));
                $stmt->bindValue(":king", 1);
                $stmt->bindValue(":gangster", $this->getInfo($player, "gangster"));
                $stmt->bindValue(":hitman", $this->getInfo($player, "hitman"));
                $stmt->bindValue(":mobster", $this->getInfo($player, "mobster"));
                $stmt->bindValue(":loner", $this->getInfo($player, "loner"));
                $stmt->bindValue(":horion", $this->getInfo($player, "horion"));
                $stmt->bindValue(":injected", $this->getInfo($player, "injected"));
                $stmt->bindValue(":bustdown", $this->getInfo($player, "bustdown"));
                $stmt->bindValue(":pro", $this->getInfo($player, "pro"));
                $stmt->bindValue(":hacker", $this->getInfo($player, "hacker"));
                $stmt->bindValue(":gucci", $this->getInfo($player, "gucci"));
                $stmt->bindValue(":troll", $this->getInfo($player, "troll"));
                $stmt->bindValue(":nou", $this->getInfo($player, "nou"));
                $stmt->bindValue(":killer", $this->getInfo($player, "killer"));
                $stmt->bindValue(":gangbang", $this->getInfo($player, "gangbang"));
                $stmt->bindValue(":chugnub", $this->getInfo($player, "chugnub"));
                $stmt->bindValue(":cornhub", $this->getInfo($player, "cornhub"));
                $stmt->bindValue(":androidgod", $this->getInfo($player, "androidgod"));
                $stmt->bindValue(":iosgod", $this->getInfo($player, "iosgod"));
                $stmt->execute();
                break;
            case "gangster":
                $stmt = $this->tags->prepare("INSERT OR REPLACE INTO tags (player, lit, enhanced, mvp, ultimate, crusader, legend, overlord, experienced, ez, pyro, elite, windows10, fresh, emperor, salty, pancakes, uwu, zoomer, dirt, god, king, gangster, hitman, mobster, loner, horion, injected, bustdown, pro, hacker, gucci, troll, nou, killer, gangbang, chugnub, cornhub, androidgod, iosgod) VALUES (:player, :lit, :enhanced, :mvp, :ultimate, :crusader, :legend, :overlord, :experienced, :ez, :pyro, :elite, :windows10, :fresh, :emperor, :salty, :pancakes, :uwu, :zoomer, :dirt, :god, :king, :gangster, :hitman, :mobster, :loner, :horion, :injected, :bustdown, :pro, :hacker, :gucci, :troll, :nou, :killer, :gangbang, :chugnub, :cornhub, :androidgod, :iosgod)");
                $stmt->bindValue(":player", $player->getName());
                $stmt->bindValue(":lit", $this->getInfo($player, "lit"));
                $stmt->bindValue(":enhanced", $this->getInfo($player, "enhanced"));
                $stmt->bindValue(":mvp", $this->getInfo($player, "mvp"));
                $stmt->bindValue(":ultimate", $this->getInfo($player, "ultimate"));
                $stmt->bindValue(":crusader", $this->getInfo($player, "crusader"));
                $stmt->bindValue(":legend", $this->getInfo($player, "legend"));
                $stmt->bindValue(":overlord", $this->getInfo($player, "overlord"));
                $stmt->bindValue(":experienced", $this->getInfo($player, "experienced"));
                $stmt->bindValue(":ez", $this->getInfo($player, "ez"));
                $stmt->bindValue(":pyro", $this->getInfo($player, "pyro"));
                $stmt->bindValue(":elite", $this->getInfo($player, "elite"));
                $stmt->bindValue(":windows10", $this->getInfo($player, "windows10"));
                $stmt->bindValue(":fresh", $this->getInfo($player, "fresh"));
                $stmt->bindValue(":emperor", $this->getInfo($player, "emperor"));
                $stmt->bindValue(":salty", $this->getInfo($player, "salty"));
                $stmt->bindValue(":pancakes", $this->getInfo($player, "pancakes"));
                $stmt->bindValue(":uwu", $this->getInfo($player, "uwu"));
                $stmt->bindValue(":zoomer", $this->getInfo($player, "zoomer"));
                $stmt->bindValue(":dirt", $this->getInfo($player, "dirt"));
                $stmt->bindValue(":god", $this->getInfo($player, "god"));
                $stmt->bindValue(":king", $this->getInfo($player, "king"));
                $stmt->bindValue(":gangster", 1);
                $stmt->bindValue(":hitman", $this->getInfo($player, "hitman"));
                $stmt->bindValue(":mobster", $this->getInfo($player, "mobster"));
                $stmt->bindValue(":loner", $this->getInfo($player, "loner"));
                $stmt->bindValue(":horion", $this->getInfo($player, "horion"));
                $stmt->bindValue(":injected", $this->getInfo($player, "injected"));
                $stmt->bindValue(":bustdown", $this->getInfo($player, "bustdown"));
                $stmt->bindValue(":pro", $this->getInfo($player, "pro"));
                $stmt->bindValue(":hacker", $this->getInfo($player, "hacker"));
                $stmt->bindValue(":gucci", $this->getInfo($player, "gucci"));
                $stmt->bindValue(":troll", $this->getInfo($player, "troll"));
                $stmt->bindValue(":nou", $this->getInfo($player, "nou"));
                $stmt->bindValue(":killer", $this->getInfo($player, "killer"));
                $stmt->bindValue(":gangbang", $this->getInfo($player, "gangbang"));
                $stmt->bindValue(":chugnub", $this->getInfo($player, "chugnub"));
                $stmt->bindValue(":cornhub", $this->getInfo($player, "cornhub"));
                $stmt->bindValue(":androidgod", $this->getInfo($player, "androidgod"));
                $stmt->bindValue(":iosgod", $this->getInfo($player, "iosgod"));
                $stmt->execute();
                break;
            case "hitman":
                $stmt = $this->tags->prepare("INSERT OR REPLACE INTO tags (player, lit, enhanced, mvp, ultimate, crusader, legend, overlord, experienced, ez, pyro, elite, windows10, fresh, emperor, salty, pancakes, uwu, zoomer, dirt, god, king, gangster, hitman, mobster, loner, horion, injected, bustdown, pro, hacker, gucci, troll, nou, killer, gangbang, chugnub, cornhub, androidgod, iosgod) VALUES (:player, :lit, :enhanced, :mvp, :ultimate, :crusader, :legend, :overlord, :experienced, :ez, :pyro, :elite, :windows10, :fresh, :emperor, :salty, :pancakes, :uwu, :zoomer, :dirt, :god, :king, :gangster, :hitman, :mobster, :loner, :horion, :injected, :bustdown, :pro, :hacker, :gucci, :troll, :nou, :killer, :gangbang, :chugnub, :cornhub, :androidgod, :iosgod)");
                $stmt->bindValue(":player", $player->getName());
                $stmt->bindValue(":lit", $this->getInfo($player, "lit"));
                $stmt->bindValue(":enhanced", $this->getInfo($player, "enhanced"));
                $stmt->bindValue(":mvp", $this->getInfo($player, "mvp"));
                $stmt->bindValue(":ultimate", $this->getInfo($player, "ultimate"));
                $stmt->bindValue(":crusader", $this->getInfo($player, "crusader"));
                $stmt->bindValue(":legend", $this->getInfo($player, "legend"));
                $stmt->bindValue(":overlord", $this->getInfo($player, "overlord"));
                $stmt->bindValue(":experienced", $this->getInfo($player, "experienced"));
                $stmt->bindValue(":ez", $this->getInfo($player, "ez"));
                $stmt->bindValue(":pyro", $this->getInfo($player, "pyro"));
                $stmt->bindValue(":elite", $this->getInfo($player, "elite"));
                $stmt->bindValue(":windows10", $this->getInfo($player, "windows10"));
                $stmt->bindValue(":fresh", $this->getInfo($player, "fresh"));
                $stmt->bindValue(":emperor", $this->getInfo($player, "emperor"));
                $stmt->bindValue(":salty", $this->getInfo($player, "salty"));
                $stmt->bindValue(":pancakes", $this->getInfo($player, "pancakes"));
                $stmt->bindValue(":uwu", $this->getInfo($player, "uwu"));
                $stmt->bindValue(":zoomer", $this->getInfo($player, "zoomer"));
                $stmt->bindValue(":dirt", $this->getInfo($player, "dirt"));
                $stmt->bindValue(":god", $this->getInfo($player, "god"));
                $stmt->bindValue(":king", $this->getInfo($player, "king"));
                $stmt->bindValue(":gangster", $this->getInfo($player, "king"));
                $stmt->bindValue(":hitman", 1);
                $stmt->bindValue(":mobster", $this->getInfo($player, "mobster"));
                $stmt->bindValue(":loner", $this->getInfo($player, "loner"));
                $stmt->bindValue(":horion", $this->getInfo($player, "horion"));
                $stmt->bindValue(":injected", $this->getInfo($player, "injected"));
                $stmt->bindValue(":bustdown", $this->getInfo($player, "bustdown"));
                $stmt->bindValue(":pro", $this->getInfo($player, "pro"));
                $stmt->bindValue(":hacker", $this->getInfo($player, "hacker"));
                $stmt->bindValue(":gucci", $this->getInfo($player, "gucci"));
                $stmt->bindValue(":troll", $this->getInfo($player, "troll"));
                $stmt->bindValue(":nou", $this->getInfo($player, "nou"));
                $stmt->bindValue(":killer", $this->getInfo($player, "killer"));
                $stmt->bindValue(":gangbang", $this->getInfo($player, "gangbang"));
                $stmt->bindValue(":chugnub", $this->getInfo($player, "chugnub"));
                $stmt->bindValue(":cornhub", $this->getInfo($player, "cornhub"));
                $stmt->bindValue(":androidgod", $this->getInfo($player, "androidgod"));
                $stmt->bindValue(":iosgod", $this->getInfo($player, "iosgod"));
                $stmt->execute();
                break;
            case "mobster":
                $stmt = $this->tags->prepare("INSERT OR REPLACE INTO tags (player, lit, enhanced, mvp, ultimate, crusader, legend, overlord, experienced, ez, pyro, elite, windows10, fresh, emperor, salty, pancakes, uwu, zoomer, dirt, god, king, gangster, hitman, mobster, loner, horion, injected, bustdown, pro, hacker, gucci, troll, nou, killer, gangbang, chugnub, cornhub, androidgod, iosgod) VALUES (:player, :lit, :enhanced, :mvp, :ultimate, :crusader, :legend, :overlord, :experienced, :ez, :pyro, :elite, :windows10, :fresh, :emperor, :salty, :pancakes, :uwu, :zoomer, :dirt, :god, :king, :gangster, :hitman, :mobster, :loner, :horion, :injected, :bustdown, :pro, :hacker, :gucci, :troll, :nou, :killer, :gangbang, :chugnub, :cornhub, :androidgod, :iosgod)");
                $stmt->bindValue(":player", $player->getName());
                $stmt->bindValue(":lit", $this->getInfo($player, "lit"));
                $stmt->bindValue(":enhanced", $this->getInfo($player, "enhanced"));
                $stmt->bindValue(":mvp", $this->getInfo($player, "mvp"));
                $stmt->bindValue(":ultimate", $this->getInfo($player, "ultimate"));
                $stmt->bindValue(":crusader", $this->getInfo($player, "crusader"));
                $stmt->bindValue(":legend", $this->getInfo($player, "legend"));
                $stmt->bindValue(":overlord", $this->getInfo($player, "overlord"));
                $stmt->bindValue(":experienced", $this->getInfo($player, "experienced"));
                $stmt->bindValue(":ez", $this->getInfo($player, "ez"));
                $stmt->bindValue(":pyro", $this->getInfo($player, "pyro"));
                $stmt->bindValue(":elite", $this->getInfo($player, "elite"));
                $stmt->bindValue(":windows10", $this->getInfo($player, "windows10"));
                $stmt->bindValue(":fresh", $this->getInfo($player, "fresh"));
                $stmt->bindValue(":emperor", $this->getInfo($player, "emperor"));
                $stmt->bindValue(":salty", $this->getInfo($player, "salty"));
                $stmt->bindValue(":pancakes", $this->getInfo($player, "pancakes"));
                $stmt->bindValue(":uwu", $this->getInfo($player, "uwu"));
                $stmt->bindValue(":zoomer", $this->getInfo($player, "zoomer"));
                $stmt->bindValue(":dirt", $this->getInfo($player, "dirt"));
                $stmt->bindValue(":god", $this->getInfo($player, "god"));
                $stmt->bindValue(":king", $this->getInfo($player, "king"));
                $stmt->bindValue(":gangster", $this->getInfo($player, "king"));
                $stmt->bindValue(":hitman", $this->getInfo($player, "hitman"));
                $stmt->bindValue(":mobster", 1);
                $stmt->bindValue(":loner", $this->getInfo($player, "loner"));
                $stmt->bindValue(":horion", $this->getInfo($player, "horion"));
                $stmt->bindValue(":injected", $this->getInfo($player, "injected"));
                $stmt->bindValue(":bustdown", $this->getInfo($player, "bustdown"));
                $stmt->bindValue(":pro", $this->getInfo($player, "pro"));
                $stmt->bindValue(":hacker", $this->getInfo($player, "hacker"));
                $stmt->bindValue(":gucci", $this->getInfo($player, "gucci"));
                $stmt->bindValue(":troll", $this->getInfo($player, "troll"));
                $stmt->bindValue(":nou", $this->getInfo($player, "nou"));
                $stmt->bindValue(":killer", $this->getInfo($player, "killer"));
                $stmt->bindValue(":gangbang", $this->getInfo($player, "gangbang"));
                $stmt->bindValue(":chugnub", $this->getInfo($player, "chugnub"));
                $stmt->bindValue(":cornhub", $this->getInfo($player, "cornhub"));
                $stmt->bindValue(":androidgod", $this->getInfo($player, "androidgod"));
                $stmt->bindValue(":iosgod", $this->getInfo($player, "iosgod"));
                $stmt->execute();
                break;
            case "loner":
                $stmt = $this->tags->prepare("INSERT OR REPLACE INTO tags (player, lit, enhanced, mvp, ultimate, crusader, legend, overlord, experienced, ez, pyro, elite, windows10, fresh, emperor, salty, pancakes, uwu, zoomer, dirt, god, king, gangster, hitman, mobster, loner, horion, injected, bustdown, pro, hacker, gucci, troll, nou, killer, gangbang, chugnub, cornhub, androidgod, iosgod) VALUES (:player, :lit, :enhanced, :mvp, :ultimate, :crusader, :legend, :overlord, :experienced, :ez, :pyro, :elite, :windows10, :fresh, :emperor, :salty, :pancakes, :uwu, :zoomer, :dirt, :god, :king, :gangster, :hitman, :mobster, :loner, :horion, :injected, :bustdown, :pro, :hacker, :gucci, :troll, :nou, :killer, :gangbang, :chugnub, :cornhub, :androidgod, :iosgod)");
                $stmt->bindValue(":player", $player->getName());
                $stmt->bindValue(":lit", $this->getInfo($player, "lit"));
                $stmt->bindValue(":enhanced", $this->getInfo($player, "enhanced"));
                $stmt->bindValue(":mvp", $this->getInfo($player, "mvp"));
                $stmt->bindValue(":ultimate", $this->getInfo($player, "ultimate"));
                $stmt->bindValue(":crusader", $this->getInfo($player, "crusader"));
                $stmt->bindValue(":legend", $this->getInfo($player, "legend"));
                $stmt->bindValue(":overlord", $this->getInfo($player, "overlord"));
                $stmt->bindValue(":experienced", $this->getInfo($player, "experienced"));
                $stmt->bindValue(":ez", $this->getInfo($player, "ez"));
                $stmt->bindValue(":pyro", $this->getInfo($player, "pyro"));
                $stmt->bindValue(":elite", $this->getInfo($player, "elite"));
                $stmt->bindValue(":windows10", $this->getInfo($player, "windows10"));
                $stmt->bindValue(":fresh", $this->getInfo($player, "fresh"));
                $stmt->bindValue(":emperor", $this->getInfo($player, "emperor"));
                $stmt->bindValue(":salty", $this->getInfo($player, "salty"));
                $stmt->bindValue(":pancakes", $this->getInfo($player, "pancakes"));
                $stmt->bindValue(":uwu", $this->getInfo($player, "uwu"));
                $stmt->bindValue(":zoomer", $this->getInfo($player, "zoomer"));
                $stmt->bindValue(":dirt", $this->getInfo($player, "dirt"));
                $stmt->bindValue(":god", $this->getInfo($player, "god"));
                $stmt->bindValue(":king", $this->getInfo($player, "king"));
                $stmt->bindValue(":gangster", $this->getInfo($player, "gangster"));
                $stmt->bindValue(":hitman", $this->getInfo($player, "hitman"));
                $stmt->bindValue(":mobster", $this->getInfo($player, "mobster"));
                $stmt->bindValue(":loner", 1);
                $stmt->bindValue(":horion", $this->getInfo($player, "horion"));
                $stmt->bindValue(":injected", $this->getInfo($player, "injected"));
                $stmt->bindValue(":bustdown", $this->getInfo($player, "bustdown"));
                $stmt->bindValue(":pro", $this->getInfo($player, "pro"));
                $stmt->bindValue(":hacker", $this->getInfo($player, "hacker"));
                $stmt->bindValue(":gucci", $this->getInfo($player, "gucci"));
                $stmt->bindValue(":troll", $this->getInfo($player, "troll"));
                $stmt->bindValue(":nou", $this->getInfo($player, "nou"));
                $stmt->bindValue(":killer", $this->getInfo($player, "killer"));
                $stmt->bindValue(":gangbang", $this->getInfo($player, "gangbang"));
                $stmt->bindValue(":chugnub", $this->getInfo($player, "chugnub"));
                $stmt->bindValue(":cornhub", $this->getInfo($player, "cornhub"));
                $stmt->bindValue(":androidgod", $this->getInfo($player, "androidgod"));
                $stmt->bindValue(":iosgod", $this->getInfo($player, "iosgod"));
                $stmt->execute();
                break;
            case "horion":
                $stmt = $this->tags->prepare("INSERT OR REPLACE INTO tags (player, lit, enhanced, mvp, ultimate, crusader, legend, overlord, experienced, ez, pyro, elite, windows10, fresh, emperor, salty, pancakes, uwu, zoomer, dirt, god, king, gangster, hitman, mobster, loner, horion, injected, bustdown, pro, hacker, gucci, troll, nou, killer, gangbang, chugnub, cornhub, androidgod, iosgod) VALUES (:player, :lit, :enhanced, :mvp, :ultimate, :crusader, :legend, :overlord, :experienced, :ez, :pyro, :elite, :windows10, :fresh, :emperor, :salty, :pancakes, :uwu, :zoomer, :dirt, :god, :king, :gangster, :hitman, :mobster, :loner, :horion, :injected, :bustdown, :pro, :hacker, :gucci, :troll, :nou, :killer, :gangbang, :chugnub, :cornhub, :androidgod, :iosgod)");
                $stmt->bindValue(":player", $player->getName());
                $stmt->bindValue(":lit", $this->getInfo($player, "lit"));
                $stmt->bindValue(":enhanced", $this->getInfo($player, "enhanced"));
                $stmt->bindValue(":mvp", $this->getInfo($player, "mvp"));
                $stmt->bindValue(":ultimate", $this->getInfo($player, "ultimate"));
                $stmt->bindValue(":crusader", $this->getInfo($player, "crusader"));
                $stmt->bindValue(":legend", $this->getInfo($player, "legend"));
                $stmt->bindValue(":overlord", $this->getInfo($player, "overlord"));
                $stmt->bindValue(":experienced", $this->getInfo($player, "experienced"));
                $stmt->bindValue(":ez", $this->getInfo($player, "ez"));
                $stmt->bindValue(":pyro", $this->getInfo($player, "pyro"));
                $stmt->bindValue(":elite", $this->getInfo($player, "elite"));
                $stmt->bindValue(":windows10", $this->getInfo($player, "windows10"));
                $stmt->bindValue(":fresh", $this->getInfo($player, "fresh"));
                $stmt->bindValue(":emperor", $this->getInfo($player, "emperor"));
                $stmt->bindValue(":salty", $this->getInfo($player, "salty"));
                $stmt->bindValue(":pancakes", $this->getInfo($player, "pancakes"));
                $stmt->bindValue(":uwu", $this->getInfo($player, "uwu"));
                $stmt->bindValue(":zoomer", $this->getInfo($player, "zoomer"));
                $stmt->bindValue(":dirt", $this->getInfo($player, "dirt"));
                $stmt->bindValue(":god", $this->getInfo($player, "god"));
                $stmt->bindValue(":king", $this->getInfo($player, "king"));
                $stmt->bindValue(":gangster", $this->getInfo($player, "gangster"));
                $stmt->bindValue(":hitman", $this->getInfo($player, "hitman"));
                $stmt->bindValue(":mobster", $this->getInfo($player, "mobster"));
                $stmt->bindValue(":loner", $this->getInfo($player, "loner"));
                $stmt->bindValue(":horion", 1);
                $stmt->bindValue(":injected", $this->getInfo($player, "injected"));
                $stmt->bindValue(":bustdown", $this->getInfo($player, "bustdown"));
                $stmt->bindValue(":pro", $this->getInfo($player, "pro"));
                $stmt->bindValue(":hacker", $this->getInfo($player, "hacker"));
                $stmt->bindValue(":gucci", $this->getInfo($player, "gucci"));
                $stmt->bindValue(":troll", $this->getInfo($player, "troll"));
                $stmt->bindValue(":nou", $this->getInfo($player, "nou"));
                $stmt->bindValue(":killer", $this->getInfo($player, "killer"));
                $stmt->bindValue(":gangbang", $this->getInfo($player, "gangbang"));
                $stmt->bindValue(":chugnub", $this->getInfo($player, "chugnub"));
                $stmt->bindValue(":cornhub", $this->getInfo($player, "cornhub"));
                $stmt->bindValue(":androidgod", $this->getInfo($player, "androidgod"));
                $stmt->bindValue(":iosgod", $this->getInfo($player, "iosgod"));
                $stmt->execute();
                break;
            case "injected":
                $stmt = $this->tags->prepare("INSERT OR REPLACE INTO tags (player, lit, enhanced, mvp, ultimate, crusader, legend, overlord, experienced, ez, pyro, elite, windows10, fresh, emperor, salty, pancakes, uwu, zoomer, dirt, god, king, gangster, hitman, mobster, loner, horion, injected, bustdown, pro, hacker, gucci, troll, nou, killer, gangbang, chugnub, cornhub, androidgod, iosgod) VALUES (:player, :lit, :enhanced, :mvp, :ultimate, :crusader, :legend, :overlord, :experienced, :ez, :pyro, :elite, :windows10, :fresh, :emperor, :salty, :pancakes, :uwu, :zoomer, :dirt, :god, :king, :gangster, :hitman, :mobster, :loner, :horion, :injected, :bustdown, :pro, :hacker, :gucci, :troll, :nou, :killer, :gangbang, :chugnub, :cornhub, :androidgod, :iosgod)");
                $stmt->bindValue(":player", $player->getName());
                $stmt->bindValue(":lit", $this->getInfo($player, "lit"));
                $stmt->bindValue(":enhanced", $this->getInfo($player, "enhanced"));
                $stmt->bindValue(":mvp", $this->getInfo($player, "mvp"));
                $stmt->bindValue(":ultimate", $this->getInfo($player, "ultimate"));
                $stmt->bindValue(":crusader", $this->getInfo($player, "crusader"));
                $stmt->bindValue(":legend", $this->getInfo($player, "legend"));
                $stmt->bindValue(":overlord", $this->getInfo($player, "overlord"));
                $stmt->bindValue(":experienced", $this->getInfo($player, "experienced"));
                $stmt->bindValue(":ez", $this->getInfo($player, "ez"));
                $stmt->bindValue(":pyro", $this->getInfo($player, "pyro"));
                $stmt->bindValue(":elite", $this->getInfo($player, "elite"));
                $stmt->bindValue(":windows10", $this->getInfo($player, "windows10"));
                $stmt->bindValue(":fresh", $this->getInfo($player, "fresh"));
                $stmt->bindValue(":emperor", $this->getInfo($player, "emperor"));
                $stmt->bindValue(":salty", $this->getInfo($player, "salty"));
                $stmt->bindValue(":pancakes", $this->getInfo($player, "pancakes"));
                $stmt->bindValue(":uwu", $this->getInfo($player, "uwu"));
                $stmt->bindValue(":zoomer", $this->getInfo($player, "zoomer"));
                $stmt->bindValue(":dirt", $this->getInfo($player, "dirt"));
                $stmt->bindValue(":god", $this->getInfo($player, "god"));
                $stmt->bindValue(":king", $this->getInfo($player, "king"));
                $stmt->bindValue(":gangster", $this->getInfo($player, "gangster"));
                $stmt->bindValue(":hitman", $this->getInfo($player, "hitman"));
                $stmt->bindValue(":mobster", $this->getInfo($player, "mobster"));
                $stmt->bindValue(":loner", $this->getInfo($player, "loner"));
                $stmt->bindValue(":horion", $this->getInfo($player, "horion"));
                $stmt->bindValue(":injected", 1);
                $stmt->bindValue(":bustdown", $this->getInfo($player, "bustdown"));
                $stmt->bindValue(":pro", $this->getInfo($player, "pro"));
                $stmt->bindValue(":hacker", $this->getInfo($player, "hacker"));
                $stmt->bindValue(":gucci", $this->getInfo($player, "gucci"));
                $stmt->bindValue(":troll", $this->getInfo($player, "troll"));
                $stmt->bindValue(":nou", $this->getInfo($player, "nou"));
                $stmt->bindValue(":killer", $this->getInfo($player, "killer"));
                $stmt->bindValue(":gangbang", $this->getInfo($player, "gangbang"));
                $stmt->bindValue(":chugnub", $this->getInfo($player, "chugnub"));
                $stmt->bindValue(":cornhub", $this->getInfo($player, "cornhub"));
                $stmt->bindValue(":androidgod", $this->getInfo($player, "androidgod"));
                $stmt->bindValue(":iosgod", $this->getInfo($player, "iosgod"));
                $stmt->execute();
                break;
            case "bustdown":
                $stmt = $this->tags->prepare("INSERT OR REPLACE INTO tags (player, lit, enhanced, mvp, ultimate, crusader, legend, overlord, experienced, ez, pyro, elite, windows10, fresh, emperor, salty, pancakes, uwu, zoomer, dirt, god, king, gangster, hitman, mobster, loner, horion, injected, bustdown, pro, hacker, gucci, troll, nou, killer, gangbang, chugnub, cornhub, androidgod, iosgod) VALUES (:player, :lit, :enhanced, :mvp, :ultimate, :crusader, :legend, :overlord, :experienced, :ez, :pyro, :elite, :windows10, :fresh, :emperor, :salty, :pancakes, :uwu, :zoomer, :dirt, :god, :king, :gangster, :hitman, :mobster, :loner, :horion, :injected, :bustdown, :pro, :hacker, :gucci, :troll, :nou, :killer, :gangbang, :chugnub, :cornhub, :androidgod, :iosgod)");
                $stmt->bindValue(":player", $player->getName());
                $stmt->bindValue(":lit", $this->getInfo($player, "lit"));
                $stmt->bindValue(":enhanced", $this->getInfo($player, "enhanced"));
                $stmt->bindValue(":mvp", $this->getInfo($player, "mvp"));
                $stmt->bindValue(":ultimate", $this->getInfo($player, "ultimate"));
                $stmt->bindValue(":crusader", $this->getInfo($player, "crusader"));
                $stmt->bindValue(":legend", $this->getInfo($player, "legend"));
                $stmt->bindValue(":overlord", $this->getInfo($player, "overlord"));
                $stmt->bindValue(":experienced", $this->getInfo($player, "experienced"));
                $stmt->bindValue(":ez", $this->getInfo($player, "ez"));
                $stmt->bindValue(":pyro", $this->getInfo($player, "pyro"));
                $stmt->bindValue(":elite", $this->getInfo($player, "elite"));
                $stmt->bindValue(":windows10", $this->getInfo($player, "windows10"));
                $stmt->bindValue(":fresh", $this->getInfo($player, "fresh"));
                $stmt->bindValue(":emperor", $this->getInfo($player, "emperor"));
                $stmt->bindValue(":salty", $this->getInfo($player, "salty"));
                $stmt->bindValue(":pancakes", $this->getInfo($player, "pancakes"));
                $stmt->bindValue(":uwu", $this->getInfo($player, "uwu"));
                $stmt->bindValue(":zoomer", $this->getInfo($player, "zoomer"));
                $stmt->bindValue(":dirt", $this->getInfo($player, "dirt"));
                $stmt->bindValue(":god", $this->getInfo($player, "god"));
                $stmt->bindValue(":king", $this->getInfo($player, "king"));
                $stmt->bindValue(":gangster", $this->getInfo($player, "gangster"));
                $stmt->bindValue(":hitman", $this->getInfo($player, "hitman"));
                $stmt->bindValue(":mobster", $this->getInfo($player, "mobster"));
                $stmt->bindValue(":loner", $this->getInfo($player, "loner"));
                $stmt->bindValue(":horion", $this->getInfo($player, "horion"));
                $stmt->bindValue(":injected", $this->getInfo($player, "injected"));
                $stmt->bindValue(":bustdown", 1);
                $stmt->bindValue(":pro", $this->getInfo($player, "pro"));
                $stmt->bindValue(":hacker", $this->getInfo($player, "hacker"));
                $stmt->bindValue(":gucci", $this->getInfo($player, "gucci"));
                $stmt->bindValue(":troll", $this->getInfo($player, "troll"));
                $stmt->bindValue(":nou", $this->getInfo($player, "nou"));
                $stmt->bindValue(":killer", $this->getInfo($player, "killer"));
                $stmt->bindValue(":gangbang", $this->getInfo($player, "gangbang"));
                $stmt->bindValue(":chugnub", $this->getInfo($player, "chugnub"));
                $stmt->bindValue(":cornhub", $this->getInfo($player, "cornhub"));
                $stmt->bindValue(":androidgod", $this->getInfo($player, "androidgod"));
                $stmt->bindValue(":iosgod", $this->getInfo($player, "iosgod"));
                $stmt->execute();
                break;
                break;
            case "pro":
                $stmt = $this->tags->prepare("INSERT OR REPLACE INTO tags (player, lit, enhanced, mvp, ultimate, crusader, legend, overlord, experienced, ez, pyro, elite, windows10, fresh, emperor, salty, pancakes, uwu, zoomer, dirt, god, king, gangster, hitman, mobster, loner, horion, injected, bustdown, pro, hacker, gucci, troll, nou, killer, gangbang, chugnub, cornhub, androidgod, iosgod) VALUES (:player, :lit, :enhanced, :mvp, :ultimate, :crusader, :legend, :overlord, :experienced, :ez, :pyro, :elite, :windows10, :fresh, :emperor, :salty, :pancakes, :uwu, :zoomer, :dirt, :god, :king, :gangster, :hitman, :mobster, :loner, :horion, :injected, :bustdown, :pro, :hacker, :gucci, :troll, :nou, :killer, :gangbang, :chugnub, :cornhub, :androidgod, :iosgod)");
                $stmt->bindValue(":player", $player->getName());
                $stmt->bindValue(":lit", $this->getInfo($player, "lit"));
                $stmt->bindValue(":enhanced", $this->getInfo($player, "enhanced"));
                $stmt->bindValue(":mvp", $this->getInfo($player, "mvp"));
                $stmt->bindValue(":ultimate", $this->getInfo($player, "ultimate"));
                $stmt->bindValue(":crusader", $this->getInfo($player, "crusader"));
                $stmt->bindValue(":legend", $this->getInfo($player, "legend"));
                $stmt->bindValue(":overlord", $this->getInfo($player, "overlord"));
                $stmt->bindValue(":experienced", $this->getInfo($player, "experienced"));
                $stmt->bindValue(":ez", $this->getInfo($player, "ez"));
                $stmt->bindValue(":pyro", $this->getInfo($player, "pyro"));
                $stmt->bindValue(":elite", $this->getInfo($player, "elite"));
                $stmt->bindValue(":windows10", $this->getInfo($player, "windows10"));
                $stmt->bindValue(":fresh", $this->getInfo($player, "fresh"));
                $stmt->bindValue(":emperor", $this->getInfo($player, "emperor"));
                $stmt->bindValue(":salty", $this->getInfo($player, "salty"));
                $stmt->bindValue(":pancakes", $this->getInfo($player, "pancakes"));
                $stmt->bindValue(":uwu", $this->getInfo($player, "uwu"));
                $stmt->bindValue(":zoomer", $this->getInfo($player, "zoomer"));
                $stmt->bindValue(":dirt", $this->getInfo($player, "dirt"));
                $stmt->bindValue(":god", $this->getInfo($player, "god"));
                $stmt->bindValue(":king", $this->getInfo($player, "king"));
                $stmt->bindValue(":gangster", $this->getInfo($player, "gangster"));
                $stmt->bindValue(":hitman", $this->getInfo($player, "hitman"));
                $stmt->bindValue(":mobster", $this->getInfo($player, "mobster"));
                $stmt->bindValue(":loner", $this->getInfo($player, "loner"));
                $stmt->bindValue(":horion", $this->getInfo($player, "horion"));
                $stmt->bindValue(":injected", $this->getInfo($player, "injected"));
                $stmt->bindValue(":bustdown", $this->getInfo($player, "bustdown"));
                $stmt->bindValue(":pro", 1);
                $stmt->bindValue(":hacker", $this->getInfo($player, "hacker"));
                $stmt->bindValue(":gucci", $this->getInfo($player, "gucci"));
                $stmt->bindValue(":troll", $this->getInfo($player, "troll"));
                $stmt->bindValue(":nou", $this->getInfo($player, "nou"));
                $stmt->bindValue(":killer", $this->getInfo($player, "killer"));
                $stmt->bindValue(":gangbang", $this->getInfo($player, "gangbang"));
                $stmt->bindValue(":chugnub", $this->getInfo($player, "chugnub"));
                $stmt->bindValue(":cornhub", $this->getInfo($player, "cornhub"));
                $stmt->bindValue(":androidgod", $this->getInfo($player, "androidgod"));
                $stmt->bindValue(":iosgod", $this->getInfo($player, "iosgod"));
                $stmt->execute();
                break;
            case "hacker":
                $stmt = $this->tags->prepare("INSERT OR REPLACE INTO tags (player, lit, enhanced, mvp, ultimate, crusader, legend, overlord, experienced, ez, pyro, elite, windows10, fresh, emperor, salty, pancakes, uwu, zoomer, dirt, god, king, gangster, hitman, mobster, loner, horion, injected, bustdown, pro, hacker, gucci, troll, nou, killer, gangbang, chugnub, cornhub, androidgod, iosgod) VALUES (:player, :lit, :enhanced, :mvp, :ultimate, :crusader, :legend, :overlord, :experienced, :ez, :pyro, :elite, :windows10, :fresh, :emperor, :salty, :pancakes, :uwu, :zoomer, :dirt, :god, :king, :gangster, :hitman, :mobster, :loner, :horion, :injected, :bustdown, :pro, :hacker, :gucci, :troll, :nou, :killer, :gangbang, :chugnub, :cornhub, :androidgod, :iosgod)");
                $stmt->bindValue(":player", $player->getName());
                $stmt->bindValue(":lit", $this->getInfo($player, "lit"));
                $stmt->bindValue(":enhanced", $this->getInfo($player, "enhanced"));
                $stmt->bindValue(":mvp", $this->getInfo($player, "mvp"));
                $stmt->bindValue(":ultimate", $this->getInfo($player, "ultimate"));
                $stmt->bindValue(":crusader", $this->getInfo($player, "crusader"));
                $stmt->bindValue(":legend", $this->getInfo($player, "legend"));
                $stmt->bindValue(":overlord", $this->getInfo($player, "overlord"));
                $stmt->bindValue(":experienced", $this->getInfo($player, "experienced"));
                $stmt->bindValue(":ez", $this->getInfo($player, "ez"));
                $stmt->bindValue(":pyro", $this->getInfo($player, "pyro"));
                $stmt->bindValue(":elite", $this->getInfo($player, "elite"));
                $stmt->bindValue(":windows10", $this->getInfo($player, "windows10"));
                $stmt->bindValue(":fresh", $this->getInfo($player, "fresh"));
                $stmt->bindValue(":emperor", $this->getInfo($player, "emperor"));
                $stmt->bindValue(":salty", $this->getInfo($player, "salty"));
                $stmt->bindValue(":pancakes", $this->getInfo($player, "pancakes"));
                $stmt->bindValue(":uwu", $this->getInfo($player, "uwu"));
                $stmt->bindValue(":zoomer", $this->getInfo($player, "zoomer"));
                $stmt->bindValue(":dirt", $this->getInfo($player, "dirt"));
                $stmt->bindValue(":god", $this->getInfo($player, "god"));
                $stmt->bindValue(":king", $this->getInfo($player, "king"));
                $stmt->bindValue(":gangster", $this->getInfo($player, "gangster"));
                $stmt->bindValue(":hitman", $this->getInfo($player, "hitman"));
                $stmt->bindValue(":mobster", $this->getInfo($player, "mobster"));
                $stmt->bindValue(":loner", $this->getInfo($player, "loner"));
                $stmt->bindValue(":horion", $this->getInfo($player, "horion"));
                $stmt->bindValue(":injected", $this->getInfo($player, "injected"));
                $stmt->bindValue(":bustdown", $this->getInfo($player, "bustdown"));
                $stmt->bindValue(":pro", $this->getInfo($player, "pro"));
                $stmt->bindValue(":hacker", 1);
                $stmt->bindValue(":gucci", $this->getInfo($player, "gucci"));
                $stmt->bindValue(":troll", $this->getInfo($player, "troll"));
                $stmt->bindValue(":nou", $this->getInfo($player, "nou"));
                $stmt->bindValue(":killer", $this->getInfo($player, "killer"));
                $stmt->bindValue(":gangbang", $this->getInfo($player, "gangbang"));
                $stmt->bindValue(":chugnub", $this->getInfo($player, "chugnub"));
                $stmt->bindValue(":cornhub", $this->getInfo($player, "cornhub"));
                $stmt->bindValue(":androidgod", $this->getInfo($player, "androidgod"));
                $stmt->bindValue(":iosgod", $this->getInfo($player, "iosgod"));
                $stmt->execute();
                break;
            case "gucci":
                $stmt = $this->tags->prepare("INSERT OR REPLACE INTO tags (player, lit, enhanced, mvp, ultimate, crusader, legend, overlord, experienced, ez, pyro, elite, windows10, fresh, emperor, salty, pancakes, uwu, zoomer, dirt, god, king, gangster, hitman, mobster, loner, horion, injected, bustdown, pro, hacker, gucci, troll, nou, killer, gangbang, chugnub, cornhub, androidgod, iosgod) VALUES (:player, :lit, :enhanced, :mvp, :ultimate, :crusader, :legend, :overlord, :experienced, :ez, :pyro, :elite, :windows10, :fresh, :emperor, :salty, :pancakes, :uwu, :zoomer, :dirt, :god, :king, :gangster, :hitman, :mobster, :loner, :horion, :injected, :bustdown, :pro, :hacker, :gucci, :troll, :nou, :killer, :gangbang, :chugnub, :cornhub, :androidgod, :iosgod)");
                $stmt->bindValue(":player", $player->getName());
                $stmt->bindValue(":lit", $this->getInfo($player, "lit"));
                $stmt->bindValue(":enhanced", $this->getInfo($player, "enhanced"));
                $stmt->bindValue(":mvp", $this->getInfo($player, "mvp"));
                $stmt->bindValue(":ultimate", $this->getInfo($player, "ultimate"));
                $stmt->bindValue(":crusader", $this->getInfo($player, "crusader"));
                $stmt->bindValue(":legend", $this->getInfo($player, "legend"));
                $stmt->bindValue(":overlord", $this->getInfo($player, "overlord"));
                $stmt->bindValue(":experienced", $this->getInfo($player, "experienced"));
                $stmt->bindValue(":ez", $this->getInfo($player, "ez"));
                $stmt->bindValue(":pyro", $this->getInfo($player, "pyro"));
                $stmt->bindValue(":elite", $this->getInfo($player, "elite"));
                $stmt->bindValue(":windows10", $this->getInfo($player, "windows10"));
                $stmt->bindValue(":fresh", $this->getInfo($player, "fresh"));
                $stmt->bindValue(":emperor", $this->getInfo($player, "emperor"));
                $stmt->bindValue(":salty", $this->getInfo($player, "salty"));
                $stmt->bindValue(":pancakes", $this->getInfo($player, "pancakes"));
                $stmt->bindValue(":uwu", $this->getInfo($player, "uwu"));
                $stmt->bindValue(":zoomer", $this->getInfo($player, "zoomer"));
                $stmt->bindValue(":dirt", $this->getInfo($player, "dirt"));
                $stmt->bindValue(":god", $this->getInfo($player, "god"));
                $stmt->bindValue(":king", $this->getInfo($player, "king"));
                $stmt->bindValue(":gangster", $this->getInfo($player, "gangster"));
                $stmt->bindValue(":hitman", $this->getInfo($player, "hitman"));
                $stmt->bindValue(":mobster", $this->getInfo($player, "mobster"));
                $stmt->bindValue(":loner", $this->getInfo($player, "loner"));
                $stmt->bindValue(":horion", $this->getInfo($player, "horion"));
                $stmt->bindValue(":injected", $this->getInfo($player, "injected"));
                $stmt->bindValue(":bustdown", $this->getInfo($player, "bustdown"));
                $stmt->bindValue(":pro", $this->getInfo($player, "pro"));
                $stmt->bindValue(":hacker", $this->getInfo($player, "hacker"));
                $stmt->bindValue(":gucci", 1);
                $stmt->bindValue(":troll", $this->getInfo($player, "troll"));
                $stmt->bindValue(":nou", $this->getInfo($player, "nou"));
                $stmt->bindValue(":killer", $this->getInfo($player, "killer"));
                $stmt->bindValue(":gangbang", $this->getInfo($player, "gangbang"));
                $stmt->bindValue(":chugnub", $this->getInfo($player, "chugnub"));
                $stmt->bindValue(":cornhub", $this->getInfo($player, "cornhub"));
                $stmt->bindValue(":androidgod", $this->getInfo($player, "androidgod"));
                $stmt->bindValue(":iosgod", $this->getInfo($player, "iosgod"));
                $stmt->execute();
                break;
            case "troll":
                $stmt = $this->tags->prepare("INSERT OR REPLACE INTO tags (player, lit, enhanced, mvp, ultimate, crusader, legend, overlord, experienced, ez, pyro, elite, windows10, fresh, emperor, salty, pancakes, uwu, zoomer, dirt, god, king, gangster, hitman, mobster, loner, horion, injected, bustdown, pro, hacker, gucci, troll, nou, killer, gangbang, chugnub, cornhub, androidgod, iosgod) VALUES (:player, :lit, :enhanced, :mvp, :ultimate, :crusader, :legend, :overlord, :experienced, :ez, :pyro, :elite, :windows10, :fresh, :emperor, :salty, :pancakes, :uwu, :zoomer, :dirt, :god, :king, :gangster, :hitman, :mobster, :loner, :horion, :injected, :bustdown, :pro, :hacker, :gucci, :troll, :nou, :killer, :gangbang, :chugnub, :cornhub, :androidgod, :iosgod)");
                $stmt->bindValue(":player", $player->getName());
                $stmt->bindValue(":lit", $this->getInfo($player, "lit"));
                $stmt->bindValue(":enhanced", $this->getInfo($player, "enhanced"));
                $stmt->bindValue(":mvp", $this->getInfo($player, "mvp"));
                $stmt->bindValue(":ultimate", $this->getInfo($player, "ultimate"));
                $stmt->bindValue(":crusader", $this->getInfo($player, "crusader"));
                $stmt->bindValue(":legend", $this->getInfo($player, "legend"));
                $stmt->bindValue(":overlord", $this->getInfo($player, "overlord"));
                $stmt->bindValue(":experienced", $this->getInfo($player, "experienced"));
                $stmt->bindValue(":ez", $this->getInfo($player, "ez"));
                $stmt->bindValue(":pyro", $this->getInfo($player, "pyro"));
                $stmt->bindValue(":elite", $this->getInfo($player, "elite"));
                $stmt->bindValue(":windows10", $this->getInfo($player, "windows10"));
                $stmt->bindValue(":fresh", $this->getInfo($player, "fresh"));
                $stmt->bindValue(":emperor", $this->getInfo($player, "emperor"));
                $stmt->bindValue(":salty", $this->getInfo($player, "salty"));
                $stmt->bindValue(":pancakes", $this->getInfo($player, "pancakes"));
                $stmt->bindValue(":uwu", $this->getInfo($player, "uwu"));
                $stmt->bindValue(":zoomer", $this->getInfo($player, "zoomer"));
                $stmt->bindValue(":dirt", $this->getInfo($player, "dirt"));
                $stmt->bindValue(":god", $this->getInfo($player, "god"));
                $stmt->bindValue(":king", $this->getInfo($player, "king"));
                $stmt->bindValue(":gangster", $this->getInfo($player, "gangster"));
                $stmt->bindValue(":hitman", $this->getInfo($player, "hitman"));
                $stmt->bindValue(":mobster", $this->getInfo($player, "mobster"));
                $stmt->bindValue(":loner", $this->getInfo($player, "loner"));
                $stmt->bindValue(":horion", $this->getInfo($player, "horion"));
                $stmt->bindValue(":injected", $this->getInfo($player, "injected"));
                $stmt->bindValue(":bustdown", $this->getInfo($player, "bustdown"));
                $stmt->bindValue(":pro", $this->getInfo($player, "pro"));
                $stmt->bindValue(":hacker", $this->getInfo($player, "hacker"));
                $stmt->bindValue(":gucci", $this->getInfo($player, "gucci"));
                $stmt->bindValue(":troll", 1);
                $stmt->bindValue(":nou", $this->getInfo($player, "nou"));
                $stmt->bindValue(":killer", $this->getInfo($player, "killer"));
                $stmt->bindValue(":gangbang", $this->getInfo($player, "gangbang"));
                $stmt->bindValue(":chugnub", $this->getInfo($player, "chugnub"));
                $stmt->bindValue(":cornhub", $this->getInfo($player, "cornhub"));
                $stmt->bindValue(":androidgod", $this->getInfo($player, "androidgod"));
                $stmt->bindValue(":iosgod", $this->getInfo($player, "iosgod"));
                $stmt->execute();
                break;
            case "nou":
                $stmt = $this->tags->prepare("INSERT OR REPLACE INTO tags (player, lit, enhanced, mvp, ultimate, crusader, legend, overlord, experienced, ez, pyro, elite, windows10, fresh, emperor, salty, pancakes, uwu, zoomer, dirt, god, king, gangster, hitman, mobster, loner, horion, injected, bustdown, pro, hacker, gucci, troll, nou, killer, gangbang, chugnub, cornhub, androidgod, iosgod) VALUES (:player, :lit, :enhanced, :mvp, :ultimate, :crusader, :legend, :overlord, :experienced, :ez, :pyro, :elite, :windows10, :fresh, :emperor, :salty, :pancakes, :uwu, :zoomer, :dirt, :god, :king, :gangster, :hitman, :mobster, :loner, :horion, :injected, :bustdown, :pro, :hacker, :gucci, :troll, :nou, :killer, :gangbang, :chugnub, :cornhub, :androidgod, :iosgod)");
                $stmt->bindValue(":player", $player->getName());
                $stmt->bindValue(":lit", $this->getInfo($player, "lit"));
                $stmt->bindValue(":enhanced", $this->getInfo($player, "enhanced"));
                $stmt->bindValue(":mvp", $this->getInfo($player, "mvp"));
                $stmt->bindValue(":ultimate", $this->getInfo($player, "ultimate"));
                $stmt->bindValue(":crusader", $this->getInfo($player, "crusader"));
                $stmt->bindValue(":legend", $this->getInfo($player, "legend"));
                $stmt->bindValue(":overlord", $this->getInfo($player, "overlord"));
                $stmt->bindValue(":experienced", $this->getInfo($player, "experienced"));
                $stmt->bindValue(":ez", $this->getInfo($player, "ez"));
                $stmt->bindValue(":pyro", $this->getInfo($player, "pyro"));
                $stmt->bindValue(":elite", $this->getInfo($player, "elite"));
                $stmt->bindValue(":windows10", $this->getInfo($player, "windows10"));
                $stmt->bindValue(":fresh", $this->getInfo($player, "fresh"));
                $stmt->bindValue(":emperor", $this->getInfo($player, "emperor"));
                $stmt->bindValue(":salty", $this->getInfo($player, "salty"));
                $stmt->bindValue(":pancakes", $this->getInfo($player, "pancakes"));
                $stmt->bindValue(":uwu", $this->getInfo($player, "uwu"));
                $stmt->bindValue(":zoomer", $this->getInfo($player, "zoomer"));
                $stmt->bindValue(":dirt", $this->getInfo($player, "dirt"));
                $stmt->bindValue(":god", $this->getInfo($player, "god"));
                $stmt->bindValue(":king", $this->getInfo($player, "king"));
                $stmt->bindValue(":gangster", $this->getInfo($player, "gangster"));
                $stmt->bindValue(":hitman", $this->getInfo($player, "hitman"));
                $stmt->bindValue(":mobster", $this->getInfo($player, "mobster"));
                $stmt->bindValue(":loner", $this->getInfo($player, "loner"));
                $stmt->bindValue(":horion", $this->getInfo($player, "horion"));
                $stmt->bindValue(":injected", $this->getInfo($player, "injected"));
                $stmt->bindValue(":bustdown", $this->getInfo($player, "bustdown"));
                $stmt->bindValue(":pro", $this->getInfo($player, "pro"));
                $stmt->bindValue(":hacker", $this->getInfo($player, "hacker"));
                $stmt->bindValue(":gucci", $this->getInfo($player, "gucci"));
                $stmt->bindValue(":troll", $this->getInfo($player, "troll"));
                $stmt->bindValue(":nou", 1);
                $stmt->bindValue(":killer", $this->getInfo($player, "killer"));
                $stmt->bindValue(":gangbang", $this->getInfo($player, "gangbang"));
                $stmt->bindValue(":chugnub", $this->getInfo($player, "chugnub"));
                $stmt->bindValue(":cornhub", $this->getInfo($player, "cornhub"));
                $stmt->bindValue(":androidgod", $this->getInfo($player, "androidgod"));
                $stmt->bindValue(":iosgod", $this->getInfo($player, "iosgod"));
                $stmt->execute();
                break;
            case "killer":
                $stmt = $this->tags->prepare("INSERT OR REPLACE INTO tags (player, lit, enhanced, mvp, ultimate, crusader, legend, overlord, experienced, ez, pyro, elite, windows10, fresh, emperor, salty, pancakes, uwu, zoomer, dirt, god, king, gangster, hitman, mobster, loner, horion, injected, bustdown, pro, hacker, gucci, troll, nou, killer, gangbang, chugnub, cornhub, androidgod, iosgod) VALUES (:player, :lit, :enhanced, :mvp, :ultimate, :crusader, :legend, :overlord, :experienced, :ez, :pyro, :elite, :windows10, :fresh, :emperor, :salty, :pancakes, :uwu, :zoomer, :dirt, :god, :king, :gangster, :hitman, :mobster, :loner, :horion, :injected, :bustdown, :pro, :hacker, :gucci, :troll, :nou, :killer, :gangbang, :chugnub, :cornhub, :androidgod, :iosgod)");
                $stmt->bindValue(":player", $player->getName());
                $stmt->bindValue(":lit", $this->getInfo($player, "lit"));
                $stmt->bindValue(":enhanced", $this->getInfo($player, "enhanced"));
                $stmt->bindValue(":mvp", $this->getInfo($player, "mvp"));
                $stmt->bindValue(":ultimate", $this->getInfo($player, "ultimate"));
                $stmt->bindValue(":crusader", $this->getInfo($player, "crusader"));
                $stmt->bindValue(":legend", $this->getInfo($player, "legend"));
                $stmt->bindValue(":overlord", $this->getInfo($player, "overlord"));
                $stmt->bindValue(":experienced", $this->getInfo($player, "experienced"));
                $stmt->bindValue(":ez", $this->getInfo($player, "ez"));
                $stmt->bindValue(":pyro", $this->getInfo($player, "pyro"));
                $stmt->bindValue(":elite", $this->getInfo($player, "elite"));
                $stmt->bindValue(":windows10", $this->getInfo($player, "windows10"));
                $stmt->bindValue(":fresh", $this->getInfo($player, "fresh"));
                $stmt->bindValue(":emperor", $this->getInfo($player, "emperor"));
                $stmt->bindValue(":salty", $this->getInfo($player, "salty"));
                $stmt->bindValue(":pancakes", $this->getInfo($player, "pancakes"));
                $stmt->bindValue(":uwu", $this->getInfo($player, "uwu"));
                $stmt->bindValue(":zoomer", $this->getInfo($player, "zoomer"));
                $stmt->bindValue(":dirt", $this->getInfo($player, "dirt"));
                $stmt->bindValue(":god", $this->getInfo($player, "god"));
                $stmt->bindValue(":king", $this->getInfo($player, "king"));
                $stmt->bindValue(":gangster", $this->getInfo($player, "gangster"));
                $stmt->bindValue(":hitman", $this->getInfo($player, "hitman"));
                $stmt->bindValue(":mobster", $this->getInfo($player, "mobster"));
                $stmt->bindValue(":loner", $this->getInfo($player, "loner"));
                $stmt->bindValue(":horion", $this->getInfo($player, "horion"));
                $stmt->bindValue(":injected", $this->getInfo($player, "injected"));
                $stmt->bindValue(":bustdown", $this->getInfo($player, "bustdown"));
                $stmt->bindValue(":pro", $this->getInfo($player, "pro"));
                $stmt->bindValue(":hacker", $this->getInfo($player, "hacker"));
                $stmt->bindValue(":gucci", $this->getInfo($player, "gucci"));
                $stmt->bindValue(":troll", $this->getInfo($player, "troll"));
                $stmt->bindValue(":nou", $this->getInfo($player, "nou"));
                $stmt->bindValue(":killer", 1);
                $stmt->bindValue(":gangbang", $this->getInfo($player, "gangbang"));
                $stmt->bindValue(":chugnub", $this->getInfo($player, "chugnub"));
                $stmt->bindValue(":cornhub", $this->getInfo($player, "cornhub"));
                $stmt->bindValue(":androidgod", $this->getInfo($player, "androidgod"));
                $stmt->bindValue(":iosgod", $this->getInfo($player, "iosgod"));
                $stmt->execute();
                break;
            case "gangbang":
                $stmt = $this->tags->prepare("INSERT OR REPLACE INTO tags (player, lit, enhanced, mvp, ultimate, crusader, legend, overlord, experienced, ez, pyro, elite, windows10, fresh, emperor, salty, pancakes, uwu, zoomer, dirt, god, king, gangster, hitman, mobster, loner, horion, injected, bustdown, pro, hacker, gucci, troll, nou, killer, gangbang, chugnub, cornhub, androidgod, iosgod) VALUES (:player, :lit, :enhanced, :mvp, :ultimate, :crusader, :legend, :overlord, :experienced, :ez, :pyro, :elite, :windows10, :fresh, :emperor, :salty, :pancakes, :uwu, :zoomer, :dirt, :god, :king, :gangster, :hitman, :mobster, :loner, :horion, :injected, :bustdown, :pro, :hacker, :gucci, :troll, :nou, :killer, :gangbang, :chugnub, :cornhub, :androidgod, :iosgod)");
                $stmt->bindValue(":player", $player->getName());
                $stmt->bindValue(":lit", $this->getInfo($player, "lit"));
                $stmt->bindValue(":enhanced", $this->getInfo($player, "enhanced"));
                $stmt->bindValue(":mvp", $this->getInfo($player, "mvp"));
                $stmt->bindValue(":ultimate", $this->getInfo($player, "ultimate"));
                $stmt->bindValue(":crusader", $this->getInfo($player, "crusader"));
                $stmt->bindValue(":legend", $this->getInfo($player, "legend"));
                $stmt->bindValue(":overlord", $this->getInfo($player, "overlord"));
                $stmt->bindValue(":experienced", $this->getInfo($player, "experienced"));
                $stmt->bindValue(":ez", $this->getInfo($player, "ez"));
                $stmt->bindValue(":pyro", $this->getInfo($player, "pyro"));
                $stmt->bindValue(":elite", $this->getInfo($player, "elite"));
                $stmt->bindValue(":windows10", $this->getInfo($player, "windows10"));
                $stmt->bindValue(":fresh", $this->getInfo($player, "fresh"));
                $stmt->bindValue(":emperor", $this->getInfo($player, "emperor"));
                $stmt->bindValue(":salty", $this->getInfo($player, "salty"));
                $stmt->bindValue(":pancakes", $this->getInfo($player, "pancakes"));
                $stmt->bindValue(":uwu", $this->getInfo($player, "uwu"));
                $stmt->bindValue(":zoomer", $this->getInfo($player, "zoomer"));
                $stmt->bindValue(":dirt", $this->getInfo($player, "dirt"));
                $stmt->bindValue(":god", $this->getInfo($player, "god"));
                $stmt->bindValue(":king", $this->getInfo($player, "king"));
                $stmt->bindValue(":gangster", $this->getInfo($player, "gangster"));
                $stmt->bindValue(":hitman", $this->getInfo($player, "hitman"));
                $stmt->bindValue(":mobster", $this->getInfo($player, "mobster"));
                $stmt->bindValue(":loner", $this->getInfo($player, "loner"));
                $stmt->bindValue(":horion", $this->getInfo($player, "horion"));
                $stmt->bindValue(":injected", $this->getInfo($player, "injected"));
                $stmt->bindValue(":bustdown", $this->getInfo($player, "bustdown"));
                $stmt->bindValue(":pro", $this->getInfo($player, "pro"));
                $stmt->bindValue(":hacker", $this->getInfo($player, "hacker"));
                $stmt->bindValue(":gucci", $this->getInfo($player, "gucci"));
                $stmt->bindValue(":troll", $this->getInfo($player, "troll"));
                $stmt->bindValue(":nou", $this->getInfo($player, "nou"));
                $stmt->bindValue(":killer", $this->getInfo($player, "killer"));
                $stmt->bindValue(":gangbang", 1);
                $stmt->bindValue(":chugnub", $this->getInfo($player, "chugnub"));
                $stmt->bindValue(":cornhub", $this->getInfo($player, "cornhub"));
                $stmt->bindValue(":androidgod", $this->getInfo($player, "androidgod"));
                $stmt->bindValue(":iosgod", $this->getInfo($player, "iosgod"));
                $stmt->execute();
                break;
            case "chugnub":
                $stmt = $this->tags->prepare("INSERT OR REPLACE INTO tags (player, lit, enhanced, mvp, ultimate, crusader, legend, overlord, experienced, ez, pyro, elite, windows10, fresh, emperor, salty, pancakes, uwu, zoomer, dirt, god, king, gangster, hitman, mobster, loner, horion, injected, bustdown, pro, hacker, gucci, troll, nou, killer, gangbang, chugnub, cornhub, androidgod, iosgod) VALUES (:player, :lit, :enhanced, :mvp, :ultimate, :crusader, :legend, :overlord, :experienced, :ez, :pyro, :elite, :windows10, :fresh, :emperor, :salty, :pancakes, :uwu, :zoomer, :dirt, :god, :king, :gangster, :hitman, :mobster, :loner, :horion, :injected, :bustdown, :pro, :hacker, :gucci, :troll, :nou, :killer, :gangbang, :chugnub, :cornhub, :androidgod, :iosgod)");
                $stmt->bindValue(":player", $player->getName());
                $stmt->bindValue(":lit", $this->getInfo($player, "lit"));
                $stmt->bindValue(":enhanced", $this->getInfo($player, "enhanced"));
                $stmt->bindValue(":mvp", $this->getInfo($player, "mvp"));
                $stmt->bindValue(":ultimate", $this->getInfo($player, "ultimate"));
                $stmt->bindValue(":crusader", $this->getInfo($player, "crusader"));
                $stmt->bindValue(":legend", $this->getInfo($player, "legend"));
                $stmt->bindValue(":overlord", $this->getInfo($player, "overlord"));
                $stmt->bindValue(":experienced", $this->getInfo($player, "experienced"));
                $stmt->bindValue(":ez", $this->getInfo($player, "ez"));
                $stmt->bindValue(":pyro", $this->getInfo($player, "pyro"));
                $stmt->bindValue(":elite", $this->getInfo($player, "elite"));
                $stmt->bindValue(":windows10", $this->getInfo($player, "windows10"));
                $stmt->bindValue(":fresh", $this->getInfo($player, "fresh"));
                $stmt->bindValue(":emperor", $this->getInfo($player, "emperor"));
                $stmt->bindValue(":salty", $this->getInfo($player, "salty"));
                $stmt->bindValue(":pancakes", $this->getInfo($player, "pancakes"));
                $stmt->bindValue(":uwu", $this->getInfo($player, "uwu"));
                $stmt->bindValue(":zoomer", $this->getInfo($player, "zoomer"));
                $stmt->bindValue(":dirt", $this->getInfo($player, "dirt"));
                $stmt->bindValue(":god", $this->getInfo($player, "god"));
                $stmt->bindValue(":king", $this->getInfo($player, "king"));
                $stmt->bindValue(":gangster", $this->getInfo($player, "gangster"));
                $stmt->bindValue(":hitman", $this->getInfo($player, "hitman"));
                $stmt->bindValue(":mobster", $this->getInfo($player, "mobster"));
                $stmt->bindValue(":loner", $this->getInfo($player, "loner"));
                $stmt->bindValue(":horion", $this->getInfo($player, "horion"));
                $stmt->bindValue(":injected", $this->getInfo($player, "injected"));
                $stmt->bindValue(":bustdown", $this->getInfo($player, "bustdown"));
                $stmt->bindValue(":pro", $this->getInfo($player, "pro"));
                $stmt->bindValue(":hacker", $this->getInfo($player, "hacker"));
                $stmt->bindValue(":gucci", $this->getInfo($player, "gucci"));
                $stmt->bindValue(":troll", $this->getInfo($player, "troll"));
                $stmt->bindValue(":nou", $this->getInfo($player, "nou"));
                $stmt->bindValue(":killer", $this->getInfo($player, "killer"));
                $stmt->bindValue(":gangbang", $this->getInfo($player, "gangbang"));
                $stmt->bindValue(":chugnub", 1);
                $stmt->bindValue(":cornhub", $this->getInfo($player, "cornhub"));
                $stmt->bindValue(":androidgod", $this->getInfo($player, "androidgod"));
                $stmt->bindValue(":iosgod", $this->getInfo($player, "iosgod"));
                $stmt->execute();
                break;
            case "cornhub":
                $stmt = $this->tags->prepare("INSERT OR REPLACE INTO tags (player, lit, enhanced, mvp, ultimate, crusader, legend, overlord, experienced, ez, pyro, elite, windows10, fresh, emperor, salty, pancakes, uwu, zoomer, dirt, god, king, gangster, hitman, mobster, loner, horion, injected, bustdown, pro, hacker, gucci, troll, nou, killer, gangbang, chugnub, cornhub, androidgod, iosgod) VALUES (:player, :lit, :enhanced, :mvp, :ultimate, :crusader, :legend, :overlord, :experienced, :ez, :pyro, :elite, :windows10, :fresh, :emperor, :salty, :pancakes, :uwu, :zoomer, :dirt, :god, :king, :gangster, :hitman, :mobster, :loner, :horion, :injected, :bustdown, :pro, :hacker, :gucci, :troll, :nou, :killer, :gangbang, :chugnub, :cornhub, :androidgod, :iosgod)");
                $stmt->bindValue(":player", $player->getName());
                $stmt->bindValue(":lit", $this->getInfo($player, "lit"));
                $stmt->bindValue(":enhanced", $this->getInfo($player, "enhanced"));
                $stmt->bindValue(":mvp", $this->getInfo($player, "mvp"));
                $stmt->bindValue(":ultimate", $this->getInfo($player, "ultimate"));
                $stmt->bindValue(":crusader", $this->getInfo($player, "crusader"));
                $stmt->bindValue(":legend", $this->getInfo($player, "legend"));
                $stmt->bindValue(":overlord", $this->getInfo($player, "overlord"));
                $stmt->bindValue(":experienced", $this->getInfo($player, "experienced"));
                $stmt->bindValue(":ez", $this->getInfo($player, "ez"));
                $stmt->bindValue(":pyro", $this->getInfo($player, "pyro"));
                $stmt->bindValue(":elite", $this->getInfo($player, "elite"));
                $stmt->bindValue(":windows10", $this->getInfo($player, "windows10"));
                $stmt->bindValue(":fresh", $this->getInfo($player, "fresh"));
                $stmt->bindValue(":emperor", $this->getInfo($player, "emperor"));
                $stmt->bindValue(":salty", $this->getInfo($player, "salty"));
                $stmt->bindValue(":pancakes", $this->getInfo($player, "pancakes"));
                $stmt->bindValue(":uwu", $this->getInfo($player, "uwu"));
                $stmt->bindValue(":zoomer", $this->getInfo($player, "zoomer"));
                $stmt->bindValue(":dirt", $this->getInfo($player, "dirt"));
                $stmt->bindValue(":god", $this->getInfo($player, "god"));
                $stmt->bindValue(":king", $this->getInfo($player, "king"));
                $stmt->bindValue(":gangster", $this->getInfo($player, "gangster"));
                $stmt->bindValue(":hitman", $this->getInfo($player, "hitman"));
                $stmt->bindValue(":mobster", $this->getInfo($player, "mobster"));
                $stmt->bindValue(":loner", $this->getInfo($player, "loner"));
                $stmt->bindValue(":horion", $this->getInfo($player, "horion"));
                $stmt->bindValue(":injected", $this->getInfo($player, "injected"));
                $stmt->bindValue(":bustdown", $this->getInfo($player, "bustdown"));
                $stmt->bindValue(":pro", $this->getInfo($player, "pro"));
                $stmt->bindValue(":hacker", $this->getInfo($player, "hacker"));
                $stmt->bindValue(":gucci", $this->getInfo($player, "gucci"));
                $stmt->bindValue(":troll", $this->getInfo($player, "troll"));
                $stmt->bindValue(":nou", $this->getInfo($player, "nou"));
                $stmt->bindValue(":killer", $this->getInfo($player, "killer"));
                $stmt->bindValue(":gangbang", $this->getInfo($player, "gangbang"));
                $stmt->bindValue(":chugnub", $this->getInfo($player, "chugnub"));
                $stmt->bindValue(":cornhub", 1);
                $stmt->bindValue(":androidgod", $this->getInfo($player, "androidgod"));
                $stmt->bindValue(":iosgod", $this->getInfo($player, "iosgod"));
                $stmt->execute();
                break;
            case "cornhub":
                $stmt = $this->tag->prepare("INSERT OR REPLACE INTO tags (player, cornhub) VALUES (:player, :cornhub)");
                $stmt->bindValue(":player", $player->getName());
                $stmt->bindValue(":cornhub", 1);
                $stmt->execute();
                $ranks = new Ranks($this);
                $ranks->setTag($player, "CornHub");
                break;
            case "cornhub":
                $stmt = $this->tags->prepare("INSERT OR REPLACE INTO tags (player, lit, enhanced, mvp, ultimate, crusader, legend, overlord, experienced, ez, pyro, elite, windows10, fresh, emperor, salty, pancakes, uwu, zoomer, dirt, god, king, gangster, hitman, mobster, loner, horion, injected, bustdown, pro, hacker, gucci, troll, nou, killer, gangbang, chugnub, cornhub, androidgod, iosgod) VALUES (:player, :lit, :enhanced, :mvp, :ultimate, :crusader, :legend, :overlord, :experienced, :ez, :pyro, :elite, :windows10, :fresh, :emperor, :salty, :pancakes, :uwu, :zoomer, :dirt, :god, :king, :gangster, :hitman, :mobster, :loner, :horion, :injected, :bustdown, :pro, :hacker, :gucci, :troll, :nou, :killer, :gangbang, :chugnub, :cornhub, :androidgod, :iosgod)");
                $stmt->bindValue(":player", $player->getName());
                $stmt->bindValue(":lit", $this->getInfo($player, "lit"));
                $stmt->bindValue(":enhanced", $this->getInfo($player, "enhanced"));
                $stmt->bindValue(":mvp", $this->getInfo($player, "mvp"));
                $stmt->bindValue(":ultimate", $this->getInfo($player, "ultimate"));
                $stmt->bindValue(":crusader", $this->getInfo($player, "crusader"));
                $stmt->bindValue(":legend", $this->getInfo($player, "legend"));
                $stmt->bindValue(":overlord", $this->getInfo($player, "overlord"));
                $stmt->bindValue(":experienced", $this->getInfo($player, "experienced"));
                $stmt->bindValue(":ez", $this->getInfo($player, "ez"));
                $stmt->bindValue(":pyro", $this->getInfo($player, "pyro"));
                $stmt->bindValue(":elite", $this->getInfo($player, "elite"));
                $stmt->bindValue(":windows10", $this->getInfo($player, "windows10"));
                $stmt->bindValue(":fresh", $this->getInfo($player, "fresh"));
                $stmt->bindValue(":emperor", $this->getInfo($player, "emperor"));
                $stmt->bindValue(":salty", $this->getInfo($player, "salty"));
                $stmt->bindValue(":pancakes", $this->getInfo($player, "pancakes"));
                $stmt->bindValue(":uwu", $this->getInfo($player, "uwu"));
                $stmt->bindValue(":zoomer", $this->getInfo($player, "zoomer"));
                $stmt->bindValue(":dirt", $this->getInfo($player, "dirt"));
                $stmt->bindValue(":god", $this->getInfo($player, "god"));
                $stmt->bindValue(":king", $this->getInfo($player, "king"));
                $stmt->bindValue(":gangster", $this->getInfo($player, "gangster"));
                $stmt->bindValue(":hitman", $this->getInfo($player, "hitman"));
                $stmt->bindValue(":mobster", $this->getInfo($player, "mobster"));
                $stmt->bindValue(":loner", $this->getInfo($player, "loner"));
                $stmt->bindValue(":horion", $this->getInfo($player, "horion"));
                $stmt->bindValue(":injected", $this->getInfo($player, "injected"));
                $stmt->bindValue(":bustdown", $this->getInfo($player, "bustdown"));
                $stmt->bindValue(":pro", $this->getInfo($player, "pro"));
                $stmt->bindValue(":hacker", $this->getInfo($player, "hacker"));
                $stmt->bindValue(":gucci", $this->getInfo($player, "gucci"));
                $stmt->bindValue(":troll", $this->getInfo($player, "troll"));
                $stmt->bindValue(":nou", $this->getInfo($player, "nou"));
                $stmt->bindValue(":killer", $this->getInfo($player, "killer"));
                $stmt->bindValue(":gangbang", $this->getInfo($player, "gangbang"));
                $stmt->bindValue(":chugnub", $this->getInfo($player, "chugnub"));
                $stmt->bindValue(":cornhub", $this->getInfo($player, "cornhub"));
                $stmt->bindValue(":androidgod", 1);
                $stmt->bindValue(":iosgod", $this->getInfo($player, "iosgod"));
                $stmt->execute();
                break;
            case "androidgod":
                $stmt = $this->tag->prepare("INSERT OR REPLACE INTO tags (player, androidgod) VALUES (:player, :androidgod)");
                $stmt->bindValue(":player", $player->getName());
                $stmt->bindValue(":androidgod", 1);
                $stmt->execute();
                $ranks = new Ranks($this);
                $ranks->setTag($player, "AndroidGod");
                break;
            case "iosgod":
                $stmt = $this->tag->prepare("INSERT OR REPLACE INTO tags (player, iosgod) VALUES (:player, :iosgod)");
                $stmt->bindValue(":player", $player->getName());
                $stmt->bindValue(":iosgod", 1);
                $stmt->execute();
                $ranks = new Ranks($this);
                $ranks->setTag($player, "IOSGOD");
                break;
        }
    }

    /**
     * @param Player $player
     */
    public function resetStreak(Player $player): void
    {
        $stmt = $this->db->prepare("INSERT OR REPLACE INTO master (player, kills, deaths, lvl, xp, streak, rank, tag) VALUES (:player, :kills, :deaths, :lvl, :xp, :streak, :rank, :tag)");
        $stmt->bindValue(":player", $player->getName());
        $stmt->bindValue(":kills", $this->getKills($player));
        $stmt->bindValue(":deaths", $this->getDeaths($player));
        $stmt->bindValue(":lvl", $this->getLevel($player));
        $stmt->bindValue(":xp", $this->getXP($player));
        $stmt->bindValue(":streak", 0);
        $stmt->bindValue(":rank", $this->getRank($player));
        $stmt->bindValue(":tag", $this->getTag($player));
        $stmt->execute();
        $config = new Config($this->getDataFolder() . "Streaks.yml");
        $config->set($player->getName(), 0);
        $config->save();
    }

    /**
     * @param Player $player
     * @param $points
     */
    public function addXP(Player $player, $points): void
    {
        $stmt = $this->db->prepare("INSERT OR REPLACE INTO master (player, kills, deaths, lvl, xp, streak, rank, tag) VALUES (:player, :kills, :deaths, :lvl, :xp, :streak, :rank, :tag)");
        $stmt->bindValue(":player", $player->getName());
        $stmt->bindValue(":kills", $this->getKills($player));
        $stmt->bindValue(":deaths", $this->getDeaths($player));
        $stmt->bindValue(":lvl", $this->getLevel($player));
        $stmt->bindValue(":xp", $this->getXP($player) + $points);
        $stmt->bindValue(":streak", $this->getStreak($player));
        $stmt->bindValue(":rank", $this->getRank($player));
        $stmt->bindValue(":tag", $this->getTag($player));
        $stmt->execute();
    }

    /**
     * @param Player $player
     */
    public function resetXP(Player $player): void
    {
        $stmt = $this->db->prepare("INSERT OR REPLACE INTO master (player, kills, deaths, lvl, xp, streak, rank, tag) VALUES (:player, :kills, :deaths, :lvl, :xp, :streak, :rank, :tag)");
        $stmt->bindValue(":player", $player->getName());
        $stmt->bindValue(":kills", $this->getKills($player));
        $stmt->bindValue(":deaths", $this->getDeaths($player));
        $stmt->bindValue(":lvl", $this->getLevel($player));
        $stmt->bindValue(":xp", 0);
        $stmt->bindValue(":streak", $this->getStreak($player));
        $stmt->bindValue(":rank", $this->getRank($player));
        $stmt->bindValue(":tag", $this->getTag($player));
        $stmt->execute();
    }

    /**
     * @param Player $player
     * @return int
     */
    public function getLevel(Player $player): int
    {
        $players = $player->getName();
        $levels = $this->db->query("SELECT lvl FROM master WHERE player = '$players';");
        $array = $levels->fetchArray(SQLITE3_ASSOC);
        return (int)$array["lvl"];
    }

    /**
     * @param Player $player
     * @return int
     */
    public function getXP(Player $player): int
    {
        $players = $player->getName();
        $xp = $this->db->query("SELECT xp FROM master WHERE player = '$players';");
        $array = $xp->fetchArray(SQLITE3_ASSOC);
        return (int)$array["xp"];
    }

    /**
     * @param Player $player
     * @return mixed
     */
    public function getRank(Player $player)
    {
        $players = $player->getName();
        $rank = $this->db->query("SELECT rank FROM master WHERE player = '$players';");
        $array = $rank->fetchArray(SQLITE3_ASSOC);
        return $array["rank"];
    }

    /**
     * @param Player $player
     * @return mixed
     */
    public function getTag(Player $player)
    {
        $players = $player->getName();
        $tag = $this->db->query("SELECT tag FROM master WHERE player = '$players';");
        $array = $tag->fetchArray(SQLITE3_ASSOC);
        return $array["tag"];
    }

    /**
     * @param Player $player
     * @param int $points
     */
    public function addLevel(Player $player, int $points = 1): void
    {
        $stmt = $this->db->prepare("INSERT OR REPLACE INTO master (player, kills, deaths, lvl, xp, streak, rank, tag) VALUES (:player, :kills, :deaths, :lvl, :xp, :streak, :rank, :tag)");
        $stmt->bindValue(":player", $player->getName());
        $stmt->bindValue(":kills", $this->getKills($player));
        $stmt->bindValue(":deaths", $this->getDeaths($player));
        $stmt->bindValue(":lvl", $this->getLevel($player) + $points);
        $stmt->bindValue(":xp", $this->getXP($player));
        $stmt->bindValue(":streak", $this->getStreak($player));
        $stmt->bindValue(":rank", $this->getRank($player));
        $stmt->bindValue(":tag", $this->getTag($player));
        $stmt->execute();
        $cfg = new Config($this->getDataFolder() . "Levels.yml");
        $cfg->set($player->getName(), $this->getLevel($player) + $points);
        $cfg->save();
    }

    /**
     * @param $player
     * @return bool
     */
    public function coinExists($player): bool
    {
        $result = $this->coins->query("SELECT player FROM coins WHERE player='$player';");
        $array = $result->fetchArray(SQLITE3_ASSOC);
        return empty($array) == false;
    }

    /**
     * @param Player $player
     * @return bool
     */
    public function coinTag(Player $player): bool
    {
        $playerName = $player->getLowerCaseName();
        $result = $this->tag->query("SELECT player FROM tags WHERE player='$playerName';");
        $array = $result->fetchArray(SQLITE3_ASSOC);
        return empty($array) == false;
    }

    /**
     * @param Player $player
     */
    public function LevelUP(Player $player): void
    {
        $xp = $this->getXP($player);
        if ($xp >= 5000) {
            $this->addLevel($player);
            $this->resetXP($player);
            $player->addTitle(C::BOLD . C::GREEN . "LEVEL UP!", C::AQUA . $this->getLevel($player) . "");
            $player->sendMessage(C::BOLD . C::GREEN . "LEVEL UP! LEVEL: " . C::AQUA . $this->getLevel($player) . "");
        }
    }

    /**
     * @param QueryRegenerateEvent $event
     */
    public function onQuery(QueryRegenerateEvent $event): void
    {
        $event->setMaxPlayerCount($event->getPlayerCount() + 1);
        $event->setPlayerCount($event->getPlayerCount() + 0);
    }

    public function onDisable(): void
    {
        $this->getServer()->getLogger()->warning(C::RED . $this->prefix . " Disabled");
    }
}
