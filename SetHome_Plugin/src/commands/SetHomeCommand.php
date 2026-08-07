<?php

namespace commands;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;

use SetHomePlugin\Main;


class SetHomeCommand extends Command{


    public function __construct(){

        parent::__construct(
            "sethome",
            "Créer un home",
            "/sethome <nom>"
        );

        $this->setPermission("sethomeplugin.sethome");
    }



    public function execute(CommandSender $sender, string $label, array $args): bool{


        if(!$sender instanceof Player){

            $sender->sendMessage("Commande uniquement en jeu.");
            return false;
        }


        if(!isset($args[0])){

            $sender->sendMessage(
                "§cUtilisation : /sethome <nom>"
            );

            return false;
        }


        $name = strtolower($args[0]);


        $manager = Main::getInstance()->getHomeManager();


        if(!$manager->setHome($sender, $name)){


            $sender->sendMessage(
                "§cVous avez déjà 4 homes maximum."
            );

            return false;
        }



        $sender->sendMessage(
            "§aHome §e".$name." §acréé avec succès !"
        );


        return true;
    }
}