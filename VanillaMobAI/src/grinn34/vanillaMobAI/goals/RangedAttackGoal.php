<?php

declare(strict_types=1);

namespace grinn34\vanillaMobAI\goals;

use grinn34\vanillaMobAI\combat\CombatAnimationHelper;
use grinn34\vanillaMobAI\combat\CombatHelper;
use grinn34\vanillaMobAI\combat\RangedCombatHelper;
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

final class RangedAttackGoal implements Goal{
	private const PRIORITY = 1;
	private const CHASE_SPEED = 1.0;
	private const REPATH_DISTANCE_SQ = 4.0;

	private ?Living $target = null;
	private ?PathNavigation $navigation = null;
	private ?Vector3 $lastRepathPosition = null;
	private int $attackCooldown = 0;
	private int $windupTicks = 0;

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
		$this->navigation->setSpeedMultiplier(self::CHASE_SPEED);
		$this->lastRepathPosition = null;
		$this->attackCooldown = 0;
		$this->windupTicks = 0;
		CombatAnimationHelper::setAggressivePose($entity, true);
	}

	public function onStop(MobBrain $brain) : void{
		$entity = $brain->getEntity();
		$this->navigation?->stop();
		$this->navigation = null;
		$this->target = null;
		$this->lastRepathPosition = null;
		$this->attackCooldown = 0;
		$this->windupTicks = 0;
		$brain->setAttackTarget(null);
		CombatAnimationHelper::setAggressivePose($entity, false);
		MovementHelper::stopHorizontalMovement($entity, true);
	}

	public function tick(MobBrain $brain) : void{
		$entity = $brain->getEntity();
		$target = $this->target;

		if($target === null){
			return;
		}

		if($this->attackCooldown > 0){
			$this->attackCooldown--;
		}

		$targetPos = $target->getPosition();
		$entityPos = $entity->getPosition();
		$world = $entity->getWorld();
		$attackRange = MobCombatRegistry::getAttackRange($entity);
		$attackRangeSq = $attackRange * $attackRange;
		$horizontalDistSq = MovementHelper::horizontalDistanceSquared($entityPos, $targetPos);
		$hasLineOfSight = $brain->getLineOfSightTo($target);
		$brain->tickTargetVisibility($hasLineOfSight);

		MovementHelper::lookAt($entity, $target->getEyePos(), true);

		if($horizontalDistSq <= $attackRangeSq && $hasLineOfSight){
			$this->navigation?->stop();
			MovementHelper::stopHorizontalMovement($entity, true);

			if($this->attackCooldown > 0){
				return;
			}

			if($this->windupTicks <= 0){
				$this->windupTicks = MobCombatRegistry::getAttackWindupTicks($entity);
				return;
			}

			$this->windupTicks--;
			if($this->windupTicks > 0){
				return;
			}

			RangedCombatHelper::shootArrow($entity);
			$this->attackCooldown = MobCombatRegistry::rollAttackCooldown($entity);
			return;
		}

		$this->windupTicks = 0;
		$this->chaseTarget($entity, $targetPos);
	}

	private function chaseTarget(Living $entity, Vector3 $targetPos) : void{
		$world = $entity->getWorld();
		$entityPos = $entity->getPosition();

		if(TickThrottle::every($entity->getId(), PerformanceConfig::walkLineCheckInterval()) && BlockPathfinder::hasWalkLine($world, $entityPos, $targetPos)){
			MovementHelper::navigateTowards($entity, $targetPos, self::CHASE_SPEED);
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
			MovementHelper::navigateTowards($entity, $targetPos, self::CHASE_SPEED);
		}
	}

	public function tickAlways() : void{}
}
