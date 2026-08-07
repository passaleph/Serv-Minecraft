<?php

declare(strict_types=1);

namespace grinn34\vanillaMobAI\entity;

use grinn34\vanillaMobAI\entity\hostile\HostileCreeper;
use grinn34\vanillaMobAI\entity\hostile\HostileSkeleton;
use grinn34\vanillaMobAI\entity\hostile\HostileSpider;
use grinn34\vanillaMobAI\entity\hostile\HostileZombie;
use grinn34\vanillaMobAI\entity\passive\Chicken;
use grinn34\vanillaMobAI\entity\passive\Cow;
use grinn34\vanillaMobAI\entity\passive\Pig;
use grinn34\vanillaMobAI\entity\passive\Sheep;
use pocketmine\entity\EntityDataHelper;
use pocketmine\entity\EntityFactory;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\world\World;

final class EntityRegistrar{
	private function __construct(){}

	public static function register() : void{
		$factory = EntityFactory::getInstance();

		$factory->register(Cow::class, static function(World $world, CompoundTag $nbt) : Cow{
			return new Cow(EntityDataHelper::parseLocation($nbt, $world), $nbt);
		}, ["Cow", "minecraft:cow"]);

		$factory->register(Pig::class, static function(World $world, CompoundTag $nbt) : Pig{
			return new Pig(EntityDataHelper::parseLocation($nbt, $world), $nbt);
		}, ["Pig", "minecraft:pig"]);

		$factory->register(Sheep::class, static function(World $world, CompoundTag $nbt) : Sheep{
			return new Sheep(EntityDataHelper::parseLocation($nbt, $world), $nbt);
		}, ["Sheep", "minecraft:sheep"]);

		$factory->register(Chicken::class, static function(World $world, CompoundTag $nbt) : Chicken{
			return new Chicken(EntityDataHelper::parseLocation($nbt, $world), $nbt);
		}, ["Chicken", "minecraft:chicken"]);

		$factory->register(HostileZombie::class, static function(World $world, CompoundTag $nbt) : HostileZombie{
			return new HostileZombie(EntityDataHelper::parseLocation($nbt, $world), $nbt);
		}, ["Zombie", "minecraft:zombie"]);

		$factory->register(HostileSkeleton::class, static function(World $world, CompoundTag $nbt) : HostileSkeleton{
			return new HostileSkeleton(EntityDataHelper::parseLocation($nbt, $world), $nbt);
		}, ["Skeleton", "minecraft:skeleton"]);

		$factory->register(HostileSpider::class, static function(World $world, CompoundTag $nbt) : HostileSpider{
			return new HostileSpider(EntityDataHelper::parseLocation($nbt, $world), $nbt);
		}, ["Spider", "minecraft:spider"]);

		$factory->register(HostileCreeper::class, static function(World $world, CompoundTag $nbt) : HostileCreeper{
			return new HostileCreeper(EntityDataHelper::parseLocation($nbt, $world), $nbt);
		}, ["Creeper", "minecraft:creeper"]);
	}
}
