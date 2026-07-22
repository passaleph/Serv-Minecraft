<?php

namespace TPA\tasks;

use pocketmine\scheduler\Task;
use pocketmine\player\Player;

class TPATask extends Task
{

    private Player $target;
    private Player $sender;
    private int $time;


    public function __construct(Player $target, Player $sender)
    {
        $this->target = $target;
        $this->sender = $sender;
        $this->time = 5;
    }


    public function onRun(): void
    {

        if (!$this->target->isOnline() || !$this->sender->isOnline()) {
            $this->getHandler()->cancel();
            return;
        }


        if ($this->time > 0) {

            $this->target->sendMessage(
                "§eTéléportation dans §c" . $this->time . " §esecondes..."
            );

            $this->sender->sendMessage(
                "§eTéléportation de §f" . $this->target->getName() .
                " §edans §c" . $this->time . " §esecondes..."
            );

            $this->time--;

            return;
        }


        $this->target->teleport(
            $this->sender->getPosition()
        );


        $this->target->sendMessage(
            "§aTéléportation réussie !"
        );


        $this->sender->sendMessage(
            "§aLe joueur a été téléporté vers vous."
        );


        $this->getHandler()->cancel();
    }
}