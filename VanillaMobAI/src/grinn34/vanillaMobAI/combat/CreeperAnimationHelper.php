<?php

declare(strict_types=1);

namespace grinn34\vanillaMobAI\combat;

use grinn34\vanillaMobAI\entity\hostile\SwellingCapable;
use pocketmine\entity\Living;

final class CreeperAnimationHelper{
	private function __construct(){}

	public static function setSwelling(Living $entity, bool $swelling) : void{
		if($entity instanceof SwellingCapable){
			$entity->setSwelling($swelling);
		}
	}
}
