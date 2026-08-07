<?php

declare(strict_types=1);

namespace grinn34\vanillaMobAI;

use grinn34\vanillaMobAI\command\MobAICommand;
use grinn34\vanillaMobAI\config\PluginSettings;
use grinn34\vanillaMobAI\entity\EntityRegistrar;
use grinn34\vanillaMobAI\item\SpawnEggRegistrar;
use grinn34\vanillaMobAI\listener\BreedingListener;
use grinn34\vanillaMobAI\listener\CreativeInventorySyncListener;
use grinn34\vanillaMobAI\listener\EntityLifecycleListener;
use grinn34\vanillaMobAI\listener\MobInteractionListener;
use grinn34\vanillaMobAI\listener\SpawnEggListener;
use grinn34\vanillaMobAI\registry\MobRegistry;
use grinn34\vanillaMobAI\spawner\MonsterSpawnerManager;
use grinn34\vanillaMobAI\spawning\NaturalSpawnManager;
use pocketmine\plugin\PluginBase;

final class Main extends PluginBase{
	private static self $instance;

	private AIManager $aiManager;
	private MobRegistry $mobRegistry;
	private NaturalSpawnManager $naturalSpawnManager;
	private MonsterSpawnerManager $monsterSpawnerManager;

	public static function getInstance() : self{
		return self::$instance;
	}

	public function getAIManager() : AIManager{
		return $this->aiManager;
	}

	public function getMobRegistry() : MobRegistry{
		return $this->mobRegistry;
	}

	public function getPluginSettings() : PluginSettings{
		return PluginSettings::get();
	}

	public function reloadPluginSettings() : void{
		PluginSettings::load($this);

		$this->naturalSpawnManager->shutdown();
		$this->monsterSpawnerManager->shutdown();
		$this->naturalSpawnManager->start();
		$this->monsterSpawnerManager->start();
	}

	protected function onLoad() : void{
		self::$instance = $this;
	}

	protected function onEnable() : void{
		PluginSettings::load($this);

		EntityRegistrar::register();
		if(PluginSettings::get()->isSpawnEggsEnabled()){
			SpawnEggRegistrar::register();
		}

		$this->aiManager = new AIManager();
		$this->mobRegistry = new MobRegistry($this->aiManager);
		$this->naturalSpawnManager = new NaturalSpawnManager($this, $this->mobRegistry);
		$this->monsterSpawnerManager = new MonsterSpawnerManager($this, $this->mobRegistry);

		$this->getServer()->getPluginManager()->registerEvents(
			new EntityLifecycleListener($this->mobRegistry, $this->aiManager),
			$this
		);

		if(PluginSettings::get()->isSpawnEggsEnabled()){
			$this->getServer()->getPluginManager()->registerEvents(new SpawnEggListener($this->mobRegistry), $this);
			$this->getServer()->getPluginManager()->registerEvents(new CreativeInventorySyncListener(), $this);
		}

		if(PluginSettings::get()->isMobInteractionsEnabled()){
			$this->getServer()->getPluginManager()->registerEvents(new MobInteractionListener(), $this);
		}

		if(PluginSettings::get()->isBreedingEnabled()){
			$this->getServer()->getPluginManager()->registerEvents(new BreedingListener(), $this);
		}

		$commandMap = $this->getServer()->getCommandMap();
		$existing = $commandMap->getCommand("mobai");
		if($existing !== null){
			$commandMap->unregister($existing);
		}
		$commandMap->register("mobai", new MobAICommand($this));

		if(PluginSettings::get()->isMobAiEnabled()){
			$this->aiManager->start($this);
		}

		$this->naturalSpawnManager->start();
		$this->monsterSpawnerManager->start();

		foreach($this->getServer()->getWorldManager()->getWorlds() as $world){
			foreach($world->getEntities() as $entity){
				$this->mobRegistry->tryAttach($entity);
			}
		}

		$this->getLogger()->info("VanillaMobAI enabled.");
	}

	protected function onDisable() : void{
		$this->monsterSpawnerManager->shutdown();
		$this->naturalSpawnManager->shutdown();
		$this->aiManager->shutdown();
	}
}
