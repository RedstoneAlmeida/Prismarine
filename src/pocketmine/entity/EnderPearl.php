<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
*/

declare(strict_types=1);

namespace pocketmine\entity;

use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityEnderPearlEvent;
use pocketmine\level\Level;
use pocketmine\level\particle\PortalParticle;
use pocketmine\level\sound\EndermanTeleportSound;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\AddEntityPacket;
use pocketmine\Player;
use pocketmine\Server;

class EnderPearl extends Projectile{
	const NETWORK_ID = 87;

	public $width = 0.25;
	public $length = 0.25;
	public $height = 0.25;

	protected $gravity = 0.03;
	protected $drag = 0.01;

	private $hasTeleportedShooter = false;

	public function __construct(Level $level, CompoundTag $nbt, Entity $shootingEntity = null){
		parent::__construct($level, $nbt, $shootingEntity);
	}

	public function onUpdate(int $currentTick): bool{
		if($this->closed){
			return false;
		}

		$this->timings->startTiming();

		$hasUpdate = parent::onUpdate($currentTick);

		if($this->age > 1200 or $this->isCollided){
			if($this->shootingEntity instanceof Player and $this->shootingEntity->isOnline() and $this->y > 0){
				$ev = new EntityEnderPearlEvent($this->shootingEntity, $this);
				Server::getInstance()->getPluginManager()->callEvent($ev);
				if(!$ev->isCancelled()){
					if($this->shootingEntity->isSurvival()){
						$ev = new EntityDamageEvent($this->shootingEntity, EntityDamageEvent::CAUSE_FALL, 5);
						$this->shootingEntity->attack($ev->getFinalDamage(), $ev);
					}
					for($i = 0; $i < 5; $i++){
						$this->shootingEntity->getLevel()->addParticle(new PortalParticle(new Vector3($this->shootingEntity->x + mt_rand(-15, 15) / 10, $this->shootingEntity->y + mt_rand(0, 20) / 10, $this->shootingEntity->z + mt_rand(-15, 15) / 10)));
					}
					$this->shootingEntity->getLevel()->addSound(new EndermanTeleportSound($this->getPosition()), [$this->shootingEntity]);
					$this->shootingEntity->teleport($this->getPosition());
				}
			}
			$this->kill();
			$hasUpdate = true;
		}

		$this->timings->stopTiming();

		return $hasUpdate;
	}

	public function spawnTo(Player $player){
		$pk = new AddEntityPacket();
		$pk->type = EnderPearl::NETWORK_ID;
		$pk->entityRuntimeId = $this->getId();
		$pk->x = $this->x;
		$pk->y = $this->y;
		$pk->z = $this->z;
		$pk->speedX = $this->motionX;
		$pk->speedY = $this->motionY;
		$pk->speedZ = $this->motionZ;
		$pk->metadata = $this->dataProperties;
		$player->dataPacket($pk);

		parent::spawnTo($player);
	}
}