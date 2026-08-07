<?php

declare(strict_types=1);

namespace grinn34\vanillaMobAI\pathfinding;

use pocketmine\math\Vector3;
use pocketmine\scheduler\AsyncTask;
use function unserialize;

final class PathfindAsyncTask extends AsyncTask{
	public function __construct(
		private readonly string $gridData,
		private readonly float $startX,
		private readonly float $startY,
		private readonly float $startZ,
		private readonly float $endX,
		private readonly float $endY,
		private readonly float $endZ,
		private readonly int $requestId
	){}

	public function onCompletion() : void{
		PathfindingService::get()->completeAsyncRequest($this);
	}

	public function onRun() : void{
		$grid = TraversabilityGrid::deserialize($this->gridData);
		$path = GridPathfinder::findPath(
			$grid,
			new Vector3($this->startX, $this->startY, $this->startZ),
			new Vector3($this->endX, $this->endY, $this->endZ)
		);

		$serialized = [];
		foreach($path as $node){
			$serialized[] = [$node->x, $node->y, $node->z];
		}

		$this->setResult(serialize([
			"id" => $this->requestId,
			"path" => $serialized,
		]));
	}

	public function getRequestId() : int{
		return $this->requestId;
	}

	/**
	 * @return array{id: int, path: array<int, array{float, float, float}>}|null
	 */
	public function getPathResult() : ?array{
		$result = $this->getResult();
		if(!is_string($result)){
			return null;
		}

		$decoded = unserialize($result);
		return is_array($decoded) ? $decoded : null;
	}
}
