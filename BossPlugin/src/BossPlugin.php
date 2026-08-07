<?php

declare(strict_types=1);

use pocketmine\plugin\PluginBase;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\resourcepacks\ZippedResourcePack;
use customiesdevs\customies\entity\CustomiesEntityFactory;
use entity\Oni;

class BossPlugin extends PluginBase {

    public function onEnable(): void {
        // 1) Charger le resource pack (modèle + texture + animations du boss)
        $this->saveResource("oni_rp.zip");
        $rpManager = $this->getServer()->getResourcePackManager();
        $pack = new ZippedResourcePack($this->getDataFolder() . "oni_rp.zip");
        $rpManager->setResourceStack(array_merge($rpManager->getResourceStack(), [$pack]));
        $rpManager->setResourcePacksRequired(true);

        // 2) Enregistrer l'entité custom via Customies
        CustomiesEntityFactory::getInstance()->registerEntity(Oni::class, "boss:oni");
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
        if ($command->getName() !== "boss") {
            return false;
        }
        if (!$sender instanceof Player) {
            $sender->sendMessage("Commande réservée aux joueurs.");
            return true;
        }

        $oni = new Oni($sender->getLocation());
        $oni->spawnToAll();
        $sender->sendMessage("§aBoss Oni invoqué !");
        return true;
    }
}
