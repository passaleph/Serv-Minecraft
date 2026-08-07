<?php

declare(strict_types=1);

namespace grinn34\vanillaMobAI;

use grinn34\vanillaMobAI\combat\CombatHelper;
use grinn34\vanillaMobAI\entity\hostile\HostileMob;
use grinn34\vanillaMobAI\movement\MovementPause;
use grinn34\vanillaMobAI\performance\PerformanceConfig;
use grinn34\vanillaMobAI\registry\MobCombatRegistry;
use pocketmine\entity\Living;
use pocketmine\math\Vector3;

final class MobBrain{
	private const PANIC_PRIORITY = 1;

	/** @var Goal[] */
	private array $goals = [];

	private ?Goal $activeGoal = null;
	private int $panicTicks = 0;
	private ?Vector3 $panicSource = null;
	private ?Living $attackTarget = null;
	private int $targetUnseenMemoryTicks = 0;
	private int $targetScanCooldown = 0;
	private int $losCheckCooldown = 0;
	private ?bool $cachedLineOfSight = null;
	private ?Living $cachedLosTarget = null;

	public function __construct(
		private readonly Living $entity
	){}

	public function getEntity() : Living{
		return $this->entity;
	}

	public function isHostile() : bool{
		return $this->entity instanceof HostileMob;
	}

	public function addGoal(Goal $goal) : void{
		$this->goals[] = $goal;
		usort($this->goals, static fn(Goal $a, Goal $b) => $a->getPriority() <=> $b->getPriority());
	}

	public function tick(bool $activated = true) : void{
		if(!$this->entity->isAlive() || $this->entity->isClosed()){
			return;
		}

		if(!$activated){
			if($this->panicTicks > 0){
				$this->panicTicks--;
			}
			$this->validateAttackTarget($activated);
			return;
		}

		MovementPause::tick($this->entity);

		if($this->panicTicks > 0){
			$this->panicTicks--;
		}

		$this->interruptForPanic();
		$this->validateAttackTarget($activated);
		$this->interruptForHigherPriorityGoals();

		if($this->activeGoal !== null && !$this->activeGoal->canContinue($this)){
			$this->activeGoal->onStop($this);
			$this->activeGoal = null;
		}

		if($this->activeGoal === null){
			foreach($this->goals as $goal){
				if($goal->canUse($this)){
					$this->activeGoal = $goal;
					$goal->onStart($this);
					break;
				}
			}
		}

		foreach($this->goals as $goal){
			$goal->tickAlways();
		}

		$this->activeGoal?->tick($this);
	}

	public function canScanForTargets() : bool{
		if($this->targetScanCooldown > 0){
			$this->targetScanCooldown--;
			return false;
		}

		$this->targetScanCooldown = PerformanceConfig::targetScanInterval();
		return true;
	}

	public function getLineOfSightTo(Living $target) : bool{
		if($this->cachedLosTarget !== $target){
			$this->cachedLosTarget = $target;
			$this->cachedLineOfSight = null;
			$this->losCheckCooldown = 0;
		}

		if($this->losCheckCooldown <= 0){
			$this->cachedLineOfSight = CombatHelper::hasLineOfSight($this->entity, $target);
			$this->losCheckCooldown = PerformanceConfig::losCheckInterval();
		}else{
			$this->losCheckCooldown--;
		}

		return $this->cachedLineOfSight ?? false;
	}

	public function triggerPanic(Vector3 $source, int $duration = 140) : void{
		$this->panicTicks = max($this->panicTicks, $duration);
		$this->panicSource = $source;

		if($this->activeGoal !== null && $this->activeGoal->getPriority() >= self::PANIC_PRIORITY){
			$this->activeGoal->onStop($this);
			$this->activeGoal = null;
		}
	}

	public function getPanicTicks() : int{
		return $this->panicTicks;
	}

	public function getPanicSource() : ?Vector3{
		return $this->panicSource;
	}

	private function interruptForPanic() : void{
		if($this->panicTicks <= 0 || $this->activeGoal === null){
			return;
		}

		if($this->activeGoal->getPriority() > self::PANIC_PRIORITY){
			$this->activeGoal->onStop($this);
			$this->activeGoal = null;
		}
	}

	public function getActiveGoal() : ?Goal{
		return $this->activeGoal;
	}

	/** @return Goal[] */
	public function getGoals() : array{
		return $this->goals;
	}

	public function setAttackTarget(?Living $target) : void{
		$this->attackTarget = $target;
		if($target === null){
			$this->targetUnseenMemoryTicks = 0;
			$this->cachedLosTarget = null;
			$this->cachedLineOfSight = null;
			return;
		}

		$this->targetUnseenMemoryTicks = MobCombatRegistry::getUnseenMemoryTicks($this->entity);
	}

	public function getTargetUnseenMemoryTicks() : int{
		return $this->targetUnseenMemoryTicks;
	}

	public function tickTargetVisibility(bool $hasLineOfSight) : void{
		if($this->attackTarget === null){
			return;
		}

		if($hasLineOfSight){
			$this->targetUnseenMemoryTicks = MobCombatRegistry::getUnseenMemoryTicks($this->entity);
		}elseif($this->targetUnseenMemoryTicks > 0){
			$this->targetUnseenMemoryTicks--;
		}
	}

	public function getAttackTarget() : ?Living{
		return $this->attackTarget;
	}

	public function hasAttackTarget() : bool{
		return $this->attackTarget !== null;
	}

	public function pauseMovement(int $ticks = 8) : void{
		MovementPause::activate($this->entity, $ticks);
	}

	private function validateAttackTarget(bool $activated = true) : void{
		if($this->attackTarget === null){
			return;
		}

		$followRange = MobCombatRegistry::getFollowRange($this->entity);
		$valid = $activated
			? (CombatHelper::isValidTarget($this->entity, $this->attackTarget, $followRange)
				&& ($this->getLineOfSightTo($this->attackTarget) || $this->targetUnseenMemoryTicks > 0))
			: CombatHelper::isValidTarget($this->entity, $this->attackTarget, $followRange);

		if(!$valid){
			$this->attackTarget = null;
			$this->targetUnseenMemoryTicks = 0;
			$this->cachedLosTarget = null;
			$this->cachedLineOfSight = null;
		}
	}

	private function interruptForHigherPriorityGoals() : void{
		if($this->activeGoal === null){
			return;
		}

		foreach($this->goals as $goal){
			if($goal === $this->activeGoal){
				continue;
			}

			if($goal->getPriority() < $this->activeGoal->getPriority() && $goal->canUse($this)){
				$this->activeGoal->onStop($this);
				$this->activeGoal = null;
				break;
			}
		}
	}
}
