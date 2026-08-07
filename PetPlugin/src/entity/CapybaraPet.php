<?php

declare(strict_types=1);

namespace entity;

use pocketmine\player\Player;
use pocketmine\entity\Living;
use pocketmine\math\Vector3;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;

class CapybaraPet extends PetEntity {

    private float $angle = 0.0;

    public static function getNetworkTypeId(): string {
        return "pet:capybara";
    }

    public function getPetName(): string {
        return "Capybara";
    }

    protected function deplacement(Player $owner): void {
        // flotte tranquillement a cote du joueur (balancement doux)
        $this->angle += 0.06;
        $ownerPos = $owner->getPosition();
        $bob = sin($this->angle) * 0.3;
        $cible = $ownerPos->add(1.8, 1.2 + $bob, 0);

        $petPosition = $this->getPosition();
        if ($petPosition->distance($cible) > 10) {
            $this->teleport($cible);
            return;
        }
        $this->lookAt($ownerPos);
        $this->setMotion($cible->subtractVector($petPosition)->multiply(0.4));
    }

    protected function combat(Player $owner, int $currentTick): void {
        if ($currentTick % 20 !== 0) {
            return;
        }

        // niveau 25 : dans l'eau -> respiration + nage rapide
        if ($this->getLevel() >= 25 && $owner->isUnderwater()) {
            $owner->getEffects()->add(new EffectInstance(VanillaEffects::WATER_BREATHING(), 60, 0));
            $owner->getEffects()->add(new EffectInstance(VanillaEffects::SPEED(), 60, 1));
        }

        // niveau 50 : plus besoin de manger
        if ($this->getLevel() >= 50) {
            $hunger = $owner->getHungerManager();
            $hunger->setFood($hunger->getMaxFood());
        }
    }

    public function capaciteSpeciale(Living $cible): void {
        // pas de capacite ciblee : son pouvoir principal (pacifier les mobs) est gere par le plugin
    }
}
