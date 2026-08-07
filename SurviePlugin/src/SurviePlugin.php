<?php


declare(strict_types=1);

use pocketmine\plugin\PluginBase;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;



class SurviePlugin extends PluginBase {




    public function onEnable(): void {
      
    }


    public function onDisable(): void {
     
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {

        $name = $command->getName();

        if ($command->getName() !== "survie" && $command->getName() !== "spawn" && $command->getName() !== "village" ) {
            return false;
        }

        if (!$sender instanceof Player) {
            $sender->sendMessage("Cette commande est réservée aux joueurs.");
            return true;
        }

        if (count($args) > 1) {
            $sender->sendMessage("Nombre d'arguments invalide.");
            return true;
        }

        if ($name === "survie") {
            return $this->SurvieTp($sender);
        }
            
        if ($name === "spawn") {
            return $this->SpawnTp($sender); 
        }   

        if ($name === "village") {
            return $this->VillageTp($sender); 
        }   


        return false; 
        
    }


    private function SurvieTp(Player $player): bool {
        $world = $this->getServer()->getWorldManager()->getWorldByName("survie");
        if ($world === null) {
            $player->sendMessage("§cCe monde n'existe pas ou n'est pas chargé.");
            return true;
        }

        $player->teleport($world->getSpawnLocation());
        return true;

    }


    private function SpawnTp(Player $player): bool {
        $world = $this->getServer()->getWorldManager()->getWorldByName("spawn");
        if ($world === null) {
            $player->sendMessage("§cCe monde n'existe pas ou n'est pas chargé.");
            return true;
        }

        $player->teleport($world->getSpawnLocation());
        return true;

    }



     private function VillageTp(Player $player): bool {
        $world = $this->getServer()->getWorldManager()->getWorldByName("village");
        if ($world === null) {
            $player->sendMessage("§cCe monde n'existe pas ou n'est pas chargé.");
            return true;
        }

        $player->teleport($world->getSpawnLocation());
        return true;

    }
}   



