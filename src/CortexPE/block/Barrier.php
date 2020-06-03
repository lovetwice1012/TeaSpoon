<?php

declare(strict_types=1);

namespace CortexPE\block;

use pocketmine\item\Item;
use pocketmine\block\Transparent;

class Barrier extends Transparent{

	protected $id = 166;

public function __construct(){

	}

	public function getName() : string{
		return "barrier";
	}

	public function getHardness() : float{
		return -1;
	}

	public function getBlastResistance() : float{
		return 18000000;
	}

	public function isBreakable(Item $item) : bool{
		return false;
	}
}
