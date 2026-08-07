<?php

declare(strict_types=1);

namespace entity;

use pocketmine\entity\Living;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\network\mcpe\NetworkBroadcastUtils;
use pocketmine\network\mcpe\protocol\AnimateEntityPacket;

class Portail extends Living {

    public static function getNetworkTypeId(): string {
        return "portail:portail";
    }

    protected function getInitialSizeInfo(): EntitySizeInfo {
        // hauteur ~5.5 blocs, largeur ~4 (hitbox), d'après le modèle
        return new EntitySizeInfo(5.5, 4.0);
    }

    protected function getInitialGravity(): float {
        return 0.0; // pas de gravité -> le portail flotte où on le spawn (il ne tombe pas)
    }

    protected function getInitialDragMultiplier(): float {
        return 1.0;
    }

    public function getName(): string {
        return "Portail";
    }

    /**
     * Joue une animation du resource pack sur ce portail, pour tous les joueurs qui le voient.
     */
    public function playAnimation(string $animation): void {
        $pk = AnimateEntityPacket::create($animation, "", "", 0, "", 0.0, [$this->getId()]);
        NetworkBroadcastUtils::broadcastPackets($this->getViewers(), [$pk]);
    }

    /**
     * onUpdate() tourne 20 fois par seconde.
     * On déclenche l'animation toutes les 10 secondes (200 ticks).
     */
    public function onUpdate(int $currentTick): bool {
        if ($currentTick % 200 === 0) {
            $this->playAnimation("animation.model.new");
        }
        return parent::onUpdate($currentTick);
    }
}
