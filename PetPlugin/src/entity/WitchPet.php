<?php

declare(strict_types=1);

namespace entity;

use pocketmine\player\Player;
use pocketmine\entity\Living;
use pocketmine\math\Vector3;
use pocketmine\block\Crops;
use pocketmine\world\particle\DustParticle;
use pocketmine\world\particle\PortalParticle;
use pocketmine\color\Color;

class WitchPet extends PetEntity {

    private float $angle = 0.0;

    public static function getNetworkTypeId(): string {
        return "pet:witch";
    }

    public function getPetName(): string {
        return "Sorciere";
    }

    /** Sac de 5 slots de base, 27 (coffre) a partir du niveau 25. */
    public function getTailleSac(): int {
        return $this->getLevel() >= 25 ? 27 : 5;
    }

    protected function deplacement(Player $owner): void {
        $this->angle += 0.08;
        $this->particules();

        // reste a cote du joueur avec un balancement vertical doux
        $ownerPos = $owner->getPosition();
        $bob = sin($this->angle) * 0.4;
        $cible = $ownerPos->add(1.8, 1.4 + $bob, 0);

        $petPosition = $this->getPosition();

        // trop loin (le joueur a bouge vite) -> teleport
        if ($petPosition->distance($cible) > 10) {
            $this->teleport($cible);
            return;
        }

        $this->lookAt($ownerPos);
        $this->setMotion($cible->subtractVector($petPosition)->multiply(0.4));
    }

    protected function combat(Player $owner, int $currentTick): void {
        // capacite de BASE : fait pousser les cultures dans un rayon de 30 autour de l'owner
        if ($currentTick % 10 !== 0) {
            return;
        }
        $world = $owner->getWorld();
        $c = $owner->getPosition();
        for ($i = 0; $i < 15; $i++) {
            $x = (int) $c->x + rand(-30, 30);
            $y = (int) $c->y + rand(-4, 4);
            $z = (int) $c->z + rand(-30, 30);
            if (!$world->isChunkLoaded($x >> 4, $z >> 4)) {
                continue;
            }
            $block = $world->getBlockAt($x, $y, $z);
            if ($block instanceof Crops) {
                try {
                    $world->setBlock($block->getPosition(), $block->setAge($block->getAge() + 1));
                } catch (\InvalidArgumentException $e) {
                    // deja au maximum pour ce type de culture
                }
            }
        }
    }

    private function particules(): void {
        $world = $this->getWorld();
        $pos = $this->getPosition();

        // anneau doux : 2 particules opposees, 2 nuances de violet
        $violet = new Color(140, 70, 200);
        $rose = new Color(200, 130, 235);
        $a = $this->angle;
        $h = $pos->y + 0.7;
        $world->addParticle(new Vector3($pos->x + cos($a) * 0.6, $h, $pos->z + sin($a) * 0.6), new DustParticle($violet));
        $world->addParticle(new Vector3($pos->x + cos($a + M_PI) * 0.6, $h, $pos->z + sin($a + M_PI) * 0.6), new DustParticle($rose));

        // de temps en temps une particule "portail" qui flotte -> effet magique doux
        if (rand(1, 6) === 1) {
            $world->addParticle($pos->add(0, 0.9, 0), new PortalParticle());
        }
    }

    public function capaciteSpeciale(Living $cible): void {
        // pas de capacite ciblee : le special de la sorciere se declenche a la mort du owner (plugin)
    }
}
