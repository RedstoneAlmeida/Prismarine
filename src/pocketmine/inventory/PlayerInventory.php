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

use pocketmine\entity\Human;
use pocketmine\event\entity\EntityArmorChangeEvent;
use pocketmine\event\entity\EntityInventoryChangeEvent;
use pocketmine\event\player\PlayerItemHeldEvent;
use pocketmine\item\Item;
use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\ListTag;
use pocketmine\network\mcpe\protocol\ContainerSetContentPacket;
use pocketmine\network\mcpe\protocol\ContainerSetSlotPacket;
use pocketmine\network\mcpe\protocol\MobArmorEquipmentPacket;
use pocketmine\network\mcpe\protocol\MobEquipmentPacket;
use pocketmine\network\mcpe\protocol\types\ContainerIds;
use pocketmine\Player;
use pocketmine\Server;

class PlayerInventory extends BaseInventory{

	protected $itemInHandIndex = 0;
	/** @var int[] */
	protected $hotbar;

	public function __construct(Human $player, $contents = null){
		$this->resetHotbar(false);
		parent::__construct($player, InventoryType::get(InventoryType::PLAYER));
		if($contents !== null){
			if($contents instanceof ListTag){ //Saved data to be loaded into the inventory
				foreach($contents as $item){
					if($item["Slot"] >= 0 and $item["Slot"] < $this->getHotbarSize()){ //Hotbar
						if(isset($item["TrueSlot"])){

							//Valid slot was found, change the linkage to this slot
							if(0 <= $item["TrueSlot"] and $item["TrueSlot"] < $this->getSize()){
								$this->hotbar[$item["Slot"]] = $item["TrueSlot"];

							}elseif($item["TrueSlot"] < 0){ //Link to an empty slot (empty hand)
								$this->hotbar[$item["Slot"]] = -1;
							}
						}
						/* If TrueSlot is not set, leave the slot index as its default which was filled in above
						 * This only overwrites slot indexes for valid links */
					}elseif($item["Slot"] >= 100 and $item["Slot"] < 104){ //Armor
						$this->setItem($this->getSize() + $item["Slot"] - 100, Item::nbtDeserialize($item), false);
					}else{
						$this->setItem($item["Slot"] - $this->getHotbarSize(), Item::nbtDeserialize($item), false);
					}
				}
			}else{
				throw new \InvalidArgumentException("Expecting ListTag, received ".gettype($contents));
			}
		}
	}

	public function getSize() : int{
		return parent::getSize() - 4; //Remove armor slots
	}

	public function setSize(int $size){
		parent::setSize($size + 4);
		$this->sendContents($this->getViewers());
	}

	/**
	 * Called when a client equips a hotbar slot. This method should not be used by plugins.
	 * This method will call PlayerItemHeldEvent.
	 *
	 * @param int      $hotbarSlot Number of the hotbar slot to equip.
	 * @param int|null $inventorySlot Inventory slot to map to the specified hotbar slot. Supply null to make no change to the link.
	 *
	 * @return bool if the equipment change was successful, false if not.
	 */
	public function equipItem(int $hotbarSlot, $inventorySlot = null) : bool{
		if($inventorySlot === null){
			$inventorySlot = $this->getHotbarSlotIndex($hotbarSlot);
		}

		if($hotbarSlot < 0 or $hotbarSlot >= $this->getHotbarSize() or $inventorySlot < -1 or $inventorySlot >= $this->getSize()){
			$this->sendContents($this->getHolder());
			return false;
		}

		if($inventorySlot === -1){
			$item = Item::get(Item::AIR, 0, 0);
		}else{
			$item = $this->getItem($inventorySlot);
		}

		$this->getHolder()->getLevel()->getServer()->getPluginManager()->callEvent($ev = new PlayerItemHeldEvent($this->getHolder(), $item, $inventorySlot, $hotbarSlot));

		if($ev->isCancelled()){
			$this->sendContents($this->getHolder());
			return false;
		}

		/**
		 * Handle hotbar slot remapping
		 * This is the only time and place when hotbar mapping should ever be changed.
		 * Changing hotbar slot mapping at will has been deprecated because it causes far too many
		 * issues with Windows 10 Edition Beta.
		 */
		$this->setHeldItemIndex($hotbarSlot, false, $inventorySlot);

		return true;
	}

