<?php

declare(strict_types=1);

namespace grinn34\vanillaMobAI\movement;

use grinn34\vanillaMobAI\pathfinding\BlockPathfinder;
use grinn34\vanillaMobAI\pathfinding\PathfindingService;
use grinn34\vanillaMobAI\performance\PerformanceConfig;
use grinn34\vanillaMobAI\performance\TickThrottle;
use pocketmine\entity\Living;
use pocketmine\math\Vector3;

final class PathNavigation{
	private const WAYPOINT_DISTANCE_SQ = 1.2;

	/** @var Vector3[] */
	private array $waypoints = [];
	private int $waypointIndex = 0;
	private float $speedMultiplier = 1.0;
	private bool $pathPending = false;
	private ?Vector3 $pendingDestination = null;

	public function __construct(
		private readonly Living $entity
	){}

	public function setSpeedMultiplier(float $multiplier) : void{
		$this->speedMultiplier = max(0.1, $multiplier);
	}

	public function setDestination(Vector3 $destination) : bool{
		$position = $this->entity->getPosition();
		$world = $this->entity->getWorld();

		if(BlockPathfinder::hasWalkLine($world, $position, $destination)){
			$this->waypoints = [ $destination ];
			$this->waypointIndex = 0;
			$this->pathPending = false;
			$this->pendingDestination = null;
			return true;
		}

		if(
			$this->pathPending
			&& $this->pendingDestination !== null
			&& MovementHelper::horizontalDistanceSquared($this->pendingDestination, $destination) <= 1.0
		){
			return $this->isActive() || $this->pathPending;
		}

		$this->pathPending = true;
		$this->pendingDestination = $destination->asVector3();

		PathfindingService::get()->requestPath($world, $position, $destination, function(array $path) : void{
			$this->pathPending = false;
			if($this->entity->isClosed() || !$this->entity->isAlive()){
				return;
			}

			if($path === []){
				return;
			}

			$this->waypoints = $path;
			$this->waypointIndex = 0;
		});

		return $this->isActive() || $this->pathPending;
	}

	public function isActive() : bool{
		return $this->waypointIndex < count($this->waypoints);
	}

	public function isPathPending() : bool{
		return $this->pathPending;
	}

	public function stop() : void{
		$this->waypoints = [];
		$this->waypointIndex = 0;
		$this->pathPending = false;
		$this->pendingDestination = null;
		MovementHelper::stopHorizontalMovement($this->entity);
	}

	public function tick() : bool{
		if(!$this->isActive()){
			return false;
		}

		if(TickThrottle::every($this->entity->getId(), PerformanceConfig::waypointSkipInterval())){
			$this->skipVisibleWaypoints();
		}

		if(!$this->isActive()){
			MovementHelper::stopHorizontalMovement($this->entity);
			return true;
		}

		$target = $this->waypoints[$this->waypointIndex];
		$position = $this->entity->getPosition();

		if(MovementHelper::horizontalDistanceSquared($position, $target) <= self::WAYPOINT_DISTANCE_SQ){
			$this->waypointIndex++;
			if(!$this->isActive()){
				MovementHelper::stopHorizontalMovement($this->entity);
				return true;
			}
			$target = $this->waypoints[$this->waypointIndex];
		}

		return MovementHelper::navigateTowards($this->entity, $target, $this->speedMultiplier);
	}

	private function skipVisibleWaypoints() : void{
		$position = $this->entity->getPosition();
		$world = $this->entity->getWorld();
		$lastIndex = count($this->waypoints) - 1;

		for($scan = $lastIndex; $scan > $this->waypointIndex; $scan--){
			if(BlockPathfinder::hasWalkLine($world, $position, $this->waypoints[$scan])){
				$this->waypointIndex = $scan;
				return;
			}
		}
	}
}
