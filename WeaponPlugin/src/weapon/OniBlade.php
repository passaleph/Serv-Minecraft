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
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\player\Player;
use pocketmine\world\particle\FlameParticle;
use pocketmine\event\entity\EntityDamageByEntityEvent;

class OniBlade extends Sword implements ItemComponents {

    use ItemComponentsTrait;

    public function __construct(ItemIdentifier $identifier) {
        parent::__construct($identifier, "§4Lame de l'Oni", ToolTier::DIAMOND);
        $this->initComponent("oni_blade"); // doit matcher item_texture.json
    }

    public function getAttackPoints(): int {
        return 8;
    }

    public function getMaxDurability(): int {
        return 1500;
    }

    public function onAttackEntity(Entity $victim, array &$returnedItems): bool {

        if ($victim instanceof Player) {
            // On travaille avec la VICTIME (l'entité a bien getPosition/getWorld, pas l'item)
            $posVictim = $victim->getPosition();
            $world = $victim->getWorld();

            $cause = $victim->getLastDamageCause();
            if ($cause instanceof EntityDamageByEntityEvent) {
                $attaquant = $cause->getDamager();      // 👈 le joueur qui a l'arme
                if ($attaquant instanceof Player) {
                    $posVictim = $attaquant->getPosition();   // 👈 sa position
                }
            }

            // Burst de particules de feu autour de la victime
            for ($i = 0; $i < 30; $i++) {
                $ox = mt_rand(-20, 20) / 10;
                $oy = mt_rand(0, 30) / 10;
                $oz = mt_rand(-20, 20) / 10;
                $world->addParticle($posVictim->add($ox, $oy, $oz), new FlameParticle());
            }

            
          
            $victim->getEffects()->add(new EffectInstance(VanillaEffects::SLOWNESS(), 60, 0, false));
           

        } elseif ($victim instanceof Living) {
            // animal / mob → 20% de chance de le brûler 2 secondes
            if (mt_rand(1, 100) <= 20) {
                $victim->setOnFire(2);
            }
        }

        return parent::onAttackEntity($victim, $returnedItems);
    }
}
