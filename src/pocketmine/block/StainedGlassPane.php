<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
*/

declare(strict_types=1);

namespace pocketmine\block;

use pocketmine\block\utils\ColorBlockMetaHelper;
use pocketmine\item\Item;

class StainedGlassPane extends GlassPane{

	protected $id = self::STAINED_GLASS_PANE;

	public function __construct(int $meta = 0){
		$this->meta = $meta;
	}

	public function getName(): string{
		static $names = [
			0 => "White Stained Glass Pane",
			1 => "Orange Stained Glass Pane",
			2 => "Magenta Stained Glass Pane",
			3 => "Light Blue Stained Glass Pane",
			4 => "Yellow Stained Glass Pane",
			5 => "Lime Stained Glass Pane",
			6 => "Pink Stained Glass Pane",
			7 => "Gray Stained Glass Pane",
			8 => "Light Gray Stained Glass Pane",
			9 => "Cyan Stained Glass Pane",
			10 => "Purple Stained Glass Pane",
			11 => "Blue Stained Glass Pane",
			12 => "Brown Stained Glass Pane",
			13 => "Green Stained Glass Pane",
			14 => "Red Stained Glass Pane",
			15 => "Black Stained Glass Pane",
		];
		return $names[$this->meta & 0x0f];
	}
}