<?php

namespace TPA\tasks;

use pocketmine\scheduler\Task;
use TPA\Main;

class TPAExpireTask extends Task
{

    private Main $plugin;
    private string $target;


    public function __construct(Main $plugin, string $target)
    {
        $this->plugin = $plugin;
        $this->target = $target;
    }


    public function onRun(): void
    {

        $request = $this->plugin->getRequest($this->target);


        if ($request !== null) {

            $player = $this->plugin->getServer()->getPlayerByPrefix($this->target);


            if ($player !== null) {
                $player->sendMessage(
                    "§cVotre demande de téléportation a expiré."
                );
            }


            $this->plugin->removeRequest($this->target);
        }

    }
}