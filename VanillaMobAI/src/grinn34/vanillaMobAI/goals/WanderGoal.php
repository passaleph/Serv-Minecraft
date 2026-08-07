<?php

declare(strict_types=1);

namespace grinn34\vanillaMobAI\goals;

use grinn34\vanillaMobAI\Goal;
use grinn34\vanillaMobAI\MobBrain;
use grinn34\vanillaMobAI\movement\MovementHelper;
use grinn34\vanillaMobAI\movement\PathNavigation;
use grinn34\vanillaMobAI\registry\MobBehaviorRegistry;
use grinn34\vanillaMobAI\registry\MobTemptRegistry;
use pocketmine\math\Vector3;
use pocketmine\player\Player;

final class WanderGoal implements Goal{
	private const PRIORITY = 7;
	private const REACHED_DISTANCE_SQ = 1.0;
	private const STUCK_TICKS_LIMIT = 12;

	private ?Vector3 $target = null;
	private ?PathNavigation $navigation = null;
	private int $ticksRemaining = 0;
	private int $stuckTicks = 0;
	private ?Vector3 $lastPosition = null;

	public function getPriority() : int{
		return self::PRIORITY;
	}

	public function canUse(MobBrain $brain) : bool{
		return $brain->getPanicTicks() <= 0
			&& !$brain->hasAttackTarget()
			&& !$this->isTempted($brain)
			&& mt_rand(1, MobBehaviorRegistry::STROLL_INTERVAL) === 1;
	}

	public function canContinue(MobBrain $brain) : bool{
		if($brain->getPanicTicks() > 0){
			return false;
		}

		if($this->target === null || $this->ticksRemaining <= 0){
			return false;
		}

		return MovementHelper::horizontalDistanceSquared($brain->getEntity()->getPosition(), $this->target) > self::REACHED_DISTANCE_SQ;
	}

	public function onStart(MobBrain $brain) : void{
		$entity = $brain->getEntity();
		$this->target = MovementHelper::findRandomWalkablePosition(
			$entity->getPosition(),
			$entity->getWorld(),
			MobBehaviorRegistry::STROLL_XZ_DIST,
			12,
			MobBehaviorRegistry::STROLL_Y_DIST
		);

		if($this->target === null){
			$this->ticksRemaining = 0;
			return;
		}

		$this->navigation = new PathNavigation($entity);
		if(!$this->navigation->setDestination($this->target)){
			$this->ticksRemaining = 0;
			return;
		}
		$this->ticksRemaining = MobBehaviorRegistry::STROLL_MAX_TICKS;
		$this->stuckTicks = 0;
		$this->lastPosition = $entity->getPosition()->asVector3();
	}

	public function onStop(MobBrain $brain) : void{
		$this->navigation?->stop();
		$this->navigation = null;
		$this->target = null;
		$this->ticksRemaining = 0;
		$this->stuckTicks = 0;
		$this->lastPosition = null;
	}

	public function tick(MobBrain $brain) : void{
		if($this->target === null || $this->navigation === null){
			return;
		}

		$entity = $brain->getEntity();
		$position = $entity->getPosition();

		if($this->lastPosition !== null && MovementHelper::horizontalDistanceSquared($this->lastPosition, $position) < 0.004){
			$this->stuckTicks++;
		}else{
			$this->stuckTicks = max(0, $this->stuckTicks - 1);
		}
		$this->lastPosition = $position->asVector3();

		if($this->stuckTicks >= self::STUCK_TICKS_LIMIT){
			$this->ticksRemaining = 0;
			return;
		}

		$this->ticksRemaining--;

		if(!$this->navigation->tick()){
			$this->stuckTicks++;
		}
	}

	public function tickAlways() : void{}

	private function isTempted(MobBrain $brain) : bool{
		$entity = $brain->getEntity();
		$rangeSq = MobTemptRegistry::getContinueRange() ** 2;

		foreach($entity->getWorld()->getPlayers() as $player){
			if(!$player instanceof Player || !$player->isConnected()){
				continue;
			}

			if(!MobTemptRegistry::isTemptItem($entity, $player->getInventory()->getItemInHand())){
				continue;
			}

			if($entity->getPosition()->distanceSquared($player->getPosition()) <= $rangeSq){
				return true;
			}
		}

		return false;
	}
}
