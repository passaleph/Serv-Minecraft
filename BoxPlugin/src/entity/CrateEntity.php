<?php

declare(strict_types=1);

namespace entity;

use pocketmine\entity\Living;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\event\entity\EntityDamageEvent;

abstract class CrateEntity extends Living {

    /** Le tier de la crate : "common", "rare" ou "legendary". */
    abstract public function getTier(): string;

    protected function getInitialSizeInfo(): EntitySizeInfo {
        return new EntitySizeInfo(1.4, 1.5); // hitbox adaptée au modèle agrandi
    }

    protected function getInitialGravity(): float {
        return 0.0; // ne tombe pas
    }

    protected function getInitialDragMultiplier(): float {
        return 1.0;
    }

    public function getName(): string {
        return "Crate";
    }

    /** La crate est incassable : on annule tous les dégâts. */
    public function attack(EntityDamageEvent $source): void {
        $source->cancel();
    }
}
