<?php

declare(strict_types=1);

namespace item;

use customiesdevs\customies\item\ItemComponents;
use customiesdevs\customies\item\ItemComponentsTrait;
use pocketmine\item\Item;
use pocketmine\item\ItemIdentifier;
use pocketmine\nbt\LittleEndianNbtSerializer;
use pocketmine\nbt\TreeRoot;
use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;

/**
 * L'oeuf de compagnon (emeraude).
 * Les infos du pet (type + niveau + xp) sont stockees dans le NBT de l'oeuf,
 * donc elles voyagent avec l'objet (pas besoin de base de donnees).
 */
class PetEgg extends Item implements ItemComponents {

    use ItemComponentsTrait;

    public function __construct(ItemIdentifier $identifier) {
        parent::__construct($identifier, "§aOeuf de compagnon");
        $this->initComponent("egg_emeraude"); // la texture (item_texture.json)
    }

    // ======== Lecture / ecriture du NBT ========
    // Le 2e argument de get* = la valeur par defaut si le tag n'existe pas encore.

    public function getPetType(): string {
        return $this->getNamedTag()->getString("pet_type", "villager");
    }

    public function setPetType(string $type): void {
        $this->getNamedTag()->setString("pet_type", $type);
    }

    public function getLevel(): int {
        return $this->getNamedTag()->getInt("level", 1);
    }

    public function setLevel(int $level): void {
        $this->getNamedTag()->setInt("level", $level);
    }

    public function getXp(): int {
        return $this->getNamedTag()->getInt("xp", 0);
    }

    public function setXp(int $xp): void {
        $this->getNamedTag()->setInt("xp", $xp);
    }

    public function refreshLore(): void {
        $noms = ["villager" => "Villageois", "witch" => "Sorciere"];
        $nom = $noms[$this->getPetType()] ?? $this->getPetType();

        $this->setLore([
            "§7Compagnon: §f" . $nom,
            "§7Niveau: §e" . $this->getLevel(),
            "§7XP: §b" . $this->getXp(),
        ]);
    }

    // ======== Sac a dos (contenu serialise en NBT) ========

    public function setSacData(string $data): void {
        $this->getNamedTag()->setString("sac", $data);
    }

    public function getSacData(): string {
        return $this->getNamedTag()->getString("sac", "");
    }

    /**
     * Transforme un tableau d'items en texte (pour le stocker dans le NBT).
     * @param Item[] $items
     */
    public static function serialiserSac(array $items): string {
        $list = new ListTag([], NBT::TAG_Compound);
        foreach ($items as $slot => $item) {
            if ($item instanceof Item && !$item->isNull()) {
                $list->push($item->nbtSerialize($slot));
            }
        }
        $compound = CompoundTag::create()->setTag("items", $list);
        return base64_encode((new LittleEndianNbtSerializer())->write(new TreeRoot($compound)));
    }

    /**
     * Reconstruit le tableau d'items depuis le texte.
     * @return Item[]
     */
    public static function deserialiserSac(string $data): array {
        if ($data === "") {
            return [];
        }
        $items = [];
        $compound = (new LittleEndianNbtSerializer())->read(base64_decode($data))->mustGetCompoundTag();
        $list = $compound->getListTag("items");
        if ($list !== null) {
            foreach ($list as $tag) {
                if ($tag instanceof CompoundTag) {
                    $items[$tag->getByte("Slot", 0)] = Item::nbtDeserialize($tag);
                }
            }
        }
        return $items;
    }
}