	/**
	 * Returns the index of the inventory slot mapped to the specified hotbar slot, or -1 if the hotbar slot does not exist.
	 * @param int $index
	 *
	 * @return int
	 */
	public function getHotbarSlotIndex($index){
		return $this->hotbar[$index] ?? -1;
	}

	/**
	 * @deprecated
	 *
	 * Changes the linkage of the specified hotbar slot. This should never be done unless it is requested by the client.
	 */
	public function setHotbarSlotIndex($hotbarSlot, $inventorySlot){
		if($this->getHolder()->getServer()->getProperty("settings.deprecated-verbose") !== false){
			trigger_error("Do not attempt to change hotbar links in plugins!", E_USER_DEPRECATED);
		}
	}

	/**
	 * Returns the item in the slot linked to the specified hotbar slot, or Air if the slot is not linked to any hotbar slot.
	 * @param int $hotbarSlotIndex
	 *
	 * @return Item
	 */
	public function getHotbarSlotItem(int $hotbarSlotIndex) : Item{
		$inventorySlot = $this->getHotbarSlotIndex($hotbarSlotIndex);
		if($inventorySlot !== -1){
			return $this->getItem($inventorySlot);
		}else{
			return Item::get(Item::AIR, 0, 0);
		}
	}

	/**
	 * Resets hotbar links to their original defaults.
	 * @param bool $send Whether to send changes to the holder.
	 */
	public function resetHotbar(bool $send = true){
		$this->hotbar = range(0, $this->getHotbarSize() - 1, 1);
		if($send){
			$this->sendContents($this->getHolder());
		}
	}

	/**
	 * Returns the hotbar slot number the holder is currently holding.
	 * @return int
	 */
	public function getHeldItemIndex(){
		return $this->itemInHandIndex;
	}

	/**
	 * @param int  $hotbarSlotIndex
	 * @param bool $sendToHolder
	 * @param int  $slotMapping
	 *
	 * Sets which hotbar slot the player is currently holding.
	 * Allows slot remapping as specified by a MobEquipmentPacket. DO NOT CHANGE SLOT MAPPING IN PLUGINS!
	 * This new implementation is fully compatible with older APIs.
	 * NOTE: Slot mapping is the raw slot index sent by MCPE, which will be between 9 and 44.
	 */
	public function setHeldItemIndex($hotbarSlotIndex, $sendToHolder = true, $slotMapping = null){
		if($slotMapping !== null){
			//Get the index of the slot in the actual inventory
			$slotMapping -= $this->getHotbarSize();
		}
		if(0 <= $hotbarSlotIndex and $hotbarSlotIndex < $this->getHotbarSize()){
			$this->itemInHandIndex = $hotbarSlotIndex;
			if($slotMapping !== null){
				/* Handle a hotbar slot mapping change. This allows PE to select different inventory slots.
				 * This is the only time slot mapping should ever be changed. */

				if($slotMapping < 0 or $slotMapping >= $this->getSize()){
					//Mapping was not in range of the inventory, set it to -1
					//This happens if the client selected a blank slot (sends 255)
					$slotMapping = -1;
				}

				$item = $this->getItem($slotMapping);
				if($this->getHolder() instanceof Player){
					Server::getInstance()->getPluginManager()->callEvent($ev = new PlayerItemHeldEvent($this->getHolder(), $item, $slotMapping, $hotbarSlotIndex));
					if($ev->isCancelled()){
						$this->sendHeldItem($this->getHolder());
						$this->sendContents($this->getHolder());
						return;
					}
				}
				if(($key = array_search($slotMapping, $this->hotbar)) !== false and $slotMapping !== -1){
					/* Do not do slot swaps if the slot was null
					 * Chosen slot is already linked to a hotbar slot, swap the two slots around.
					 * This will already have been done on the client-side so no changes need to be sent. */
					$this->hotbar[$key] = $this->hotbar[$this->itemInHandIndex];
				}

				$this->hotbar[$this->itemInHandIndex] = $slotMapping;
			}
			$this->sendHeldItem($this->getHolder()->getViewers());
			if($sendToHolder){
				$this->sendHeldItem($this->getHolder());
 			}
 		}
 	}

