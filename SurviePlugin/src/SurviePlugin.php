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

        if ($command->getName() !== "survie") {
            return false;
        }

        if (!$sender instanceof Player) {
            $sender->sendMessage("Cette commande est réservée aux joueurs.");
            return true;
        }

        if ($command->getName() == "survie" && count($args) > 1) {
            $sender->sendMessage("Nombre argment invalide.");
            return true;
        } else {
            return $this->SurvieTp($sender);
        }  
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
}   



