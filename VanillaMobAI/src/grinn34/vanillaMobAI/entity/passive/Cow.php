<?php

declare(strict_types=1);

namespace grinn34\vanillaMobAI\entity\passive;

use pocketmine\entity\EntitySizeInfo;
use pocketmine\item\Item;
use pocketmine\nbt\tag\CompoundTag;
use grinn34\vanillaMobAI\item\VanillaMobSpawnEgg;
use pocketmine\item\VanillaItems;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use function mt_rand;

class Cow extends BreedablePassiveMob{
	public static function getNetworkTypeId() : string{
		return EntityIds::COW;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(1.4, 0.9);
	}

	protected function initEntity(CompoundTag $nbt) : void{
		$this->setMaxHealth(10);
		parent::initEntity($nbt);
	}

	public function getName() : string{
		return "Cow";
	}

	public function getDrops() : array{
		return [
			VanillaItems::LEATHER()->setCount(mt_rand(0, 2)),
			VanillaItems::RAW_BEEF()->setCount(mt_rand(1, 3))
		];
	}

	public function getXpDropAmount() : int{
		return mt_rand(1, 3);
	}

	public function getPickedItem() : ?Item{
		return VanillaMobSpawnEgg::create("cow");
	}
}