	/**
	 * Returns the currently-held item.
	 *
	 * @return Item
	 */
	public function getItemInHand(){
		return $this->getHotbarSlotItem($this->itemInHandIndex);
	}

	/**
	 * Sets the item in the currently-held slot to the specified item.
	 * @param Item $item
	 *
	 * @return bool
	 */
	public function setItemInHand(Item $item, $send = true){
		return $this->setItem($this->getHeldItemSlot(), $item, $send);
	}

	/**
	 * @return int[]
	 *
	 * Returns an array of hotbar indices
	 */
	public function getHotbar(){
		return $this->hotbar;
	}

	/**
	 * Returns the hotbar slot number currently held.
	 *
	 * @return int
	 */
	public function getHeldItemSlot(){
		return $this->getHotbarSlotIndex($this->itemInHandIndex);
	}

	/**
	 * @deprecated
	 * @param int $slot
	 */
	public function setHeldItemSlot($slot){
	}

	/**
	 * Sends the currently-held item to specified targets.
	 * @param Player|Player[] $target
	 */
	public function sendHeldItem($target){
		$item = $this->getItemInHand();

		$pk = new MobEquipmentPacket();
		$pk->entityRuntimeId = $this->getHolder()->getId();
		$pk->item = $item;
		$pk->inventorySlot = $this->getHeldItemSlot();
		$pk->hotbarSlot = $this->getHeldItemIndex();
		$pk->windowId = ContainerIds::INVENTORY;

		if(!is_array($target)){
			$target->dataPacket($pk);
			if($this->getHeldItemSlot() !== -1 and $target === $this->getHolder()){
				$this->sendSlot($this->getHeldItemSlot(), $target);
			}
		}else{
			$this->getHolder()->getLevel()->getServer()->broadcastPacket($target, $pk);
			if($this->getHeldItemSlot() !== -1 and in_array($this->getHolder(), $target, true)){
				$this->sendSlot($this->getHeldItemSlot(), $this->getHolder());
			}
		}
	}

	public function onSlotChange($index, $before, $send){
		if($send){
			$holder = $this->getHolder();
			if(!$holder instanceof Player or !$holder->spawned){
				return;
			}
			parent::onSlotChange($index, $before, $send);
		}
		if($index === $this->itemInHandIndex){
			$this->sendHeldItem($this->getHolder()->getViewers());
			if($send){
				$this->sendHeldItem($this->getHolder());
			}
		}elseif($index >= $this->getSize()){ //Armour equipment
 			$this->sendArmorSlot($index, $this->getViewers());
 			$this->sendArmorSlot($index, $this->getHolder()->getViewers());
 		}
 	}

	/**
	 * Returns the number of slots in the hotbar.
	 * @return int
	 */
	public function getHotbarSize(){
		return 9;
	}

	public function getArmorItem($index){
		return $this->getItem($this->getSize() + $index);
	}

	public function setArmorItem($index, Item $item){
		return $this->setItem($this->getSize() + $index, $item);
	}

	public function getHelmet(){
		return $this->getItem($this->getSize());
	}

	public function getChestplate(){
		return $this->getItem($this->getSize() + 1);
	}

	public function getLeggings(){
		return $this->getItem($this->getSize() + 2);
	}

	public function getBoots(){
		return $this->getItem($this->getSize() + 3);
	}

	public function setHelmet(Item $helmet){
		return $this->setItem($this->getSize(), $helmet);
	}

