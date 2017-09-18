<?php
/**
 * Created by PhpStorm.
 * User: ASUS
 * Date: 18/09/2017
 * Time: 13:48
 */

namespace pocketmine\event\server;

class PacketMakerEvent extends ServerEvent
{

    public static $handlerList = null;

    const PACKET_MAKER_SET = 0;
    const PACKET_MAKER_SEND_PACKET = 1;

    private $condition;
    private $perLevel;

    public function __construct(int $condition, bool $perLevel)
    {
        $this->condition = $condition;
        $this->perLevel = $perLevel;
    }

    /**
     * @return int
     */
    public function getCondition(): int
    {
        return $this->condition;
    }

    /**
     * @return boolean
     */
    public function isPerLevel(): bool
    {
        return $this->perLevel;
    }

}