<?php

declare(strict_types=1);

namespace grinn34\vanillaMobAI\entity\passive;

use pocketmine\entity\Living;
use pocketmine\entity\Location;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\world\particle\HeartParticle;

abstract class BreedablePassiveMob extends Living implements BreedableMob{
	private const TAG_AGE = "Age";
	private const TAG_IN_LOVE = "InLove";

	private const LOVE_DURATION = 600;
	private const BREED_COOLDOWN = 6000;
	private const BABY_AGE = -24000;
	private const BABY_SCALE = 0.5;

	private bool $baby = false;
	private int $age = 0;
	private int $loveTicks = 0;
	private int $breedCooldown = 0;
	private int $heartParticleCooldown = 0;

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);

		$this->age = $nbt->getInt(self::TAG_AGE, 0);
		$this->baby = $this->age < 0;
		$this->loveTicks = $nbt->getShort(self::TAG_IN_LOVE, 0);

		if($this->baby){
			$this->setScale(self::BABY_SCALE);
		}
	}

	public function saveNBT() : CompoundTag{
		$nbt = parent::saveNBT();
		$nbt->setInt(self::TAG_AGE, $this->age);

		if($this->loveTicks > 0){
			$nbt->setShort(self::TAG_IN_LOVE, $this->loveTicks);
		}

		return $nbt;
	}

	protected function syncNetworkData(EntityMetadataCollection $properties) : void{
		parent::syncNetworkData($properties);
		$properties->setGenericFlag(EntityMetadataFlags::BABY, $this->baby);
	}

	public function entityBaseTick(int $tickDiff = 1) : bool{
		$hasUpdate = parent::entityBaseTick($tickDiff);

		if($this->breedCooldown > 0){
			$this->breedCooldown = max(0, $this->breedCooldown - $tickDiff);
		}

		if($this->loveTicks > 0){
			$this->loveTicks = max(0, $this->loveTicks - $tickDiff);
			$this->heartParticleCooldown -= $tickDiff;
			if($this->heartParticleCooldown <= 0){
				$this->heartParticleCooldown = 35;
				$this->getWorld()->addParticle(
					$this->getPosition()->add(0, $this->getSize()->getHeight() * 0.8, 0),
					new HeartParticle()
				);
			}
		}else{
			$this->heartParticleCooldown = 0;
		}

		if($this->baby && $this->age < 0){
			$this->age += $tickDiff;
			if($this->age >= 0){
				$this->setBaby(false);
			}
			$hasUpdate = true;
		}

		return $hasUpdate;
	}

	public function isBaby() : bool{
		return $this->baby;
	}

	public function isInLove() : bool{
		return $this->loveTicks > 0;
	}

	public function getLoveTicks() : int{
		return $this->loveTicks;
	}

	public function canEnterLoveMode() : bool{
		return !$this->baby && $this->loveTicks <= 0 && $this->breedCooldown <= 0;
	}

	public function canBreed() : bool{
		return !$this->baby && $this->breedCooldown <= 0 && $this->loveTicks > 0;
	}

	public function enterLoveMode() : void{
		if(!$this->canEnterLoveMode()){
			return;
		}

		$this->loveTicks = self::LOVE_DURATION;
	}

	public function finishBreeding() : void{
		$this->loveTicks = 0;
		$this->breedCooldown = self::BREED_COOLDOWN;
	}

	public function setBaby(bool $baby) : void{
		$this->baby = $baby;

		if($baby){
			$this->age = self::BABY_AGE;
			$this->setScale(self::BABY_SCALE);
		}else{
			$this->age = 0;
			$this->setScale(1.0);
		}

		$this->networkPropertiesDirty = true;
	}

	public function spawnChild(Location $location) : self{
		/** @var self $child */
		$child = new static($location);
		$child->setBaby(true);
		return $child;
	}
}
