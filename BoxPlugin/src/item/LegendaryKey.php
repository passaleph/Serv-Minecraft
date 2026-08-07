<?php

declare(strict_types=1);

namespace item;

use customiesdevs\customies\item\ItemComponents;
use customiesdevs\customies\item\ItemComponentsTrait;
use pocketmine\item\Item;
use pocketmine\item\ItemIdentifier;

class LegendaryKey extends Item implements ItemComponents {

    use ItemComponentsTrait;

    public function __construct(ItemIdentifier $identifier) {
        parent::__construct($identifier, "§5Clé Légendaire");
        $this->initComponent("legendary_key");
    }
}
