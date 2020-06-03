<?php

declare(strict_types=1);

namespace CortexPE\block;

use pocketmine\item\Item;
use pocketmine\block\Transparent;

class InvisibleBedrock extends Transparent{

	protected $id = self::INVISIBLE_BEDROCK;

public function __construct(){

	}

	public function getName() : string{
		return "InvisibleBedrock";
	}

	public function getHardness() : float{
		return -1;
	}

	public function getBlastResistance() : float{
		return 18000000;
	}
	
}
