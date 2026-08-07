<?php

declare(strict_types=1);

namespace grinn34\vanillaMobAI\spawner;

use grinn34\vanillaMobAI\config\PluginSettings;
use grinn34\vanillaMobAI\registry\MobRegistry;
use pocketmine\block\tile\MonsterSpawner;
use pocketmine\plugin\Plugin;
use pocketmine\scheduler\ClosureTask;
use pocketmine\scheduler\TaskHandler;

final class MonsterSpawnerManager{
	private ?TaskHandler $taskHandler = null;

	public function __construct(
		private readonly Plugin $plugin,
		private readonly MobRegistry $mobRegistry
	){}

	public function start() : void{
		if(!PluginSettings::get()->isMonsterSpawnersEnabled()){
			return;
		}

		$interval = PluginSettings::get()->getSpawnerTickInterval();
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
	}

	private function tick() : void{
		$settings = PluginSettings::get();
		$chunkRadius = $settings->getSpawnerChunkScanRadius();
		$processed = [];

		foreach($this->plugin->getServer()->getOnlinePlayers() as $player){
			if(!$player->isConnected() || $player->isClosed() || $player->isSpectator()){
				continue;
			}

			$world = $player->getWorld();
			if(!$settings->allowsSpawnerWorld($world)){
				continue;
			}

			$chunkX = $player->getPosition()->getFloorX() >> 4;
			$chunkZ = $player->getPosition()->getFloorZ() >> 4;

			for($dx = -$chunkRadius; $dx <= $chunkRadius; $dx++){
				for($dz = -$chunkRadius; $dz <= $chunkRadius; $dz++){
					$targetX = $chunkX + $dx;
					$targetZ = $chunkZ + $dz;
					$key = $world->getId() . ":" . $targetX . ":" . $targetZ;

					if(isset($processed[$key]) || !$world->isChunkLoaded($targetX, $targetZ)){
						continue;
					}

					$processed[$key] = true;
					$chunk = $world->getChunk($targetX, $targetZ);

					foreach($chunk->getTiles() as $tile){
						if($tile instanceof MonsterSpawner){
							MonsterSpawnerHelper::tick($tile, $this->mobRegistry);
						}
					}
				}
			}
		}
	}
}
