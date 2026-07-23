<?php

declare(strict_types=1);

namespace BlockFixer;

use BlockFixer\block\WaterPlant;
use pocketmine\block\Block;
use pocketmine\block\BlockBreakInfo;
use pocketmine\block\BlockIdentifier;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\BlockTypeInfo;
use pocketmine\block\Opaque;
use pocketmine\block\RuntimeBlockStateRegistry;
use pocketmine\block\Transparent;
use pocketmine\data\bedrock\block\convert\BlockStateReader as Reader;
use pocketmine\data\bedrock\block\convert\BlockStateWriter as Writer;
use pocketmine\plugin\PluginBase;
use pocketmine\world\format\io\GlobalBlockStateHandlers;

/**
 * BlockFixerPlugin 2.0
 *
 * Le probleme : la map "Kepler" a ete creee sur Minecraft Bedrock 1.21.101 et
 * contient des blocs que ce build de PocketMine-MP n'implemente PAS cote serveur.
 * Au chargement des chunks, PocketMine ne trouve pas de "deserializer" pour ces
 * IDs et jette une erreur "Unknown block ID", puis remplace le bloc par un bloc
 * inconnu / de l'air => trous et terrain "mal genere".
 *
 * Blocs concernes dans cette map :
 *   - minecraft:dripstone_block
 *   - minecraft:pointed_dripstone   (proprietes: dripstone_thickness + hanging)
 *   - minecraft:kelp                (algue - propriete: kelp_age)
 *   - minecraft:seagrass            (algue - propriete: sea_grass_type)
 *
 * La solution : on enregistre nous-memes ces blocs.
 *   1) on cree un objet Block cote serveur (pour la collision / la lumiere) ;
 *   2) on l'inscrit dans le RuntimeBlockStateRegistry ;
 *   3) on ajoute un "serializer"   (serveur -> etat Bedrock envoye au client) qui
 *      renvoie EXACTEMENT l'etat vanilla, donc le client 1.21 affiche le vrai bloc ;
 *   4) on ajoute un "deserializer" (monde .mcworld -> objet Block) qui lit les
 *      proprietes stockees dans la map et renvoie la bonne variante.
 *
 * Comme ces blocs existent deja cote client (ce sont des blocs vanilla), aucun
 * resource pack n'est necessaire : ils s'affichent normalement.
 *
 * Tout se fait dans onLoad(), AVANT que les mondes ne soient charges.
 */
final class Main extends PluginBase{

	public function onLoad() : void{
		try{
			$count = $this->registerMissingBlocks();
			$this->getLogger()->info("§a$count bloc(s) manquant(s) enregistre(s) : dripstone_block, pointed_dripstone, kelp, seagrass.");
		}catch(\Throwable $e){
			$this->getLogger()->error("Echec de l'enregistrement des blocs : " . $e->getMessage());
			$this->getLogger()->logException($e);
		}
	}

	private function registerMissingBlocks() : int{
		$registry = RuntimeBlockStateRegistry::getInstance();
		$deserializer = GlobalBlockStateHandlers::getDeserializer();
		$serializer = GlobalBlockStateHandlers::getSerializer();

		/**
		 * Cree un bloc serveur unique et l'enregistre dans le registre runtime.
		 * @phpstan-param class-string<Block> $class
		 */
		$make = static function(string $displayName, string $class, BlockBreakInfo $break) use ($registry) : Block{
			$block = new $class(new BlockIdentifier(BlockTypeIds::newId()), $displayName, new BlockTypeInfo($break));
			$registry->register($block);
			return $block;
		};

		$registered = 0;

		//--------------------------------------------------------------
		// 1) minecraft:dripstone_block  (aucune propriete)
		//--------------------------------------------------------------
		$dripstone = $make("Dripstone Block", Opaque::class, BlockBreakInfo::pickaxe(1.5));
		$serializer->map($dripstone, Writer::create("minecraft:dripstone_block"));
		$deserializer->map("minecraft:dripstone_block", static fn(Reader $in) : Block => $dripstone);
		$registered++;

		//--------------------------------------------------------------
		// 2) minecraft:pointed_dripstone
		//    proprietes : dripstone_thickness (string) + hanging (bool/byte)
		//    On cree une variante par combinaison pour conserver l'apparence.
		//--------------------------------------------------------------
		$thicknesses = ["tip", "frustum", "middle", "base", "merge"];
		$pointedVariants = []; // "thickness|hanging" => Block
		foreach($thicknesses as $thickness){
			foreach([false, true] as $hanging){
				$variant = $make("Pointed Dripstone", Transparent::class, BlockBreakInfo::pickaxe(1.5));
				$serializer->map(
					$variant,
					Writer::create("minecraft:pointed_dripstone")
						->writeString("dripstone_thickness", $thickness)
						->writeBool("hanging", $hanging)
				);
				$pointedVariants[$thickness . "|" . ($hanging ? "1" : "0")] = $variant;
			}
		}
		$deserializer->map("minecraft:pointed_dripstone", static function(Reader $in) use ($pointedVariants) : Block{
			$thickness = $in->readString("dripstone_thickness");
			$hanging = $in->readBool("hanging");
			$key = $thickness . "|" . ($hanging ? "1" : "0");
			return $pointedVariants[$key] ?? $pointedVariants["tip|0"];
		});
		$registered++;

		//--------------------------------------------------------------
		// 3) minecraft:kelp  (algue) - propriete : kelp_age (int 0..25)
		//    L'age n'a pas d'impact visuel notable : on collapse a age 0.
		//--------------------------------------------------------------
		$kelp = $make("Kelp", WaterPlant::class, BlockBreakInfo::instant());
		$serializer->map($kelp, Writer::create("minecraft:kelp")->writeInt("kelp_age", 0));
		$deserializer->map("minecraft:kelp", static function(Reader $in) use ($kelp) : Block{
			$in->readInt("kelp_age"); // consommer la propriete (obligatoire)
			return $kelp;
		});
		$registered++;

		//--------------------------------------------------------------
		// 4) minecraft:seagrass  (algue)
		//    propriete : sea_grass_type (default / double_top / double_bot)
		//--------------------------------------------------------------
		$seagrassTypes = ["default", "double_top", "double_bot"];
		$seagrassVariants = [];
		foreach($seagrassTypes as $type){
			$variant = $make("Seagrass", WaterPlant::class, BlockBreakInfo::instant());
			$serializer->map($variant, Writer::create("minecraft:seagrass")->writeString("sea_grass_type", $type));
			$seagrassVariants[$type] = $variant;
		}
		$deserializer->map("minecraft:seagrass", static function(Reader $in) use ($seagrassVariants) : Block{
			$type = $in->readString("sea_grass_type");
			return $seagrassVariants[$type] ?? $seagrassVariants["default"];
		});
		$registered++;

		return $registered;
	}
}
