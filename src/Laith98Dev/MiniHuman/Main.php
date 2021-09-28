<?php

namespace Laith98Dev\MiniHuman;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\Player;
use pocketmine\level\Level;
use pocketmine\entity\Entity;
use pocketmine\Server;
use pocketmine\math\Vector3;
use pocketmine\command\{Command, CommandSender};
use pocketmine\utils\TextFormat as TF;
use pocketmine\event\player\PlayerQuitEvent;

use jojoe77777\FormAPI\SimpleForm;
use jojoe77777\FormAPI\CustomForm;
use jojoe77777\FormAPI\ModalForm;

class Main extends PluginBase implements Listener 
{
	public static $instance = null;
	
	/** @var string[] */
	public $entites = [];
	
	public function onLoad(){
		self::$instance = $this;
	}
	
	public static function getInstance(){
		return self::$instance;
	}
	
	public function onEnable(){
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		
		if(!class_exists(SimpleForm::class)){
			$this->getLogger()->error("§4Vui lòng cài thêm plugin FormAPI");
			$this->getServer()->getPluginManager()->disablePlugin($this);
		}
		
		Entity::registerEntity(MiniEntity::class, true);
	}
	
	public function onCommand(CommandSender $sender, Command $cmd, string $commandLabel, array $args): bool{
		if($cmd->getName() == "mh"){
			if($sender instanceof Player){
				if(!$sender->hasPermission("minihuman.command")){
					$sender-->sendMessage(TF::RED . "You don't have permission to use this command!");
					return false;
				}
				
				$this->OpenMainForm($sender);
			} else {
				$sender->sendMessage("run command in-game onyl!");
				return false;
			}
		}
		return true;
	}
	
	public function OpenMainForm(Player $player){
		$form = new SimpleForm(function (Player $player, int $data = null){
			$result = $data;
			if($result === null)
				return false;
			
			switch ($result){
				case 0:
					$this->SpawnMiniForm($player);
				break;
				
				case 1:
					$this->removeMini($player);
				break;
			}
		});
		
		$form->setTitle("MiniHuman Menu");
		$form->addButton("§aSpawn", 0, "textures/ui/dressing_room_skins");
		$form->addButton("§cXoá bỏ", 0, "textures/ui/trash");
		$form->sendToPlayer($player);
		return $form;
	}
	
	public function SpawnMiniForm(Player $player){
		$form = new CustomForm(function (Player $player, array $data = null){
			$result = $data;
			if($result === null)
				return false;
			
			$players = [];
			
			foreach ($this->getServer()->getOnlinePlayers() as $pp){
				$players[] = $pp->getName();
			}
			
			$miniPlayer = null;
			$name = null;
			if($data[0] !== null){
				$p = $players[$data[0]];
				$miniPlayer = $this->getServer()->getPlayer($p);
				if($miniPlayer == null)
					return false;
			}
			
			if($data[1] !== null)
				$name = $data[1];
			
			if($name == null){
				$player->sendMessage(TF::RED . "§4Nhập tên cho miniHuman nào!");
				return false;
			}
			
			if($this->hasBaby($player)){
				$player->sendMessage(TF::RED . "§4Bạn chỉ đc spawn 1 lần thôi!");
				return false;
			}
			
			$this->createMini($player, $miniPlayer, $name);
		});
		$players = [];
		
		foreach ($this->getServer()->getOnlinePlayers() as $pp){
			$players[] = $pp->getName();
		}
		
		$form->setTitle("§bNgười chơi trực tuyến§f");
		$form->addDropdown("Chọn người chơi", $players);
		$form->addInput("Tên:", "Đặt tên cho miniHuman");
		$form->sendToPlayer($player);
		return $form;
	}
	
	public function createMini(Player $player, Player $miniPlayer, string $name): bool{
		
		if($player === null || $miniPlayer === null)
			return false;
		
		if($this->hasBaby($player)){
			$player->sendMessage(TF::RED . "§4Bạn chỉ đc spawn 1 lần thôi!");
			return false;
		}
		
		$setEntitySkin = false;
		$level = $player->getLevel();
		$nbt = Entity::createBaseNBT($player->asVector3(), null, 2, 2);
		if(($skin = $miniPlayer->namedtag->getTag("Skin")) !== null){
			$nbt->setTag($skin);
		} else {
			$setEntitySkin = true;
		}
		$nbt->setString("MiniOwner", $player->getName());
		$nbt->setString("MiniName", $name);
		$entity = new MiniEntity($level, $nbt);
		$entity->setNameTag($name);
		$entity->setNameTagAlwaysVisible(true);
		//$entity->setCanSaveWithChunk(false);
		$entity->setNameTagVisible(true);
		$entity->setScale(0.40);
		$entity->namedtag->setString("MiniOwner", $player->getName());
		$entity->namedtag->setString("MiniName", $name);
		if($setEntitySkin)
			$entity->setSkin($miniPlayer->getSkin());
		$entity->spawnToAll();
		$this->entites[$player->getName()] = $entity;
		$player->sendMessage("§aĐã tạo thành công");
		return true;
	}
	
	public function removeMini(Player $player): bool{
		$mini = $this->getMini($player);
		if($mini == null){
			$player->sendMessage(TF::RED . "§4Bạn chưa spawn lần nào");
			return false;
		}
		
		$mini->flagForDespawn();
		unset($this->entites[$player->getName()]);
		return true;
	}
	
	public function hasBaby(Player $player){
		return isset($this->entites[$player->getName()]);
	}
	
	public function getMini(Player $player){
		if(!$this->hasBaby($player))
			return null;
		
		return $this->entites[$player->getName()];
	}
	
	public function getEntites(){
		return $this->entites;
	}
	
	public function onQuit(PlayerQuitEvent $event){
		$player = $event->getPlayer();
		
		if($this->hasBaby($player)){
			$this->removeMini($player);
		}
	}
}