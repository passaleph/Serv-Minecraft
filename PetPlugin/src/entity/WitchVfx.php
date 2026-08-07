<?php

declare(strict_types=1);

namespace entity;

use pocketmine\entity\Living;
use pocketmine\entity\EntitySizeInfo;

class WitchVfx extends Living {

    public static function getNetworkTypeId(): string {
        return "witch:vfx";
    }

    public function getName(): string {
        return "WitchVfx";
    }

    protected function getInitialSizeInfo(): EntitySizeInfo {
        return new EntitySizeInfo(1.0, 1.0);
    }

    protected function getInitialGravity(): float {
        return 0.0;
    }
}
