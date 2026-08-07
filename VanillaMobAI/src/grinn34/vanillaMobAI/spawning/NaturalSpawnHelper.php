<?php

declare(strict_types=1);

namespace grinn34\vanillaMobAI\spawning;

use grinn34\vanillaMobAI\config\PluginSettings;
use grinn34\vanillaMobAI\movement\MovementHelper;
use grinn34\vanillaMobAI\registry\MobNaturalSpawnRegistry;
use pocketmine\block\Grass;
use pocketmine\entity\Living;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\World;
use function cos;
use function floor;
use function mt_rand;
use function sin;
use const M_PI;

final class NaturalSpawnHelper{
	private function __construct(){}

	public static function findSpawnPosition(Player $player, string $category) : ?Vector3{
		$origin = $player->getPosition();
		$world = $player->getWorld();
		$angle = mt_rand(0, 359) * (M_PI / 180);
		$settings = PluginSettings::get();
		$distance = mt_rand(
			$settings->getSpawnMinDistance(),
			$settings->getSpawnMaxDistance()
		);

		$targetX = (int) floor($origin->x + cos($angle) * $distance);
		$targetZ = (int) floor($origin->z + sin($angle) * $distance);

		for($attempt = 0; $attempt < 12; $attempt++){
			$scanX = $targetX + mt_rand(-6, 6);
			$scanZ = $targetZ + mt_rand(-6, 6);
			$position = self::findSurfacePosition($world, $scanX, $scanZ);
			if($position === null){
				continue;
			}

			if(self::isValidSpawn($world, $position, $category)){
				return $position;
			}
		}

		return null;
	}

	private static function findSurfacePosition(World $world, int $x, int $z) : ?Vector3{
		for($y = $world->getMaxY() - 2; $y >= $world->getMinY() + 1; $y--){
			$feet = new Vector3($x + 0.5, (float) $y, $z + 0.5);
			if(MovementHelper::isWalkable($world, $feet)){
				return $feet;
			}
		}

		return null;
	}

	public static function isValidSpawn(World $world, Vector3 $position, string $category) : bool{
		if(!MovementHelper::isWalkable($world, $position)){
			return false;
		}

		$x = (int) floor($position->x);
		$y = (int) floor($position->y);
		$z = (int) floor($position->z);
		$light = $world->getFullLightAt($x, $y, $z);

		$settings = PluginSettings::get();

		if($category === MobNaturalSpawnRegistry::CATEGORY_PASSIVE){
			$ground = $world->getBlockAt($x, $y - 1, $z);
			$grassOk = !$settings->requiresGrassForPassive() || $ground instanceof Grass;
			return $light >= $settings->getPassiveMinLight() && $grassOk;
		}

		return $light <= $settings->getHostileMaxLight();
	}

	public static function countCategoryMobs(World $world, Vector3 $center, string $category) : int{
		return MobCountCache::count($world, $center, $category);
	}
}
