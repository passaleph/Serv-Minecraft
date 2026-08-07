<?php

declare(strict_types=1);

namespace grinn34\vanillaMobAI\config;

use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\world\World;
use function array_map;
use function is_array;
use function is_bool;
use function is_numeric;
use function max;
use function min;
use function strtolower;

final class PluginSettings{
	private static ?self $instance = null;

	public static function get() : self{
		return self::$instance ?? throw new \RuntimeException("PluginSettings not loaded");
	}

	public static function load(PluginBase $plugin) : self{
		$plugin->saveDefaultConfig();
		$plugin->reloadConfig();
		self::$instance = new self($plugin->getConfig());
		return self::$instance;
	}

	public static function isLoaded() : bool{
		return self::$instance !== null;
	}

	private function __construct(Config $config){
		$features = $this->section($config, "features");
		$this->mobAiEnabled = $this->bool($features, "mob-ai", true);
		$this->naturalSpawnEnabled = $this->bool($features, "natural-spawn", true);
		$this->monsterSpawnersEnabled = $this->bool($features, "monster-spawners", true);
		$this->spawnEggsEnabled = $this->bool($features, "spawn-eggs", true);
		$this->breedingEnabled = $this->bool($features, "breeding", true);
		$this->mobInteractionsEnabled = $this->bool($features, "mob-interactions", true);

		$worlds = $this->section($config, "worlds");
		$this->naturalSpawnWorldMode = $this->string($worlds, "natural-spawn-mode", "blacklist");
		$this->naturalSpawnWhitelist = $this->stringList($worlds, "natural-spawn-whitelist");
		$this->naturalSpawnBlacklist = $this->stringList($worlds, "natural-spawn-blacklist", ["nether", "hell", "ender", "end"]);
		$this->spawnerWorldMode = $this->string($worlds, "spawner-mode", "blacklist");
		$this->spawnerWhitelist = $this->stringList($worlds, "spawner-whitelist");
		$this->spawnerBlacklist = $this->stringList($worlds, "spawner-blacklist", ["nether", "hell", "ender", "end"]);

		$spawn = $this->section($config, "natural-spawn");
		$this->spawnAttemptIntervalTicks = $this->intRange($spawn, "attempt-interval-ticks", 20, 20 * 60, 100);
		$this->spawnChancePercent = $this->intRange($spawn, "spawn-chance-percent", 1, 100, 25);
		$this->spawnPlayersPerTick = $this->intRange($spawn, "players-per-tick", 1, 32, 2);
		$this->spawnMinDistance = $this->intRange($spawn, "min-distance", 8, 256, 24);
		$this->spawnMaxDistance = $this->intRange($spawn, "max-distance", $this->spawnMinDistance, 512, 96);
		$this->capCheckRadius = $this->intRange($spawn, "cap-check-radius", 16, 256, 64);
		$this->passiveCap = $this->intRange($spawn, "passive-cap", 0, 256, 12);
		$this->hostileCap = $this->intRange($spawn, "hostile-cap", 0, 256, 24);
		$this->passiveMinLight = $this->intRange($spawn, "passive-min-light", 0, 15, 9);
		$this->hostileMaxLight = $this->intRange($spawn, "hostile-max-light", 0, 15, 7);
		$this->requireGrassForPassive = $this->bool($spawn, "require-grass-for-passive", true);
		$this->blockHostilesOnPeaceful = $this->bool($spawn, "block-hostiles-on-peaceful", true);
		$this->naturalSpawnMobs = $this->mobFlags($spawn);

		$spawners = $this->section($config, "spawners");
		$this->spawnersSectionEnabled = $this->bool($spawners, "enabled", true);
		$this->spawnerTickInterval = $this->intRange($spawners, "tick-interval", 1, 100, 1);
		$this->spawnerChunkScanRadius = $this->intRange($spawners, "chunk-scan-radius", 0, 8, 1);
		$this->spawnerBlockHostilesOnPeaceful = $this->bool($spawners, "block-hostiles-on-peaceful", true);

		$perf = $this->section($config, "performance");
		$this->activationRange = $this->floatRange($perf, "activation-range", 8.0, 128.0, 48.0);
		$this->activeHostileTickInterval = $this->intRange($perf, "active-hostile-tick-interval", 1, 40, 1);
		$this->activePassiveTickInterval = $this->intRange($perf, "active-passive-tick-interval", 1, 40, 2);
		$this->inactiveTickInterval = $this->intRange($perf, "inactive-tick-interval", 1, 200, 40);
		$this->maxBrainsPerTick = $this->intRange($perf, "max-brains-per-tick", 1, 512, 48);
		$this->syncPathfindsPerTick = $this->intRange($perf, "sync-pathfinds-per-tick", 0, 64, 3);
		$this->pathfindQueueSize = $this->intRange($perf, "pathfind-queue-size", 1, 256, 32);
		$this->pathfindSnapshotRadius = $this->intRange($perf, "pathfind-snapshot-radius", 4, 64, 20);
		$this->pathfindSnapshotYBelow = $this->intRange($perf, "pathfind-snapshot-y-below", 0, 32, 2);
		$this->pathfindSnapshotYAbove = $this->intRange($perf, "pathfind-snapshot-y-above", 0, 32, 5);
		$this->losCheckInterval = $this->intRange($perf, "line-of-sight-check-interval", 1, 40, 5);
		$this->targetScanInterval = $this->intRange($perf, "target-scan-interval", 1, 40, 10);
		$this->walkLineCheckInterval = $this->intRange($perf, "walk-line-check-interval", 1, 40, 4);
		$this->waypointSkipInterval = $this->intRange($perf, "waypoint-skip-interval", 1, 40, 8);
		$this->mobCountCacheTtlTicks = $this->intRange($perf, "mob-count-cache-ttl-ticks", 1, 200, 40);
	}

