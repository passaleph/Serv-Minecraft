<?php

namespace SetHomePlugin\commands;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;

use SetHomePlugin\Main;
use SetHomePlugin\tasks\HomeTeleportTask;


class HomeCommand extends Command{


    public function __construct(){


        parent::__construct(
            "home",
            "Se téléporter à un home",
            "/home <nom>"
        );


        $this->setPermission(
            "sethomeplugin.home"
        );

    }





    public function execute(CommandSender $sender, string $label, array $args): bool{


        if(!$sender instanceof Player){


            $sender->sendMessage(
                "§cCommande uniquement en jeu."
            );


            return false;

        }





        if(!isset($args[0])){


            $sender->sendMessage(
                "§cUtilisation : /home <nom>"
            );


            return false;

        }





        $name = strtolower($args[0]);



        $manager = Main::getInstance()
            ->getHomeManager();





        $home = $manager->getHome(
            $sender,
            $name
        );





        if($home === null){


            $sender->sendMessage(
                "§cCe home n'existe pas."
            );


            return false;

        }





        // Vérification du cooldown

        $cooldown = Main::getInstance()
            ->getCooldownManager();




        if($cooldown->hasCooldown($sender)){


            $sender->sendMessage(

                "§cVous devez attendre encore §e"
                .$cooldown->getRemaining($sender)
                ."s§c."

            );


            return false;

        }





        $sender->sendMessage(

            "§aTéléportation vers §e".$name." §adans quelques secondes..."

        );





        // Lancement du compte à rebours

        Main::getInstance()
            ->getScheduler()
            ->scheduleRepeatingTask(

                new HomeTeleportTask(
                    $sender,
                    $home
                ),

                20

            );




        return true;

    }

}