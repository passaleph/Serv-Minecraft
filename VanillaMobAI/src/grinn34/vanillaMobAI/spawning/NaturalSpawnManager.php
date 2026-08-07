<?php

declare(strict_types=1);

namespace grinn34\vanillaMobAI\spawning;

use grinn34\vanillaMobAI\config\PluginSettings;
use grinn34\vanillaMobAI\performance\PerformanceConfig;
use grinn34\vanillaMobAI\registry\MobNaturalSpawnRegistry;
use grinn34\vanillaMobAI\registry\MobRegistry;
use grinn34\vanillaMobAI\registry\MobSpawnRegistry;
use pocketmine\entity\Location;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\plugin\Plugin;
use pocketmine\scheduler\ClosureTask;
use pocketmine\scheduler\TaskHandler;
use pocketmine\world\World;
use function cos;
use function mt_rand;
use function sin;
use const M_PI;

final class NaturalSpawnManager{
	private ?TaskHandler $taskHandler = null;
	private int $playerCursor = 0;

	public function __construct(
		private readonly Plugin $plugin,
		private readonly MobRegistry $mobRegistry
	){}

	public function start() : void{
		if(!PluginSettings::get()->isNaturalSpawnEnabled()){
			return;
		}

		$interval = PluginSettings::get()->getSpawnAttemptIntervalTicks();
		$this->taskHandler = $this->plugin->getScheduler()->scheduleRepeatingTask(
			new ClosureTask(function() : void{
				$this->tick();
			}),
			$interval
		);
	}

	public function shutdown() : void{
		$this->taskHandler?->cancel();
		$this->taskHandler = null;
		MobCountCache::reset();
	}

	private function tick() : void{
		$settings = PluginSettings::get();
		$players = [];

		foreach($this->plugin->getServer()->getOnlinePlayers() as $player){
			if(!$player->isConnected() || $player->isClosed() || $player->isSpectator()){
				continue;
			}

			if(!$settings->allowsNaturalSpawnWorld($player->getWorld())){
				continue;
			}

			$players[] = $player;
		}

		if($players === []){
			return;
		}

		$totalPlayers = count($players);
		$processed = 0;

		while($processed < PerformanceConfig::spawnPlayersPerTick() && $processed < $totalPlayers){
			$player = $players[$this->playerCursor % $totalPlayers];
			$this->playerCursor++;

			$this->trySpawnCategory($player, MobNaturalSpawnRegistry::CATEGORY_PASSIVE);

			if(
				!$settings->blocksHostilesOnPeaceful()
				|| $player->getWorld()->getDifficulty() !== MobNaturalSpawnRegistry::DIFFICULTY_PEACEFUL
			){
				$this->trySpawnCategory($player, MobNaturalSpawnRegistry::CATEGORY_HOSTILE);
			}

			$processed++;
		}
	}

	private function trySpawnCategory(Player $player, string $category) : void{
		$world = $player->getWorld();
		$center = $player->getPosition();

		if(NaturalSpawnHelper::countCategoryMobs($world, $center, $category) >= MobNaturalSpawnRegistry::getCapForCategory($category)){
			return;
		}

		if(mt_rand(1, 100) > PluginSettings::get()->getSpawnChancePercent()){
			return;
		}

		$spawnPosition = NaturalSpawnHelper::findSpawnPosition($player, $category);
		if($spawnPosition === null){
			return;
		}

		$mobName = MobNaturalSpawnRegistry::rollRule($category);
		if($mobName === null){
			return;
		}

		$rule = MobNaturalSpawnRegistry::getRule($mobName);
		if($rule === null){
			return;
		}

		$packSize = mt_rand($rule["pack_min"], $rule["pack_max"]);
		$this->spawnPack($world, $spawnPosition, $mobName, $packSize, $category);
	}

	private function spawnPack(World $world, Vector3 $origin, string $mobName, int $packSize, string $category) : void{
		for($i = 0; $i < $packSize; $i++){
			$offset = $this->spreadOffset($origin, $i);
			$location = Location::fromObject($offset, $world, mt_rand(0, 359), 0);

			if(!NaturalSpawnHelper::isValidSpawn($world, $location, $category)){
				continue;
			}

			$entity = MobSpawnRegistry::create($mobName, $location);
			if($entity === null){
				continue;
			}

			$entity->spawnToAll();
			$this->mobRegistry->tryAttach($entity);
		}
	}

	private function spreadOffset(Vector3 $origin, int $index) : Vector3{
		if($index === 0){
			return $origin;
		}

		$angle = ($index * 137.5) * (M_PI / 180);
		$radius = 1.0 + ($index * 0.6);
		return new Vector3(
			$origin->x + cos($angle) * $radius,
			$origin->y,
			$origin->z + sin($angle) * $radius
		);
	}
}
