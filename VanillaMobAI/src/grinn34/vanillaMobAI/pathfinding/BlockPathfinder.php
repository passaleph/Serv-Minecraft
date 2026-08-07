<?php

declare(strict_types=1);

namespace grinn34\vanillaMobAI\pathfinding;

use pocketmine\math\Vector3;
use pocketmine\world\World;
use function abs;
use function array_map;
use function array_reverse;
use function count;
use function explode;
use function floor;
use function intval;
use function max;
use function min;
use function sqrt;

final class BlockPathfinder{
	private const MAX_ITERATIONS = 512;
	private const MAX_RANGE = 20;

	private function __construct(){}

	/**
	 * @return Vector3[]
	 */
	public static function findPath(World $world, Vector3 $start, Vector3 $end) : array{
		$startNode = self::toNode($start);
		$endNode = self::toNode($end);

		if($startNode === $endNode){
			return [$end];
		}

		$resolvedEnd = self::resolveWalkableNear($world, $endNode[0], $endNode[1], $endNode[2]);
		if($resolvedEnd === null){
			return [];
		}
		$endNode = $resolvedEnd;

		if(self::hasWalkLine($world, $start, self::toVector($endNode))){
			return [self::toVector($endNode)];
		}

		$open = [];
		$closed = [];
		$cameFrom = [];
		$gScore = [];

		$startKey = self::nodeKey(...$startNode);
		$endKey = self::nodeKey(...$endNode);

		$open[$startKey] = $startNode;
		$gScore[$startKey] = 0.0;
		$fScore = [ $startKey => self::heuristic($startNode, $endNode) ];

		$iterations = 0;

		while($open !== [] && $iterations < self::MAX_ITERATIONS){
			$iterations++;

			$currentKey = self::lowestFScore($open, $fScore);
			$current = $open[$currentKey];

			if($currentKey === $endKey){
				$path = self::reconstructPath($cameFrom, $currentKey, self::toVector($endNode));
				return self::smoothPath($world, $path);
			}

			unset($open[$currentKey]);
			$closed[$currentKey] = true;

			foreach(self::neighbours($world, $current, $startNode) as $neighbour){
				$neighbourKey = self::nodeKey(...$neighbour);

				if(isset($closed[$neighbourKey])){
					continue;
				}

				$tentativeG = ($gScore[$currentKey] ?? INF) + self::moveCost($current, $neighbour);

				if(!isset($open[$neighbourKey]) || $tentativeG < ($gScore[$neighbourKey] ?? INF)){
					$cameFrom[$neighbourKey] = $currentKey;
					$gScore[$neighbourKey] = $tentativeG;
					$fScore[$neighbourKey] = $tentativeG + self::heuristic($neighbour, $endNode);
					$open[$neighbourKey] = $neighbour;
				}
			}
		}

		return [];
	}

	public static function hasWalkLine(World $world, Vector3 $from, Vector3 $to) : bool{
		$dx = $to->x - $from->x;
		$dz = $to->z - $from->z;
		$steps = (int) max(abs($dx), abs($dz), 1.0);
		$steps = min($steps, 24);

		for($i = 1; $i <= $steps; $i++){
			$t = $i / $steps;
			$x = (int) floor($from->x + $dx * $t);
			$z = (int) floor($from->z + $dz * $t);
			$baseY = (int) floor($from->y + ($to->y - $from->y) * $t);

			if(self::resolveWalkableNear($world, $x, $baseY, $z) === null){
				return false;
			}
		}

		return true;
	}

	public static function findFleePosition(World $world, Vector3 $origin, Vector3 $threat, int $searchRadius = 14) : ?Vector3{
		$startNode = self::toNode($origin);
		$startKey = self::nodeKey(...$startNode);

		$queue = [ $startNode ];
		$visited = [ $startKey => true ];

		$bestNode = null;
		$bestDistanceSq = self::horizontalDistanceSquared($origin, $threat);

		$iterations = 0;

		while($queue !== [] && $iterations < self::MAX_ITERATIONS){
			$iterations++;
			$current = array_shift($queue);

			$nodeVec = self::toVector($current);
			$distSq = self::horizontalDistanceSquared($nodeVec, $threat);

			if($distSq > $bestDistanceSq + 0.5){
				$bestDistanceSq = $distSq;
				$bestNode = $current;
			}

			foreach(self::neighbours($world, $current, $startNode) as $neighbour){
				$key = self::nodeKey(...$neighbour);

				if(isset($visited[$key])){
					continue;
				}

				if(abs($neighbour[0] - $startNode[0]) > $searchRadius || abs($neighbour[2] - $startNode[2]) > $searchRadius){
					continue;
				}

				$visited[$key] = true;
				$queue[] = $neighbour;
			}
		}

		return $bestNode !== null ? self::toVector($bestNode) : null;
	}

	/**
	 * @param Vector3[] $path
	 * @return Vector3[]
	 */
	private static function smoothPath(World $world, array $path) : array{
		if(count($path) <= 2){
			return $path;
		}

		$smoothed = [ $path[0] ];
		$index = 0;
		$lastIndex = count($path) - 1;

		while($index < $lastIndex){
			$furthest = $index + 1;
			for($scan = $lastIndex; $scan > $index + 1; $scan--){
				if(self::hasWalkLine($world, $path[$index], $path[$scan])){
					$furthest = $scan;
					break;
				}
			}
			$smoothed[] = $path[$furthest];
			$index = $furthest;
		}

		return $smoothed;
	}

