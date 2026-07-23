<?php

declare(strict_types=1);

namespace tasks;

use pocketmine\scheduler\Task;

class TPAExpireTask extends Task {

    /**
     * @param \TpPlugin $plugin
     * @param string    $target    Nom du joueur qui devait accepter la demande
     * @param string    $requester Nom du joueur qui a envoye la demande
     */
    public function __construct(
        private \TpPlugin $plugin,
        private string $target,
        private string $requester
    ) {}

    public function onRun(): void {
        // On n'expire que si CETTE demande est toujours celle en attente
        // (une demande acceptee, annulee ou remplacee ne doit pas etre touchee)
        if ($this->plugin->getRequest($this->target) !== $this->requester) {
            return;
        }

        $this->plugin->removeRequest($this->target);

        // On previent le demandeur que sa demande a expire
        $player = $this->plugin->getServer()->getPlayerByPrefix($this->requester);
        if ($player !== null) {
            $player->sendMessage("§cVotre demande de téléportation à §e" . $this->target . " §ca expiré.");
        }
    }
}
