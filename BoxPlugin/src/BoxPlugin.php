<?php

declare(strict_types=1);

use pocketmine\plugin\PluginBase;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\entity\Location;
use pocketmine\resourcepacks\ZippedResourcePack;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerEntityInteractEvent;
use pocketmine\item\StringToItemParser;
use pocketmine\item\VanillaItems;
use pocketmine\block\VanillaBlocks;
use customiesdevs\customies\entity\CustomiesEntityFactory;
use customiesdevs\customies\item\CustomiesItemFactory;
use entity\CrateEntity;
use entity\CrateCommon;
use entity\CrateRare;
use entity\CrateLegendary;
use item\CommonKey;
use item\RareKey;
use item\LegendaryKey;

class BoxPlugin extends PluginBase implements Listener {

    /** tier => typeId de la clé correspondante */
    private array $keyTypeIds = [];

    public function onEnable(): void {
        // 1) Resource pack
        $this->saveResource("box_rp.zip");
        $rpManager = $this->getServer()->getResourcePackManager();
        $pack = new ZippedResourcePack($this->getDataFolder() . "box_rp.zip");
        $rpManager->setResourceStack(array_merge($rpManager->getResourceStack(), [$pack]));
        $rpManager->setResourcePacksRequired(true);

        // 2) Enregistrer les entités crates
        $ef = CustomiesEntityFactory::getInstance();
        $ef->registerEntity(CrateCommon::class, "box:common");
        $ef->registerEntity(CrateRare::class, "box:rare");
        $ef->registerEntity(CrateLegendary::class, "box:legendary");

        // 3) Enregistrer les clés (items custom)
        $if = CustomiesItemFactory::getInstance();
        $if->registerItem(CommonKey::class, "boxplugin:common_key", "§aClé Commune");
        $if->registerItem(RareKey::class, "boxplugin:rare_key", "§9Clé Rare");
        $if->registerItem(LegendaryKey::class, "boxplugin:legendary_key", "§5Clé Légendaire");

        // On mémorise le typeId de chaque clé pour vérifier l'item en main plus tard
        foreach (["common", "rare", "legendary"] as $tier) {
            $key = StringToItemParser::getInstance()->parse("boxplugin:" . $tier . "_key");
            if ($key !== null) {
                $this->keyTypeIds[$tier] = $key->getTypeId();
            }
        }

        // 4) Écouter les interactions (ouverture des crates)
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
        if (!$sender instanceof Player) {
            $sender->sendMessage("Commande réservée aux joueurs.");
            return true;
        }

        // /box remove -> supprime les crates autour de toi (dans 6 blocs)
        if ($command->getName() === "box" && strtolower($args[0] ?? "") === "remove") {
            return $this->removeCrates($sender);
        }

        $tier = strtolower($args[0] ?? "");
        if (!in_array($tier, ["common", "rare", "legendary"], true)) {
            $usage = $command->getName() === "box" ? "<common|rare|legendary|remove>" : "<common|rare|legendary>";
            $sender->sendMessage("§cUsage: /" . $command->getName() . " " . $usage);
            return true;
        }

        // /box <tier> -> fait spawn la crate
        if ($command->getName() === "box") {
            $this->spawnCrate($tier, $sender->getLocation());
            $sender->sendMessage("§aCrate §e" . $tier . " §ainvoquée !");
            return true;
        }

        // /key <tier> -> donne une clé
        if ($command->getName() === "key") {
            $key = StringToItemParser::getInstance()->parse("boxplugin:" . $tier . "_key");
            if ($key === null) {
                $sender->sendMessage("§cClé introuvable.");
                return true;
            }
            $sender->getInventory()->addItem($key);
            $sender->sendMessage("§aTu as reçu une clé §e" . $tier . " §a!");
            return true;
        }

        return false;
    }

