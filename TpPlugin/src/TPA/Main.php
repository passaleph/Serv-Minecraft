<?php

namespace TPA;

use pocketmine\plugin\PluginBase;
use TPA\commands\TPACommand;
use TPA\commands\TPAAcceptCommand;

class Main extends PluginBase
{

    private array $requests = [];

    public function onEnable(): void
    {
        $this->getLogger()->info("Plugin TPA activé !");

        $this->getServer()->getCommandMap()->register("tpa", new TPACommand($this));
        $this->getServer()->getCommandMap()->register("tpaccept", new TPAAcceptCommand($this));
    }


    public function addRequest(string $target, string $sender): void
    {
        $this->requests[$target] = $sender;
    }


    public function getRequest(string $player): ?string
    {
        return $this->requests[$player] ?? null;
    }


    public function removeRequest(string $player): void
    {
        unset($this->requests[$player]);
    }
}