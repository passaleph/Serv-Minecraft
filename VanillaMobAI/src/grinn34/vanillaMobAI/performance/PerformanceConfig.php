<?php

declare(strict_types=1);

namespace grinn34\vanillaMobAI\performance;

use grinn34\vanillaMobAI\config\PluginSettings;

final class PerformanceConfig{
	private function __construct(){}

	public static function activationRange() : float{
		return PluginSettings::get()->getActivationRange();
	}

	public static function activationRangeSq() : float{
		return PluginSettings::get()->getActivationRangeSq();
	}

	public static function activeHostileTickInterval() : int{
		return PluginSettings::get()->getActiveHostileTickInterval();
	}

	public static function activePassiveTickInterval() : int{
		return PluginSettings::get()->getActivePassiveTickInterval();
	}

	public static function inactiveTickInterval() : int{
		return PluginSettings::get()->getInactiveTickInterval();
	}

	public static function maxBrainsPerTick() : int{
		return PluginSettings::get()->getMaxBrainsPerTick();
	}

	public static function syncPathfindsPerTick() : int{
		return PluginSettings::get()->getSyncPathfindsPerTick();
	}

	public static function pathfindQueueSize() : int{
		return PluginSettings::get()->getPathfindQueueSize();
	}

	public static function pathfindSnapshotRadius() : int{
		return PluginSettings::get()->getPathfindSnapshotRadius();
	}

	public static function pathfindSnapshotYBelow() : int{
		return PluginSettings::get()->getPathfindSnapshotYBelow();
	}

	public static function pathfindSnapshotYAbove() : int{
		return PluginSettings::get()->getPathfindSnapshotYAbove();
	}

	public static function losCheckInterval() : int{
		return PluginSettings::get()->getLosCheckInterval();
	}

	public static function targetScanInterval() : int{
		return PluginSettings::get()->getTargetScanInterval();
	}

	public static function walkLineCheckInterval() : int{
		return PluginSettings::get()->getWalkLineCheckInterval();
	}

	public static function waypointSkipInterval() : int{
		return PluginSettings::get()->getWaypointSkipInterval();
	}

	public static function mobCountCacheTtlTicks() : int{
		return PluginSettings::get()->getMobCountCacheTtlTicks();
	}

	public static function spawnPlayersPerTick() : int{
		return PluginSettings::get()->getSpawnPlayersPerTick();
	}
}
