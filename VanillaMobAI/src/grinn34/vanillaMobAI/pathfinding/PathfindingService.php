<?php

declare(strict_types=1);

namespace grinn34\vanillaMobAI\pathfinding;

use grinn34\vanillaMobAI\performance\PerformanceConfig;
use pocketmine\math\Vector3;
use pocketmine\plugin\Plugin;
use pocketmine\world\World;
use function count;

final class PathfindingService{
	private static ?self $instance = null;

	private int $globalTick = 0;
	private int $syncPathfindsThisTick = 0;
	private int $nextRequestId = 1;

	/** @var array<int, \Closure(Vector3[]): void> */
	private array $pendingCallbacks = [];

	/** @var array<int, array{world: World, start: Vector3, end: Vector3, callback: \Closure(Vector3[]): void}> */
	private array $syncQueue = [];

	private int $asyncInFlight = 0;

	public function __construct(
		private readonly Plugin $plugin
	){}

	public static function init(Plugin $plugin) : self{
		self::$instance = new self($plugin);
		return self::$instance;
	}

	public static function get() : self{
		if(self::$instance === null){
			throw new \RuntimeException("PathfindingService is not initialized");
		}
		return self::$instance;
	}

	public static function reset() : void{
		self::$instance = null;
	}

	public function beginTick() : void{
		$this->globalTick++;
		$this->syncPathfindsThisTick = 0;
		$this->drainSyncQueue();
	}

	public function getGlobalTick() : int{
		return $this->globalTick;
	}

	public function getSyncPathfindsThisTick() : int{
		return $this->syncPathfindsThisTick;
	}

	public function getAsyncInFlight() : int{
		return $this->asyncInFlight;
	}

	public function getPendingSyncQueueSize() : int{
		return count($this->syncQueue);
	}

	/**
	 * @param \Closure(Vector3[]): void $onComplete
	 */
	public function requestPath(World $world, Vector3 $start, Vector3 $end, \Closure $onComplete) : void{
		if(BlockPathfinder::hasWalkLine($world, $start, $end)){
			$onComplete([$end]);
			return;
		}

		if($this->syncPathfindsThisTick < PerformanceConfig::syncPathfindsPerTick()){
			$this->runSyncPathfind($world, $start, $end, $onComplete);
			return;
		}

		if(count($this->syncQueue) < PerformanceConfig::pathfindQueueSize()){
			$this->syncQueue[] = [
				"world" => $world,
				"start" => $start->asVector3(),
				"end" => $end->asVector3(),
				"callback" => $onComplete,
			];
			return;
		}

		$this->runAsyncPathfind($world, $start, $end, $onComplete);
	}

	private function drainSyncQueue() : void{
		while($this->syncQueue !== [] && $this->syncPathfindsThisTick < PerformanceConfig::syncPathfindsPerTick()){
			$request = array_shift($this->syncQueue);
			$this->runSyncPathfind($request["world"], $request["start"], $request["end"], $request["callback"]);
		}
	}

	/**
	 * @param \Closure(Vector3[]): void $onComplete
	 */
	private function runSyncPathfind(World $world, Vector3 $start, Vector3 $end, \Closure $onComplete) : void{
		$this->syncPathfindsThisTick++;
		$onComplete(BlockPathfinder::findPath($world, $start, $end));
	}

	/**
	 * @param \Closure(Vector3[]): void $onComplete
	 */
	private function runAsyncPathfind(World $world, Vector3 $start, Vector3 $end, \Closure $onComplete) : void{
		$midpoint = new Vector3(
			($start->x + $end->x) * 0.5,
			($start->y + $end->y) * 0.5,
			($start->z + $end->z) * 0.5
		);

		$grid = TraversabilityGrid::capture(
			$world,
			$midpoint,
			PerformanceConfig::pathfindSnapshotRadius(),
			PerformanceConfig::pathfindSnapshotYBelow(),
			PerformanceConfig::pathfindSnapshotYAbove()
		);

		$requestId = $this->nextRequestId++;
		$this->pendingCallbacks[$requestId] = $onComplete;
		$this->asyncInFlight++;

		$task = new PathfindAsyncTask(
			$grid->serialize(),
			$start->x,
			$start->y,
			$start->z,
			$end->x,
			$end->y,
			$end->z,
			$requestId
		);

		$this->plugin->getServer()->getAsyncPool()->submitTask($task);
	}

	public function completeAsyncRequest(PathfindAsyncTask $task) : void{
		if(self::$instance === null){
			return;
		}

		$this->asyncInFlight = max(0, $this->asyncInFlight - 1);
		$result = $task->getPathResult();
		if($result === null){
			unset($this->pendingCallbacks[$task->getRequestId()]);
			return;
		}

		$callback = $this->pendingCallbacks[$result["id"]] ?? null;
		unset($this->pendingCallbacks[$result["id"]]);
		if($callback === null){
			return;
		}

		$path = [];
		foreach($result["path"] as $node){
			$path[] = new Vector3($node[0], $node[1], $node[2]);
		}

		$callback($path);
	}
}
