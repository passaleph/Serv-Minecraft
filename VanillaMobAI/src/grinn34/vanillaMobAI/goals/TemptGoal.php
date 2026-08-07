<?php

declare(strict_types=1);

namespace grinn34\vanillaMobAI\goals;

use grinn34\vanillaMobAI\entity\passive\BreedableMob;
use grinn34\vanillaMobAI\Goal;
use grinn34\vanillaMobAI\MobBrain;
use grinn34\vanillaMobAI\movement\MovementHelper;
use grinn34\vanillaMobAI\movement\PathNavigation;
use grinn34\vanillaMobAI\pathfinding\BlockPathfinder;
use grinn34\vanillaMobAI\performance\PerformanceConfig;
use grinn34\vanillaMobAI\performance\TickThrottle;
use grinn34\vanillaMobAI\registry\MobTemptRegistry;
use pocketmine\entity\Living;
use pocketmine\math\Vector3;
use pocketmine\player\Player;

final class TemptGoal implements Goal{
	private const PRIORITY = 3;
	private const STOP_DISTANCE_SQ = 2.25;
	private const SPEED_MULTIPLIER = 1.0;
	private const REPATH_DISTANCE_SQ = 4.0;

	private ?PathNavigation $navigation = null;
	private ?Player $targetPlayer = null;
	private ?Vector3 $lastRepathPosition = null;

	public function getPriority() : int{
		return self::PRIORITY;
	}

	public function canUse(MobBrain $brain) : bool{
		if($brain->getPanicTicks() > 0){
			return false;
		}

		$entity = $brain->getEntity();
		if($entity instanceof BreedableMob && $entity->isInLove()){
			return false;
		}

		return $this->findTemptingPlayer($entity) !== null;
	}

	public function canContinue(MobBrain $brain) : bool{
		if($brain->getPanicTicks() > 0){
			return false;
		}

		$player = $this->targetPlayer;
		if($player === null || !$player->isConnected() || $player->isClosed()){
			return false;
		}

		$entity = $brain->getEntity();
		if($entity instanceof BreedableMob && $entity->isInLove()){
			return false;
		}

		if($entity->getWorld() !== $player->getWorld()){
			return false;
		}

		if(!MobTemptRegistry::isTemptItem($entity, $player->getInventory()->getItemInHand())){
			return false;
		}

		$range = MobTemptRegistry::getContinueRange();
		return $entity->getPosition()->distanceSquared($player->getPosition()) <= ($range * $range);
	}

	public function onStart(MobBrain $brain) : void{
		$this->targetPlayer = $this->findTemptingPlayer($brain->getEntity());
		$this->navigation = new PathNavigation($brain->getEntity());
		$this->navigation->setSpeedMultiplier(self::SPEED_MULTIPLIER);
		$this->lastRepathPosition = null;
	}

	public function onStop(MobBrain $brain) : void{
		$this->navigation?->stop();
		$this->navigation = null;
		$this->targetPlayer = null;
		$this->lastRepathPosition = null;
		MovementHelper::stopHorizontalMovement($brain->getEntity());
	}

	public function tick(MobBrain $brain) : void{
		if($this->targetPlayer === null){
			return;
		}

		$entity = $brain->getEntity();
		$playerPos = $this->targetPlayer->getPosition();
		$entityPos = $entity->getPosition();
		$world = $entity->getWorld();

		if(MovementHelper::horizontalDistanceSquared($entityPos, $playerPos) <= self::STOP_DISTANCE_SQ){
			MovementHelper::lookAt($entity, $playerPos);
			MovementHelper::stopHorizontalMovement($entity);
			return;
		}

		if(TickThrottle::every($entity->getId(), PerformanceConfig::walkLineCheckInterval()) && BlockPathfinder::hasWalkLine($world, $entityPos, $playerPos)){
			MovementHelper::navigateTowards($entity, $playerPos, self::SPEED_MULTIPLIER);
			$this->lastRepathPosition = $playerPos->asVector3();
			return;
		}

		if($this->navigation === null){
			return;
		}

		if(
			$this->lastRepathPosition === null
			|| MovementHelper::horizontalDistanceSquared($this->lastRepathPosition, $playerPos) > self::REPATH_DISTANCE_SQ
			|| !$this->navigation->isActive()
		){
			$this->navigation->setDestination($playerPos);
			$this->lastRepathPosition = $playerPos->asVector3();
		}

		if($this->navigation->isActive()){
			$this->navigation->tick();
		}else{
			MovementHelper::navigateTowards($entity, $playerPos, self::SPEED_MULTIPLIER);
		}
	}

	public function tickAlways() : void{}

	private function findTemptingPlayer(Living $entity) : ?Player{
		$world = $entity->getWorld();
		$range = MobTemptRegistry::getTemptRange();
		$rangeSq = $range * $range;

		$nearest = null;
		$nearestDist = $rangeSq;

		foreach($world->getPlayers() as $player){
			if(!$player->isConnected() || $player->isClosed()){
				continue;
			}

			if(!MobTemptRegistry::isTemptItem($entity, $player->getInventory()->getItemInHand())){
				continue;
			}

			$dist = $entity->getPosition()->distanceSquared($player->getPosition());
			if($dist <= $nearestDist){
				$nearestDist = $dist;
				$nearest = $player;
			}
		}

		return $nearest;
	}
}