	public function setChestplate(Item $chestplate){
		return $this->setItem($this->getSize() + 1, $chestplate);
	}

	public function setLeggings(Item $leggings){
		return $this->setItem($this->getSize() + 2, $leggings);
	}

	public function setBoots(Item $boots){
		return $this->setItem($this->getSize() + 3, $boots);
	}

	public function setItem(int $index, Item $item, $send = true) : bool{
		if($index < 0 or $index >= $this->size){
			return false;
		}elseif($item->getId() === 0 or $item->getCount() <= 0){
			return $this->clear($index, $send);
		}

		if($index >= $this->getSize()){ //Armor change
			Server::getInstance()->getPluginManager()->callEvent($ev = new EntityArmorChangeEvent($this->getHolder(), $this->getItem($index), $item, $index));
			if($ev->isCancelled() and $this->getHolder() instanceof Human){
				$this->sendArmorSlot($index, $this->getViewers());
				return false;
			}
			$item = $ev->getNewItem();
		}else{
			Server::getInstance()->getPluginManager()->callEvent($ev = new EntityInventoryChangeEvent($this->getHolder(), $this->getItem($index), $item, $index));
			if($ev->isCancelled()){
				$this->sendSlot($index, $this->getViewers());
				return false;
			}
			$item = $ev->getNewItem();
		}


		$old = $this->getItem($index);
		$this->slots[$index] = clone $item;
		$this->onSlotChange($index, $old, $send);

		return true;
	}

	public function clear(int $index, $send = true) : bool{
		if(isset($this->slots[$index])){
			$item = Item::get(Item::AIR, 0, 0);
			$old = $this->slots[$index];
			if($index >= $this->getSize() and $index < $this->size){ //Armor change
				Server::getInstance()->getPluginManager()->callEvent($ev = new EntityArmorChangeEvent($this->getHolder(), $old, $item, $index));
				if($ev->isCancelled()){
					if($index >= $this->size){
						$this->sendArmorSlot($index, $this->getViewers());
					}else{
						$this->sendSlot($index, $this->getViewers());
					}
					return false;
				}
				$item = $ev->getNewItem();
			}else{
				Server::getInstance()->getPluginManager()->callEvent($ev = new EntityInventoryChangeEvent($this->getHolder(), $old, $item, $index));
				if($ev->isCancelled()){
					if($index >= $this->size){
						$this->sendArmorSlot($index, $this->getViewers());
					}else{
						$this->sendSlot($index, $this->getViewers());
					}
					return false;
				}
				$item = $ev->getNewItem();
			}
			if($item->getId() !== Item::AIR){
				$this->slots[$index] = clone $item;
			}else{
				unset($this->slots[$index]);
			}

			$this->onSlotChange($index, $old, $send);
		}

		return true;
	}

	/**
	 * @return Item[]
	 */
	public function getArmorContents(){
		$armor = [];

		for($i = 0; $i < 4; ++$i){
			$armor[$i] = $this->getItem($this->getSize() + $i);
		}

		return $armor;
	}

	public function clearAll(){
		$limit = $this->getSize() + 4;
		for($index = 0; $index < $limit; ++$index){
			$this->clear($index, false);
		}
		$this->hotbar = range(0, $this->getHotbarSize() - 1, 1);
		$this->sendContents($this->getViewers());
	}

	/**
	 * @param Player|Player[] $target
	 */
	public function sendArmorContents($target){
		if($target instanceof Player){
			$target = [$target];
		}

		$armor = $this->getArmorContents();

		$pk = new MobArmorEquipmentPacket();
		$pk->entityRuntimeId = $this->getHolder()->getId();
		$pk->slots = $armor;
		$pk->encode();

		foreach($target as $player){
			if($player === $this->getHolder()){
				$pk2 = new ContainerSetContentPacket();
				$pk2->windowid = ContainerIds::ARMOR;
				$pk2->slots = $armor;
				$pk2->targetEid = $player->getId();
				$player->dataPacket($pk2);
			}else{
				$player->dataPacket($pk);
			}
		}
	}

