<?php

namespace commands;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;

use SetHomePlugin\Main;


class DelHomeCommand extends Command{


    public function __construct(){

        parent::__construct(
            "delhome",
            "Supprimer un home",
            "/delhome <nom>"
        );

        $this->setPermission("sethomeplugin.delhome");
    }



    public function execute(CommandSender $sender, string $label, array $args): bool{


        if(!$sender instanceof Player){

            $sender->sendMessage(
                "Commande uniquement en jeu."
            );

            return false;
        }



        if(!isset($args[0])){

            $sender->sendMessage(
                "§cUtilisation : /delhome <nom>"
            );

            return false;
        }



        $name = strtolower($args[0]);


        $manager = Main::getInstance()->getHomeManager();



        if(!$manager->deleteHome($sender, $name)){


            $sender->sendMessage(
                "§cCe home n'existe pas."
            );

            return false;
        }



        $sender->sendMessage(
            "§aHome §e".$name." §asupprimé avec succès !"
        );


        return true;
    }
}