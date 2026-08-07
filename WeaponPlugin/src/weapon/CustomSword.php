<?php

declare(strict_types=1);

namespace weapon;

use customiesdevs\customies\item\ItemComponents;
use customiesdevs\customies\item\ItemComponentsTrait;
use pocketmine\item\ItemIdentifier;
use pocketmine\item\Sword;
use pocketmine\item\ToolTier;
use pocketmine\entity\Entity;
use pocketmine\entity\Living;
use pocketmine\player\Player;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\world\particle\HugeExplodeSeedParticle;
use pocketmine\world\sound\ExplodeSound;


class CustomSword extends Sword implements ItemComponents {

    use ItemComponentsTrait;

    public function __construct(ItemIdentifier $identifier) {
        parent::__construct($identifier, "§bKaelix", ToolTier::DIAMOND);
        $this->initComponent("custom_sword");
    }

    public function getAttackPoints(): int {
        return 10;
    }

    public function getMaxDurability(): int {
        return 1000;
    }

    public function onAttackEntity(Entity $victim, array &$returnedItems): bool {

        if ($victim instanceof Player) {
            // 20% de chance de déclencher l'explosion
            if (mt_rand(1, 100) <= 20) {
                $pos = $victim->getPosition();
                $world = $victim->getWorld();

                $world->addParticle($pos, new HugeExplodeSeedParticle());
                $world->addSound($pos, new ExplodeSound());

                foreach ($world->getNearbyEntities($victim->getBoundingBox()->expandedCopy(2, 2, 2), $victim) as $entity) {
                    if ($entity instanceof Living) {
                        $entity->attack(new EntityDamageEvent($entity, EntityDamageEvent::CAUSE_CUSTOM, 4));
                    }
                }
            }

        } elseif ($victim instanceof Living) {
            // animal / mob → brûle 2 secondes
            $victim->setOnFire(2);
        }

        return parent::onAttackEntity($victim, $returnedItems);
    }
}