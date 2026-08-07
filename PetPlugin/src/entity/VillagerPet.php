<?php

declare(strict_types=1);

namespace entity;

use pocketmine\player\Player;
use pocketmine\entity\projectile\Arrow;
use pocketmine\entity\Living;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\AnimateEntityPacket;
use pocketmine\network\mcpe\NetworkBroadcastUtils;
use pocketmine\entity\Location;

class VillagerPet extends PetEntity {

    private int $lastShot = 0;
    private int $burstRemaining = 0;
    private int $burstTick = 0;
    private ?Vector3 $burstCiblePos = null;

    public static function getNetworkTypeId(): string {
        return "pet:villager";
    }

    public function getPetName(): string {
        return "Villageois";
    }

    public function capaciteSpeciale(Living $cible): void {
        $this->burstRemaining = 10;
        $this->burstCiblePos = $cible->getEyePos();
        $this->burstTick = 0;
        $this->jouerAnimSpecial();
    }

    private function tirerFleche(Vector3 $ciblePos): void {
        $petPosition = Location::fromObject($this->getEyePos(), $this->getWorld(), $this->getLocation()->getYaw(), $this->getLocation()->getPitch());
        $arrow = new Arrow($petPosition, $this, false);

        $direction = $ciblePos->subtractVector($petPosition)->normalize();
        $direction = $direction->add(rand(-8, 8) / 100, rand(-8, 8) / 100, rand(-8, 8) / 100);

        $arrow->setMotion($direction->multiply(3));
        $arrow->spawnToAll();
    }

    private function jouerAnimTir(): void {
        $packet = AnimateEntityPacket::create(
            "animation.pet_villager.shoot",
            "default",
            "query.any_animation_finished",
            0,
            "controller.animation.pet_villager.shoot",
            0.0,
            [$this->getId()]
        );
        NetworkBroadcastUtils::broadcastPackets($this->getViewers(), [$packet]);
    }

    private function jouerAnimSpecial(): void {
        $packet = AnimateEntityPacket::create(
            "animation.pet_villager.special",
            "default",
            "query.any_animation_finished",
            0,
            "controller.animation.pet_villager.special",
            0.0,
            [$this->getId()]
        );
        NetworkBroadcastUtils::broadcastPackets($this->getViewers(), [$packet]);
    }

    protected function deplacement(Player $owner): void {
        $ownerPosition = $owner->getPosition()->add(2, 2, 0);
        $petPosition = $this->getPosition();

        if ($petPosition->distance($ownerPosition) > 2) {
            if ($petPosition->distance($ownerPosition) > 10) {
                $this->teleport($ownerPosition);
                return;
            }

            $direction = $ownerPosition->subtractVector($petPosition);
            $direction = $direction->normalize();
            $this->lookAt($ownerPosition);
            $this->setMotion($direction->multiply(0.4));
        } else {
            $this->setMotion(new Vector3(0, 0, 0));
        }
    }

    protected function combat(Player $owner, $currentTick): void {
        // rafale en cours : 1 fleche tous les 2 ticks jusqu'a epuisement
        if ($this->burstRemaining > 0) {
            $this->burstTick++;
            if ($this->burstTick >= 2) {
                $this->burstTick = 0;
                if ($this->burstCiblePos !== null) {
                    $this->lookAt($this->burstCiblePos);
                    $this->tirerFleche($this->burstCiblePos);
                }
                $this->burstRemaining--;
            }
            return;
        }

        $zone = $owner->getBoundingBox()->expandedCopy(8, 4, 8);

        $tick = 40;
        if ($this->getLevel() >= 25) {
            $tick = 20;
        }

        foreach ($owner->getWorld()->getNearbyEntities($zone, $this) as $entity) {
            if ($entity instanceof Living && !$entity instanceof Player && !$entity instanceof PetEntity) {
                $this->lookAt($entity->getEyePos());

                if ($currentTick - $this->lastShot >= $tick) {
                    $this->lastShot = $currentTick;
                    $special = ($this->getLevel() >= 50 && rand(1, 20) === 1);
                    if ($special) {
                        $this->capaciteSpeciale($entity);
                    } else {
                        $this->tirerFleche($entity->getEyePos());
                        $this->jouerAnimTir();
                    }
                }
                return;
            }
        }
    }
}
