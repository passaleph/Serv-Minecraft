<?php

declare(strict_types=1);

namespace grinn34\vanillaMobAI\pathfinding;

use pocketmine\math\Vector3;
use pocketmine\world\World;
use function abs;
use function floor;

final class TraversabilityGrid{
	private function __construct(
		private readonly string $walkableData,
		private readonly string $solidData,
		private readonly int $originX,
		private readonly int $originY,
		private readonly int $originZ,
		private readonly int $sizeX,
		private readonly int $sizeY,
		private readonly int $sizeZ
	){}

	public static function capture(World $world, Vector3 $center, int $radius, int $yBelow, int $yAbove) : self{
		$centerX = (int) floor($center->x);
		$centerY = (int) floor($center->y);
		$centerZ = (int) floor($center->z);

		$originX = $centerX - $radius;
		$originZ = $centerZ - $radius;
		$originY = max($world->getMinY() + 1, $centerY - $yBelow);

		$sizeX = ($radius * 2) + 1;
		$sizeZ = ($radius * 2) + 1;
		$maxY = min($world->getMaxY() - 2, $centerY + $yAbove);
		$sizeY = max(1, $maxY - $originY + 1);

		$cellCount = $sizeX * $sizeY * $sizeZ;
		$walkableBytes = str_repeat("\0", $cellCount);
		$solidBytes = str_repeat("\0", $cellCount);

		for($y = 0; $y < $sizeY; $y++){
			$worldY = $originY + $y;
			for($z = 0; $z < $sizeZ; $z++){
				for($x = 0; $x < $sizeX; $x++){
					$worldX = $originX + $x;
					$worldZ = $originZ + $z;
					$index = self::index($x, $y, $z, $sizeX, $sizeY);
					$block = $world->getBlockAt($worldX, $worldY, $worldZ);

					if($block->isSolid()){
						$solidBytes[$index] = "\1";
					}

					if(self::isTraversable($world, $worldX, $worldY, $worldZ)){
						$walkableBytes[$index] = "\1";
					}
				}
			}
		}

		return new self($walkableBytes, $solidBytes, $originX, $originY, $originZ, $sizeX, $sizeY, $sizeZ);
	}

	public static function deserialize(string $packed) : self{
		$header = unpack("i7", substr($packed, 0, 28));
		$cellCount = $header[4] * $header[5] * $header[6];
		$offset = 28;
		$walkable = substr($packed, $offset, $cellCount);
		$offset += $cellCount;
		$solid = substr($packed, $offset, $cellCount);

		return new self(
			$walkable,
			$solid,
			$header[1],
			$header[2],
			$header[3],
			$header[4],
			$header[5],
			$header[6]
		);
	}

	public function serialize() : string{
		return pack("i7", 0, $this->originX, $this->originY, $this->originZ, $this->sizeX, $this->sizeY, $this->sizeZ)
			. $this->walkableData
			. $this->solidData;
	}

	public function getOriginX() : int{
		return $this->originX;
	}

	public function getOriginY() : int{
		return $this->originY;
	}

	public function getOriginZ() : int{
		return $this->originZ;
	}

	public function contains(int $x, int $y, int $z) : bool{
		return $x >= $this->originX && $x < $this->originX + $this->sizeX
			&& $y >= $this->originY && $y < $this->originY + $this->sizeY
			&& $z >= $this->originZ && $z < $this->originZ + $this->sizeZ;
	}

	public function isTraversableAt(int $x, int $y, int $z) : bool{
		if(!$this->contains($x, $y, $z)){
			return false;
		}

		$localX = $x - $this->originX;
		$localY = $y - $this->originY;
		$localZ = $z - $this->originZ;

		return $this->walkableData[self::index($localX, $localY, $localZ, $this->sizeX, $this->sizeY)] === "\1";
	}

	public function isSolidAt(int $x, int $y, int $z) : bool{
		if(!$this->contains($x, $y, $z)){
			return true;
		}

		$localX = $x - $this->originX;
		$localY = $y - $this->originY;
		$localZ = $z - $this->originZ;

		return $this->solidData[self::index($localX, $localY, $localZ, $this->sizeX, $this->sizeY)] === "\1";
	}

	/**
	 * @return array{int, int, int}|null
	 */
	public function resolveWalkableNear(int $x, int $y, int $z) : ?array{
		for($yScan = 0; $yScan <= 3; $yScan++){
			if($this->isTraversableAt($x, $y + $yScan, $z)){
				return [$x, $y + $yScan, $z];
			}
		}

		for($yScan = 1; $yScan <= 2; $yScan++){
			if($this->isTraversableAt($x, $y - $yScan, $z)){
				return [$x, $y - $yScan, $z];
			}
		}

		return null;
	}

	public function canStepUp(int $fromX, int $fromY, int $fromZ, int $toX, int $toY, int $toZ) : bool{
		if($toY - $fromY !== 1){
			return false;
		}

		return $this->isSolidAt($toX, $fromY, $toZ) && !$this->isSolidAt($fromX, $fromY + 2, $fromZ);
	}

	private static function index(int $x, int $y, int $z, int $sizeX, int $sizeY) : int{
		return $x + ($z * $sizeX) + ($y * $sizeX * $sizeY);
	}

	private static function isTraversable(World $world, int $x, int $y, int $z) : bool{
		$feet = $world->getBlockAt($x, $y, $z);
		$head = $world->getBlockAt($x, $y + 1, $z);

		if($feet->isSolid() || $head->isSolid()){
			return false;
		}

		if($world->getBlockAt($x, $y - 1, $z)->isSolid()){
			return true;
		}

		for($drop = 1; $drop <= 3; $drop++){
			if($world->getBlockAt($x, $y - 1 - $drop, $z)->isSolid()){
				return true;
			}
		}

		return false;
	}
}
