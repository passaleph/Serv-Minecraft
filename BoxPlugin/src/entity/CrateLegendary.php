<?php

declare(strict_types=1);

namespace entity;

class CrateLegendary extends CrateEntity {

    public static function getNetworkTypeId(): string {
        return "box:legendary";
    }

    public function getTier(): string {
        return "legendary";
    }
}
