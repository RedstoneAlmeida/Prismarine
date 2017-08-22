<?php

/**
 *
 *  ____       _                          _
 * |  _ \ _ __(_)___ _ __ ___   __ _ _ __(_)_ __   ___
 * | |_) | '__| / __| '_ ` _ \ / _` | '__| | '_ \ / _ \
 * |  __/| |  | \__ \ | | | | | (_| | |  | | | | |  __/
 * |_|   |_|  |_|___/_| |_| |_|\__,_|_|  |_|_| |_|\___|
 *
 * Prismarine is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author Prismarine Team
 * @link   https://github.com/PrismarineMC/Prismarine
 *
 *
 */

namespace pocketmine\inventory;

use pocketmine\item\Item;
use pocketmine\Player;

class DropItemTransaction extends BaseTransaction{

	const TRANSACTION_TYPE = Transaction::TYPE_DROP_ITEM;

	protected $inventory = null;

	protected $slot = null;

	protected $sourceItem = null;

	/**
	 * @param Item $droppedItem
	 */
	public function __construct(Item $droppedItem){
		$this->targetItem = $droppedItem;
	}

	public function setSourceItem(Item $item){
		//Nothing to update
	}

	public function getInventory(){
		return null;
	}

	public function getSlot(): int{
		return -1;
	}

	public function sendSlotUpdate(Player $source){
		//Nothing to update
	}

	public function getChange(){
		return ["in" => $this->getTargetItem(),
				"out" => null];
	}

	public function execute(Player $source): bool{
		$droppedItem = $this->getTargetItem();
		if($source->getFloatingInventory()->contains($droppedItem)){
			$source->getFloatingInventory()->removeItem($droppedItem);
		}elseif($source->getInventory()->contains($droppedItem)){
			$source->getInventory()->removeItem($droppedItem);
		}
		$source->dropItem($droppedItem);
		$source->getInventory()->sendContents($source);
		return true;
	}
}