	private bool $mobAiEnabled;
	private bool $naturalSpawnEnabled;
	private bool $monsterSpawnersEnabled;
	private bool $spawnEggsEnabled;
	private bool $breedingEnabled;
	private bool $mobInteractionsEnabled;

	private string $naturalSpawnWorldMode;
	/** @var string[] */
	private array $naturalSpawnWhitelist;
	/** @var string[] */
	private array $naturalSpawnBlacklist;
	private string $spawnerWorldMode;
	/** @var string[] */
	private array $spawnerWhitelist;
	/** @var string[] */
	private array $spawnerBlacklist;

	private int $spawnAttemptIntervalTicks;
	private int $spawnChancePercent;
	private int $spawnPlayersPerTick;
	private int $spawnMinDistance;
	private int $spawnMaxDistance;
	private int $capCheckRadius;
	private int $passiveCap;
	private int $hostileCap;
	private int $passiveMinLight;
	private int $hostileMaxLight;
	private bool $requireGrassForPassive;
	private bool $blockHostilesOnPeaceful;
	/** @var array<string, bool> */
	private array $naturalSpawnMobs;

	private bool $spawnersSectionEnabled;
	private int $spawnerTickInterval;
	private int $spawnerChunkScanRadius;
	private bool $spawnerBlockHostilesOnPeaceful;

	private float $activationRange;
	private int $activeHostileTickInterval;
	private int $activePassiveTickInterval;
	private int $inactiveTickInterval;
	private int $maxBrainsPerTick;
	private int $syncPathfindsPerTick;
	private int $pathfindQueueSize;
	private int $pathfindSnapshotRadius;
	private int $pathfindSnapshotYBelow;
	private int $pathfindSnapshotYAbove;
	private int $losCheckInterval;
	private int $targetScanInterval;
	private int $walkLineCheckInterval;
	private int $waypointSkipInterval;
	private int $mobCountCacheTtlTicks;

	public function isMobAiEnabled() : bool{
		return $this->mobAiEnabled;
	}

	public function isNaturalSpawnEnabled() : bool{
		return $this->naturalSpawnEnabled;
	}

	public function isMonsterSpawnersEnabled() : bool{
		return $this->monsterSpawnersEnabled && $this->spawnersSectionEnabled;
	}

	public function isSpawnEggsEnabled() : bool{
		return $this->spawnEggsEnabled;
	}

	public function isBreedingEnabled() : bool{
		return $this->breedingEnabled;
	}

	public function isMobInteractionsEnabled() : bool{
		return $this->mobInteractionsEnabled;
	}

	public function allowsNaturalSpawnWorld(World $world) : bool{
		return WorldFilter::allows(
			$this->naturalSpawnWorldMode,
			$this->naturalSpawnWhitelist,
			$this->naturalSpawnBlacklist,
			$world
		);
	}

	public function allowsSpawnerWorld(World $world) : bool{
		return WorldFilter::allows(
			$this->spawnerWorldMode,
			$this->spawnerWhitelist,
			$this->spawnerBlacklist,
			$world
		);
	}

	public function isNaturalSpawnMobEnabled(string $mobKey) : bool{
		return $this->naturalSpawnMobs[strtolower($mobKey)] ?? false;
	}

	public function getSpawnAttemptIntervalTicks() : int{
		return $this->spawnAttemptIntervalTicks;
	}

	public function getSpawnChancePercent() : int{
		return $this->spawnChancePercent;
	}

	public function getSpawnPlayersPerTick() : int{
		return $this->spawnPlayersPerTick;
	}

	public function getSpawnMinDistance() : int{
		return $this->spawnMinDistance;
	}

