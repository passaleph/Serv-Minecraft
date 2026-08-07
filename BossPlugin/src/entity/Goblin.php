<?php

declare(strict_types=1);

namespace entity;

use pocketmine\entity\Living;
use pocketmine\entity\EntitySizeInfo;

class Goblin extends Living {

    public static function getNetworkTypeId(): string {
        return "boss:goblin";
    }

    protected function getInitialSizeInfo(): EntitySizeInfo {
        return new EntitySizeInfo(2.0, 1.0); // hauteur, largeur (hitbox)
    }

    protected function getInitialDragMultiplier(): float {
        return 0.02;
    }

    protected function getInitialGravity(): float {
        return 0.08;
    }

    public function getName(): string {
        return "Gobelin Boss";
    }
}
