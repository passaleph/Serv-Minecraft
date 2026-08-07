<?php

declare(strict_types=1);

namespace item;

use customiesdevs\customies\item\ItemComponents;
use customiesdevs\customies\item\ItemComponentsTrait;
use pocketmine\item\Item;
use pocketmine\item\ItemIdentifier;


class Bonbon extends Item implements ItemComponents {

    use ItemComponentsTrait;

    public function __construct(ItemIdentifier $identifier) {
        parent::__construct($identifier, "§apet candy");
        $this->initComponent("bonbon");
    }
}
