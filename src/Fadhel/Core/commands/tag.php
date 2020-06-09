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

class tag extends Command
{
    private $plugin;

    public function __construct(Main $plugin)
    {
        $this->plugin = $plugin;
        parent::__construct("tag", "Tags command!", null, ["tags"]);
        $this->setPermission("tags.command");
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
            $sender->sendMessage(C::RED . "Usage /tags <player> <tag>");
        } elseif (count($args) === 2) {
            $player = $this->plugin->getServer()->getPlayer($args[0]);
            if ($player !== null) {
                $ranks = new Ranks($this->plugin);
                $ranks->setTag($player, $args[1]);
                return true;
            } else {
                $sender->sendMessage(C::RED . "Player not found!");
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
                $ranks->setTag($player, "EBoy");
            }
            if ($data[2]) {
                $ranks = new Ranks($this->plugin);
                $ranks->setTag($player, "EGirl");
            }
            if ($data[3]) {
                $ranks = new Ranks($this->plugin);
                $ranks->setTag($player, "Idot");
            }
            if ($data[4]) {
                $ranks = new Ranks($this->plugin);
                $ranks->setTag($player, "OOF");
            }
            if ($data[5]) {
                $ranks = new Ranks($this->plugin);
                $ranks->setTag($player, "Savage");
            }
            if ($data[6]) {
                $ranks = new Ranks($this->plugin);
                $ranks->setTag($player, "PvPGod");
            }
            if ($data[7]) {
                $ranks = new Ranks($this->plugin);
                $ranks->setTag($player, "TryHard");
            }
            if ($data[8]) {
                $ranks = new Ranks($this->plugin);
                $ranks->setTag($player, "Triggered");
            }
            if ($data[9]) {
                $ranks = new Ranks($this->plugin);
                $ranks->setTag($player, "Nerd");
            }
            if ($data[10]) {
                $ranks = new Ranks($this->plugin);
                $ranks->setTag($player, "XD");
            }
            if ($data[11]) {
                $ranks = new Ranks($this->plugin);
                $ranks->setTag($player, "God");
            }
            if ($data[12]) {
                $ranks = new Ranks($this->plugin);
                $ranks->setTag($player, "Turtle");
            }
            if ($data[13]) {
                $ranks = new Ranks($this->plugin);
                $ranks->setTag($player, "Amazing");
            }
            if ($data[14]) {
                $ranks = new Ranks($this->plugin);
                $ranks->setTag($player, "Toast");
            }
            if ($data[15]) {
                $ranks = new Ranks($this->plugin);
                $ranks->setTag($player, "Cookie");
            }
            if ($data[16]) {
                $ranks = new Ranks($this->plugin);
                $ranks->setTag($player, "Pancakes");
            }
            if ($data[17]) {
                $ranks = new Ranks($this->plugin);
                $ranks->setTag($player, "Boomer");
            }
            if ($data[18]) {
                $ranks = new Ranks($this->plugin);
                $ranks->setTag($player, "Raider");
            }
            if ($data[19]) {
                $ranks = new Ranks($this->plugin);
                $ranks->setTag($player, "Zeus");
            }
            if ($data[20]) {
                $ranks = new Ranks($this->plugin);
                $ranks->setTag($player, "Toxic");
            }
            if ($data[21]) {
                $ranks = new Ranks($this->plugin);
                $ranks->setTag($player, "Creeper");
            }
            if ($data[22]) {
                $ranks = new Ranks($this->plugin);
                $ranks->setTag($player, "EnderMan");
            }
            if ($data[23]) {
                $ranks = new Ranks($this->plugin);
                $ranks->setTag($player, "Dirt");
            }
            if ($data[24]) {
                $ranks = new Ranks($this->plugin);
                $ranks->setTag($player, "none");
            }
        });
        $form->setTitle("Tags");
        $form->addLabel("Tags!");
        $form->addToggle("§7[§bEBOY§7]");
        $form->addToggle("§7[§l§dE§r§l-§dGirl§7]");
        $form->addToggle("§7[§3IDOT§7]");
        $form->addToggle("§7[§4OOF§7]");
        $form->addToggle("§7[§5SAVAGE§7]§7");
        $form->addToggle("§7[§2PvPGod§7]");
        $form->addToggle("§7[§1TryHard§7]");
        $form->addToggle("§7[§6TRIGGERED§7]");
        $form->addToggle("§7[§eNERD§7]");
        $form->addToggle("§7[§aXD§7]");
        $form->addToggle("§7[§6I§eAm§a2§9Gud§54§cYou§7]");
        $form->addToggle("§7[§2TURTLE§7]");
        $form->addToggle("§7[§aAMAZING§7]");
        $form->addToggle("§7[§6Toast§7]");
        $form->addToggle("§7[§6ImCookie§7]");
        $form->addToggle("§7[§6Pan§dcake§7]");
        $form->addToggle("§7[§eBoomer§7]");
        $form->addToggle("§7[§bRaider§7]");
        $form->addToggle("§7[§9Zeus§7]");
        $form->addToggle("§7[§aT§7o§ax§7i§ac§7]");
        $form->addToggle("§5[§l§2C§8R§2E§8E§2P§8E§2R§5]");
        $form->addToggle("§5[§dEnder§0Man§5]");
        $form->addToggle("§7[§l§0Dirt§7]");
        $form->addToggle("Hide");
        $form->sendToPlayer($player);
        return $form;
    }
}