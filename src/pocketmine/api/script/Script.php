<?php
/**
 * Created by PhpStorm.
 * User: ASUS
 * Date: 18/09/2017
 * Time: 17:27
 */

namespace pocketmine\api\script;


use pocketmine\command\CommandSender;
use pocketmine\event\Event;
use pocketmine\Server;

abstract class Script
{
    /**
     * @return string
     */
    public abstract function getName();

    /**
     * @param Server $server
     * @return bool
     */
    public abstract function onLoad(Server $server);

    /**
     * @param Server $server
     * @return bool
     */
    public abstract function onStart(Server $server);

    /**
     * @param Server $server
     */
    public abstract function onStop(Server $server);

    /**
     * @param Server $server
     */
    public abstract function onCustom(Server $server);


    /**
     * Commands
     *
     * @param Server $server
     * @param string $name
     * @param string $description
     *
     * @return bool|null
     */
    public function createCommand(Server $server, Script $script, string $name, string $description = ""){
        $server->getCommandMap()->register($this->getName(), new ScriptCommand($server, $script, $name, $description));
        return true;
    }

    /**
     * @param CommandSender $sender
     * @param string $commandName
     * @param array $args
     * @param Server $server
     * @return mixed
     */
    public abstract function commandFunction(CommandSender $sender, string $commandName, array $args, Server $server);


    /**
     * @param Event $event
     */
    public function processEvents(Event $event){

    }




}