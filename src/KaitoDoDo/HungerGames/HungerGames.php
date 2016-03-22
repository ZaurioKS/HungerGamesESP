<?php

namespace KaitoDoDo\HungerGames;

use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\PluginTask;
use pocketmine\event\Listener;

use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerLoginEvent;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\utils\TextFormat;
use pocketmine\utils\Config;
use pocketmine\math\Vector3;
use pocketmine\level\Position;
use pocketmine\Player;
use pocketmine\block\Block;
use pocketmine\tile\Sign;
use pocketmine\level\Level;
use pocketmine\item\Item;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\entity\Effect;
use pocketmine\tile\Chest;
use pocketmine\inventory\ChestInventory;

class HungerGames extends PluginBase implements Listener {

    public $prefix = TextFormat::GRAY . "[" . TextFormat::WHITE . TextFormat::BOLD . "Hunger" . TextFormat::RED . "Games" . TextFormat::RESET . TextFormat::GRAY . "]";
	public $mode = 0;
	public $arenas = array();
	public $currentLevel = "";
	
	public function onEnable()
	{
		  $this->getLogger()->info(TextFormat::RED . "HungerGames by KaitoDoDo");

        $this->getServer()->getPluginManager()->registerEvents($this ,$this);
		@mkdir($this->getDataFolder());
                $config2 = new Config($this->getDataFolder() . "/rank.yml", Config::YAML);
		$config2->save();
		$config = new Config($this->getDataFolder() . "/config.yml", Config::YAML);
		if($config->get("arenas")!=null)
		{
			$this->arenas = $config->get("arenas");
		}
		foreach($this->arenas as $lev)
		{
			$this->getServer()->loadLevel($lev);
		}
		$items = array(array(258,0,1),array(260,0,5),array(261,0,1),array(262,0,6),array(264,0,1),array(265,0,1),array(268,0,1),array(271,0,1),array(272,0,1),array(275,0,1),array(283,0,1),array(286,0,1),array(297,0,3),array(298,0,1),array(299,0,1),array(300,0,1),array(301,0,1),array(302,0,1),array(303,0,1),array(304,0,1),array(305,0,1),array(306,0,1),array(307,0,1),array(308,0,1),array(309,0,1),array(314,0,1),array(315,0,1),array(316,0,1),array(317,0,1),array(320,0,4),array(280,0,1),array(364,0,4),array(366,0,5),array(391,0,5));
		if($config->get("chestitems")==null)
		{
			$config->set("chestitems",$items);
		}
		$config->save();
		
		$playerlang = new Config($this->getDataFolder() . "/languages.yml", Config::YAML);
		$playerlang->save();
		
		$lang = new Config($this->getDataFolder() . "/lang.yml", Config::YAML);
		if($lang->get("en")==null)
		{
			$messages = array();
			$messages["kill"] = "fue matado por";
			$messages["cannotjoin"] = "El juego ya ha iniciado.";
			$messages["seconds"] = "segundos para empezar";
			$messages["won"] = "Ha ganado Los juegos del Hambre!";
			$messages["deathmatchminutes"] = "minutos para el combate final!";
			$messages["deathmatchseconds"] = "segundos para el combate final!";
			$messages["chestrefill"] = "Los cofres han sido rellenados!";
			$messages["remainingminutes"] = "minutos restantes!";
			$messages["remainingseconds"] = "segundos restantes!";
			$messages["nowinner"] = "Sin ganadores esta vez!";
			$messages["moreplayers"] = "Faltan jugadores!";
			$lang->set("en",$messages);
		}
		$lang->save();
		
		$statistic = new Config($this->getDataFolder() . "/statistic.yml", Config::YAML);
		$statistic->save();
		$this->getServer()->getScheduler()->scheduleRepeatingTask(new GameSender($this), 20);
		$this->getServer()->getScheduler()->scheduleRepeatingTask(new RefreshSigns($this), 10);
	}
	
