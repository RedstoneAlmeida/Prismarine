<?php
/**
 * Created by PhpStorm.
 * User: ASUS
 * Date: 18/09/2017
 * Time: 17:16
 */

namespace pocketmine\api;

use pocketmine\inventory\WindowInventory;
use pocketmine\Player;

class InventoryCustom
{

    const INVENTORY_CUSTOM_ID = 25;

    /**
     * @var WindowInventory
     */
    private $inventory;
    /**
     * @var Player
     */
    private $player;

    public function __construct(Player $player, int $size = 27, string $customName = "", array $items = [])
    {
        $this->player = $player;
        $this->inventory = new WindowInventory($player, $customName);
        if(count($items) >= 1){
            $this->inventory->addItem($items);
        }
    }

    /**
     * @return WindowInventory
     */
    public function open(){
        $this->player->addWindow($this->inventory, self::INVENTORY_CUSTOM_ID); // INVENTORY_CUSTOM_ID fix removeWindow bug
        return $this->inventory;
    }

    /**
     * @return WindowInventory
     */
    public function getInventory(){
        return $this->inventory;
    }

}