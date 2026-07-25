<?php

namespace SetHomePlugin\commands;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;

use SetHomePlugin\Main;


class HomesCommand extends Command{


    public function __construct(){

        parent::__construct(
            "homes",
            "Voir la liste de vos homes",
            "/homes"
        );

        $this->setPermission("sethomeplugin.homes");
    }



    public function execute(CommandSender $sender, string $label, array $args): bool{


        if(!$sender instanceof Player){

            $sender->sendMessage(
                "Commande uniquement en jeu."
            );

            return false;
        }



        $manager = Main::getInstance()->getHomeManager();


        $homes = $manager->getHomes($sender);



        if(count($homes) === 0){

            $sender->sendMessage(
                "§cVous n'avez aucun home."
            );

            return true;
        }



        $sender->sendMessage(
            "§8[§aHome§8] §fVos homes :"
        );



        foreach($homes as $name => $data){

            $sender->sendMessage(
                "§e- ".$name
            );
        }


        return true;
    }
}