	public function onDeath(PlayerDeathEvent $event){
        $jugador = $event->getEntity();
		$level = $jugador->getLevel();
        $cause = $jugador->getLastDamageCause();
        if(!($cause instanceof EntityDamageByEntityEvent)) return;
        $asassin = ($cause->getDamager() instanceof Player ? $cause->getDamager() : false);
		if($asassin !== false) {
			$event->setDeathMessage("");
			foreach($level->getPlayers() as $pl)
			{
				$playerlang = new Config($this->getDataFolder() . "/languages.yml", Config::YAML);
				$lang = new Config($this->getDataFolder() . "/lang.yml", Config::YAML);
				$toUse = $lang->get($playerlang->get($pl->getName()));
                                $muerto = $jugador->getName();
                                $asesino = $asassin->getName();
                                $config2 = new Config($this->getDataFolder() . "/rank.yml", Config::YAML);
				$rankmuerto = $config2->get($jugador->getName());
                                $rankasesino = $config2->get($asassin->getName());
				$pl->sendMessage(TextFormat::RED . $rankmuerto . "§f" . $muerto . TextFormat::YELLOW . " " . $toUse["kill"] . " " . TextFormat::GREEN . $rankasesino. "§f" . $asesino . TextFormat::YELLOW . ".");
			}
			$statistic = new Config($this->getDataFolder() . "/statistic.yml", Config::YAML);
			$stats = $statistic->get($asassin->getName());
			$soFarPlayer = $stats[0];
			$soFarPlayer++;
			$stats[0] = $soFarPlayer;
			$statistic->set($asassin->getName(),$stats);
			$statistic->save();
		}
	}
	
	public function onMove(PlayerMoveEvent $event)
	{
		$player = $event->getPlayer();
		$level = $player->getLevel()->getFolderName();
		if(in_array($level,$this->arenas))
		{
			$config = new Config($this->getDataFolder() . "/config.yml", Config::YAML);
			$sofar = $config->get($level . "StartTime");
			if($sofar > 0)
			{
				$to = clone $event->getFrom();
				$to->yaw = $event->getTo()->yaw;
				$to->pitch = $event->getTo()->pitch;
				$event->setTo($to);
			}
		}
	}
	
	public function onLogin(PlayerLoginEvent $event)
	{
		$player = $event->getPlayer();
		$playerlang = new Config($this->getDataFolder() . "/languages.yml", Config::YAML);
		if($playerlang->get($player->getName())==null)
		{
			$playerlang->set($player->getName(),"en");
			$playerlang->save();
		}
		$statistic = new Config($this->getDataFolder() . "/statistic.yml", Config::YAML);
		if($statistic->get($player->getName())==null)
		{
			$statistic->set($player->getName(),array(0,0));
			$statistic->save();
		}
		$player->getInventory()->clearAll();
		$spawn = $this->getServer()->getDefaultLevel()->getSafeSpawn();
		$this->getServer()->getDefaultLevel()->loadChunk($spawn->getFloorX(), $spawn->getFloorZ());
		$player->teleport($spawn,0,0);
	}
	
	public function onBlockBreak(BlockBreakEvent $event)
	{
		$player = $event->getPlayer();
		$level = $player->getLevel()->getFolderName();
		if(in_array($level,$this->arenas))
		{
			$event->setCancelled(true);
		}
	}
	
	public function onBlockPlace(BlockPlaceEvent $event)
	{
		$player = $event->getPlayer();
		$level = $player->getLevel()->getFolderName();
		if(in_array($level,$this->arenas))
		{
			$event->setCancelled(true);
		}
	}
	
	public function onDamage(EntityDamageEvent $event)
	{
		if($event instanceof EntityDamageByEntityEvent)
		{
			$player = $event->getEntity();
			$damager = $event->getDamager();
			if($player instanceof Player)
			{
				if($damager instanceof Player)
				{
					$level = $player->getLevel()->getFolderName();
					$config = new Config($this->getDataFolder() . "/config.yml", Config::YAML);
					if($config->get($level . "PlayTime") != null)
					{
						if($config->get($level . "PlayTime") > 630)
						{
							$event->setCancelled(true);
						}
					}
				}
			}
		}
	}
	
