<?php

declare(strict_types=1);

namespace grinn34\vanillaMobAI\combat;

use grinn34\vanillaMobAI\pathfinding\LineOfSightHelper;
use grinn34\vanillaMobAI\registry\MobCombatRegistry;
use pocketmine\entity\Living;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\player\GameMode;
use pocketmine\player\Player;

final class CombatHelper{
	private function __construct(){}

	public static function getMeleeDamage(Living $attacker) : float{
		return MobCombatRegistry::getMeleeDamage($attacker);
	}

	public static function isWithinMeleeReach(Living $attacker, Living $victim) : bool{
		$reach = MobCombatRegistry::getMeleeReach($attacker, $victim);
		$attackerPos = $attacker->getPosition();
		$victimPos = $victim->getPosition();
		$dx = $attackerPos->x - $victimPos->x;
		$dy = $attackerPos->y - $victimPos->y;
		$dz = $attackerPos->z - $victimPos->z;

		return ($dx * $dx + $dy * $dy + $dz * $dz) <= ($reach * $reach);
	}

	public static function performMeleeAttack(Living $attacker, Living $victim) : void{
		if(!$attacker->isAlive() || !$victim->isAlive()){
			return;
		}

		if(!self::isWithinMeleeReach($attacker, $victim)){
			return;
		}

		CombatAnimationHelper::playMeleeSwing($attacker);

		$victim->attack(new EntityDamageByEntityEvent(
			$attacker,
			$victim,
			EntityDamageEvent::CAUSE_ENTITY_ATTACK,
			self::getMeleeDamage($attacker)
		));
	}

	public static function canBeHostileTarget(Player $player) : bool{
		return $player->isConnected()
			&& !$player->isClosed()
			&& !$player->isSpectator()
			&& !$player->getGamemode()->equals(GameMode::CREATIVE());
	}

	public static function findNearestPlayer(Living $entity, float $range) : ?Player{
		$world = $entity->getWorld();
		$rangeSq = $range * $range;

		$nearest = null;
		$nearestDist = $rangeSq;

		foreach($world->getPlayers() as $player){
			if(!self::canBeHostileTarget($player)){
				continue;
			}

			if($player->getWorld() !== $world){
				continue;
			}

			$dist = $entity->getPosition()->distanceSquared($player->getPosition());
			if($dist <= $nearestDist && self::hasLineOfSight($entity, $player)){
				$nearestDist = $dist;
				$nearest = $player;
			}
		}

		return $nearest;
	}

	public static function isValidTarget(Living $entity, Living $target, float $maxRange) : bool{
		if(!$target->isAlive() || $target->isClosed()){
			return false;
		}

		if($target->getWorld() !== $entity->getWorld()){
			return false;
		}

		if($target instanceof Player && !self::canBeHostileTarget($target)){
			return false;
		}

		return $entity->getPosition()->distanceSquared($target->getPosition()) <= ($maxRange * $maxRange);
	}

	public static function hasLineOfSight(Living $entity, Living $target) : bool{
		return LineOfSightHelper::hasLineOfSight(
			$entity->getWorld(),
			$entity->getEyePos(),
			$target->getEyePos()
		);
	}

	public static function canDetectTarget(Living $entity, Living $target, float $range) : bool{
		return self::isValidTarget($entity, $target, $range) && self::hasLineOfSight($entity, $target);
	}

	public static function canContinueTracking(Living $entity, Living $target, float $maxRange, int $unseenMemoryTicks) : bool{
		if(!self::isValidTarget($entity, $target, $maxRange)){
			return false;
		}

		return self::hasLineOfSight($entity, $target) || $unseenMemoryTicks > 0;
	}
}
