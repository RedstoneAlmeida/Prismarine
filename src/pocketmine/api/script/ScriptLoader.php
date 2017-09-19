<?php
/**
 * Created by PhpStorm.
 * User: ASUS
 * Date: 18/09/2017
 * Time: 17:35
 */

namespace pocketmine\api\script;


use pocketmine\event\Event;
use pocketmine\Server;

class ScriptLoader
{

    const ON_LOAD = 0;
    const ON_START = 1;
    const ON_STOP = 2;
    const ON_CUSTOM = 3;
    const ON_EVENT = 4;

    /**
     * @var Script
     */
    private $script;

    public function __construct(Script $script)
    {
        $this->script = $script;
    }

    public function process(int $loads, Event $event = null){
        $script = $this->script;
        switch($loads){
            case self::ON_LOAD:
                $script->onLoad(Server::getInstance());
                break;
            case self::ON_START:
                $script->onStart(Server::getInstance());
                break;
            case self::ON_STOP:
                $script->onStop(Server::getInstance());
                break;
            case self::ON_CUSTOM:
                $script->onCustom(Server::getInstance());
                break;
            case self::ON_EVENT:
                if($event != null) {
                    $script->processEvents($event);
                }
                break;
        }
    }

}