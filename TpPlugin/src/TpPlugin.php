<?php

declare(strict_types=1);

use pocketmine\plugin\PluginBase;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use tasks\TPATask;
use tasks\TPAExpireTask;

class TpPlugin extends PluginBase {

    /**
     * Demandes en attente.
     * Cle   = nom du joueur qui doit accepter (la cible du /tpa)
     * Valeur = nom du joueur qui a envoye la demande
     *
     * @var array<string, string>
     */
    private array $requests = [];

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
        switch ($command->getName()) {
            case "tpa":
                return $this->handleTpa($sender, $args);
            case "tpaccept":
                return $this->handleAccept($sender);
        }
        return false;
    }

    private function handleTpa(CommandSender $sender, array $args): bool {
        if (!$sender instanceof Player) {
            $sender->sendMessage("§cCommande utilisable uniquement en jeu.");
            return true;
        }

        if (!isset($args[0])) {
            $sender->sendMessage("§cUtilisation : /tpa <joueur>");
            return true;
        }

        $target = $this->getServer()->getPlayerByPrefix($args[0]);

        if ($target === null) {
            $sender->sendMessage("§cJoueur introuvable.");
            return true;
        }

        if ($target->getName() === $sender->getName()) {
            $sender->sendMessage("§cVous ne pouvez pas vous envoyer une demande à vous-même.");
            return true;
        }

        // Enregistre la demande : $target devra taper /tpaccept
        $this->requests[$target->getName()] = $sender->getName();

        // Expiration automatique apres 20 secondes
        $this->getScheduler()->scheduleDelayedTask(
            new TPAExpireTask($this, $target->getName(), $sender->getName()),
            20 * 20
        );

        $sender->sendMessage("§aDemande envoyée à §e" . $target->getName());
        $target->sendMessage("§e" . $sender->getName() . " §avous demande une téléportation.");
        $target->sendMessage("§7Vous avez §c20 secondes §7pour accepter avec §f/tpaccept");
        return true;
    }

    private function handleAccept(CommandSender $sender): bool {
        if (!$sender instanceof Player) {
            $sender->sendMessage("§cCommande utilisable uniquement en jeu.");
            return true;
        }

        $requesterName = $this->getRequest($sender->getName());

        if ($requesterName === null) {
            $sender->sendMessage("§cAucune demande de téléportation en attente.");
            return true;
        }

        // La demande est consommee des l'acceptation
        $this->removeRequest($sender->getName());

        $requester = $this->getServer()->getPlayerByPrefix($requesterName);

        if ($requester === null) {
            $sender->sendMessage("§cLe joueur n'est plus connecté.");
            return true;
        }

        $sender->sendMessage("§aDemande acceptée !");

        // Le demandeur ($requester) est teleporte vers celui qui accepte ($sender)
        $this->getScheduler()->scheduleRepeatingTask(
            new TPATask($requester, $sender),
            20
        );
        return true;
    }

    public function getRequest(string $target): ?string {
        return $this->requests[$target] ?? null;
    }

    public function removeRequest(string $target): void {
        unset($this->requests[$target]);
    }
}