	/**
	 * @param array<string, array{int, int, int}> $open
	 * @param array<string, float> $fScore
	 */
	private static function lowestFScore(array $open, array $fScore) : string{
		$bestKey = array_key_first($open);
		$bestScore = $fScore[$bestKey] ?? INF;

		foreach($open as $key => $_){
			$score = $fScore[$key] ?? INF;
			if($score < $bestScore){
				$bestKey = $key;
				$bestScore = $score;
			}
		}

		return $bestKey;
	}

	/**
	 * @return array{int, int, int}
	 */
	private static function toNode(Vector3 $position) : array{
		return [
			(int) floor($position->x),
			(int) floor($position->y),
			(int) floor($position->z)
		];
	}

	/**
	 * @param array{int, int, int} $node
	 */
	private static function toVector(array $node) : Vector3{
		return new Vector3($node[0] + 0.5, (float) $node[1], $node[2] + 0.5);
	}

	private static function nodeKey(int $x, int $y, int $z) : string{
		return $x . ":" . $y . ":" . $z;
	}

	/**
	 * @param array{int, int, int} $a
	 * @param array{int, int, int} $b
	 */
	private static function heuristic(array $a, array $b) : float{
		$dx = abs($a[0] - $b[0]);
		$dz = abs($a[2] - $b[2]);
		$dy = abs($a[1] - $b[1]);
		return (float) sqrt($dx * $dx + $dz * $dz) + ($dy > 0 ? 1.5 : 0.0);
	}

	/**
	 * @param array{int, int, int} $a
	 * @param array{int, int, int} $b
	 */
	private static function moveCost(array $a, array $b) : float{
		$dx = abs($a[0] - $b[0]);
		$dy = abs($a[1] - $b[1]);
		$dz = abs($a[2] - $b[2]);
		return (float) sqrt($dx * $dx + $dz * $dz) + ($dy > 0 ? 1.2 : 0.0);
	}

	/**
	 * @param array{int, int, int} $startOrigin
	 * @return array<int, array{int, int, int}>
	 */
	private static function neighbours(World $world, array $node, array $startOrigin) : array{
		[$x, $y, $z] = $node;
		$result = [];

		foreach([
			[1, 0, 0], [-1, 0, 0], [0, 0, 1], [0, 0, -1],
		] as [$dx, $dy, $dz]){
			$nx = $x + $dx;
			$ny = $y + $dy;
			$nz = $z + $dz;

			if(abs($nx - $startOrigin[0]) > self::MAX_RANGE || abs($nz - $startOrigin[2]) > self::MAX_RANGE){
				continue;
			}

			if(self::isTraversable($world, $nx, $ny, $nz)){
				$result[] = [$nx, $ny, $nz];
			}elseif(self::isTraversable($world, $nx, $ny + 1, $nz) && self::canStepUp($world, $x, $y, $z, $nx, $ny + 1, $nz)){
				$result[] = [$nx, $ny + 1, $nz];
			}elseif(self::isTraversable($world, $nx, $ny - 1, $nz)){
				$result[] = [$nx, $ny - 1, $nz];
			}
		}

		return $result;
	}

	private static function canStepUp(World $world, int $fromX, int $fromY, int $fromZ, int $toX, int $toY, int $toZ) : bool{
		if($toY - $fromY !== 1){
			return false;
		}

		$obstacle = $world->getBlockAt($toX, $fromY, $toZ);
		if(!$obstacle->isSolid()){
			return false;
		}

		return !$world->getBlockAt($fromX, $fromY + 2, $fromZ)->isSolid();
	}

	private static function isTraversable(World $world, int $x, int $y, int $z) : bool{
		$feet = $world->getBlockAt($x, $y, $z);
		$head = $world->getBlockAt($x, $y + 1, $z);

		if($feet->isSolid() || $head->isSolid()){
			return false;
		}

		if($world->getBlockAt($x, $y - 1, $z)->isSolid()){
			return true;
		}

		for($drop = 1; $drop <= 3; $drop++){
			if($world->getBlockAt($x, $y - 1 - $drop, $z)->isSolid()){
				return true;
			}
		}

		return false;
	}

	/**
	 * @return array{int, int, int}|null
	 */
	private static function resolveWalkableNear(World $world, int $x, int $y, int $z) : ?array{
		for($yScan = 0; $yScan <= 3; $yScan++){
			if(self::isTraversable($world, $x, $y + $yScan, $z)){
				return [$x, $y + $yScan, $z];
			}
		}

		for($yScan = 1; $yScan <= 2; $yScan++){
			if(self::isTraversable($world, $x, $y - $yScan, $z)){
				return [$x, $y - $yScan, $z];
			}
		}

		return null;
	}

	private static function horizontalDistanceSquared(Vector3 $a, Vector3 $b) : float{
		$dx = $a->x - $b->x;
		$dz = $a->z - $b->z;
		return $dx * $dx + $dz * $dz;
	}

	/**
	 * @param array<string, string> $cameFrom
	 * @return Vector3[]
	 */
	private static function reconstructPath(array $cameFrom, string $currentKey, Vector3 $end) : array{
		$path = [$end];
		$cursor = $currentKey;

		while(isset($cameFrom[$cursor])){
			$cursor = $cameFrom[$cursor];
			[$x, $y, $z] = array_map(static fn(string $v) => intval($v), explode(":", $cursor));
			$path[] = new Vector3($x + 0.5, (float) $y, $z + 0.5);
		}

		return array_reverse($path);
	}
}