	public function onCommand(CommandSender $player, Command $cmd, $label, array $args) {
		$lang = new Config($this->getDataFolder() . "/lang.yml", Config::YAML);
        switch($cmd->getName()){
			case "hg":
				if($player->isOp())
				{
					if(!empty($args[0]))
					{
						if($args[0]=="make")
						{
							if(!empty($args[1]))
							{
								if(file_exists($this->getServer()->getDataPath() . "/worlds/" . $args[1]))
								{
									$this->getServer()->loadLevel($args[1]);
									$this->getServer()->getLevelByName($args[1])->loadChunk($this->getServer()->getLevelByName($args[1])->getSafeSpawn()->getFloorX(), $this->getServer()->getLevelByName($args[1])->getSafeSpawn()->getFloorZ());
									array_push($this->arenas,$args[1]);
									$this->currentLevel = $args[1];
									$this->mode = 1;
									$player->sendMessage($this->prefix . "Toca el bloque para el spawn!");
									$player->setGamemode(1);
									$player->teleport($this->getServer()->getLevelByName($args[1])->getSafeSpawn(),0,0);
								}
								else
								{
									$player->sendMessage($this->prefix . "ERROR mundo no encontrado.");
								}
							}
							else
							{
								$player->sendMessage($this->prefix . "ERROR parametros no encontrados.");
							}
						}
						else if($args[0]=="leave")
						{
							$spawn = $this->getServer()->getDefaultLevel()->getSafeSpawn();
							$this->getServer()->getDefaultLevel()->loadChunk($spawn->getFloorX(), $spawn->getFloorZ());
							$player->teleport($spawn,0,0);
						}
						else
						{
							$player->sendMessage($this->prefix . "Comando no valido.");
						}
					}
					else
					{
						$player->sendMessage($this->prefix . "Faltan parametros.");
					}
				}
				else
				{
					if(!empty($args[0]))
					{
						if($args[0]=="leave")
						{
							$spawn = $this->getServer()->getDefaultLevel()->getSafeSpawn();
							$this->getServer()->getDefaultLevel()->loadChunk($spawn->getFloorX(), $spawn->getFloorZ());
							$player->teleport($spawn,0,0);
						}
					}
				}
			return true;
			
			case "lang":
				if(!empty($args[0]))
				{
					if($lang->get($args[0])!=null)
					{
						$playerlang = new Config($this->getDataFolder() . "/languages.yml", Config::YAML);
						$playerlang->set($player->getName(),$args[0]);
						$playerlang->save();
						$player->sendMessage(TextFormat::GREEN . "Lenguaje: " . $args[0]);
					}
					else
					{
						$player->sendMessage(TextFormat::RED . "Lenguaje no encontrado!");
					}
				}
			return true;
                        
                        case "setrank":
				if($player->isOp())
				{
				if(!empty($args[0]))
				{
					if(!empty($args[1]))
					{
					$rank = "";
					if($args[0]=="VIP+")
					{
						$rank = "§b[§aVIP§4+§b]";
					}
					else if($args[0]=="YouTuber")
					{
						$rank = "§b[§4You§7Tuber§b]";
					}
					else if($args[0]=="YouTuber+")
					{
						$rank = "§b[§4You§7Tuber§4+§b]";
					}
					else
					{
						$rank = "§b[§a" . $args[0] . "§b]";
					}
					$config = new Config($this->getDataFolder() . "/rank.yml", Config::YAML);
					$config->set($args[1],$rank);
					$config->save();
					$player->sendMessage($args[1] . " obtuvo el rango de: " . $rank);
					}
					else
					{
						$player->sendMessage("Missing parameter(s)");
					}
				}
				else
				{
					$player->sendMessage("Missing parameter(s)");
				}
				}
			return true;
                        
                        case "start":
                            if($player->isOp())
				{
                                $player->sendMessage("§bInicio en 10 segundos...");
                                $config = new Config($this->getDataFolder() . "/config.yml", Config::YAML);
		$config->set("arenas",$this->arenas);
		foreach($this->arenas as $arena)
		{
			$config->set($arena . "PlayTime", 660);
			$config->set($arena . "StartTime", 10);
		}
		$config->save();
                                }
                                else{
                                    $player->sendMessage("§cNo eres OP");
                                }
			break;
		

		}
	}
	
