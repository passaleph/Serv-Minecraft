<?php


declare(strict_types=1);

use pocketmine\plugin\PluginBase;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\Config;
use pocketmine\item\StringToItemParser;
use forms\SuccesListForm;



class SuccesPlugin extends PluginBase {




    public function onEnable(): void {
        $this->saveResource("succes.yml");
    }


    public function onDisable(): void {
     
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {

        $name = $command->getName();

        if ($command->getName() !== "sucess"  ) {
            return false;
        }

        if ($command->getName() == "sucess"  ) {
         if (!$sender instanceof Player) {
            $sender->sendMessage("§cCommande réservée aux joueurs.");
            return true;
        }
        $this->showSuccess($sender);
        return true;
            
            
        }

        return true;
    }


    public function showSuccess(Player $player): void {
        $config = new Config($this->getDataFolder() . "succes.yml", Config::YAML);
        $succes = $config->get("succes", []);

        $player->sendForm(new SuccesListForm($this, $succes));
    }

    /**
     * Appelée quand le joueur clique sur un succès.
     * @param Player $player  Le joueur
     * @param string $item    L'item requis (ex: "oak_log")
     * @param int    $amount  La quantité requise (ex: 1000)
     * @param string $reward        La récompense (ex: "elytra")
     * @param int    $rewardAmount  La quantité de récompense
     */
    public function verifierSucces(Player $player, string $item, int $amount, string $reward, int $rewardAmount): void {

        // 1) On fabrique l'item requis à partir de son nom
        $requis = StringToItemParser::getInstance()->parse($item);
        if ($requis === null) {
            return; // nom d'item invalide dans le succes.yml
        }
        $requis->setCount($amount);

        // 2) Le joueur a-t-il au moins cette quantité ?
        if ($player->getInventory()->contains($requis)) {

            // 3) On fabrique la récompense et on la donne
            $recompense = StringToItemParser::getInstance()->parse($reward);
            if ($recompense !== null) {
                $recompense->setCount($rewardAmount);
                $player->getInventory()->addItem($recompense);
                $player->sendMessage("§aSuccès validé ! Tu as reçu ta récompense.");
            }

        } else {
            $player->sendMessage("§cTu n'as pas encore rempli ce succès.");
        }
    }

}   



