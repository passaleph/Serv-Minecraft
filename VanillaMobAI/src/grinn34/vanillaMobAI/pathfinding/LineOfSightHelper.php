<?php

declare(strict_types=1);

namespace grinn34\vanillaMobAI\pathfinding;

use pocketmine\block\Block;
use pocketmine\math\Vector3;
use pocketmine\world\World;
use function ceil;
use function floor;
use function sqrt;

final class LineOfSightHelper{
	private const MAX_DISTANCE = 64.0;
	private const SAMPLE_STEP = 0.5;

	private function __construct(){}

	public static function hasLineOfSight(World $world, Vector3 $from, Vector3 $to) : bool{
		$dx = $to->x - $from->x;
		$dy = $to->y - $from->y;
		$dz = $to->z - $from->z;
		$distance = sqrt($dx * $dx + $dy * $dy + $dz * $dz);

		if($distance < 0.01){
			return true;
		}

		if($distance > self::MAX_DISTANCE){
			return false;
		}

		$steps = (int) ceil($distance / self::SAMPLE_STEP);

		for($i = 1; $i < $steps; $i++){
			$t = $i / $steps;
			$x = $from->x + $dx * $t;
			$y = $from->y + $dy * $t;
			$z = $from->z + $dz * $t;

			if(self::blocksVision($world, $x, $y, $z)){
				return false;
			}
		}

		return true;
	}

	private static function blocksVision(World $world, float $x, float $y, float $z) : bool{
		$block = $world->getBlockAt((int) floor($x), (int) floor($y), (int) floor($z));
		return self::blocksVisionBlock($block);
	}

	private static function blocksVisionBlock(Block $block) : bool{
		return $block->isSolid() && !$block->isTransparent();
	}
}
