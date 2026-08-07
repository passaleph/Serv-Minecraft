<?php

declare(strict_types=1);

namespace grinn34\vanillaMobAI\goals;

use grinn34\vanillaMobAI\breeding\BreedingHelper;
use grinn34\vanillaMobAI\entity\passive\BreedableMob;
use grinn34\vanillaMobAI\Goal;
use grinn34\vanillaMobAI\MobBrain;
use grinn34\vanillaMobAI\movement\MovementHelper;
use grinn34\vanillaMobAI\movement\PathNavigation;
use grinn34\vanillaMobAI\pathfinding\BlockPathfinder;
use grinn34\vanillaMobAI\performance\PerformanceConfig;
use grinn34\vanillaMobAI\performance\TickThrottle;
use pocketmine\entity\Living;
use pocketmine\math\Vector3;

final class BreedGoal implements Goal{
	private const PRIORITY = 2;
	private const SEARCH_RANGE = 8.0;
	private const SPEED_MULTIPLIER = 1.0;
	private const REPATH_DISTANCE_SQ = 4.0;
	private ?PathNavigation $navigation = null;
	private ?BreedableMob $partner = null;
	private ?Vector3 $lastRepathPosition = null;

	public function getPriority() : int{
		return self::PRIORITY;
	}

	public function canUse(MobBrain $brain) : bool{
		if($brain->getPanicTicks() > 0){
			return false;
		}

		$entity = $brain->getEntity();
		if(!$entity instanceof BreedableMob || !$entity->canBreed()){
			return false;
		}

		return $this->findPartner($entity) !== null;
	}

	public function canContinue(MobBrain $brain) : bool{
		if($brain->getPanicTicks() > 0){
			return false;
		}

		$entity = $brain->getEntity();
		if(!$entity instanceof BreedableMob || !$entity->canBreed()){
			return false;
		}

		$partner = $this->partner;
		if($partner === null || !$partner->isAlive() || $partner->isClosed()){
			return false;
		}

		if($entity->getWorld() !== $partner->getWorld()){
			return false;
		}

		if(!$partner->canBreed()){
			return false;
		}

		$range = self::SEARCH_RANGE;
		return $entity->getPosition()->distanceSquared($partner->getPosition()) <= ($range * $range);
	}

	public function onStart(MobBrain $brain) : void{
		$entity = $brain->getEntity();
		if(!$entity instanceof BreedableMob){
			return;
		}

		$this->partner = $this->findPartner($entity);
		$this->navigation = new PathNavigation($entity);
		$this->navigation->setSpeedMultiplier(self::SPEED_MULTIPLIER);
		$this->lastRepathPosition = null;
	}

	public function onStop(MobBrain $brain) : void{
		$this->navigation?->stop();
		$this->navigation = null;
		$this->partner = null;
		$this->lastRepathPosition = null;
		MovementHelper::stopHorizontalMovement($brain->getEntity());
	}

	public function tick(MobBrain $brain) : void{
		$entity = $brain->getEntity();
		if(!$entity instanceof BreedableMob){
			return;
		}

		$partner = $this->partner;
		if($partner === null){
			return;
		}

		if(BreedingHelper::tryBreed($entity, $partner)){
			return;
		}

		$partnerPos = $partner->getPosition();
		$entityPos = $entity->getPosition();
		$world = $entity->getWorld();

		if(TickThrottle::every($entity->getId(), PerformanceConfig::walkLineCheckInterval()) && BlockPathfinder::hasWalkLine($world, $entityPos, $partnerPos)){
			MovementHelper::navigateTowards($entity, $partnerPos, self::SPEED_MULTIPLIER);
			$this->lastRepathPosition = $partnerPos->asVector3();
			return;
		}

		if($this->navigation === null){
			return;
		}

		if(
			$this->lastRepathPosition === null
			|| MovementHelper::horizontalDistanceSquared($this->lastRepathPosition, $partnerPos) > self::REPATH_DISTANCE_SQ
			|| !$this->navigation->isActive()
		){
			$this->navigation->setDestination($partnerPos);
			$this->lastRepathPosition = $partnerPos->asVector3();
		}

		if($this->navigation->isActive()){
			$this->navigation->tick();
		}else{
			MovementHelper::navigateTowards($entity, $partnerPos, self::SPEED_MULTIPLIER);
		}
	}

	public function tickAlways() : void{}

	private function findPartner(Living $entity) : ?BreedableMob{
		if(!$entity instanceof BreedableMob){
			return null;
		}

		$world = $entity->getWorld();
		$range = self::SEARCH_RANGE;
		$rangeSq = $range * $range;

		$nearest = null;
		$nearestDist = $rangeSq;

		foreach($world->getNearbyEntities($entity->getBoundingBox()->expandedCopy($range, $range, $range)) as $nearby){
			if($nearby === $entity || $nearby::class !== $entity::class){
				continue;
			}

			if(!$nearby instanceof BreedableMob || !$nearby->canBreed()){
				continue;
			}

			$dist = $entity->getPosition()->distanceSquared($nearby->getPosition());
			if($dist <= $nearestDist){
				$nearestDist = $dist;
				$nearest = $nearby;
			}
		}

		return $nearest;
	}
}
