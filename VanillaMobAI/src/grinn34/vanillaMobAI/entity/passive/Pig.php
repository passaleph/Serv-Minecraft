<?php

declare(strict_types=1);

namespace grinn34\vanillaMobAI\entity\passive;

use pocketmine\entity\EntitySizeInfo;
use pocketmine\item\Item;
use grinn34\vanillaMobAI\item\VanillaMobSpawnEgg;
use pocketmine\item\VanillaItems;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use function mt_rand;

class Pig extends BreedablePassiveMob{
	public static function getNetworkTypeId() : string{
		return EntityIds::PIG;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(0.9, 0.9);
	}

	protected function initEntity(CompoundTag $nbt) : void{
		$this->setMaxHealth(10);
		parent::initEntity($nbt);
	}

	public function getName() : string{
		return "Pig";
	}

	public function getDrops() : array{
		return [
			VanillaItems::RAW_PORKCHOP()->setCount(mt_rand(1, 3))
		];
	}

	public function getXpDropAmount() : int{
		return mt_rand(1, 3);
	}

	public function getPickedItem() : ?Item{
		return VanillaMobSpawnEgg::create("pig");
	}
}
