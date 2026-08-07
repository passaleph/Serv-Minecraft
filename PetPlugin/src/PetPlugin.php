<?php

declare(strict_types=1);

use pocketmine\plugin\PluginBase;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\resourcepacks\ZippedResourcePack;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerInteractEvent;
use customiesdevs\customies\entity\CustomiesEntityFactory;
use customiesdevs\customies\item\CustomiesItemFactory;
use item\PetEgg;
use item\Bonbon;
use entity\PetEntity;
use entity\VillagerPet;
use entity\WitchPet;
use entity\WitchVfx;
use entity\CapybaraPet;
use pocketmine\entity\Living;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use muqsit\invmenu\InvMenu;
use muqsit\invmenu\InvMenuHandler;
use muqsit\invmenu\type\InvMenuTypeIds;
use pocketmine\inventory\Inventory;

class PetPlugin extends PluginBase implements Listener {

    /** @var array<string, PetEntity> owner XUID => son pet actuellement en jeu */
    private array $pets = [];

    public function onEnable(): void {
        CustomiesEntityFactory::getInstance()->registerEntity(VillagerPet::class, "pet:villager");
        CustomiesEntityFactory::getInstance()->registerEntity(WitchPet::class, "pet:witch");
        CustomiesEntityFactory::getInstance()->registerEntity(WitchVfx::class, "witch:vfx");
        CustomiesEntityFactory::getInstance()->registerEntity(CapybaraPet::class, "pet:capybara");
        CustomiesItemFactory::getInstance()->registerItem(Bonbon::class, "pet:bonbon", "Bonbon");
        CustomiesItemFactory::getInstance()->registerItem(PetEgg::class, "pet:egg_emeraude", "Oeuf Emeraude");

        $this->getServer()->getPluginManager()->registerEvents($this, $this);

        if (!InvMenuHandler::isRegistered()) {
            InvMenuHandler::register($this);
        }

        $this->saveResource("pets_rp.zip");
        $rpManager = $this->getServer()->getResourcePackManager();
        $pack = new ZippedResourcePack($this->getDataFolder() . "pets_rp.zip");
        $rpManager->setResourceStack(array_merge($rpManager->getResourceStack(), [$pack]));
        $rpManager->setResourcePacksRequired(true);
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
        if (!$sender instanceof Player) {
            $sender->sendMessage("Reserve aux joueurs.");
            return true;
        }

        if ($command->getName() === "pet") {
            $type = rand(0, 2); 
            if ($type == 0) {
                $type = "villager";
            }
            if ($type == 1) {
                $type = "witch";
            } else {
                $type = "capybara";
            }
            $egg = CustomiesItemFactory::getInstance()->get("pet:egg_emeraude");
            if ($egg instanceof PetEgg) {
                $egg->setPetType($type);
                $egg->refreshLore();
            }
            $sender->getInventory()->addItem($egg);
            $sender->sendMessage("§aTu as recu un oeuf ! Pose-le (clic droit sur un bloc) pour faire apparaitre ton pet.");

        } else if ($command->getName() === "bonbon") {
            $sender->getInventory()->addItem(CustomiesItemFactory::getInstance()->get("pet:bonbon"));
        }
        return true;
    }

    /** Poser un oeuf (clic droit) -> fait apparaitre le pet, au niveau stocke dans l'oeuf. */
    public function onInteract(PlayerInteractEvent $event): void {
        $item = $event->getItem();
        if (!$item instanceof PetEgg) {
            return;
        }
        if ($event->getAction() !== PlayerInteractEvent::RIGHT_CLICK_BLOCK) {
            return;
        }

        $player = $event->getPlayer();
        

        // 1) spawn le pet a la position du joueur (selon le type stocke dans l'oeuf)
        if ($item->getPetType() === "witch") {
            $pet = new WitchPet($player->getLocation());
        } else {
            $pet = new VillagerPet($player->getLocation());
        }
        $pet->setGravity(0.0);
        $pet->setNoClientPredictions(true);
        $pet->setOwner($player->getXuid());
        $pet->setLevel($item->getLevel());                 // niveau lu dans le NBT de l'oeuf
        $pet->setSac(PetEgg::deserialiserSac($item->getSacData()));   // contenu du sac lu dans le NBT
        $pet->setNameTag("§eNiveau " . $pet->getLevel());
        $pet->setNameTagAlwaysVisible(true);
        $pet->spawnToAll();

        // 2) on retient quel pet appartient a ce joueur
        $this->pets[$player->getXuid()] = $pet;

        // 3) consomme 1 oeuf
        $item->setCount($item->getCount() - 1);
        $player->getInventory()->setItemInHand($item);

        $event->cancel(); // evite le comportement par defaut
    }

    /** Nourrir le pet -> monte de niveau (en memoire + nametag). Appele depuis PetEntity. */
    public function AddLevel(Player $player): void {
        $xuid = $player->getXuid();
        if (isset($this->pets[$xuid])) {
            $pet = $this->pets[$xuid];
            $nouveau = min(50, $pet->getLevel() + 1);
            $pet->setLevel($nouveau);
            $pet->setNameTag("§eNiveau " . $pet->getLevel());
        }
    }

    /** Deconnexion -> range le pet dans un oeuf (garde le niveau) et le fait disparaitre. */
    public function onQuit(PlayerQuitEvent $event): void {
        $player = $event->getPlayer();
        $xuid = $player->getXuid();
    
        if (isset($this->pets[$xuid])) {
            $pet = $this->pets[$xuid];

            // recree un oeuf avec le niveau actuel du pet
            $egg = CustomiesItemFactory::getInstance()->get("pet:egg_emeraude");
            if ($egg instanceof PetEgg) {
                $egg->setPetType($pet instanceof WitchPet ? "witch" : "villager");
                $egg->setLevel($pet->getLevel());
                $egg->setSacData(PetEgg::serialiserSac($pet->getSac()));
                $egg->refreshLore();
                $player->getInventory()->addItem($egg);
            }

            $pet->flagForDespawn();
            unset($this->pets[$xuid]);
        }
    }

    /** Ouvre le sac a dos d'un pet (clic droit sur le pet). */
    public function ouvrirSac(Player $player, PetEntity $pet): void {
        $taille = $pet->getTailleSac();
        if ($taille <= 5) {
            $type = InvMenuTypeIds::TYPE_HOPPER;
        } elseif ($taille <= 27) {
            $type = InvMenuTypeIds::TYPE_CHEST;
        } else {
            $type = InvMenuTypeIds::TYPE_DOUBLE_CHEST;
        }

        $menu = InvMenu::create($type);
        $menu->setName("Sac de " . $pet->getPetName());
        $menu->getInventory()->setContents($pet->getSac());
        $menu->setInventoryCloseListener(function(Player $joueur, Inventory $inv) use ($pet): void {
            $pet->setSac($inv->getContents());
        });
        $menu->send($player);
    }

}
