<?php

declare(strict_types=1);

namespace grinn34\vanillaMobAI\movement;

use pocketmine\block\Block;
use pocketmine\entity\Entity;
use pocketmine\entity\Living;
use pocketmine\math\Vector3;
use pocketmine\world\World;
use function atan2;
use function floor;
use function max;
use function sqrt;
use const M_PI;

final class MovementHelper{
	public const STEP_HEIGHT = 1.0;
	private const CHECK_DISTANCE = 0.6;
	private const CLIMB_VELOCITY = 0.34;

	private const OBSTACLE_NONE = 0;
	private const OBSTACLE_STEP = 1;
	private const OBSTACLE_WALL = 2;

	private function __construct(){}

	public static function configureEntity(Living $entity) : void{
		$entity->setStepHeight(self::STEP_HEIGHT);
	}

	public static function lookAt(Entity $entity, Vector3 $target, bool $includePitch = false) : void{
		$eye = $entity instanceof Living ? $entity->getEyePos() : $entity->getPosition();
		$dx = $target->x - $eye->x;
		$dz = $target->z - $eye->z;
		$yaw = atan2($dz, $dx) / M_PI * 180 - 90;

		$pitch = 0.0;
		if($includePitch){
			$dy = $target->y - $eye->y;
			$horizontal = sqrt($dx * $dx + $dz * $dz);
			if($horizontal > 0.001){
				$pitch = -atan2($dy, $horizontal) / M_PI * 180;
			}
		}

		$entity->setRotation($yaw, $pitch);
	}

	public static function navigateTowards(Living $entity, Vector3 $target, float $speedMultiplier = 1.0) : bool{
		if(self::shouldPreserveHorizontalMotion($entity)){
			return false;
		}

		$location = $entity->getLocation();
		$dx = $target->x - $location->x;
		$dz = $target->z - $location->z;
		$horizontalDistance = sqrt($dx * $dx + $dz * $dz);

		$feetY = (int) floor($location->y);
		$blockX = (int) floor($location->x);
		$blockZ = (int) floor($location->z);
		$world = $entity->getWorld();

		if($horizontalDistance < 0.001){
			if(
				$entity->canClimbWalls()
				&& $target->y > $location->y + 0.5
				&& self::hasAdjacentWall($world, $blockX, $feetY, $blockZ)
			){
				$entity->setMotion(new Vector3(0, max($entity->getMotion()->y, self::CLIMB_VELOCITY), 0));
				return true;
			}

			self::stopHorizontalMovement($entity);
			return true;
		}

		self::lookAt($entity, $target);
		$targetFeetY = (int) floor($target->y);
		$dirX = $dx / $horizontalDistance;
		$dirZ = $dz / $horizontalDistance;

		$obstacle = self::getDirectionObstacle(
			$world,
			$location->x,
			$feetY,
			$location->z,
			$dirX,
			$dirZ
		);

		if($entity->canClimbWalls() && $target->y > $location->y + 0.5){
			if(
				$obstacle === self::OBSTACLE_WALL
				|| self::hasAdjacentWall($world, $blockX, $feetY, $blockZ)
			){
				return self::applyWallClimbMotion($entity, $dirX, $dirZ, $speedMultiplier);
			}
		}

		if($obstacle === self::OBSTACLE_WALL && $targetFeetY <= $feetY && !$entity->canClimbWalls()){
			self::stopHorizontalMovement($entity);
			return false;
		}

		if($obstacle === self::OBSTACLE_WALL && $targetFeetY > $feetY && !$entity->canClimbWalls()){
			$obstacle = self::OBSTACLE_STEP;
		}

		$speed = max(0.15, $entity->getMovementSpeed()) * $speedMultiplier;
		$motion = $entity->getMotion();
		$motionY = $motion->y;

		if($entity->canClimbWalls() && $target->y > $location->y + 0.75 && $obstacle === self::OBSTACLE_STEP){
			$motionY = max($motionY, self::CLIMB_VELOCITY);
			$speed *= 0.65;
		}

		$entity->setMotion(new Vector3(
			$dirX * $speed,
			$motionY,
			$dirZ * $speed
		));

		return true;
	}

	public static function stopHorizontalMovement(Living $entity, bool $force = false) : void{
		if(!$force && self::shouldPreserveHorizontalMotion($entity)){
			return;
		}

		$motion = $entity->getMotion();
		$entity->setMotion(new Vector3(0, $motion->y, 0));
	}

