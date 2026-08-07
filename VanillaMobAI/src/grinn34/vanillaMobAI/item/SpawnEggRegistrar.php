<?php

declare(strict_types=1);

namespace grinn34\vanillaMobAI\item;

use grinn34\vanillaMobAI\registry\MobSpawnEggRegistry;
use pocketmine\data\bedrock\item\SavedItemData;
use pocketmine\inventory\CreativeCategory;
use pocketmine\inventory\CreativeInventory;
use pocketmine\item\Item;
use pocketmine\item\StringToItemParser;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\world\format\io\GlobalItemDataHandlers;

final class SpawnEggRegistrar{
	private function __construct(){}

	public static function register() : void{
		$parser = StringToItemParser::getInstance();
		$creative = CreativeInventory::getInstance();
		$serializer = GlobalItemDataHandlers::getSerializer();
		$deserializer = GlobalItemDataHandlers::getDeserializer();

		foreach(MobSpawnEggRegistry::getAll() as $mobKey => $definition){
			$item = self::resolveSpawnEggItem($mobKey);
			$bedrockId = "minecraft:" . $definition["aliases"][0];

			if($mobKey !== "zombie" && $deserializer->getDeserializerForId($bedrockId) === null){
				$deserializer->map($bedrockId, fn() => VanillaMobSpawnEgg::createCustom($mobKey));
				$serializer->map($item, fn() => new SavedItemData($bedrockId));
			}

			foreach($definition["aliases"] as $alias){
				if($parser->parse($alias) !== null){
					continue;
				}

				try{
					$parser->register($alias, fn() => self::resolveSpawnEggItem($mobKey));
				}catch(\InvalidArgumentException){
				}
			}

			if(!$creative->contains($item)){
				$creative->add($item, CreativeCategory::NATURE);
			}
		}

		self::syncCreativeForAllPlayers();
	}

	public static function syncCreativeForPlayer(Player $player) : void{
		$player->getNetworkSession()->getInvManager()?->syncCreative();
	}

	private static function syncCreativeForAllPlayers() : void{
		foreach(Server::getInstance()->getOnlinePlayers() as $player){
			self::syncCreativeForPlayer($player);
		}
	}

	public static function resolveSpawnEggItem(string $mobKey) : Item{
		if($mobKey === "zombie"){
			return VanillaItems::ZOMBIE_SPAWN_EGG();
		}

		return VanillaMobSpawnEgg::createCustom($mobKey);
	}
}
