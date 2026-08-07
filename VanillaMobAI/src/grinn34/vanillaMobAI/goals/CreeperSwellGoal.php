<?php

declare(strict_types=1);

namespace grinn34\vanillaMobAI\goals;

use grinn34\vanillaMobAI\combat\CreeperAnimationHelper;
use grinn34\vanillaMobAI\combat\CombatHelper;
use grinn34\vanillaMobAI\combat\ExplosionHelper;
use grinn34\vanillaMobAI\Goal;
use grinn34\vanillaMobAI\MobBrain;
use grinn34\vanillaMobAI\movement\MovementHelper;
use grinn34\vanillaMobAI\movement\PathNavigation;
use grinn34\vanillaMobAI\pathfinding\BlockPathfinder;
use grinn34\vanillaMobAI\performance\PerformanceConfig;
use grinn34\vanillaMobAI\performance\TickThrottle;
use grinn34\vanillaMobAI\registry\MobCombatRegistry;
use pocketmine\entity\Living;
use pocketmine\math\Vector3;

final class CreeperSwellGoal implements Goal{
	private const PRIORITY = 1;
	private const SPEED_MULTIPLIER = 1.0;
	private const REPATH_DISTANCE_SQ = 4.0;
	private const SWELL_ABORT_RANGE_MULTIPLIER = 2.5;

	private ?Living $target = null;
	private ?PathNavigation $navigation = null;
	private ?Vector3 $lastRepathPosition = null;
	private int $fuseTicks = 0;
	private bool $swelling = false;

	public function getPriority() : int{
		return self::PRIORITY;
	}

	public function canUse(MobBrain $brain) : bool{
		$entity = $brain->getEntity();
		$target = $brain->getAttackTarget();
		$followRange = MobCombatRegistry::getFollowRange($entity);

		if(
			$target !== null
			&& CombatHelper::isValidTarget($entity, $target, $followRange)
			&& ($brain->getLineOfSightTo($target) || $brain->getTargetUnseenMemoryTicks() > 0)
		){
			return true;
		}

		if(!$brain->canScanForTargets()){
			return false;
		}

		$target = CombatHelper::findNearestPlayer($entity, MobCombatRegistry::getDetectionRange($entity));
		if($target === null){
			return false;
		}

		$brain->setAttackTarget($target);
		return true;
	}

	public function canContinue(MobBrain $brain) : bool{
		$target = $this->target ?? $brain->getAttackTarget();
		if($target === null){
			return false;
		}

		return CombatHelper::isValidTarget(
			$brain->getEntity(),
			$target,
			MobCombatRegistry::getFollowRange($brain->getEntity())
		) && ($brain->getLineOfSightTo($target) || $brain->getTargetUnseenMemoryTicks() > 0);
	}

	public function onStart(MobBrain $brain) : void{
		$entity = $brain->getEntity();
		$this->target = $brain->getAttackTarget();
		$this->navigation = new PathNavigation($entity);
		$this->navigation->setSpeedMultiplier(self::SPEED_MULTIPLIER);
		$this->lastRepathPosition = null;
		$this->fuseTicks = 0;
		$this->swelling = false;
	}

	public function onStop(MobBrain $brain) : void{
		$entity = $brain->getEntity();
		$this->navigation?->stop();
		$this->navigation = null;
		$this->target = null;
		$this->lastRepathPosition = null;
		$this->fuseTicks = 0;
		$this->stopSwell($entity);
		$brain->setAttackTarget(null);
		MovementHelper::stopHorizontalMovement($entity);
	}

	public function tick(MobBrain $brain) : void{
		$entity = $brain->getEntity();
		$target = $this->target;

		if($target === null){
			return;
		}

		$targetPos = $target->getPosition();
		$entityPos = $entity->getPosition();
		$world = $entity->getWorld();
		$swellDistance = MobCombatRegistry::getSwellDistance($entity);
		$swellDistanceSq = $swellDistance * $swellDistance;
		$hasLineOfSight = $brain->getLineOfSightTo($target);
		$brain->tickTargetVisibility($hasLineOfSight);

		if($this->swelling){
			$abortRange = $swellDistance * self::SWELL_ABORT_RANGE_MULTIPLIER;
			if($entityPos->distanceSquared($targetPos) > ($abortRange * $abortRange)){
				$this->stopSwell($entity);
			}else{
				MovementHelper::lookAt($entity, $targetPos);
				MovementHelper::stopHorizontalMovement($entity);
				$this->fuseTicks--;

				if($this->fuseTicks <= 0){
					ExplosionHelper::explode($entity);
					return;
				}
			}

			return;
		}

		MovementHelper::lookAt($entity, $targetPos);

		if($entityPos->distanceSquared($targetPos) <= $swellDistanceSq && $hasLineOfSight){
			$this->startSwell($entity);
			MovementHelper::stopHorizontalMovement($entity);
			return;
		}

		if(TickThrottle::every($entity->getId(), PerformanceConfig::walkLineCheckInterval()) && BlockPathfinder::hasWalkLine($world, $entityPos, $targetPos)){
			MovementHelper::navigateTowards($entity, $targetPos, self::SPEED_MULTIPLIER);
			$this->lastRepathPosition = $targetPos->asVector3();
			return;
		}

		if($this->navigation === null){
			return;
		}

		if(
			$this->lastRepathPosition === null
			|| MovementHelper::horizontalDistanceSquared($this->lastRepathPosition, $targetPos) > self::REPATH_DISTANCE_SQ
			|| !$this->navigation->isActive()
		){
			$this->navigation->setDestination($targetPos);
			$this->lastRepathPosition = $targetPos->asVector3();
		}

		if($this->navigation->isActive()){
			$this->navigation->tick();
		}else{
			MovementHelper::navigateTowards($entity, $targetPos, self::SPEED_MULTIPLIER);
		}
	}

	public function tickAlways() : void{}

	private function startSwell(Living $entity) : void{
		$this->swelling = true;
		$this->fuseTicks = MobCombatRegistry::getFuseTicks($entity);
		CreeperAnimationHelper::setSwelling($entity, true);
	}

	private function stopSwell(Living $entity) : void{
		$this->swelling = false;
		$this->fuseTicks = 0;
		CreeperAnimationHelper::setSwelling($entity, false);
	}
}