        public function onChat(PlayerChatEvent $event)
	{
		$player = $event->getPlayer();
		$message = $event->getMessage();
		$config = new Config($this->getDataFolder() . "/rank.yml", Config::YAML);
		$rank = "";
		if($config->get($player->getName()) != null)
		{
			$rank = $config->get($player->getName());
		}
		$event->setFormat($rank . TextFormat::WHITE . $player->getName() . " §d:§f " . $message);
	}
        
	public function onInteract(PlayerInteractEvent $event)
	{
		$player = $event->getPlayer();
		$block = $event->getBlock();
		$tile = $player->getLevel()->getTile($block);
		
		if($tile instanceof Sign) 
		{
			if($this->mode==26)
			{
				$tile->setText(TextFormat::AQUA . "[Unirse]",TextFormat::YELLOW  . "0 / 24",$this->currentLevel,$this->prefix);
				$this->refreshArenas();
				$this->currentLevel = "";
				$this->mode = 0;
				$player->sendMessage($this->prefix . "Arena registrada!");
			}
			else
			{
				$text = $tile->getText();
				if($text[3] == $this->prefix)
				{
					if($text[0]==TextFormat::AQUA . "[Unirse]")
					{
						$config = new Config($this->getDataFolder() . "/config.yml", Config::YAML);
						$level = $this->getServer()->getLevelByName($text[2]);
						$aop = count($level->getPlayers());
						$thespawn = $config->get($text[2] . "Spawn" . ($aop+1));
						$spawn = new Position($thespawn[0]+0.5,$thespawn[1],$thespawn[2]+0.5,$level);
						$level->loadChunk($spawn->getFloorX(), $spawn->getFloorZ());
						$player->teleport($spawn,0,0);
						$player->setNameTag($player->getName());
						$player->getInventory()->clearAll();
                                                $config2 = new Config($this->getDataFolder() . "/rank.yml", Config::YAML);
						$rank = $config2->get($player->getName());
                                                $p = $player->getName();
                                                $player->setNameTag($rank . "§f" . $p);
                                                if($rank == "§b[§aVIP§4+§b]")
						{
							$player->getInventory()->setContents(array(Item::get(0, 0, 0)));
							$player->getInventory()->setHelmet(Item::get(Item::CHAIN_HELMET));
							$player->getInventory()->setChestplate(Item::get(Item::CHAIN_CHESTPLATE));
							$player->getInventory()->setLeggings(Item::get(Item::CHAIN_LEGGINGS));
							$player->getInventory()->setBoots(Item::get(Item::CHAIN_BOOTS));
							$player->getInventory()->setItem(0, Item::get(Item::DIAMOND_AXE, 0, 1));
							$player->getInventory()->setHotbarSlotIndex(0, 0);
						}
						else if($rank == "§b[§aVIP§b]")
						{
							$player->getInventory()->setContents(array(Item::get(0, 0, 0)));
							$player->getInventory()->setHelmet(Item::get(Item::GOLD_HELMET));
							$player->getInventory()->setChestplate(Item::get(Item::GOLD_CHESTPLATE));
							$player->getInventory()->setLeggings(Item::get(Item::LEATHER_PANTS));
							$player->getInventory()->setBoots(Item::get(Item::LEATHER_BOOTS));
							$player->getInventory()->setItem(0, Item::get(Item::IRON_AXE, 0, 1));
							$player->getInventory()->setHotbarSlotIndex(0, 0);
						}
						else if($rank == "§b[§4You§7Tuber§b]")
						{
							$player->getInventory()->setContents(array(Item::get(0, 0, 0)));
							$player->getInventory()->setHelmet(Item::get(Item::GOLD_HELMET));
							$player->getInventory()->setChestplate(Item::get(Item::GOLD_CHESTPLATE));
							$player->getInventory()->setLeggings(Item::get(Item::GOLD_LEGGINGS));
							$player->getInventory()->setBoots(Item::get(Item::GOLD_BOOTS));
							$player->getInventory()->setItem(0, Item::get(Item::IRON_AXE, 0, 1));
							$player->getInventory()->setHotbarSlotIndex(0, 0);
						}
						else if($rank == "§b[§4You§7Tuber§4+§b]")
						{
							$player->getInventory()->setContents(array(Item::get(0, 0, 0)));
							$player->getInventory()->setHelmet(Item::get(Item::DIAMOND_HELMET));
							$player->getInventory()->setChestplate(Item::get(Item::CHAIN_CHESTPLATE));
							$player->getInventory()->setLeggings(Item::get(Item::CHAIN_LEGGINGS));
							$player->getInventory()->setBoots(Item::get(Item::DIAMOND_BOOTS));
							$player->getInventory()->setItem(0, Item::get(Item::DIAMOND_AXE, 0, 1));
							$player->getInventory()->setHotbarSlotIndex(0, 0);
						}
					}
					else
					{
						$playerlang = new Config($this->getDataFolder() . "/languages.yml", Config::YAML);
						$lang = new Config($this->getDataFolder() . "/lang.yml", Config::YAML);
						$toUse = $lang->get($playerlang->get($player->getName()));
						$player->sendMessage($this->prefix . $toUse["cannotjoin"]);
					}
				}
			}
		}
		else if($this->mode>=1&&$this->mode<=24)
		{
			$config = new Config($this->getDataFolder() . "/config.yml", Config::YAML);
			$config->set($this->currentLevel . "Spawn" . $this->mode, array($block->getX(),$block->getY()+1,$block->getZ()));
			$player->sendMessage($this->prefix . "Spawn " . $this->mode . " ha sido registrado!");
			$this->mode++;
			if($this->mode==25)
			{
				$player->sendMessage($this->prefix . "Ahora toca el spawn del combate final.");
			}
			$config->save();
		}
		else if($this->mode==25)
		{
			$config = new Config($this->getDataFolder() . "/config.yml", Config::YAML);
			$level = $this->getServer()->getLevelByName($this->currentLevel);
			$level->setSpawn = (new Vector3($block->getX(),$block->getY()+2,$block->getZ()));
			$config->set("arenas",$this->arenas);
			$player->sendMessage($this->prefix . "Toca el cartel para registrar la arena!");
			$spawn = $this->getServer()->getDefaultLevel()->getSafeSpawn();
			$this->getServer()->getDefaultLevel()->loadChunk($spawn->getFloorX(), $spawn->getFloorZ());
			$player->teleport($spawn,0,0);
			$config->save();
			$this->mode=26;
		}
	}
	
