<?php

declare(strict_types=1);

namespace grinn34\vanillaMobAI\entity\hostile;

interface AggressivePoseCapable{
	public function setAggressivePose(bool $aggressive) : void;
}
