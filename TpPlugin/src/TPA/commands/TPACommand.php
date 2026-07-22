<?php

namespace TPA\commands;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use TPA\Main;
use TPA\tasks\TPAExpireTask;

class TPACommand extends Command
{

    private Main $plugin;


    public function __construct(Main $plugin)
    {
        parent::__construct(
            "tpa",
            "Envoyer une demande de téléportation",
            "/tpa <joueur>"
        );

        $this->plugin = $plugin;
    }


    public function execute(CommandSender $sender, string $label, array $args): bool
    {

        if (!$sender instanceof Player) {
            $sender->sendMessage("§cCommande uniquement en jeu.");
            return true;
        }


        if (!isset($args[0])) {
            $sender->sendMessage("§cUtilisation : /tpa <joueur>");
            return true;
        }


        $target = $this->plugin->getServer()->getPlayerByPrefix($args[0]);


        if ($target === null) {
            $sender->sendMessage("§cJoueur introuvable.");
            return true;
        }


        if ($target->getName() === $sender->getName()) {
            $sender->sendMessage("§cImpossible de vous envoyer une demande.");
            return true;
        }


        // Enregistre la demande
        $this->plugin->addRequest(
            $target->getName(),
            $sender->getName()
        );


        // Expiration après 20 secondes
        $this->plugin->getScheduler()->scheduleDelayedTask(
            new TPAExpireTask(
                $this->plugin,
                $target->getName()
            ),
            20 * 20
        );


        $sender->sendMessage(
            "§aDemande envoyée à §e" . $target->getName()
        );


        $target->sendMessage(
            "§e" . $sender->getName() . 
            " §avous demande une téléportation."
        );


        $target->sendMessage(
            "§7Vous avez §c20 secondes §7pour accepter avec §f/tpaccept"
        );


        return true;
    }
}