	public static function shouldPreserveHorizontalMotion(Living $entity) : bool{
		if(MovementPause::isActive($entity)){
			return true;
		}

		$motion = $entity->getMotion();
		$walkSpeed = max(0.15, $entity->getMovementSpeed());
		$thresholdSq = ($walkSpeed * 2.0) * ($walkSpeed * 2.0);
		$horizontalSpeedSq = ($motion->x * $motion->x) + ($motion->z * $motion->z);

		return $horizontalSpeedSq > $thresholdSq;
	}

	private static function applyWallClimbMotion(Living $entity, float $dirX, float $dirZ, float $speedMultiplier) : bool{
		$speed = max(0.15, $entity->getMovementSpeed()) * $speedMultiplier;
		$location = $entity->getLocation();
		$world = $entity->getWorld();
		$feetY = (int) floor($location->y);
		$blockX = (int) floor($location->x);
		$blockZ = (int) floor($location->z);

		$touchingWall = self::hasAdjacentWall($world, $blockX, $feetY, $blockZ)
			|| self::getDirectionObstacle($world, $location->x, $feetY, $location->z, $dirX, $dirZ) === self::OBSTACLE_WALL;

		if(!$touchingWall){
			$entity->setMotion(new Vector3(
				$dirX * $speed,
				$entity->getMotion()->y,
				$dirZ * $speed
			));
			return true;
		}

		$entity->setMotion(new Vector3(
			$dirX * $speed * 0.15,
			max($entity->getMotion()->y, self::CLIMB_VELOCITY),
			$dirZ * $speed * 0.15
		));

		return true;
	}

	private static function hasAdjacentWall(World $world, int $blockX, int $feetY, int $blockZ) : bool{
		foreach([[1, 0], [-1, 0], [0, 1], [0, -1]] as [$offsetX, $offsetZ]){
			$wallX = $blockX + $offsetX;
			$wallZ = $blockZ + $offsetZ;

			if(
				self::isSolidAt($world, (float) $wallX, $feetY, (float) $wallZ)
				|| self::isSolidAt($world, (float) $wallX, $feetY + 1, (float) $wallZ)
			){
				return true;
			}
		}

		return false;
	}

	private static function getDirectionObstacle(World $world, float $x, int $feetY, float $z, float $dirX, float $dirZ) : int{
		$checkX = $x + $dirX * self::CHECK_DISTANCE;
		$checkZ = $z + $dirZ * self::CHECK_DISTANCE;

		$feetBlocked = self::isSolidAt($world, $checkX, $feetY, $checkZ);
		$headBlocked = self::isSolidAt($world, $checkX, $feetY + 1, $checkZ);

		if(!$feetBlocked && !$headBlocked){
			return self::OBSTACLE_NONE;
		}

		if($feetBlocked && !$headBlocked){
			return self::OBSTACLE_STEP;
		}

		return self::OBSTACLE_WALL;
	}

	private static function isSolidAt(World $world, float $x, int $y, float $z) : bool{
		return self::isSolidBlock($world->getBlockAt((int) floor($x), $y, (int) floor($z)));
	}

	private static function isSolidBlock(Block $block) : bool{
		return $block->isSolid();
	}

	public static function isWalkable(World $world, Vector3 $position) : bool{
		$x = (int) floor($position->x);
		$y = (int) floor($position->y);
		$z = (int) floor($position->z);

		$feet = $world->getBlockAt($x, $y, $z);
		$head = $world->getBlockAt($x, $y + 1, $z);
		$ground = $world->getBlockAt($x, $y - 1, $z);

		return !self::isSolidBlock($feet) && !self::isSolidBlock($head) && $ground->isSolid();
	}

	public static function findRandomWalkablePosition(Vector3 $origin, World $world, int $radius = 10, int $attempts = 12, int $yDist = 3) : ?Vector3{
		for($i = 0; $i < $attempts; $i++){
			$x = $origin->x + mt_rand(-$radius, $radius);
			$z = $origin->z + mt_rand(-$radius, $radius);
			$y = (int) floor($origin->y);

			for($yScan = 0; $yScan <= $yDist; $yScan++){
				$candidate = new Vector3($x, $y + $yScan, $z);
				if(self::isWalkable($world, $candidate)){
					return $candidate;
				}
			}
		}

		return null;
	}

	public static function horizontalDistanceSquared(Vector3 $a, Vector3 $b) : float{
		$dx = $a->x - $b->x;
		$dz = $a->z - $b->z;
		return $dx * $dx + $dz * $dz;
	}
}
