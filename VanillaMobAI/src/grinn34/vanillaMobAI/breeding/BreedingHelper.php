<?php

declare(strict_types=1);

namespace grinn34\vanillaMobAI\breeding;

use grinn34\vanillaMobAI\entity\passive\BreedableMob;
use grinn34\vanillaMobAI\entity\passive\Sheep;
use pocketmine\entity\object\ExperienceOrb;
use pocketmine\entity\Location;
use pocketmine\math\Vector3;
use pocketmine\world\particle\HeartParticle;
use function mt_rand;

final class BreedingHelper{
	private const BREED_DISTANCE_SQ = 6.25;

	private function __construct(){}

	public static function tryBreed(BreedableMob $first, BreedableMob $second) : bool{
		if($first === $second){
			return false;
		}

		if($first::class !== $second::class){
			return false;
		}

		if(!$first->isInLove() || !$second->isInLove()){
			return false;
		}

		if($first->isBaby() || $second->isBaby()){
			return false;
		}

		if($first->getId() > $second->getId()){
			return false;
		}

		$firstEntity = $first;
		$secondEntity = $second;

		if(!$firstEntity->isAlive() || !$secondEntity->isAlive()){
			return false;
		}

		$firstPos = $firstEntity->getPosition();
		$secondPos = $secondEntity->getPosition();

		if($firstPos->distanceSquared($secondPos) > self::BREED_DISTANCE_SQ){
			return false;
		}

		$world = $firstEntity->getWorld();
		if($world !== $secondEntity->getWorld()){
			return false;
		}

		$childLocation = self::createChildLocation($firstEntity, $secondEntity);
		$child = $first->spawnChild($childLocation);
		if($child instanceof Sheep && $firstEntity instanceof Sheep && $secondEntity instanceof Sheep){
			$child->inheritCoatFrom($firstEntity, $secondEntity);
		}
		$child->spawnToAll();

		$first->finishBreeding();
		$second->finishBreeding();

		self::spawnHeartsAt($world, $firstEntity->getPosition()->add(0, $firstEntity->getSize()->getHeight() * 0.8, 0));
		self::spawnHeartsAt($world, $secondEntity->getPosition()->add(0, $secondEntity->getSize()->getHeight() * 0.8, 0));
		self::spawnHeartsAt($world, $child->getPosition()->add(0, $child->getSize()->getHeight() * 0.5, 0));

		$experience = mt_rand(1, 7);
		if($experience > 0){
			$orb = new ExperienceOrb($childLocation, $experience, null);
			$orb->spawnToAll();
		}

		return true;
	}

	public static function spawnHeartsAt(\pocketmine\world\World $world, Vector3 $position) : void{
		$world->addParticle($position, new HeartParticle());
	}

	private static function createChildLocation(BreedableMob $first, BreedableMob $second) : Location{
		$firstPos = $first->getPosition();
		$secondPos = $second->getPosition();
		$firstLocation = $first->getLocation();
		$world = $first->getWorld();

		$x = ($firstPos->x + $secondPos->x) / 2;
		$y = max($firstPos->y, $secondPos->y);
		$z = ($firstPos->z + $secondPos->z) / 2;

		return new Location($x, $y, $z, $world, $firstLocation->yaw, 0);
	}
}
