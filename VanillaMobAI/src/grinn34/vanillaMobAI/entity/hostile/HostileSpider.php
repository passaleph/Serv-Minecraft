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
use function mt_rand;

class HostileSpider extends Living implements HostileMob, AggressivePoseCapable{
	private bool $aggressivePose = false;

	public static function getNetworkTypeId() : string{
		return EntityIds::SPIDER;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(0.9, 1.4);
	}

	protected function initEntity(CompoundTag $nbt) : void{
		$this->setMaxHealth(16);
		$this->setCanClimb(true);
		$this->setCanClimbWalls(true);
		parent::initEntity($nbt);
	}

	public function getName() : string{
		return "Spider";
	}

	public function getDrops() : array{
		$drops = [
			VanillaItems::STRING()->setCount(mt_rand(0, 2))
		];

		if(mt_rand(1, 100) <= 33){
			$drops[] = VanillaItems::SPIDER_EYE();
		}

		return $drops;
	}

	public function getXpDropAmount() : int{
		return 5;
	}

	public function getPickedItem() : ?Item{
		return VanillaMobSpawnEgg::create("spider");
	}

	public function setAggressivePose(bool $aggressive) : void{
		if($this->aggressivePose === $aggressive){
			return;
		}

		$this->aggressivePose = $aggressive;
		$this->networkPropertiesDirty = true;
	}

	protected function syncNetworkData(\pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);
		$properties->setGenericFlag(\pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags::DELAYED_ATTACKING, $this->aggressivePose);
	}
}
