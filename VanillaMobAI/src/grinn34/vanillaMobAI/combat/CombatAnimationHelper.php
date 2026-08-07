<?php

declare(strict_types=1);

namespace grinn34\vanillaMobAI\combat;

use grinn34\vanillaMobAI\entity\hostile\AggressivePoseCapable;
use pocketmine\entity\animation\ArmSwingAnimation;
use pocketmine\entity\Living;

final class CombatAnimationHelper{
	private function __construct(){}

	public static function setAggressivePose(Living $entity, bool $aggressive) : void{
		if($entity instanceof AggressivePoseCapable){
			$entity->setAggressivePose($aggressive);
		}
	}

	public static function playMeleeSwing(Living $entity) : void{
		$entity->broadcastAnimation(new ArmSwingAnimation($entity));
	}
}