	public function refreshArenas()
	{
		$config = new Config($this->getDataFolder() . "/config.yml", Config::YAML);
		$config->set("arenas",$this->arenas);
		foreach($this->arenas as $arena)
		{
			$config->set($arena . "PlayTime", 660);
			$config->set($arena . "StartTime", 180);
		}
		$config->save();
	}
}

class RefreshSigns extends PluginTask {
    public $prefix = TextFormat::GRAY . "[" . TextFormat::WHITE . TextFormat::BOLD . "Hunger" . TextFormat::RED . "Games" . TextFormat::RESET . TextFormat::GRAY . "]";
	public function __construct($plugin)
	{
		$this->plugin = $plugin;
		parent::__construct($plugin);
	}
  
	public function onRun($tick)
	{
		$allplayers = $this->plugin->getServer()->getOnlinePlayers();
		$level = $this->plugin->getServer()->getDefaultLevel();
		$tiles = $level->getTiles();
		foreach($tiles as $t) {
			if($t instanceof Sign) {	
				$text = $t->getText();
				if($text[3]==$this->prefix)
				{
					$aop = 0;
					foreach($allplayers as $player){if($player->getLevel()->getFolderName()==$text[2]){$aop=$aop+1;}}
					$ingame = TextFormat::AQUA . "[Unirse]";
					$config = new Config($this->plugin->getDataFolder() . "/config.yml", Config::YAML);
					if($config->get($text[2] . "PlayTime")!=660)
					{
						$ingame = TextFormat::DARK_PURPLE . "[En juego]";
					}
					else if($aop>=24)
					{
						$ingame = TextFormat::GOLD . "[Lleno]";
					}
					$t->setText($ingame,TextFormat::YELLOW  . $aop . " / 24",$text[2],$this->prefix);
				}
			}
		}
	}
}

