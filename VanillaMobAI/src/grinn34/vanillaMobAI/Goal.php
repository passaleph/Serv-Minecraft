<?php

declare(strict_types=1);

namespace grinn34\vanillaMobAI;

interface Goal{
	public function getPriority() : int;

	public function canUse(MobBrain $brain) : bool;

	public function canContinue(MobBrain $brain) : bool;

	public function onStart(MobBrain $brain) : void;

	public function onStop(MobBrain $brain) : void;

	public function tick(MobBrain $brain) : void;

	public function tickAlways() : void;
}
