<?php

declare(strict_types=1);

namespace grinn34\vanillaMobAI\combat;

use grinn34\vanillaMobAI\entity\animation\BowShootAnimation;
use grinn34\vanillaMobAI\registry\MobCombatRegistry;
use pocketmine\entity\Location;
use pocketmine\entity\Living;
use pocketmine\entity\projectile\Arrow;
use pocketmine\event\entity\ProjectileLaunchEvent;
use pocketmine\world\sound\BowShootSound;

final class RangedCombatHelper{
	private function __construct(){}

	public static function shootArrow(Living $shooter) : void{
		if(!$shooter->isAlive()){
			return;
		}

		$location = $shooter->getLocation();
		$world = $shooter->getWorld();

		$shooter->broadcastAnimation(new BowShootAnimation($shooter));

		$arrow = new Arrow(
			Location::fromObject($shooter->getEyePos(), $world, $location->yaw, $location->pitch),
			$shooter,
			false
		);
		$arrow->setPickupMode(Arrow::PICKUP_NONE);
		$arrow->setBaseDamage(MobCombatRegistry::getRangedDamage($shooter));
		$arrow->setMotion($shooter->getDirectionVector()->multiply(MobCombatRegistry::getArrowVelocity($shooter)));

		$launchEvent = new ProjectileLaunchEvent($arrow);
		$launchEvent->call();
		if($launchEvent->isCancelled()){
			$arrow->flagForDespawn();
			return;
		}

		$arrow->spawnToAll();
		$world->addSound($location, new BowShootSound());
	}
}
