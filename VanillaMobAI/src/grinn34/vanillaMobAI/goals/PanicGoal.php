<?php

declare(strict_types=1);

namespace grinn34\vanillaMobAI\goals;

use grinn34\vanillaMobAI\Goal;
use grinn34\vanillaMobAI\MobBrain;
use grinn34\vanillaMobAI\movement\MovementHelper;
use grinn34\vanillaMobAI\movement\PathNavigation;
use grinn34\vanillaMobAI\pathfinding\BlockPathfinder;
use pocketmine\math\Vector3;

final class PanicGoal implements Goal{
	private const PRIORITY = 1;
	private const PANIC_SPEED_MULTIPLIER = 1.65;
	private const STUCK_TICKS_LIMIT = 8;
	private const REPATH_INTERVAL = 15;

	private ?PathNavigation $navigation = null;
	private ?Vector3 $fleeTarget = null;
	private int $stuckTicks = 0;
	private int $repathCooldown = 0;
	private ?Vector3 $lastPosition = null;

	public function getPriority() : int{
		return self::PRIORITY;
	}

	public function canUse(MobBrain $brain) : bool{
		return $brain->getPanicTicks() > 0 && $brain->getPanicSource() !== null;
	}

	public function canContinue(MobBrain $brain) : bool{
		return $brain->getPanicTicks() > 0;
	}

	public function onStart(MobBrain $brain) : void{
		$entity = $brain->getEntity();
		$entity->setSprinting(true);

		$this->navigation = new PathNavigation($entity);
		$this->navigation->setSpeedMultiplier(self::PANIC_SPEED_MULTIPLIER);
		$this->stuckTicks = 0;
		$this->repathCooldown = 0;
		$this->lastPosition = $entity->getPosition()->asVector3();

		$this->planFleeRoute($brain);
	}

	public function onStop(MobBrain $brain) : void{
		$brain->getEntity()->setSprinting(false);
		$this->navigation?->stop();
		$this->navigation = null;
		$this->fleeTarget = null;
		$this->stuckTicks = 0;
		$this->repathCooldown = 0;
		$this->lastPosition = null;
		MovementHelper::stopHorizontalMovement($brain->getEntity());
	}

	public function tick(MobBrain $brain) : void{
		if($this->navigation === null){
			return;
		}

		$entity = $brain->getEntity();
		$position = $entity->getPosition();

		if($this->lastPosition !== null && MovementHelper::horizontalDistanceSquared($this->lastPosition, $position) < 0.006){
			$this->stuckTicks++;
		}else{
			$this->stuckTicks = max(0, $this->stuckTicks - 1);
		}
		$this->lastPosition = $position->asVector3();

		if($this->repathCooldown > 0){
			$this->repathCooldown--;
		}

		if($this->stuckTicks >= self::STUCK_TICKS_LIMIT || (!$this->navigation->isActive() && $this->repathCooldown <= 0)){
			$this->planFleeRoute($brain);
			$this->stuckTicks = 0;
			$this->repathCooldown = self::REPATH_INTERVAL;
			return;
		}

		if(!$this->navigation->tick()){
			$this->stuckTicks++;
		}
	}

	public function tickAlways() : void{}

	private function planFleeRoute(MobBrain $brain) : void{
		$entity = $brain->getEntity();
		$source = $brain->getPanicSource();
		if($source === null || $this->navigation === null){
			return;
		}

		$this->fleeTarget = BlockPathfinder::findFleePosition(
			$entity->getWorld(),
			$entity->getPosition(),
			$source,
			14
		);

		if($this->fleeTarget === null){
			return;
		}

		if(!$this->navigation->setDestination($this->fleeTarget)){
			$this->stuckTicks = self::STUCK_TICKS_LIMIT;
		}
	}
}