    /**
     * Fait spawn une crate d'un tier donné à une position.
     * Publique -> appelable par d'autres plugins (ex: le boss Oni).
     */
    public function spawnCrate(string $tier, Location $location): void {
        $class = match ($tier) {
            "common" => CrateCommon::class,
            "rare" => CrateRare::class,
            "legendary" => CrateLegendary::class,
            default => null,
        };
        if ($class === null) {
            return;
        }
        $crate = new $class($location);
        $crate->setNoClientPredictions(true); // ne bouge pas, pas poussable
        $crate->spawnToAll();
    }

    /**
     * /box remove -> supprime toutes les crates dans un rayon de 6 blocs.
     */
    private function removeCrates(Player $player): bool {
        $count = 0;
        foreach ($player->getWorld()->getEntities() as $entity) {
            if ($entity instanceof CrateEntity && $entity->getPosition()->distance($player->getPosition()) <= 6) {
                $entity->flagForDespawn(); // supprime l'entité
                $count++;
            }
        }
        $player->sendMessage("§a" . $count . " crate(s) supprimée(s) autour de toi.");
        return true;
    }

    /**
     * Ouverture d'une crate : clic droit sur la crate avec la bonne clé.
     */
    public function onInteract(PlayerEntityInteractEvent $event): void {
        $entity = $event->getEntity();
        if (!$entity instanceof CrateEntity) {
            return;
        }

        $player = $event->getPlayer();
        $tier = $entity->getTier();
        $held = $player->getInventory()->getItemInHand();

        // Vérifier que le joueur tient bien la clé du bon tier
        if (!isset($this->keyTypeIds[$tier]) || $held->getTypeId() !== $this->keyTypeIds[$tier]) {
            $player->sendMessage("§cIl te faut une §e" . $tier . " §cpour ouvrir cette crate.");
            return;
        }

        // Consommer 1 clé
        if ($held->getCount() > 1) {
            $held->setCount($held->getCount() - 1);
            $player->getInventory()->setItemInHand($held);
        } else {
            $player->getInventory()->setItemInHand(VanillaItems::AIR());
        }

        // Donner le loot
        foreach ($this->getLoot($tier) as $item) {
            $player->getInventory()->addItem($item);
        }

        $player->sendMessage("§6§lTu as ouvert une crate §e" . $tier . " §6§l!");
    }

    /**
     * Le loot de chaque tier. Renvoie une liste d'items à donner.
     * @return \pocketmine\item\Item[]
     */
    private function getLoot(string $tier): array {
        $loot = [];

        switch ($tier) {
            case "common": // stuff de base
                $loot[] = VanillaItems::IRON_INGOT()->setCount(mt_rand(3, 8));
                $loot[] = VanillaItems::STEAK()->setCount(mt_rand(8, 16));
                if (mt_rand(1, 100) <= 30) {
                    $loot[] = VanillaItems::DIAMOND()->setCount(mt_rand(1, 2));
                }
                break;

            case "rare": // stuff cool
                $loot[] = VanillaItems::DIAMOND()->setCount(mt_rand(3, 6));
                $loot[] = VanillaItems::GOLDEN_APPLE()->setCount(mt_rand(2, 4));
                $loot[] = VanillaItems::EMERALD()->setCount(mt_rand(4, 10));
                if (mt_rand(1, 100) <= 40) {
                    $loot[] = VanillaBlocks::DIAMOND()->asItem();
                }
                break;

            case "legendary": // stuff incroyable
                $loot[] = VanillaItems::NETHERITE_INGOT()->setCount(mt_rand(1, 2));
                $loot[] = VanillaItems::ENCHANTED_GOLDEN_APPLE()->setCount(mt_rand(1, 3));
                $loot[] = VanillaBlocks::DIAMOND()->asItem()->setCount(mt_rand(2, 4));
                if (mt_rand(1, 100) <= 25) {
                    $arme = StringToItemParser::getInstance()->parse("weaponplugin:oni_blade");
                    if ($arme !== null) {
                        $loot[] = $arme;
                    }
                }
                break;
        }

        return $loot;
    }
}
