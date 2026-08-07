<?php



declare(strict_types=1);



namespace grinn34\vanillaMobAI\registry;



use grinn34\vanillaMobAI\AIManager;
use grinn34\vanillaMobAI\config\PluginSettings;

use grinn34\vanillaMobAI\entity\hostile\HostileCreeper;

use grinn34\vanillaMobAI\entity\hostile\HostileSkeleton;

use grinn34\vanillaMobAI\entity\hostile\HostileSpider;

use grinn34\vanillaMobAI\entity\hostile\HostileZombie;

use grinn34\vanillaMobAI\entity\passive\Chicken;

use grinn34\vanillaMobAI\entity\passive\Cow;

use grinn34\vanillaMobAI\entity\passive\PassiveMob;

use grinn34\vanillaMobAI\entity\passive\Pig;

use grinn34\vanillaMobAI\entity\passive\Sheep;

use grinn34\vanillaMobAI\goals\BreedGoal;

use grinn34\vanillaMobAI\goals\CreeperSwellGoal;

use grinn34\vanillaMobAI\goals\LookAroundGoal;

use grinn34\vanillaMobAI\goals\MeleeAttackGoal;

use grinn34\vanillaMobAI\goals\PanicGoal;

use grinn34\vanillaMobAI\goals\RangedAttackGoal;

use grinn34\vanillaMobAI\goals\TemptGoal;

use grinn34\vanillaMobAI\goals\WanderGoal;

use grinn34\vanillaMobAI\MobBrain;

use grinn34\vanillaMobAI\movement\MovementHelper;

use pocketmine\entity\Entity;

use pocketmine\entity\Living;



final class MobRegistry{

	public function __construct(

		private readonly AIManager $aiManager

	){}



	public function tryAttach(Entity $entity) : bool{

		if(!PluginSettings::get()->isMobAiEnabled() || !$entity instanceof Living || $this->aiManager->hasBrain($entity)){

			return false;

		}



		$brain = $this->createBrainFor($entity);

		if($brain === null){

			return false;

		}



		MovementHelper::configureEntity($entity);

		$this->configureMovementSpeed($entity);

		$this->aiManager->register($entity, $brain);

		return true;

	}



	public function detach(Entity $entity) : void{

		$this->aiManager->unregister($entity);

	}



	private function createBrainFor(Living $entity) : ?MobBrain{

		return match(true){

			$entity instanceof PassiveMob => $this->createPassiveBrain($entity),

			MobHostileRegistry::isHostile($entity) => $this->createHostileBrain($entity),

			default => null

		};

	}



	private function createPassiveBrain(Living $entity) : MobBrain{

		$brain = new MobBrain($entity);

		$brain->addGoal(new PanicGoal());

		$brain->addGoal(new BreedGoal());

		$brain->addGoal(new TemptGoal());

		$brain->addGoal(new WanderGoal());

		$brain->addGoal(new LookAroundGoal());

		return $brain;

	}



	private function createHostileBrain(Living $entity) : MobBrain{

		$brain = new MobBrain($entity);



		match(MobCombatRegistry::getAttackType($entity)){

			MobCombatRegistry::ATTACK_RANGED => $brain->addGoal(new RangedAttackGoal()),

			MobCombatRegistry::ATTACK_SWELL => $brain->addGoal(new CreeperSwellGoal()),

			default => $brain->addGoal(new MeleeAttackGoal()),

		};



		$brain->addGoal(new WanderGoal());

		$brain->addGoal(new LookAroundGoal());

		return $brain;

	}



	private function configureMovementSpeed(Living $entity) : void{
		$combatSpeed = MobCombatRegistry::getMovementSpeed($entity);
		if($combatSpeed !== null){
			$entity->setMovementSpeed($combatSpeed);
			return;
		}

		match(true){
			$entity instanceof Cow => $entity->setMovementSpeed(0.2),
			$entity instanceof Pig => $entity->setMovementSpeed(0.25),
			$entity instanceof Sheep => $entity->setMovementSpeed(0.23),
			$entity instanceof Chicken => $entity->setMovementSpeed(0.25),
			$entity instanceof HostileZombie => $entity->setMovementSpeed(0.23),
			$entity instanceof HostileSpider => $entity->setMovementSpeed(0.25),
			$entity instanceof HostileCreeper => $entity->setMovementSpeed(0.25),
			default => null
		};
	}

}


