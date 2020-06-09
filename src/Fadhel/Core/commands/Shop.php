<?php

namespace Fadhel\Core\commands;

use Fadhel\Core\Main;
use Fadhel\Core\utils\form\SimpleForm;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\Player;
use pocketmine\utils\TextFormat;
use pocketmine\utils\TextFormat as C;

class Shop extends Command
{
    /**
     * @var Main
     */
    private $plugin;

    /**
     * Shop constructor.
     * @param Main $plugin
     */
    public function __construct(Main $plugin)
    {
        $this->plugin = $plugin;
        parent::__construct("shop", "Black Market - New Tags added Every Month", "", ["store", "market"]);
    }

    /**
     * @param CommandSender $sender
     * @param string $commandLabel
     * @param array $args
     * @return mixed|void
     */
    public function execute(CommandSender $sender, string $commandLabel, array $args)
    {
        if (!$sender instanceof Player) {
            $sender->sendMessage(C::RED . "Run this command in-game!");
            return false;
        }
        $this->Shop($sender);
    }

    /**
     * @param Player $player
     * @param string $tag
     * @param int $price
     */
    public function purchase(Player $player, string $tag, int $price): void
    {
        if (!$this->plugin->hasTag($player, $tag)) {
            if ($this->plugin->getCoins($player) >= $price) {
                $this->plugin->unlockTag($player, $tag);
                $player->sendMessage(TextFormat::YELLOW . "You've purchased " . $tag . "!");
                $this->plugin->reduceCoins($player, $price);
            } else {
                $player->sendMessage(TextFormat::RED . "You don't have enough coins");
            }
        } else {
            $player->sendMessage(TextFormat::RED . "You already have this tag");
        }
    }

