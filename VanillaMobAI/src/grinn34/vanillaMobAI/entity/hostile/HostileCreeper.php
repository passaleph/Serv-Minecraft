<?php

declare(strict_types=1);

namespace grinn34\vanillaMobAI\entity\hostile;

use grinn34\vanillaMobAI\item\VanillaMobSpawnEgg;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Living;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use function mt_rand;

class HostileCreeper extends Living implements HostileMob, SwellingCapable{
	private bool $swelling = false;

	public static function getNetworkTypeId() : string{
		return EntityIds::CREEPER;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(1.7, 0.6);
	}

	protected function initEntity(CompoundTag $nbt) : void{
		$this->setMaxHealth(20);
		parent::initEntity($nbt);
	}

	public function getName() : string{
		return "Creeper";
	}

	public function getDrops() : array{
		return [
			VanillaItems::GUNPOWDER()->setCount(mt_rand(0, 2))
		];
	}

	public function getXpDropAmount() : int{
		return 5;
	}

	public function getPickedItem() : ?Item{
		return VanillaMobSpawnEgg::create("creeper");
	}

	public function setSwelling(bool $swelling) : void{
		if($this->swelling === $swelling){
			return;
		}

		$this->swelling = $swelling;
		$this->networkPropertiesDirty = true;
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);
		$properties->setGenericFlag(EntityMetadataFlags::IGNITED, $this->swelling);
	}
}