	public function getSpawnMaxDistance() : int{
		return $this->spawnMaxDistance;
	}

	public function getCapCheckRadius() : int{
		return $this->capCheckRadius;
	}

	public function getPassiveCap() : int{
		return $this->passiveCap;
	}

	public function getHostileCap() : int{
		return $this->hostileCap;
	}

	public function getPassiveMinLight() : int{
		return $this->passiveMinLight;
	}

	public function getHostileMaxLight() : int{
		return $this->hostileMaxLight;
	}

	public function requiresGrassForPassive() : bool{
		return $this->requireGrassForPassive;
	}

	public function blocksHostilesOnPeaceful() : bool{
		return $this->blockHostilesOnPeaceful;
	}

	public function spawnerBlocksHostilesOnPeaceful() : bool{
		return $this->spawnerBlockHostilesOnPeaceful;
	}

	public function getSpawnerTickInterval() : int{
		return $this->spawnerTickInterval;
	}

	public function getSpawnerChunkScanRadius() : int{
		return $this->spawnerChunkScanRadius;
	}

	public function getActivationRange() : float{
		return $this->activationRange;
	}

	public function getActivationRangeSq() : float{
		return $this->activationRange ** 2;
	}

	public function getActiveHostileTickInterval() : int{
		return $this->activeHostileTickInterval;
	}

	public function getActivePassiveTickInterval() : int{
		return $this->activePassiveTickInterval;
	}

	public function getInactiveTickInterval() : int{
		return $this->inactiveTickInterval;
	}

	public function getMaxBrainsPerTick() : int{
		return $this->maxBrainsPerTick;
	}

	public function getSyncPathfindsPerTick() : int{
		return $this->syncPathfindsPerTick;
	}

	public function getPathfindQueueSize() : int{
		return $this->pathfindQueueSize;
	}

	public function getPathfindSnapshotRadius() : int{
		return $this->pathfindSnapshotRadius;
	}

	public function getPathfindSnapshotYBelow() : int{
		return $this->pathfindSnapshotYBelow;
	}

	public function getPathfindSnapshotYAbove() : int{
		return $this->pathfindSnapshotYAbove;
	}

	public function getLosCheckInterval() : int{
		return $this->losCheckInterval;
	}

	public function getTargetScanInterval() : int{
		return $this->targetScanInterval;
	}

	public function getWalkLineCheckInterval() : int{
		return $this->walkLineCheckInterval;
	}

	public function getWaypointSkipInterval() : int{
		return $this->waypointSkipInterval;
	}

	public function getMobCountCacheTtlTicks() : int{
		return $this->mobCountCacheTtlTicks;
	}

	/**
	 * @return array<string, bool>
	 */
	public function getNaturalSpawnMobFlags() : array{
		return $this->naturalSpawnMobs;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function section(Config $config, string $key) : array{
		$value = $config->get($key, []);
		return is_array($value) ? $value : [];
	}

	private function bool(array $section, string $key, bool $default) : bool{
		$value = $section[$key] ?? $default;
		return is_bool($value) ? $value : $default;
	}

	private function string(array $section, string $key, string $default) : string{
		$value = $section[$key] ?? $default;
		return is_string($value) ? $value : $default;
	}

	/**
	 * @return string[]
	 */
	private function stringList(array $section, string $key, array $default = []) : array{
		$value = $section[$key] ?? $default;
		if(!is_array($value)){
			return $default;
		}

		return array_values(array_filter($value, static fn(mixed $entry) : bool => is_string($entry) && $entry !== ""));
	}

	private function intRange(array $section, string $key, int $min, int $max, int $default) : int{
		$value = $section[$key] ?? $default;
		if(!is_numeric($value)){
			return $default;
		}

		return (int) min($max, max($min, (int) $value));
	}

	private function floatRange(array $section, string $key, float $min, float $max, float $default) : float{
		$value = $section[$key] ?? $default;
		if(!is_numeric($value)){
			return $default;
		}

		return min($max, max($min, (float) $value));
	}

	/**
	 * @return array<string, bool>
	 */
	private function mobFlags(array $spawnSection) : array{
		$defaults = [
			"cow" => true,
			"pig" => true,
			"sheep" => true,
			"chicken" => true,
			"zombie" => true,
			"skeleton" => true,
			"spider" => true,
			"creeper" => true,
		];

		$mobs = $spawnSection["mobs"] ?? [];
		if(!is_array($mobs)){
			return $defaults;
		}

		$result = $defaults;
		foreach($defaults as $mob => $_){
			if(isset($mobs[$mob])){
				$result[$mob] = is_bool($mobs[$mob]) ? $mobs[$mob] : (bool) $mobs[$mob];
			}
		}

		return $result;
	}
}