	/**
	 * @param Item[] $items
	 */
	public function setArmorContents(array $items){
		for($i = 0; $i < 4; ++$i){
			if(!isset($items[$i]) or !($items[$i] instanceof Item)){
				$items[$i] = Item::get(Item::AIR, 0, 0);
			}

			if($items[$i]->getId() === Item::AIR){
				$this->clear($this->getSize() + $i);
			}else{
				$this->setItem($this->getSize() + $i, $items[$i]);
			}
		}
	}


	/**
	 * @param int             $index
	 * @param Player|Player[] $target
	 */
	public function sendArmorSlot($index, $target){
		if($target instanceof Player){
			$target = [$target];
		}

		$armor = $this->getArmorContents();

		$pk = new MobArmorEquipmentPacket();
		$pk->entityRuntimeId = $this->getHolder()->getId();
		$pk->slots = $armor;
		$pk->encode();

		foreach($target as $player){
			if($player === $this->getHolder()){
				/** @var Player $player */
				$pk2 = new ContainerSetSlotPacket();
				$pk2->windowid = ContainerIds::ARMOR;
				$pk2->slot = $index - $this->getSize();
				$pk2->item = $this->getItem($index);
				$player->dataPacket($pk2);
			}else{
				$player->dataPacket($pk);
			}
		}
	}

	/**
	 * @param Player|Player[] $target
	 */
	public function sendContents($target){
		if($target instanceof Player){
			$target = [$target];
		}

		$pk = new ContainerSetContentPacket();
		$pk->slots = [];

		for($i = 0; $i < $this->getSize(); ++$i){ //Do not send armor by error here
			$pk->slots[$i] = $this->getItem($i);
		}

		//Because PE is stupid and shows 9 less slots than you send it, give it 9 dummy slots so it shows all the REAL slots.
		for($i = $this->getSize(); $i < $this->getSize() + $this->getHotbarSize(); ++$i){
			$pk->slots[$i] = Item::get(Item::AIR, 0, 0);
		}

		foreach($target as $player){
			$pk->hotbar = [];
			if($player === $this->getHolder()){
				for($i = 0; $i < $this->getHotbarSize(); ++$i){
					$index = $this->getHotbarSlotIndex($i);
					$pk->hotbar[$i] = $index <= -1 ? -1 : $index + $this->getHotbarSize();
				}
			}
			if(($id = $player->getWindowId($this)) === -1 or $player->spawned !== true){
				$this->close($player);
				continue;
			}
			$pk->windowid = $id;
			$pk->targetEid = $player->getId(); //TODO: check if this is correct
			$player->dataPacket(clone $pk);
			$this->sendHeldItem($player);
		}
	}

	public function sendCreativeContents(){
		$pk = new ContainerSetContentPacket();
		$pk->windowid = ContainerIds::CREATIVE;
		if($this->getHolder()->getGamemode() === Player::CREATIVE){
			foreach(Item::getCreativeItems() as $i => $item){
				$pk->slots[$i] = clone $item;
			}
		}
		$pk->targetEid = $this->getHolder()->getId();
		$this->getHolder()->dataPacket($pk);
	}

	/**
	 * @param int             $index
	 * @param Player|Player[] $target
	 */
	public function sendSlot($index, $target){
		if($target instanceof Player){
			$target = [$target];
		}

		$pk = new ContainerSetSlotPacket();
		$pk->slot = $index;
		$pk->item = clone $this->getItem($index);

		foreach($target as $player){
			if($player === $this->getHolder()){
				/** @var Player $player */
				$pk->windowid = 0;
				$player->dataPacket(clone $pk);
			}else{
				if(($id = $player->getWindowId($this)) === -1){
					$this->close($player);
					continue;
				}
				$pk->windowid = $id;
				$player->dataPacket(clone $pk);
			}
		}
	}

	/**
	 * @return Human|Player
	 */
	public function getHolder(){
		return parent::getHolder();
	}

}