    /**
     * @param Player $player
     */
    public function Shop(Player $player): void
    {
        $form = new SimpleForm(function (Player $event, $data) {
            $player = $event->getPlayer();
            if ($data === null) {
                return;
            }
            switch ($data) {
                case 0:
                    $this->purchase($player, "lit", 5000);
                    break;
                case 1:
                    $this->purchase($player, "enhanced", 5000);
                    break;
                case 2:
                    $this->purchase($player, "mvp", 5000);
                    break;
                case 3:
                    $this->purchase($player, "ultimate", 5000);
                    break;
                case 4:
                    $this->purchase($player, "crusader", 5000);
                    break;
                case 5:
                    $this->purchase($player, "legend", 5000);
                    break;
                case 6:
                    $this->purchase($player, "injected", 5000);
                    break;
                case 7:
                    $this->purchase($player, "bustdown", 5000);
                    break;
                case 8:
                    $this->purchase($player, "pro", 5000);
                    break;
                case 9:
                    $this->purchase($player, "gangbang", 5000);
                    break;
                case 10:
                    $this->purchase($player, "overlord", 15000);
                    break;
                case 11:
                    $this->purchase($player, "hacker", 15000);
                    break;
                case 12:
                    $this->purchase($player, "chugnub", 15000);
                    break;
                case 13:
                    $this->purchase($player, "experienced", 15000);
                    break;
                case 14:
                    $this->purchase($player, "king", 15000);
                    break;
                case 15:
                    $this->purchase($player, "ez", 15000);
                    break;
                case 16:
                    $this->purchase($player, "pyro", 25000);
                    break;
                case 17:
                    $this->purchase($player, "gucci", 25000);
                    break;
                case 18:
                    $this->purchase($player, "cornhub", 25000);
                    break;
                case 19:
                    $this->purchase($player, "elite", 25000);
                    break;
                case 20:
                    $this->purchase($player, "windows10", 25000);
                    break;
                case 21:
                    $this->purchase($player, "gangster", 25000);
                    break;
                case 22:
                    $this->purchase($player, "fresh", 35000);
                    break;
                case 23:
                    $this->purchase($player, "troll", 35000);
                    break;
                case 24:
                    $this->purchase($player, "androidgod", 35000);
                    break;
                case 25:
                    $this->purchase($player, "emperor", 35000);
                    break;
                case 26:
                    $this->purchase($player, "hitman", 35000);
                    break;
                case 27:
                    $this->purchase($player, "mobster", 35000);
                    break;
                case 28:
                    $this->purchase($player, "salty", 40000);
                    break;
                case 29:
                    $this->purchase($player, "nou", 40000);
                    break;
                case 30:
                    $this->purchase($player, "iosgod", 40000);
                    break;
                case 31:
                    $this->purchase($player, "pancakes", 40000);
                    break;
                case 32:
                    $this->purchase($player, "loner", 40000);
                    break;
                case 33:
                    $this->purchase($player, "horion", 40000);
                    break;
                case 34:
                    $this->purchase($player, "uwu", 45000);
                    break;
                case 35:
                    $this->purchase($player, "zoomer", 45000);
                    break;
                case 36:
                    $this->purchase($player, "dirt", 45000);
                    break;
                case 37:
                    $this->purchase($player, "god", 45000);
                    break;
                case 38:
                    $this->purchase($player, "killer", 45000);
                    break;
            }
        });
        $form->setTitle("§l§bBlack Market");
        $form->addButton("§7[§1L§4I§aT§7]\n§e5,000 coins");
        $form->addButton("§7[§1En§9han§bced§7]\n§e5,000 coins");
        $form->addButton("§7[§2M§aV§2P§7]\n§e5,000 coins");
        $form->addButton("§7[§4U§6l§2t§3i§1m§5a§ft§7e§7]\n§e5,000 coins");
        $form->addButton("§7[§6Crusader§7]\n§e5,000 coins");
        $form->addButton("§7[§2Legend§7]\n§e5,000 coins");
        $form->addButton("§7[§l§5Injected§7]\n§e5,000 coins");
        $form->addButton("§7[§l§cBustdown§7]\n§e5,000 coins");
        $form->addButton("§7[§l§bPro§r§7]\n§e5,000 coins");
        $form->addButton("§7[§l§4Gang§cBang§r§7]\n§e5,000 coins");
        $form->addButton("§7[§5Over§dlord§7]\n§e15,000 coins");
        $form->addButton("§7[§l§4Hacker§r§7]\n§e15,000 coins");
        $form->addButton("§7[§l§2Chug§aNub§r§7]\n§e15,000 coins");
        $form->addButton("§7[§2Experienced§7]\n§e15,000 coins");
        $form->addButton("§7[§l§aKing§7]\n§e15,000 coins");
        $form->addButton("§7[§1E§9z§7]\n§e15,000 coins");
        $form->addButton("§7[§2P§ry§2r§ro§7]\n§e25,000 coins");
        $form->addButton("§7[§l§4G§2u§4c§2c§4i§r§7]\n§e25,000 coins");
        $form->addButton("§7[§l§6Corn§0Hub§r§7]\n§e25,000 coins");
        $form->addButton("§7[§2El§aite§7]\n§e25,000 coins");
        $form->addButton("§7[§bWindows10§7]\n§e25,000 coins");
        $form->addButton("§7[§l§4Gangster§7]\n§e25,000 coins");
        $form->addButton("§7[§4F§cH§6R§eE§2S§aH§7]\n§e35,000 coins");
        $form->addButton("§7[§l§2Troll§r§7]\n§e35,000 coins");
        $form->addButton("§7[§l§8Android§7God§r§7]\n§e35,000 coins");
        $form->addButton("§7[§l§4Emperor§r§7]\n§e35,000 coins");
        $form->addButton("§7[§l§4Hit§cman§r§7]\n§e35,000 coins");
        $form->addButton("§7[§l§2Mobster§7]\n§e35,000 coins");
        $form->addButton("§7[§1S§3al§9ty§7]\n§e40,000 coins");
        $form->addButton("§7[§l§3No§9U§r§7]\n§e40,000 coins");
        $form->addButton("§7[§l§3IOS§9GOD§r§7]\n§e40,000 coins");
        $form->addButton("§7[§6Pan§dcake§7]\n§e40,000 coins");
        $form->addButton("§7[§l§3Loner§7]\n§e40,000 coins");
        $form->addButton("§7[§l§3Horion§bUser§7]\n§e40,000 coins");
        $form->addButton("§7[§6U§ew§6U§7]\n§e45,000 coins");
        $form->addButton("§7[§6Zoo§amer§7]\n§e45,000 coins");
        $form->addButton("§7[§0Dirt§7]\n§e45,000 coins");
        $form->addButton("§7[§6I§eAm§a2§9Gud§54§cYou§7]\n§e45,000 coins");
        $form->addButton("§7[§l§4Kill§cer§r§l.exe§r§7]\n§e45,000 coins");
        $form->addButton("Exit");
        $form->sendToPlayer($player);
    }
}