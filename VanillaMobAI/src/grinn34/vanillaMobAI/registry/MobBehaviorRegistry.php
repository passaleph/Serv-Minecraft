<?php

declare(strict_types=1);

namespace grinn34\vanillaMobAI\registry;

final class MobBehaviorRegistry{
	/** Bedrock minecraft:behavior.random_stroll */
	public const STROLL_INTERVAL = 120;
	public const STROLL_XZ_DIST = 10;
	public const STROLL_Y_DIST = 7;
	public const STROLL_MAX_TICKS = 200;

	/** Bedrock minecraft:behavior.random_look_around */
	public const LOOK_AROUND_ROLL = 50;
	public const LOOK_AROUND_MIN_TICKS = 20;
	public const LOOK_AROUND_MAX_TICKS = 40;

	/** Bedrock minecraft:behavior.look_at_player (skeleton priority 7) */
	public const LOOK_AT_PLAYER_DISTANCE = 8.0;

	private function __construct(){}
}
