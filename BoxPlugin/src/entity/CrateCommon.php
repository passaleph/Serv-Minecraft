<?php

declare(strict_types=1);

namespace entity;

class CrateCommon extends CrateEntity {

    public static function getNetworkTypeId(): string {
        return "box:common";
    }

    public function getTier(): string {
        return "common";
    }
}
