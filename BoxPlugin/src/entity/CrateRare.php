<?php

declare(strict_types=1);

namespace entity;

class CrateRare extends CrateEntity {

    public static function getNetworkTypeId(): string {
        return "box:rare";
    }

    public function getTier(): string {
        return "rare";
    }
}
