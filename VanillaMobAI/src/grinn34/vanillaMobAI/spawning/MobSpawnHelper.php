<?php

declare(strict_types=1);

namespace grinn34\vanillaMobAI\spawning;

use grinn34\vanillaMobAI\movement\MovementHelper;
use grinn34\vanillaMobAI\registry\MobRegistry;
use grinn34\vanillaMobAI\registry\MobSpawnRegistry;
use pocketmine\entity\Living;
use pocketmine\entity\Location;
use pocketmine\math\Vector3;
use pocketmine\utils\Utils;
use pocketmine\world\World;
use function mt_rand;

final class MobSpawnHelper{
	private function __construct(){}

	public static function spawnMob(
		MobRegistry $mobRegistry,
		string $mobKey,
		Location $location,
		?string $nameTag = null
	) : ?Living{
		$entity = MobSpawnRegistry::create($mobKey, $location);
		if($entity === null){
			return null;
		}

		if($nameTag !== null && $nameTag !== ""){
			$entity->setNameTag($nameTag);
		}

		$entity->spawnToAll();
		$mobRegistry->tryAttach($entity);

		return $entity;
	}

	public static function resolveSpawnPositionFromBlock(World $world, \pocketmine\block\Block $clickedBlock, int $face) : ?Vector3{
		$side = $clickedBlock->getPosition()->getSide($face);
		return self::resolveWalkableAt($world, $side->x, $side->z, $side->y);
	}

	public static function resolveWalkableAt(World $world, int $x, int $z, int $baseY) : ?Vector3{
		for($yScan = 0; $yScan <= 3; $yScan++){
			$candidate = new Vector3($x + 0.5, (float) ($baseY + $yScan), $z + 0.5);
			if(MovementHelper::isWalkable($world, $candidate)){
				return $candidate;
			}
		}

		for($yScan = 1; $yScan <= 2; $yScan++){
			$candidate = new Vector3($x + 0.5, (float) ($baseY - $yScan), $z + 0.5);
			if(MovementHelper::isWalkable($world, $candidate)){
				return $candidate;
			}
		}

		return null;
	}

	public static function findSpawnerPosition(World $world, Vector3 $center, int $spawnRange) : ?Vector3{
		for($attempt = 0; $attempt < 8; $attempt++){
			$x = $center->x + mt_rand(-$spawnRange, $spawnRange);
			$z = $center->z + mt_rand(-$spawnRange, $spawnRange);
			$y = (int) floor($center->y);

			for($yScan = -1; $yScan <= 2; $yScan++){
				$candidate = new Vector3($x + 0.5, (float) ($y + $yScan), $z + 0.5);
				if(MovementHelper::isWalkable($world, $candidate)){
					return $candidate;
				}
			}
		}

		return null;
	}

	public static function randomYaw() : float{
		return Utils::getRandomFloat() * 360;
	}
}
