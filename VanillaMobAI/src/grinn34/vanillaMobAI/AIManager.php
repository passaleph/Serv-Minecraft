<?php

declare(strict_types=1);

namespace grinn34\vanillaMobAI;

use grinn34\vanillaMobAI\config\PluginSettings;
use grinn34\vanillaMobAI\pathfinding\PathfindingService;
use grinn34\vanillaMobAI\performance\MobActivationHelper;
use grinn34\vanillaMobAI\performance\PerformanceConfig;
use grinn34\vanillaMobAI\performance\TickThrottle;
use pocketmine\entity\Entity;
use pocketmine\entity\Living;
use pocketmine\plugin\Plugin;
use pocketmine\scheduler\ClosureTask;
use pocketmine\scheduler\TaskHandler;
use WeakMap;

final class AIManager{
	/** @var WeakMap<Living, MobBrain> */
	private WeakMap $brains;

	private ?TaskHandler $taskHandler = null;
	private int $globalTick = 0;
	private int $lastTickedBrains = 0;
	private int $lastSkippedStagger = 0;
	private int $lastSkippedBudget = 0;

	public function __construct(){
		$this->brains = new WeakMap();
	}

	public function start(Plugin $plugin) : void{
		PathfindingService::init($plugin);

		$this->taskHandler = $plugin->getScheduler()->scheduleRepeatingTask(
			new ClosureTask(function() : void{
				$this->tickAll();
			}),
			1
		);
	}

	public function shutdown() : void{
		$this->taskHandler?->cancel();
		$this->taskHandler = null;
		PathfindingService::reset();
	}

	public function register(Living $entity, MobBrain $brain) : void{
		$this->brains[$entity] = $brain;
	}

	public function unregister(Entity $entity) : void{
		if($entity instanceof Living && isset($this->brains[$entity])){
			unset($this->brains[$entity]);
		}
	}

	public function hasBrain(Living $entity) : bool{
		return isset($this->brains[$entity]);
	}

	public function getBrain(Living $entity) : ?MobBrain{
		return $this->brains[$entity] ?? null;
	}

	public function getTrackedCount() : int{
		$count = 0;
		foreach($this->brains as $_){
			$count++;
		}
		return $count;
	}

	public function getGlobalTick() : int{
		return $this->globalTick;
	}

	public function getLastTickedBrains() : int{
		return $this->lastTickedBrains;
	}

	public function getLastSkippedStagger() : int{
		return $this->lastSkippedStagger;
	}

	public function getLastSkippedBudget() : int{
		return $this->lastSkippedBudget;
	}

	private function tickAll() : void{
		if(!PluginSettings::get()->isMobAiEnabled()){
			return;
		}

		$this->globalTick++;
		TickThrottle::setGlobalTick($this->globalTick);
		PathfindingService::get()->beginTick();

		$processed = 0;
		$skippedStagger = 0;
		$skippedBudget = 0;

		foreach($this->brains as $brain){
			$entity = $brain->getEntity();
			if(!$entity->isAlive() || $entity->isClosed()){
				continue;
			}

			$activated = MobActivationHelper::isActivated($entity);
			$interval = $activated
				? ($brain->isHostile()
					? PerformanceConfig::activeHostileTickInterval()
					: PerformanceConfig::activePassiveTickInterval())
				: PerformanceConfig::inactiveTickInterval();

			if(($this->globalTick + $entity->getId()) % $interval !== 0){
				$skippedStagger++;
				continue;
			}

			if($processed >= PerformanceConfig::maxBrainsPerTick()){
				$skippedBudget++;
				continue;
			}

			$brain->tick($activated);
			$processed++;
		}

		$this->lastTickedBrains = $processed;
		$this->lastSkippedStagger = $skippedStagger;
		$this->lastSkippedBudget = $skippedBudget;
	}
}
