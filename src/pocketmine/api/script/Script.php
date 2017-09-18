<?php
/**
 * Created by PhpStorm.
 * User: ASUS
 * Date: 18/09/2017
 * Time: 17:27
 */

namespace pocketmine\api\script;


use pocketmine\Server;

abstract class Script
{

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

}