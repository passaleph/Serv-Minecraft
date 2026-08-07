<?php

namespace tasks;

use pocketmine\scheduler\Task;
use pocketmine\player\Player;
use pocketmine\world\Position;

use SetHomePlugin\Main;


class HomeTeleportTask extends Task{


    private Player $player;
    private array $home;
    private int $time;


    public function __construct(Player $player, array $home){

        $this->player = $player;
        $this->home = $home;

        $this->time = Main::getInstance()
            ->getConfigFile()
            ->get("teleport-delay");

    }



    public function onRun(): void{


        if(!$this->player->isOnline()){

            $this->getHandler()->cancel();

            return;
        }



        if($this->time > 0){

            $this->player->sendMessage(
                "§aTéléportation dans §e".$this->time." §asecondes..."
            );

            $this->time--;

            return;
        }



        $world = Main::getInstance()
            ->getServer()
            ->getWorldManager()
            ->getWorldByName($this->home["world"]);



        if($world === null){

            $this->player->sendMessage(
                "§cLe monde du home n'existe plus."
            );

            $this->getHandler()->cancel();

            return;
        }



        $position = new Position(
            $this->home["x"],
            $this->home["y"],
            $this->home["z"],
            $world
        );


        $position->yaw = $this->home["yaw"];
        $position->pitch = $this->home["pitch"];



        $this->player->teleport($position);



        $this->player->sendMessage(
            "§aTéléportation réussie !"
        );



        Main::getInstance()
            ->getCooldownManager()
            ->setCooldown($this->player);



        $this->getHandler()->cancel();

    }

}