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

namespace pocketmine\inventory;

use pocketmine\item\Item;
use pocketmine\level\Position;
use pocketmine\network\mcpe\protocol\types\WindowTypes;
use pocketmine\Player;

class AnvilInventory extends ContainerInventory{

 	const TARGET = 0;
 	const SACRIFICE = 1;
 	const RESULT = 2;

	/** @var FakeBlockMenu */
	protected $holder;

	public function __construct(Position $pos){
		parent::__construct(new FakeBlockMenu($this, $pos));
	}

	public function getNetworkType() : int{
		return WindowTypes::ANVIL;
	}

	public function getName() : string{
		return "Anvil";
	}

	public function getDefaultSize() : int{
		return 3; //1 input, 1 material, 1 result
	}

	/**
	 * This override is here for documentation and code completion purposes only.
	 * @return FakeBlockMenu
	 */
	public function getHolder(){
		return $this->holder;
	}

 	public function onRename(Player $player, Item $resultItem) : bool{
 		if(!$resultItem->deepEquals($this->getItem(self::TARGET), true, false, true)){
 			//Item does not match target item. Everything must match except the tags.
 			return false;
 		}
 
 		if($player->getXpLevel() < $resultItem->getRepairCost()){ //Not enough exp
 			return false;
  		}
 		$player->setXpLevel($player->getXpLevel() - $resultItem->getRepairCost());
 		
 		$this->clearAll();
 		if(!$player->getServer()->allowInventoryCheats and !$player->isCreative()){
 			if(!$player->getFloatingInventory()->canAddItem($resultItem)){
 				return false;
 			}
 			$player->getFloatingInventory()->addItem($resultItem);
 		}

 		$player->getLevel()->addSound(new AnvilUseSound($player), [$player]);

 		return true;
 	}

  	public function onSlotChange(int $index, Item $before, bool $send){
 		//Do not send anvil slot updates to anyone. This will cause a client crash.
  	}

	public function onClose(Player $who){
		parent::onClose($who);

		for($i = 0; $i < 2; ++$i){
			$this->getHolder()->getLevel()->dropItem($this->getHolder()->add(0.5, 0.5, 0.5), $this->getItem($i));
			$this->clear($i);
		}
	}
}