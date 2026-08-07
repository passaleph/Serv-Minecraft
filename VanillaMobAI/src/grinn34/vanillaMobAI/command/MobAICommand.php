<?php

declare(strict_types=1);

namespace grinn34\vanillaMobAI\command;

use grinn34\vanillaMobAI\Main;
use grinn34\vanillaMobAI\pathfinding\PathfindingService;
use grinn34\vanillaMobAI\registry\MobSpawnEggRegistry;
use grinn34\vanillaMobAI\registry\MobSpawnRegistry;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat;
use function implode;
use function strtolower;

final class MobAICommand extends Command{
	public function __construct(
		private readonly Main $plugin
	){
		parent::__construct(
			"mobai",
			"VanillaMobAI administration",
			"/mobai <stats|reload>"
		);
		$this->setPermission("vanillaMobAI.command");
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args) : bool{
		if(!$this->testPermission($sender)){
			return false;
		}

		if($args === []){
			$sender->sendMessage(TextFormat::RED . "Usage: /mobai <stats|reload>");
			return false;
		}

		return match(strtolower($args[0])){
			"stats" => $this->handleStats($sender),
			"reload" => $this->handleReload($sender),
			default => $this->sendUsage($sender),
		};
	}

	private function sendUsage(CommandSender $sender) : bool{
		$sender->sendMessage(TextFormat::RED . "Usage: /mobai <stats|reload>");
		return false;
	}

	private function handleReload(CommandSender $sender) : bool{
		$this->plugin->reloadPluginSettings();
		$sender->sendMessage(TextFormat::GREEN . "VanillaMobAI config reloaded. Spawn managers restarted.");
		$sender->sendMessage(TextFormat::GRAY . "Mob AI / listener toggles need a full plugin restart.");
		return true;
	}

	private function handleStats(CommandSender $sender) : bool{
		$settings = $this->plugin->getPluginSettings();
		$ai = $this->plugin->getAIManager();
		$types = MobSpawnRegistry::getNames();
		$eggAliases = [];
		foreach(MobSpawnEggRegistry::getAll() as $definition){
			$eggAliases[] = $definition["aliases"][0];
		}

		$sender->sendMessage(TextFormat::DARK_GREEN . "VanillaMobAI " . $this->plugin->getDescription()->getVersion());
		$sender->sendMessage(TextFormat::GRAY . "Features: "
			. "AI=" . ($settings->isMobAiEnabled() ? "on" : "off")
			. TextFormat::GRAY . " spawn=" . ($settings->isNaturalSpawnEnabled() ? "on" : "off")
			. TextFormat::GRAY . " spawners=" . ($settings->isMonsterSpawnersEnabled() ? "on" : "off"));
		$sender->sendMessage(TextFormat::GRAY . "Active entities: " . TextFormat::WHITE . $ai->getTrackedCount());
		$sender->sendMessage(TextFormat::GRAY . "AI ticked last tick: " . TextFormat::WHITE . $ai->getLastTickedBrains()
			. TextFormat::GRAY . " | stagger skip: " . TextFormat::WHITE . $ai->getLastSkippedStagger()
			. TextFormat::GRAY . " | budget skip: " . TextFormat::WHITE . $ai->getLastSkippedBudget());

		try{
			$pathfinding = PathfindingService::get();
			$sender->sendMessage(TextFormat::GRAY . "Pathfind sync/tick: " . TextFormat::WHITE . $pathfinding->getSyncPathfindsThisTick()
				. TextFormat::GRAY . " | queue: " . TextFormat::WHITE . $pathfinding->getPendingSyncQueueSize()
				. TextFormat::GRAY . " | async: " . TextFormat::WHITE . $pathfinding->getAsyncInFlight());
		}catch(\RuntimeException){
		}

		$sender->sendMessage(TextFormat::GRAY . "Supported mobs: " . TextFormat::WHITE . implode(", ", $types));
		$sender->sendMessage(TextFormat::GRAY . "Spawn eggs: " . TextFormat::WHITE . implode(", ", $eggAliases));
		return true;
	}
}
