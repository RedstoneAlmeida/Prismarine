<?php
/**
 * Created by PhpStorm.
 * User: ASUS
 * Date: 18/09/2017
 * Time: 17:40
 */

namespace pocketmine\api\script;


use pocketmine\event\Event;
use pocketmine\Server;
use pocketmine\utils\Config;

class ScriptManager
{

    private $server;

    public function __construct(Server $server)
    {
        $this->server = $server;
    }

    public function init(int $loads = 0, Event $event = null){
        $server = $this->server;
        $path = $server->getDataPath() . "libs/";
        @mkdir($path, 777);
        foreach(glob($path . "*.php", GLOB_BRACE) as $script){
            include_once $script;
            foreach(self::getFileClasses($script) as $class){
                $class = new $class();
                if($class instanceof Script){
                    $loader = new ScriptLoader($class);
                    $loader->process($loads, $event);
                }
            }
        }
    }

    /**
     * @param $filePath
     * @return array
     */
    public static function getFileClasses($filePath) {
        $php_code = file_get_contents($filePath);
        $classes = self::getPHPClasses($php_code);
        return $classes;
    }
    /**
     * @param $php_code
     * @return array
     */
    public static function getPHPClasses($php_code) {
        $classes = [];
        $tokens = token_get_all($php_code);
        $count = count($tokens);
        for ($i = 2; $i < $count; $i++) {
            if ($tokens[$i-2][0] == T_CLASS
                and $tokens[$i-1][0] == T_WHITESPACE
                and $tokens[$i][0] == T_STRING) {
                $class_name = $tokens[$i][1];
                $classes[] = $class_name;
            }
        }
        return $classes;
    }

}