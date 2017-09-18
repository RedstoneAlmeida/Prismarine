<?php

/**
 *
 *  ____       _                          _
 * |  _ \ _ __(_)___ _ __ ___   __ _ _ __(_)_ __   ___
 * | |_) | '__| / __| '_ ` _ \ / _` | '__| | '_ \ / _ \
 * |  __/| |  | \__ \ | | | | | (_| | |  | | | | |  __/
 * |_|   |_|  |_|___/_| |_| |_|\__,_|_|  |_|_| |_|\___|
 *
 * Prismarine is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author Prismarine Team
 * @link   https://github.com/PrismarineMC/Prismarine
 *
 *
 */

namespace pocketmine\level;

use pocketmine\network\mcpe\CachedEncapsulatedPacket;
use pocketmine\network\mcpe\protocol\BatchPacket;
use pocketmine\network\mcpe\protocol\DataPacket;
use pocketmine\utils\Binary;
use pocketmine\Worker;
use raklib\protocol\EncapsulatedPacket;
use raklib\protocol\PacketReliability;
use raklib\RakLib;

class PacketMaker extends Worker{

	protected $classLoader;
	protected $shutdown = false;
	
	protected $externalQueue;
	protected $internalQueue;	

	public function __construct(\ClassLoader $loader = null) {
		$this->externalQueue = new \Threaded;
		$this->internalQueue = new \Threaded;
		$this->shutdown = false;
		$this->classLoader = $loader;
		$this->start();
	}
	
	public function run(){
		$this->registerClassLoader();
		gc_enable();
		ini_set("memory_limit", -1);
		ini_set("display_errors", 1);
	    ini_set("display_startup_errors", 1);

	    set_error_handler([$this, "errorHandler"], E_ALL);
	    register_shutdown_function([$this, "shutdownHandler"]);
		$this->tickProcessor();
	}

	public function pushMainToThreadPacket(string $data){
		$this->internalQueue[] = $data;
	}

	public function pushThreadToMainPacket(string $data){
		$this->externalQueue[] = $data;
	}

	public function readMainToThreadPacket() : string{
		return ($data = $this->internalQueue->shift()) !== null ? $data : "";
	}
	public function readThreadToMainPacket() : string{
		return ($data = $this->externalQueue->shift()) !== null ? $data : "";
	}

	protected function tickProcessor(){
		while(!$this->shutdown){		
			$start = microtime(true);
			$this->tick();
			$time = microtime(true) - $start;
			if($time < 0.05){
				time_sleep_until(microtime(true) + (0.05 - $time));
			}
		}
	}

	protected function tick(){	
		while(count($this->internalQueue) > 0){
			$data = unserialize($this->readMainToThreadPacket());
			$this->checkPacket($data);
		}
	}
	
	protected function checkPacket(PacketMakerEntry $data){
		$result = [];
		$str = "";
		foreach($data->packets as $p){
			if($p instanceof DataPacket){
				if(!$p->isEncoded){					
					$p->encode();
				}
				$str .= Binary::writeUnsignedVarInt(strlen($p->buffer)) . $p->buffer;
			}else{
				$str .= Binary::writeUnsignedVarInt(strlen($p)) . $p;
			}
		}
		$pk = new BatchPacket();
		$pk->payload = $str;
		$pk->setCompressionLevel($data->networkCompressionLevel);
		$pk->encode();
		$pk->isEncoded = true;
		foreach($data->targets as $target){
			$result[] = $this->makeBuffer($target, $pk, false, false);
		}
		if(!empty($result)){
			$this->pushThreadToMainPacket(serialize($result));
		}
	}

