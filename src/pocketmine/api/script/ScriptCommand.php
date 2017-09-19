<?php
/**
 * Created by PhpStorm.
 * User: ASUS
 * Date: 18/09/2017
 * Time: 20:16
 */

namespace pocketmine\api\script;


use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\Server;

class ScriptCommand extends Command
{
    /**
     * @var Server
     */
    private $server;

    /**
     * @var Script
     */
    private $script;

    public function __construct(Server $server, Script $script, string $name, string $description = "")
    {
        parent::__construct($name, $description);
        $this->server = $server;
        $this->script = $script;
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args)
    {
        $this->script->commandFunction($sender, $this->getName(), $args, $this->server);
    }

}