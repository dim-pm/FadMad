<?php

namespace Fadhel\Core\commands;

use Fadhel\Core\listeners\Ranks;
use Fadhel\Core\Main;
use Fadhel\Core\utils\form\CustomForm;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\Player;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat as C;

class setRank extends Command
{
    private $plugin;

    public function __construct(Main $plugin)
    {
        $this->plugin = $plugin;
        parent::__construct("rank", "Ranks command!");
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

    public function execute(CommandSender $sender, string $commandLabel, array $args): bool
    {
        if (!$this->testPermission($sender)) {
            return true;
        }
        if (count($args) === 0) {
            $this->gui($sender);
        } elseif (count($args) === 1) {
            $sender->sendMessage(C::RED . "Usage /rank <player> <rank>");
        } elseif (count($args) === 2) {
            $player = $this->plugin->getServer()->getPlayer($args[0]);
            if ($player !== null) {
                $ranks = new Ranks($this->plugin);
                $ranks->setRank($player, $args[1]);
                return true;
            } else {
                $ranks = array("Default", "YouTuber", "VIP", "MVP", "Manager", "Trainee", "Moderator", "SrMod", "Admin", "Investor", "Owner", "SrAdmin", "Developer", "Builder");
                if (in_array($args[1], $ranks)) {
                    $off = $this->plugin->getServer()->getOfflinePlayer($args[0]);
                    if ($off !== null) {
                        $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, rank) VALUES (:player, :rank)");
                        $stmt->bindValue(":player", $args[0]);
                        $stmt->bindValue(":rank", $args[1]);
                        $stmt->execute();
                        $this->sendStaffAlert(C::YELLOW . $args[0] . C::GOLD . " has been granted " . C::GRAY . $args[1]);
                    } else {
                        $sender->sendMessage(C::RED . "Player not found!");
                    }
                }
            }
        }
        return false;
    }

    public function gui($player)
    {
        $form = new CustomForm(function (Player $event, $data) {
            $player = $event->getPlayer();
            if ($data === null) {
                return;
            }
            if ($data[1]) {
                $ranks = new Ranks($this->plugin);
                $ranks->setRank($player, "Owner");
            }
            if ($data[2]) {
                $ranks = new Ranks($this->plugin);
                $ranks->setRank($player, "Investor");
            }
            if ($data[3]) {
                $ranks = new Ranks($this->plugin);
                $ranks->setRank($player, "Admin");
            }
            if ($data[4]) {
                $ranks = new Ranks($this->plugin);
                $ranks->setRank($player, "SrMod");
            }
            if ($data[5]) {
                $ranks = new Ranks($this->plugin);
                $ranks->setRank($player, "Moderator");
            }
            if ($data[6]) {
                $ranks = new Ranks($this->plugin);
                $ranks->setRank($player, "Trainee");
            }
            if ($data[7]) {
                $ranks = new Ranks($this->plugin);
                $ranks->setRank($player, "Manager");
            }
            if ($data[8]) {
                $ranks = new Ranks($this->plugin);
                $ranks->setRank($player, "SrAdmin");
            }
            if ($data[9]) {
                $ranks = new Ranks($this->plugin);
                $ranks->setRank($player, "YouTuber");
            }
            if ($data[10]) {
                $ranks = new Ranks($this->plugin);
                $ranks->setRank($player, "Developer");
            }
            if ($data[11]) {
                $ranks = new Ranks($this->plugin);
                $ranks->setRank($player, "VIP");
            }
            if ($data[12]) {
                $ranks = new Ranks($this->plugin);
                $ranks->setRank($player, "SrMod");
            }
            if ($data[13]) {
                $ranks = new Ranks($this->plugin);
                $ranks->setRank($player, "Builder");
            }
            if ($data[14]) {
                $ranks = new Ranks($this->plugin);
                $ranks->setRank($player, "Default");
            }
        });
        $form->setTitle("Ranks");
        $form->addLabel("Ranks!");
        $form->addToggle("§8[§4Owner§8]"); 
        $form->addToggle("§8[§bInvestor§8]"); 
        $form->addToggle("§8[§cAdmin§8]"); 
        $form->addToggle("§8[SrMod§8]"); 
        $form->addToggle("§8[§5Moderator§8]"); 
        $form->addToggle("§8[§eTrainee§8]"); 
        $form->addToggle("§8[§cManager§8]"); 
        $form->addToggle("§8[§4Sr§cAdmin§8]"); 
        $form->addToggle("§8[§4YOUTUBE§8]"); 
        $form->addToggle("§8[§1Developer§8]"); 
        $form->addToggle("§8[§aVIP§8]§a");
        $form->addToggle("§8[§dSr§5Mod§8]");
        $form->addToggle("§8[§6Builder§8]");
        $form->addToggle("§7[Default§7]");
        $form->sendToPlayer($player);
        return $form;
    }
}