	protected function makeBuffer(string $identifier, DataPacket $fullPacket, bool $needACK = false, int $identifierACK = -1) {		
		$pk = null;
		if(!$fullPacket->isEncoded){
			$fullPacket->encode();
		}elseif(!$needACK){
			if(isset($fullPacket->__encapsulatedPacket)){
				unset($fullPacket->__encapsulatedPacket);
			}
			$fullPacket->__encapsulatedPacket = new CachedEncapsulatedPacket();
			$fullPacket->__encapsulatedPacket->identifierACK = null;
			$fullPacket->__encapsulatedPacket->buffer = $fullPacket->buffer;
			$fullPacket->__encapsulatedPacket->reliability = PacketReliability::RELIABLE;
			$pk = $fullPacket->__encapsulatedPacket;
		}

		if($pk === null){
			$pk = new EncapsulatedPacket();
			$pk->buffer = $fullPacket->buffer;
			$pk->reliability = PacketReliability::RELIABLE;

			if($needACK === true && $identifierACK !== -1){
				$pk->identifierACK = $identifierACK;
			}
		}

		$flags = ($needACK === true ? RakLib::FLAG_NEED_ACK : RakLib::PRIORITY_NORMAL) | (RakLib::PRIORITY_NORMAL);

		$buffer = chr(RakLib::PACKET_ENCAPSULATED) . chr(strlen($identifier)) . $identifier . chr($flags) . $pk->toBinary(true);

		return $buffer;
	}
	
	public function shutdown(){		
        $this->shutdown = true;
    }
	
	
	public function errorHandler($errno, $errstr, $errfile, $errline, $context, $trace = null){
		$errorConversion = [
			E_ERROR => "E_ERROR",
			E_WARNING => "E_WARNING",
			E_PARSE => "E_PARSE",
			E_NOTICE => "E_NOTICE",
			E_CORE_ERROR => "E_CORE_ERROR",
			E_CORE_WARNING => "E_CORE_WARNING",
			E_COMPILE_ERROR => "E_COMPILE_ERROR",
			E_COMPILE_WARNING => "E_COMPILE_WARNING",
			E_USER_ERROR => "E_USER_ERROR",
			E_USER_WARNING => "E_USER_WARNING",
			E_USER_NOTICE => "E_USER_NOTICE",
			E_STRICT => "E_STRICT",
			E_RECOVERABLE_ERROR => "E_RECOVERABLE_ERROR",
			E_DEPRECATED => "E_DEPRECATED",
			E_USER_DEPRECATED => "E_USER_DEPRECATED",
		];
		$errno = isset($errorConversion[$errno]) ? $errorConversion[$errno] : $errno;
		if(($pos = strpos($errstr, "\n")) !== false){
			$errstr = substr($errstr, 0, $pos);
		}

		echo "An $errno error happened: \"$errstr\" in \"$errfile\" at line $errline\n";		

		foreach(($trace = $this->getTrace($trace === null ? 3 : 0, $trace)) as $i => $line){
			echo $line."\n";
		}

		return true;
	}
	
	
	public function getTrace($start = 1, $trace = null){
		if($trace === null){
			if(function_exists("xdebug_get_function_stack")){
				$trace = array_reverse(xdebug_get_function_stack());
			}else{
				$e = new \Exception();
				$trace = $e->getTrace();
			}
		}

		$messages = [];
		$j = 0;
		for($i = (int) $start; isset($trace[$i]); ++$i, ++$j){
			$params = "";
			if(isset($trace[$i]["args"]) or isset($trace[$i]["params"])){
				if(isset($trace[$i]["args"])){
					$args = $trace[$i]["args"];
				}else{
					$args = $trace[$i]["params"];
				}
				foreach($args as $name => $value){
					$params .= (is_object($value) ? get_class($value) . " " . (method_exists($value, "__toString") ? $value->__toString() : "object") : gettype($value) . " " . @strval($value)) . ", ";
				}
			}
			$messages[] = "#$j " . (isset($trace[$i]["file"]) ? ($trace[$i]["file"]) : "") . "(" . (isset($trace[$i]["line"]) ? $trace[$i]["line"] : "") . "): " . (isset($trace[$i]["class"]) ? $trace[$i]["class"] . (($trace[$i]["type"] === "dynamic" or $trace[$i]["type"] === "->") ? "->" : "::") : "") . $trace[$i]["function"] . "(" . substr($params, 0, -2) . ")";
		}

		return $messages;
	}
	
	public function shutdownHandler(){
		if(!$this->shutdown){
			echo "Packet thread crashed!\n";
		}
	}

}