<?php

namespace TPA\commands;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use TPA\Main;
use TPA\tasks\TPATask;

class TPAAcceptCommand extends Command
{

    private Main $plugin;

    public function __construct(Main $plugin)
    {
        parent::__construct("tpaccept", "Accepter une demande de téléportation", "/tpaccept");
        $this->plugin = $plugin;
    }


    public function execute(CommandSender $sender, string $label, array $args): bool
    {

        if (!$sender instanceof Player) {
            $sender->sendMessage("§cCommande utilisable uniquement en jeu.");
            return true;
        }


        $request = $this->plugin->getRequest($sender->getName());


        if ($request === null) {
            $sender->sendMessage("§cAucune demande de téléportation.");
            return true;
        }


        $player = $this->plugin->getServer()->getPlayerByPrefix($request);


        if ($player === null) {
            $sender->sendMessage("§cLe joueur n'est plus connecté.");
            $this->plugin->removeRequest($sender->getName());
            return true;
        }


        $sender->sendMessage("§aDemande acceptée !");


        // Lance le compteur 5 secondes
        $this->plugin->getScheduler()->scheduleRepeatingTask(
            new TPATask($player, $sender),
            20
        );


        $this->plugin->removeRequest($sender->getName());

        return true;
    }
}