class GameSender extends PluginTask {
    public $prefix = TextFormat::GRAY . "[" . TextFormat::WHITE . TextFormat::BOLD . "Hunger" . TextFormat::RED . "Games" . TextFormat::RESET . TextFormat::GRAY . "]";
	public function __construct($plugin)
	{
		$this->plugin = $plugin;
		parent::__construct($plugin);
	}
  
	public function onRun($tick)
	{
		$config = new Config($this->plugin->getDataFolder() . "/config.yml", Config::YAML);
		$arenas = $config->get("arenas");
		if(!empty($arenas))
		{
			foreach($arenas as $arena)
			{
				$time = $config->get($arena . "PlayTime");
				$timeToStart = $config->get($arena . "StartTime");
				$levelArena = $this->plugin->getServer()->getLevelByName($arena);
				if($levelArena instanceof Level)
				{
					$allplayers = $this->plugin->getServer()->getOnlinePlayers();
					$playersArena = array();
					foreach($allplayers as $play)
					{
						if($play->getLevel()->getFolderName() == $arena)
						{
							array_push($playersArena, $play);
						}
					}
					if(count($playersArena)==0)
					{
						$config->set($arena . "PlayTime", 660);
						$config->set($arena . "StartTime", 180);
					}
					else
					{
						if(count($playersArena)>=2)
						{
							if($timeToStart>0)
							{
								$timeToStart--;
								foreach($playersArena as $pl)
								{
									$playerlang = new Config($this->plugin->getDataFolder() . "/languages.yml", Config::YAML);
									$lang = new Config($this->plugin->getDataFolder() . "/lang.yml", Config::YAML);
									$toUse = $lang->get($playerlang->get($pl->getName()));
									$pl->sendTip(TextFormat::YELLOW . $timeToStart . " " . $toUse["seconds"]);
								}
								if($timeToStart<=0)
								{
									$this->refillChests($levelArena);
								}
								$config->set($arena . "StartTime", $timeToStart);
							}
							else
							{
								$aop = count($levelArena->getPlayers());
								if($aop==1)
								{
									foreach($playersArena as $pl)
									{
										foreach($this->plugin->getServer()->getOnlinePlayers() as $plpl)
										{
											$playerlang = new Config($this->plugin->getDataFolder() . "/languages.yml", Config::YAML);
											$lang = new Config($this->plugin->getDataFolder() . "/lang.yml", Config::YAML);
											$toUse = $lang->get($playerlang->get($plpl->getName()));
											$plpl->sendMessage($this->prefix . $pl->getName() . " " . $toUse["won"]);
										}
										$statistic = new Config($this->getDataFolder() . "/statistic.yml", Config::YAML);
										$stats = $statistic->get($pl->getName());
										$soFarPlayer = $stats[1];
										$soFarPlayer++;
										$stats[1] = $soFarPlayer;
										$statistic->set($pl->getName(),$stats);
										$statistic->save();
										$pl->getInventory()->clearAll();
										$pl->removeAllEffects();
										$pl->setNameTag($pl->getName());
										$spawn = $this->plugin->getServer()->getDefaultLevel()->getSafeSpawn();
										$this->plugin->getServer()->getDefaultLevel()->loadChunk($spawn->getX(), $spawn->getZ());
										$pl->teleport($spawn,0,0);
									}
									$config->set($arena . "PlayTime", 660);
									$config->set($arena . "StartTime", 180);
								}
								$time--;
                                                                if($time == 659)
								{
									foreach($playersArena as $pl)
									{
										$pl->sendMessage("§a§l--------------------------------");
                                                                                $pl->sendMessage("§b§lTienes 30 segundos de Invencibilidad");
                                                                                $pl->sendMessage("§a§l--------------------------------");
									}
								}
                                                                if($time == 645)
								{
									foreach($playersArena as $pl)
									{
										$pl->sendMessage("§a§l--------------------------------");
                                                                                $pl->sendMessage("§b§lQuedan 15 segundos de Invencibilidad");
                                                                                $pl->sendMessage("§a§l--------------------------------");
									}
								}
								if($time == 630)
								{
									foreach($playersArena as $pl)
									{
										$pl->sendMessage("§a§l-------------------");
                                                                                $pl->sendMessage("§b§lYa no eres Invencible");
                                                                                $pl->sendMessage("§a§l-------------------");
									}
								}
								if($time == 420)
								{
									foreach($playersArena as $pl)
									{
										$playerlang = new Config($this->plugin->getDataFolder() . "/languages.yml", Config::YAML);
										$lang = new Config($this->plugin->getDataFolder() . "/lang.yml", Config::YAML);
										$toUse = $lang->get($playerlang->get($pl->getName()));
										$pl->sendMessage("§a§l---------------------------");
                                                                                $pl->sendMessage("§b§lLos cofres han sido rellenados");
                                                                                $pl->sendMessage("§a§l---------------------------");
									}
									$this->refillChests($levelArena);
								}
								if($time>=180)
								{
								$time2 = $time - 180;
								$minutes = $time2 / 60;
								if(is_int($minutes) && $minutes>0)
								{
									foreach($playersArena as $pl)
									{
										$playerlang = new Config($this->plugin->getDataFolder() . "/languages.yml", Config::YAML);
									$lang = new Config($this->plugin->getDataFolder() . "/lang.yml", Config::YAML);
										$toUse = $lang->get($playerlang->get($pl->getName()));
										$pl->sendMessage($this->prefix . $minutes . " " . $toUse["deathmatchminutes"]);
									}
								}
								
								else if($time2 == 30 || $time2 == 15 || $time2 == 10 || $time2 ==5 || $time2 ==4 || $time2 ==3 || $time2 ==2 || $time2 ==1)
								{
									foreach($playersArena as $pl)
									{
										$playerlang = new Config($this->plugin->getDataFolder() . "/languages.yml", Config::YAML);
									$lang = new Config($this->plugin->getDataFolder() . "/lang.yml", Config::YAML);
										$toUse = $lang->get($playerlang->get($pl->getName()));
										$pl->sendMessage($this->prefix . $time2 . " " . $toUse["deathmatchseconds"]);
									}
								}
								if($time2 <= 0)
								{
									$spawn = $levelArena->getSafeSpawn();
									$levelArena->loadChunk($spawn->getX(), $spawn->getZ());
									foreach($playersArena as $pl)
									{
										$pl->teleport($spawn,0,0);
									}
								}
								}
								else
								{
									$minutes = $time / 60;
									if(is_int($minutes) && $minutes>0)
									{
										foreach($playersArena as $pl)
										{
											$playerlang = new Config($this->plugin->getDataFolder() . "/languages.yml", Config::YAML);
									$lang = new Config($this->plugin->getDataFolder() . "/lang.yml", Config::YAML);
											$toUse = $lang->get($playerlang->get($pl->getName()));
											$pl->sendMessage($this->prefix . $minutes . " " . $toUse["remainingminutes"]);
										}
									}
									else if($time == 30 || $time == 15 || $time == 10 || $time ==5 || $time ==4 || $time ==3 || $time ==2 || $time ==1)
									{
										foreach($playersArena as $pl)
										{
											$playerlang = new Config($this->plugin->getDataFolder() . "/languages.yml", Config::YAML);
									$lang = new Config($this->plugin->getDataFolder() . "/lang.yml", Config::YAML);
											$toUse = $lang->get($playerlang->get($pl->getName()));
											$pl->sendMessage($this->prefix . $time . " " . $toUse["remainingseconds"]);
										}
									}
									if($time <= 0)
									{
										$spawn = $this->plugin->getServer()->getDefaultLevel()->getSafeSpawn();
										$this->plugin->getServer()->getDefaultLevel()->loadChunk($spawn->getX(), $spawn->getZ());
										foreach($playersArena as $pl)
										{
											$pl->teleport($spawn,0,0);
											$playerlang = new Config($this->plugin->getDataFolder() . "/languages.yml", Config::YAML);
									$lang = new Config($this->plugin->getDataFolder() . "/lang.yml", Config::YAML);
											$toUse = $lang->get($playerlang->get($pl->getName()));
											$pl->sendMessage($this->prefix . $toUse["nowinner"]);
											$pl->getInventory()->clearAll();
										}
										$time = 660;
									}
								}
								$config->set($arena . "PlayTime", $time);
							}
						}
						else
						{
							if($timeToStart<=0)
							{
								foreach($playersArena as $pl)
								{
									foreach($this->plugin->getServer()->getOnlinePlayers() as $plpl)
									{
										$playerlang = new Config($this->plugin->getDataFolder() . "/languages.yml", Config::YAML);
									$lang = new Config($this->plugin->getDataFolder() . "/lang.yml", Config::YAML);
										$toUse = $lang->get($playerlang->get($plpl->getName()));
										$plpl->sendMessage($this->prefix . $pl->getName() . " " . $toUse["won"]);
									}
									$statistic = new Config($this->plugin->getDataFolder() . "/statistic.yml", Config::YAML);
									$stats = $statistic->get($pl->getName());
									$soFarPlayer = $stats[1];
									$soFarPlayer++;
									$stats[1] = $soFarPlayer;
									$statistic->set($pl->getName(),$stats);
									$statistic->save();
									$spawn = $this->plugin->getServer()->getDefaultLevel()->getSafeSpawn();
									$this->plugin->getServer()->getDefaultLevel()->loadChunk($spawn->getX(), $spawn->getZ());
									$pl->getInventory()->clearAll();
									$pl->teleport($spawn);
								}
								$config->set($arena . "PlayTime", 660);
								$config->set($arena . "StartTime", 180);
							}
							else
							{
								foreach($playersArena as $pl)
								{
									$playerlang = new Config($this->plugin->getDataFolder() . "/languages.yml", Config::YAML);
									$lang = new Config($this->plugin->getDataFolder() . "/lang.yml", Config::YAML);
									$toUse = $lang->get($playerlang->get($pl->getName()));
									$pl->sendTip(TextFormat::RED . $toUse["moreplayers"]);
								}
								$config->set($arena . "PlayTime", 660);
								$config->set($arena . "StartTime", 180);
							}
						}
					}
				}
			}
		}
		$config->save();
	}
	
	public function refillChests(Level $level)
	{
		$config = new Config($this->plugin->getDataFolder() . "/config.yml", Config::YAML);
		$tiles = $level->getTiles();
		foreach($tiles as $t) {
			if($t instanceof Chest) 
			{
				$chest = $t;
				$chest->getInventory()->clearAll();
				if($chest->getInventory() instanceof ChestInventory)
				{
					for($i=0;$i<=26;$i++)
					{
						$rand = rand(1,3);
						if($rand==1)
						{
							$k = array_rand($config->get("chestitems"));
							$v = $config->get("chestitems")[$k];
							$chest->getInventory()->setItem($i, Item::get($v[0],$v[1],$v[2]));
						}
					}									
				}
			}
		}
	}
}

