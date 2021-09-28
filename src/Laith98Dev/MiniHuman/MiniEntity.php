<?php

namespace Laith98Dev\MiniHuman;

use pocketmine\nbt\tag\CompoundTag;
use pocketmine\Server;
use pocketmine\entity\{
	Entity,
	Human
	};

use pocketmine\block\{
	Slab, Stair, Flowable
};
use pocketmine\block\Liquid;
use pocketmine\level\Position;
use pocketmine\Player;
use pocketmine\level\Level;
use pocketmine\entity\EntityIds;
use pocketmine\entity\Animal;
use pocketmine\entity\Living;
use pocketmine\math\Vector3;
use pocketmine\entity\Vehicle;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityRegainHealthEvent;
use pocketmine\network\mcpe\protocol\ActorEventPacket;
use pocketmine\network\mcpe\protocol\SetActorLinkPacket;
use pocketmine\network\mcpe\protocol\AnimatePacket;
use pocketmine\network\mcpe\protocol\types\EntityLink;

class MiniEntity extends Human
{
	/** @var string|null */
	public $MiniOwner = null;
	
	/** @var string|null */
	public $MiniName = null;
	
	/** @var float */
	public $speed = 0.50;
	
	/** @var int */
	public $jumpTicks = 5;
	
	public function __construct(Level $level, CompoundTag $nbt)
	{
		$this->MiniOwner = $nbt->getString("MiniOwner");
		$this->MiniName = $nbt->getString("MiniName");
		
		parent::__construct($level, $nbt);
	}
	
	public function setOwner(string $value){
		$this->MiniOwner = $value;
	}
	
	public function getOwner(){
		return $this->MiniOwner;
	}
	
	public function getName(): string{
		return $this->MiniName;
	}
	
	public function broadcastMovement(bool $teleport = false): void 
	{
		parent::broadcastMovement($teleport);
	}
	
	public function saveNBT(): void
	{
		parent::saveNBT();
	}
	
	public function entityBaseTick(int $tickDiff = 1): bool
	{
		return parent::entityBaseTick($tickDiff);
	}
	
	public function onUpdate(int $currentTick): bool
	{
		if($this->isClosed())
			return false;
		
		$plugin = Main::getInstance();
		$owner = $plugin->getServer()->getPlayer($this->getOwner());
		
		if($owner !== null){
			$xDiff = $owner->x - $this->x;
			$zDiff = $owner->z - $this->z;
			$totalDiff = abs($xDiff) + abs($zDiff);
			
			if(intval($this->getY()) <= 0){
				if($owner->getLevel()->getFolderName() == $plugin->getServer()->getDefaultLevel()->getFolderName()){
					$this->teleport($owner);
				}
			}
			
			if($this->jumpTicks > 0) {
				$this->jumpTicks--;
			}
			if(!$this->isOnGround()) {
				if($this->motion->y > -$this->gravity * 4){
					$this->motion->y = -$this->gravity * 4;
				}else{
					$this->motion->y += $this->isUnderwater() ? $this->gravity : -$this->gravity;
				}
			}else{
				$this->motion->y -= $this->gravity;
			}
			$this->move($this->motion->x, $this->motion->y, $this->motion->z);
			
			if($this->shouldJump()){
				$this->jump();
			}
			
			if($this->distance($owner) >= 1.5 && $this->distance($owner) <= 10){
				if($owner->getLevel()->getFolderName() == $plugin->getServer()->getDefaultLevel()->getFolderName()){
					if($xDiff !== 0 && $zDiff !== 0 && $totalDiff !== 0){
						$x = $owner->x - $this->getX();
						$y = $owner->y - $this->getY();
						$z = $owner->z - $this->getZ();
						
						if($x * $x + $z * $z < 4 + 1) {
							$this->motion->x = 0;
							$this->motion->z = 0;
						} else {
							$this->motion->x = $this->getSpeed() * 0.15 * ($x / (abs($x) + abs($z)));
							$this->motion->z = $this->getSpeed() * 0.15 * ($z / (abs($x) + abs($z)));
						}
						
						$this->yaw = rad2deg(atan2(-$x, $z));
						$this->pitch = rad2deg(-atan2($y, sqrt($x * $x + $z * $z)));
						
						$this->lookAt($owner);
					}
				}
			}
			
			$this->move($this->motion->x, $this->motion->y, $this->motion->z);
			
			if($this->shouldJump()){
				$this->jump();
			}
			
			if($this->distance($owner) >= 11){
				if($owner->getLevel()->getFolderName() == $plugin->getServer()->getDefaultLevel()->getFolderName()){
					$this->teleport($owner);
				}
			}
			
			if($owner->isSneaking()){
				$this->setSneaking(true);
			} else {
				$this->setSneaking(false);
			}
			
		} else {
			$this->flagForDespawn();
			return false;
		}
		
		$this->updateMovement();
		
		return parent::onUpdate($currentTick);
	}
	
	public function attack(EntityDamageEvent $source): void
	{
		$source->setCancelled();
		parent::attack($source);
	}
	
	public function getSpeed(){
		return ($this->isUnderwater() ? $this->speed / 2 : $this->speed);
	}
	
	public function getFrontBlock($y = 0){
		$dv = $this->getDirectionVector();
		$pos = $this->asVector3()->add($dv->x * $this->getScale(), $y + 1, $dv->z * $this->getScale())->round();
		return $this->getLevel()->getBlock($pos);
	}
	
	public function getJumpMultiplier(){
		return 16;
		if(
			$this->getFrontBlock() instanceof Slab ||
			$this->getFrontBlock() instanceof Stair ||
			$this->getLevel()->getBlock($this->asVector3()->subtract(0,0.5)->round()) instanceof Slab &&
			$this->getFrontBlock()->getId() != 0
		){
			$fb = $this->getFrontBlock();
			if($fb instanceof Slab && $fb->getDamage() & 0x08 > 0) return 8;
			if($fb instanceof Stair && $fb->getDamage() & 0x04 > 0) return 8;
			return 4;
		}
		return 8;
	}
	
	public function shouldJump(){
		if($this->jumpTicks > 0) return false;
		return $this->isCollidedHorizontally || 
		($this->getFrontBlock()->getId() != 0 || $this->getFrontBlock(-1) instanceof Stair) ||
		($this->getLevel()->getBlock($this->asVector3()->add(0,-0,5)) instanceof Slab &&
		(!$this->getFrontBlock(-0.5) instanceof Slab && $this->getFrontBlock(-0.5)->getId() != 0)) &&
		$this->getFrontBlock(1)->getId() == 0 && 
		$this->getFrontBlock(2)->getId() == 0 && 
		!$this->getFrontBlock() instanceof Flowable &&
		$this->jumpTicks == 0;
	}
	
	public function jump() : void{
		$this->motion->y = $this->gravity * $this->getJumpMultiplier();
		$this->move($this->motion->x * 1.25, $this->motion->y, $this->motion->z * 1.25);
		$this->jumpTicks = 5; //($this->getJumpMultiplier() == 4 ? 2 : 5);
	}
}