<?php

declare(strict_types=1);

use pocketmine\plugin\PluginBase;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\item\StringToItemParser;
use pocketmine\resourcepacks\ZippedResourcePack;
use customiesdevs\customies\item\CustomiesItemFactory;
use weapon\CustomSword;
use weapon\OniBlade;

class WeaponPlugin extends PluginBase {

    public function onEnable(): void {
        // 1) Charger le resource pack (la texture de l'épée)
        $this->saveResource("weapon_rp.zip");
        $rpManager = $this->getServer()->getResourcePackManager();
        $pack = new ZippedResourcePack($this->getDataFolder() . "weapon_rp.zip");
        $rpManager->setResourceStack(array_merge($rpManager->getResourceStack(), [$pack]));
        $rpManager->setResourcePacksRequired(true);

        // 2) Enregistrer les items custom via Customies
        CustomiesItemFactory::getInstance()->registerItem(
            CustomSword::class,
            "weaponplugin:custom_sword",
            "§6Épée Légendaire"
        );
        CustomiesItemFactory::getInstance()->registerItem(
            OniBlade::class,
            "weaponplugin:oni_blade",
            "§4Lame de l'Oni"
        );
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
        if ($command->getName() !== "epee") {
            return false;
        }
        if (!$sender instanceof Player) {
            $sender->sendMessage("Commande réservée aux joueurs.");
            return true;
        }

        // On lit l'argument (le nom de l'arme), en minuscule pour accepter "OniBlade", "oniblade", etc.
        $nom = strtolower($args[0] ?? "");

        switch ($nom) {
            case "kaelix":
                $identifiant = "weaponplugin:custom_sword";
                break;
            case "oniblade":
                $identifiant = "weaponplugin:oni_blade";
                break;
            default:
                $sender->sendMessage("§cUsage: /epee <kaelix|oniBlade>");
                return true;
        }

        $item = StringToItemParser::getInstance()->parse($identifiant);
        if ($item === null) {
            $sender->sendMessage("§cItem introuvable (Customies pas chargé ?).");
            return true;
        }

        $sender->getInventory()->addItem($item);
        $sender->sendMessage("§aTu as reçu ton arme !");
        return true;
    }
}
