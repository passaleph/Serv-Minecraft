<?php

declare(strict_types=1);

namespace grinn34\vanillaMobAI\goals;

use grinn34\vanillaMobAI\Goal;
use grinn34\vanillaMobAI\MobBrain;
use grinn34\vanillaMobAI\registry\MobBehaviorRegistry;
use function mt_rand;

final class LookAroundGoal implements Goal{
	private const PRIORITY = 8;

	private int $lookTicks = 0;
	private float $targetYaw = 0;

	public function getPriority() : int{
		return self::PRIORITY;
	}

	public function canUse(MobBrain $brain) : bool{
		return $brain->getPanicTicks() <= 0
			&& $brain->getActiveGoal() === null
			&& mt_rand(1, MobBehaviorRegistry::LOOK_AROUND_ROLL) === 1;
	}

	public function canContinue(MobBrain $brain) : bool{
		return $brain->getPanicTicks() <= 0 && $this->lookTicks > 0;
	}

	public function onStart(MobBrain $brain) : void{
		$entity = $brain->getEntity();
		$this->targetYaw = $entity->getLocation()->yaw + mt_rand(-60, 60);
		$this->lookTicks = mt_rand(
			MobBehaviorRegistry::LOOK_AROUND_MIN_TICKS,
			MobBehaviorRegistry::LOOK_AROUND_MAX_TICKS
		);
	}

	public function onStop(MobBrain $brain) : void{
		$this->lookTicks = 0;
	}

	public function tick(MobBrain $brain) : void{
		$entity = $brain->getEntity();
		$currentYaw = $entity->getLocation()->yaw;
		$delta = $this->targetYaw - $currentYaw;

		if(abs($delta) > 180){
			$delta -= $delta > 0 ? 360 : -360;
		}

		$entity->setRotation($currentYaw + max(-8, min(8, $delta)), 0);
		$this->lookTicks--;
	}

	public function tickAlways() : void{}
}
