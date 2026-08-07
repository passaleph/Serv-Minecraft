<?php

declare(strict_types=1);

namespace item;

use customiesdevs\customies\item\ItemComponents;
use customiesdevs\customies\item\ItemComponentsTrait;
use pocketmine\item\Item;
use pocketmine\item\ItemIdentifier;

class CommonKey extends Item implements ItemComponents {

    use ItemComponentsTrait;

    public function __construct(ItemIdentifier $identifier) {
        parent::__construct($identifier, "§aClé Commune");
        $this->initComponent("common_key"); // doit matcher item_texture.json
    }
}
