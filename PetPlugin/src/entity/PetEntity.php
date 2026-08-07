<?php

declare(strict_types=1);

namespace entity;

use pocketmine\entity\Living;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\player\Player;
use pocketmine\math\Vector3;
use pocketmine\item\Item;
use item\Bonbon;

/**
 * Classe de base de TOUS les pets.
 * Contient ce qui est commun : owner, niveaux, suivi du joueur.
 */
abstract class PetEntity extends Living {

    protected ?string $ownerXuid = null;
    protected int $level = 1;
    protected int $xp = 0;

    /** @var Item[] contenu du sac a dos du pet */
    protected array $backpackItems = [];

    // ======== Identité (chaque pet remplit ça) ========

    /** Le nom affiché du pet (ex: "Villageois"). */
    abstract public function getPetName(): string;

    /** Le pouvoir unique de CE pet (chaque pet est différent). */
    abstract public function capaciteSpeciale(Living $cible): void;

    /** Comment CE pet se déplace autour de son owner (chaque pet le sien). */
    abstract protected function deplacement(Player $owner): void;

    abstract protected function combat(Player $owner, int $currentTick): void;

    // ======== Owner ========

    public function setOwner(string $xuid): void {
        $this->ownerXuid = $xuid;
    }

    public function getOwner(): ?string {
        return $this->ownerXuid;
    }

    // ======== Sac a dos ========

    /** Nombre de slots du sac. Base = mini (chaque pet peut l'override). */
    public function getTailleSac(): int {
        return 5;
    }

    /** @return Item[] */
    public function getSac(): array {
        return $this->backpackItems;
    }

    /** @param Item[] $items */
    public function setSac(array $items): void {
        $this->backpackItems = $items;
    }

    // ======== Niveaux ========

    public function getLevel(): int {
        return $this->level;
    }

    public function setLevel(int $level): void {
        $this->level = $level;
    }

    public function addXp(int $montant): void {
        // TODO: ajouter l'xp, et monter de niveau si le seuil est atteint
    }

    // ======== Comportement ========

    public function onUpdate(int $currentTick): bool {
        $parent = parent::onUpdate($currentTick);

      
        $owner = null;
        foreach ($this->getWorld()->getPlayers() as $joueur) {
            if ($joueur->getXuid() === $this->ownerXuid) {
                $owner = $joueur;
                break;
            }
        }


        if ($owner === null) {
            return $parent;
        }


        $this->deplacement($owner);
        $this->combat($owner, $currentTick);

        return $parent;
    }

    /** Clic droit sur le pet avec un bonbon -> il monte de niveau. */
    public function onInteract(Player $player, Vector3 $clickPos): bool {
        $item = $player->getInventory()->getItemInHand();

        if ($item instanceof Bonbon) {
            // le level-up (DB + nametag) est gere par le plugin
            $plugin = $this->getWorld()->getServer()->getPluginManager()->getPlugin("PetPlugin");
            if ($plugin instanceof \PetPlugin) {
                $plugin->AddLevel($player);
            }

            // consomme 1 bonbon
            $item->setCount($item->getCount() - 1);
            $player->getInventory()->setItemInHand($item);
            return true;
        }

        // sinon (main vide ou autre item) -> ouvrir le sac a dos
        $plugin = $this->getWorld()->getServer()->getPluginManager()->getPlugin("PetPlugin");
        if ($plugin instanceof \PetPlugin) {
            $plugin->ouvrirSac($player, $this);
        }
        return true;
    }

    // ======== Réglages de l'entité ========

    protected function getInitialSizeInfo(): EntitySizeInfo {
        return new EntitySizeInfo(0.6, 0.6); // à ajuster selon le pet
    }

    protected function getInitialGravity(): float {
        return 0.08;
    }

    protected function getInitialDragMultiplier(): float {
        return 0.02;
    }

    public function getName(): string {
        return $this->getPetName();
    }
}
