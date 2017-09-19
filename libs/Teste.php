<?php

use pocketmine\api\script\Script;
use pocketmine\Server;
use pocketmine\command\CommandSender;
use pocketmine\event\Event;
use pocketmine\event\player\PlayerJoinEvent;

class Teste extends Script implements \pocketmine\event\Listener
{

    /**
     * @param Server $server
     * @return bool
     */
    public function onLoad(Server $server)
    {
        Server::getInstance()->getLogger()->info("§eTestando onLoad a partir de um script");
        return true;
    }

    /**
     * @param Server $server
     * @return bool
     */
    public function onStart(Server $server)
    {
        $this->createCommand($server, $this, "tested", "teste");
        Server::getInstance()->getLogger()->info("§eTestando onStart a partir de um script");
        return true;
    }

    /**
     * @param Server $server
     */
    public function onStop(Server $server)
    {
        Server::getInstance()->getLogger()->info("§eTestando onStop a partir de um script");
    }

    /**
     * Custom method
     *
     * @param Server $server
     */
    public function onCustom(Server $server)
    {
        Server::getInstance()->getLogger()->info("§eTestando onCustom a partir de um script");
    }

    /**
     * @return string
     */
    public function getName()
    {
        return "Teste";
    }

    public function commandFunction(CommandSender $sender, string $commandName, array $args, Server $server)
    {

    }

    public function processEvents(Event $event)
    {
        switch(true){
            case $event instanceof PlayerJoinEvent:
                $event->getPlayer()->sendMessage("teste");
                break;
        }
        return;
    }
}