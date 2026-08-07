<?php

declare(strict_types=1);

namespace grinn34\vanillaMobAI\spawning;

use grinn34\vanillaMobAI\config\PluginSettings;
use grinn34\vanillaMobAI\performance\PerformanceConfig;
use grinn34\vanillaMobAI\performance\TickThrottle;
use grinn34\vanillaMobAI\registry\MobNaturalSpawnRegistry;
use pocketmine\entity\Living;
use pocketmine\math\Vector3;
use pocketmine\world\World;

final class MobCountCache{
	/** @var array<string, array{tick: int, counts: array<string, int>}> */
	private static array $cache = [];

	private function __construct(){}

	public static function reset() : void{
		self::$cache = [];
	}

	public static function count(World $world, Vector3 $center, string $category) : int{
		$worldId = $world->getId();
		$bucketX = (int) floor($center->x / 16);
		$bucketZ = (int) floor($center->z / 16);
		$key = $worldId . ":" . $bucketX . ":" . $bucketZ;

		$entry = self::$cache[$key] ?? null;
		$currentTick = TickThrottle::getGlobalTick();

		if($entry !== null && ($currentTick - $entry["tick"]) < PerformanceConfig::mobCountCacheTtlTicks()){
			return $entry["counts"][$category] ?? 0;
		}

		$counts = self::scanWorld($world, $center);
		self::$cache[$key] = [
			"tick" => $currentTick,
			"counts" => $counts,
		];

		return $counts[$category] ?? 0;
	}

	/**
	 * @return array<string, int>
	 */
	private static function scanWorld(World $world, Vector3 $center) : array{
		$radius = PluginSettings::get()->getCapCheckRadius();
		$radiusSq = $radius * $radius;
		$counts = [
			MobNaturalSpawnRegistry::CATEGORY_PASSIVE => 0,
			MobNaturalSpawnRegistry::CATEGORY_HOSTILE => 0,
		];

		foreach($world->getEntities() as $entity){
			if(!$entity instanceof Living || !MobNaturalSpawnRegistry::isManagedMob($entity)){
				continue;
			}

			if($entity->getPosition()->distanceSquared($center) > $radiusSq){
				continue;
			}

			$category = MobNaturalSpawnRegistry::getCategory($entity);
			if($category !== null){
				$counts[$category]++;
			}
		}

		return $counts;
	}
}
