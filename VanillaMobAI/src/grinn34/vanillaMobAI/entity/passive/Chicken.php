<?php

declare(strict_types=1);

namespace grinn34\vanillaMobAI\entity\passive;

use grinn34\vanillaMobAI\interactions\InteractionHelper;
use grinn34\vanillaMobAI\registry\MobInteractionRegistry;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\item\Item;
use grinn34\vanillaMobAI\item\VanillaMobSpawnEgg;
use pocketmine\item\VanillaItems;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\world\sound\PopSound;
use function mt_rand;

class Chicken extends BreedablePassiveMob{
	private int $eggLayCooldown;

	public static function getNetworkTypeId() : string{
		return EntityIds::CHICKEN;
	}

	protected function getInitialSizeInfo() : EntitySizeInfo{
		return new EntitySizeInfo(0.7, 0.4);
	}

	protected function initEntity(CompoundTag $nbt) : void{
		$this->setMaxHealth(4);
		$this->eggLayCooldown = mt_rand(
			MobInteractionRegistry::CHICKEN_EGG_LAY_MIN_TICKS,
			MobInteractionRegistry::CHICKEN_EGG_LAY_MAX_TICKS
		);
		parent::initEntity($nbt);
	}

	public function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);

		if(!$this->isBaby() && $this->isOnGround()){
			$this->eggLayCooldown -= $tickDiff;
			if($this->eggLayCooldown <= 0){
				$this->layEgg();
				$this->eggLayCooldown = mt_rand(
					MobInteractionRegistry::CHICKEN_EGG_LAY_MIN_TICKS,
					MobInteractionRegistry::CHICKEN_EGG_LAY_MAX_TICKS
				);
				$hasUpdate = true;
			}
		}

		return $hasUpdate;
	}

	private function layEgg() : void{
		$position = $this->getPosition()->add(
			(mt_rand() / mt_getrandmax() - 0.5) * 0.3,
			0.2,
			(mt_rand() / mt_getrandmax() - 0.5) * 0.3
		);
		InteractionHelper::dropItem($this->getWorld(), $position, VanillaItems::EGG());
		$this->getWorld()->addSound($position, new PopSound());
	}

	public function getName() : string{
		return "Chicken";
	}

	public function getDrops() : array{
		return [
			VanillaItems::FEATHER()->setCount(mt_rand(0, 2)),
			VanillaItems::RAW_CHICKEN()->setCount(1)
		];
	}

	public function getXpDropAmount() : int{
		return mt_rand(1, 3);
	}

	public function getPickedItem() : ?Item{
		return VanillaMobSpawnEgg::create("chicken");
	}
}
