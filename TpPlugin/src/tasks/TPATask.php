<?php

declare(strict_types=1);

namespace tasks;

use pocketmine\scheduler\Task;
use pocketmine\player\Player;

class TPATask extends Task {

    private int $time = 5;

    /**
     * @param Player $toTeleport Joueur qui va etre teleporte (le demandeur)
     * @param Player $destination Joueur vers qui on teleporte (celui qui a accepte)
     */
    public function __construct(
        private Player $toTeleport,
        private Player $destination
    ) {}

    public function onRun(): void {
        if (!$this->toTeleport->isOnline() || !$this->destination->isOnline()) {
            $this->getHandler()->cancel();
            return;
        }

        if ($this->time > 0) {
            $this->toTeleport->sendMessage("§eTéléportation dans §c" . $this->time . " §esecondes...");
            $this->time--;
            return;
        }

        $this->toTeleport->teleport($this->destination->getPosition());
        $this->toTeleport->sendMessage("§aTéléportation réussie !");
        $this->destination->sendMessage("§a" . $this->toTeleport->getName() . " §aa été téléporté vers vous.");

        $this->getHandler()->cancel();
    }
}
