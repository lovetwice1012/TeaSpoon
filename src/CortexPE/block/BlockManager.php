<?php

declare(strict_types = 1);

namespace CortexPE\block;

use CortexPE\Main;
use pocketmine\block\Block;
use pocketmine\block\BlockFactory;

class BlockManager {
	public static function init(): void{
		Main::getPluginLogger()->debug("Registering Blocks...");
		BlockFactory::registerBlock(new Barrier());
	}
}
