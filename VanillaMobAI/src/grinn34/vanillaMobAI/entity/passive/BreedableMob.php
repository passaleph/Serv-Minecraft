<?php

declare(strict_types=1);

namespace grinn34\vanillaMobAI\entity\passive;

use pocketmine\entity\Ageable;
use pocketmine\entity\Location;

interface BreedableMob extends PassiveMob, Ageable{
	public function isInLove() : bool;

	public function getLoveTicks() : int;

	public function canEnterLoveMode() : bool;

	public function canBreed() : bool;

	public function enterLoveMode() : void;

	public function finishBreeding() : void;

	public function setBaby(bool $baby) : void;

	public function spawnChild(Location $location) : self;
}
