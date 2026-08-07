<?php

declare(strict_types=1);

namespace grinn34\vanillaMobAI\combat;

use grinn34\vanillaMobAI\registry\MobCombatRegistry;
use pocketmine\entity\Living;
use pocketmine\event\entity\EntityPreExplodeEvent;
use pocketmine\world\Explosion;
use pocketmine\world\Position;

final class ExplosionHelper{
	private function __construct(){}

	public static function explode(Living $entity) : void{
		if(!$entity->isAlive() || $entity->isClosed()){
			return;
		}

		$world = $entity->getWorld();
		$position = Position::fromObject(
			$entity->getPosition()->add(0, $entity->getSize()->getHeight() / 2, 0),
			$world
		);

		$preExplode = new EntityPreExplodeEvent($entity, MobCombatRegistry::getExplosionRadius($entity));
		$preExplode->call();
		if($preExplode->isCancelled()){
			$entity->kill();
			return;
		}

		$explosion = new Explosion($position, $preExplode->getRadius(), $entity, $preExplode->getFireChance());
		if($preExplode->isBlockBreaking()){
			$explosion->explodeA();
		}
		$explosion->explodeB();

		$entity->kill();
	}
}
