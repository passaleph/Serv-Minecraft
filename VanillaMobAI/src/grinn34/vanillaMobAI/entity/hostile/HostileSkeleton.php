<?php

declare(strict_types=1);

namespace grinn34\vanillaMobAI\entity\hostile;

use grinn34\vanillaMobAI\item\VanillaMobSpawnEgg;
use grinn34\vanillaMobAI\movement\MobEquipmentHelper;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Living;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use function mt_rand;

class HostileSkeleton extends Living implements HostileMob, AggressivePoseCapable{
	private bool $aggressivePose = false;

	public static function getNetworkTypeId() : string{
		return EntityIds::SKELETON;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(1.9, 0.6);
	}

	protected function initEntity(CompoundTag $nbt) : void{
		$this->setMaxHealth(20);
		parent::initEntity($nbt);
	}

	public function spawnTo(Player $player) : void{
		parent::spawnTo($player);
		MobEquipmentHelper::syncMainHand($this, VanillaItems::BOW());
	}

	public function getName() : string{
		return "Skeleton";
	}

	public function getDrops() : array{
		return [
			VanillaItems::BONE()->setCount(mt_rand(0, 2)),
			VanillaItems::ARROW()->setCount(mt_rand(0, 2))
		];
	}

	public function getXpDropAmount() : int{
		return 5;
	}

	public function getPickedItem() : ?Item{
		return VanillaMobSpawnEgg::create("skeleton");
	}

	public function setAggressivePose(bool $aggressive) : void{
		if($this->aggressivePose === $aggressive){
			return;
		}

		$this->aggressivePose = $aggressive;
		$this->networkPropertiesDirty = true;
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);
		$properties->setGenericFlag(EntityMetadataFlags::FACING_TARGET_TO_RANGE_ATTACK, $this->aggressivePose);
	}
}
