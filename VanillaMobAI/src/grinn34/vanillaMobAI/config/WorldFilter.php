<?php

declare(strict_types=1);

namespace grinn34\vanillaMobAI\config;

use pocketmine\world\World;
use function in_array;
use function strtolower;

final class WorldFilter{
	private function __construct(){}

	public static function allows(string $mode, array $whitelist, array $blacklist, World $world) : bool{
		$folder = strtolower($world->getFolderName());
		$whitelist = array_map(strtolower(...), $whitelist);
		$blacklist = array_map(strtolower(...), $blacklist);

		return match(strtolower($mode)){
			"whitelist" => $whitelist !== [] && in_array($folder, $whitelist, true),
			"blacklist" => !in_array($folder, $blacklist, true),
			default => !in_array($folder, $blacklist, true),
		};
	}
}
