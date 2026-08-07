<?php

declare(strict_types=1);

namespace grinn34\vanillaMobAI\pathfinding;

use pocketmine\math\Vector3;
use function abs;
use function array_key_first;
use function array_map;
use function array_reverse;
use function count;
use function explode;
use function floor;
use function intval;
use function sqrt;

final class GridPathfinder{
	private const MAX_ITERATIONS = 512;
	private const MAX_RANGE = 20;

	private function __construct(){}

	/**
	 * @return Vector3[]
	 */
	public static function findPath(TraversabilityGrid $grid, Vector3 $start, Vector3 $end) : array{
		$startNode = self::toNode($start);
		$endNode = self::toNode($end);

		if($startNode === $endNode){
			return [$end];
		}

		$resolvedEnd = $grid->resolveWalkableNear($endNode[0], $endNode[1], $endNode[2]);
		if($resolvedEnd === null){
			return [];
		}
		$endNode = $resolvedEnd;

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
				return array_reverse(self::reconstructPath($cameFrom, $currentKey, self::toVector($endNode)));
			}

			unset($open[$currentKey]);
			$closed[$currentKey] = true;

			foreach(self::neighbours($grid, $current, $startNode) as $neighbour){
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

	/**
	 * @param array{int, int, int} $startOrigin
	 * @return array<int, array{int, int, int}>
	 */
	private static function neighbours(TraversabilityGrid $grid, array $node, array $startOrigin) : array{
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

			if($grid->isTraversableAt($nx, $ny, $nz)){
				$result[] = [$nx, $ny, $nz];
			}elseif($grid->isTraversableAt($nx, $ny + 1, $nz) && $grid->canStepUp($x, $y, $z, $nx, $ny + 1, $nz)){
				$result[] = [$nx, $ny + 1, $nz];
			}elseif($grid->isTraversableAt($nx, $ny - 1, $nz)){
				$result[] = [$nx, $ny - 1, $nz];
			}
		}

		return $result;
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
			(int) floor($position->z),
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

		return $path;
	}
}
