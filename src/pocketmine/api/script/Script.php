<?php
/**
 * Created by PhpStorm.
 * User: ASUS
 * Date: 18/09/2017
 * Time: 17:27
 */

namespace pocketmine\api\script;


use pocketmine\command\CommandSender;
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
     * @param Server $server
     * @param string $name
     * @param string
     *
     * @return bool|null
     */
    public function createCommand(Server $server, Script $script, string $name, string $description = ""){
        $server->getCommandMap()->register($this->getName(), new ScriptCommand($server, $script, $name, $description));
        return true;
    }

    public abstract function commandFunction(CommandSender $sender, string $commandName, array $args, Server $server);

}