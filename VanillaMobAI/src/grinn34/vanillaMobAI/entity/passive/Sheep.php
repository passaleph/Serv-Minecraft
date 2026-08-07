<?php

declare(strict_types=1);

namespace grinn34\vanillaMobAI\entity\passive;

use grinn34\vanillaMobAI\interactions\DyeColorHelper;
use grinn34\vanillaMobAI\interactions\InteractionHelper;
use grinn34\vanillaMobAI\registry\MobInteractionRegistry;
use pocketmine\block\utils\DyeColor;
use pocketmine\block\VanillaBlocks;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Location;
use pocketmine\item\Item;
use grinn34\vanillaMobAI\item\VanillaMobSpawnEgg;
use pocketmine\item\VanillaItems;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\player\Player;
use function mt_rand;

class Sheep extends BreedablePassiveMob{
	private const TAG_SHEARED = "Sheared";
	private const TAG_COLOR = "Color";

	private bool $sheared = false;
	private int $colorId = 0;
	private int $woolRegrowTicks = 0;

	public static function getNetworkTypeId() : string{
		return EntityIds::SHEEP;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(1.3, 0.9);
	}

	protected function initEntity(CompoundTag $nbt) : void{
		$this->setMaxHealth(8);
		$this->sheared = $nbt->getByte(self::TAG_SHEARED, 0) === 1;
		$this->colorId = max(0, min(15, $nbt->getByte(self::TAG_COLOR, $this->rollNaturalColor())));
		parent::initEntity($nbt);
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();
		$nbt->setByte(self::TAG_SHEARED, $this->sheared ? 1 : 0);
		$nbt->setByte(self::TAG_COLOR, $this->colorId);
		return $nbt;
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);
		$properties->setGenericFlag(EntityMetadataFlags::SHEARED, $this->sheared);
		$properties->setByte(EntityMetadataProperties::COLOR, $this->colorId);
	}

	public function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);

		if($this->sheared && $this->woolRegrowTicks > 0){
			$this->woolRegrowTicks = max(0, $this->woolRegrowTicks - $tickDiff);
			if($this->woolRegrowTicks === 0){
				$this->setSheared(false);
				$hasUpdate = true;
			}
		}

		return $hasUpdate;
	}

	public function isSheared() : bool{
		return $this->sheared;
	}

	public function getCoatColorId() : int{
		return $this->colorId;
	}

	public function setCoatColor(DyeColor $color) : void{
		$this->setCoatColorId(DyeColorHelper::toColorId($color));
	}

	public function inheritCoatFrom(Sheep $first, Sheep $second) : void{
		if($first->getCoatColorId() === $second->getCoatColorId()){
			$this->setCoatColorId($first->getCoatColorId());
			return;
		}

		$this->setCoatColorId(mt_rand(0, 1) === 0 ? $first->getCoatColorId() : $second->getCoatColorId());
	}

	public function dye(DyeColor $color) : bool{
		$newColorId = DyeColorHelper::toColorId($color);
		if($this->colorId === $newColorId){
			return true;
		}

		$this->setCoatColorId($newColorId);
		return true;
	}

	public function spawnChild(Location $location) : self{
		$child = parent::spawnChild($location);
		$child->setCoatColorId($this->colorId);
		return $child;
	}

	public function canShear() : bool{
		return !$this->isBaby() && !$this->sheared;
	}

	public function shear(Player $player) : bool{
		if(!$this->canShear()){
			return false;
		}

		$wool = VanillaBlocks::WOOL()->setColor($this->getDyeColor())->asItem()->setCount(mt_rand(1, 3));
		InteractionHelper::dropItem(
			$this->getWorld(),
			$this->getPosition()->add(0, $this->getSize()->getHeight() * 0.5, 0),
			$wool
		);

		$this->setSheared(true);
		$this->woolRegrowTicks = MobInteractionRegistry::SHEEP_WOOL_REGROW_TICKS;
		return true;
	}

	private function setCoatColorId(int $colorId) : void{
		$colorId = max(0, min(15, $colorId));
		if($this->colorId === $colorId){
			return;
		}

		$this->colorId = $colorId;
		$this->networkPropertiesDirty = true;
	}

	private function setSheared(bool $sheared) : void{
		if($this->sheared === $sheared){
			return;
		}

		$this->sheared = $sheared;
		$this->networkPropertiesDirty = true;
	}

	private function getDyeColor() : DyeColor{
		return DyeColorHelper::fromColorId($this->colorId);
	}

	private function rollNaturalColor() : int{
		return mt_rand(0, 15);
	}

	public function getName() : string{
		return "Sheep";
	}

	public function getDrops() : array{
		if($this->sheared){
			return [
				VanillaItems::RAW_MUTTON()->setCount(1)
			];
		}

		return [
			VanillaBlocks::WOOL()->setColor($this->getDyeColor())->asItem()->setCount(mt_rand(1, 2)),
			VanillaItems::RAW_MUTTON()->setCount(1)
		];
	}

	public function getXpDropAmount() : int{
		return mt_rand(1, 3);
	}

	public function getPickedItem() : ?Item{
		return VanillaMobSpawnEgg::create("sheep");
	}
}
