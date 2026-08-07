<?php

declare(strict_types=1);

use pocketmine\plugin\PluginBase;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\resourcepacks\ZippedResourcePack;
use customiesdevs\customies\entity\CustomiesEntityFactory;
use entity\Portail;

class PortailPlugin extends PluginBase {

    public function onEnable(): void {
        // 1) Charger le resource pack (modèle + texture placeholder)
        $this->saveResource("portail_rp.zip");
        $rpManager = $this->getServer()->getResourcePackManager();
        $pack = new ZippedResourcePack($this->getDataFolder() . "portail_rp.zip");
        $rpManager->setResourceStack(array_merge($rpManager->getResourceStack(), [$pack]));
        $rpManager->setResourcePacksRequired(true);

        // 2) Enregistrer l'entité custom via Customies
        CustomiesEntityFactory::getInstance()->registerEntity(Portail::class, "portail:portail");
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
        if ($command->getName() !== "portail") {
            return false;
        }
        if (!$sender instanceof Player) {
            $sender->sendMessage("Commande réservée aux joueurs.");
            return true;
        }

        $portail = new Portail($sender->getLocation());
        $portail->spawnToAll();
        $sender->sendMessage("§aPortail invoqué !");
        return true;
    }
}
