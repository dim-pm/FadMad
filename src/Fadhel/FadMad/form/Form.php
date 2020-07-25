<?php

declare(strict_types=1);

namespace Fadhel\FadMad\form;

use pocketmine\form\Form as IForm;
use pocketmine\Player;

abstract class Form implements IForm
{

    protected $data = [];

    private $callable;

    public function __construct(?callable $callable)
    {
        $this->callable = $callable;
    }

    public function sendToPlayer(Player $player): void
    {
        $player->sendForm($this);
    }

    public function handleResponse(Player $player, $data): void
    {
        $this->processData($data);
        $callable = $this->getCallable();
        if ($callable !== null) {
            $callable($player, $data);
        }
    }

    public function processData(&$data): void
    {
    }

    public function getCallable(): ?callable
    {
        return $this->callable;
    }

    public function setCallable(?callable $callable)
    {
        $this->callable = $callable;
    }

    public function jsonSerialize()
    {
        return $this->data;
    }
}