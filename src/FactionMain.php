<?php

namespace FactionsPro;
use pocketmine\plugin\PluginBase;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\event\Listener;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\Player;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\utils\TextFormat;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\utils\Config;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\block\Snow;
use pocketmine\math\Vector3;
use pocketmine\level\Position;
use pocketmine\entity\Effect;
class FactionMain extends PluginBase implements Listener {
	
	public $db;
	public $prefs;
	public function onEnable() {
		
		@mkdir($this->getDataFolder());
		
		if(!file_exists($this->getDataFolder() . "BannedNames.txt")) {
			$file = fopen($this->getDataFolder() . "BannedNames.txt", "w");
			$txt = "Admin:admin:Staff:staff:Owner:owner:Builder:builder:Op:OP:op";
			fwrite($file, $txt);
		}
      
		
		$this->getServer()->getPluginManager()->registerEvents(new FactionListener($this), $this);
		$this->fCommand = new FactionCommands($this);
		
		$this->prefs = new Config($this->getDataFolder() . "Prefs.yml", CONFIG::YAML, array(
				"MaxFactionNameLength" => 15,
				"MaxPlayersPerFaction" => 30,
				"OnlyLeadersAndOfficersCanInvite" => true,
				"OfficersCanClaim" => false,
				"PlotSize" => 25,
                "PlayersNeededInFactionToClaimAPlot" => 5,
                "PowerNeededToClaimAPlot" => 1000,
                "PowerNeededToSetOrUpdateAHome" => 250,
                "PowerGainedPerPlayerInFaction" => 50,
                "PowerGainedPerKillingAnEnemy" => 10, 
                "PowerGainedPerAlly" => 100,
                "AllyLimitPerFaction" => 5,
                "TheDefaultPowerEveryFactionStartsWith" => 0,
                "EnableOverClaim" => true,
		));
		$this->db = new \SQLite3($this->getDataFolder() . "FactionsPro.db");
		$this->db->exec("CREATE TABLE IF NOT EXISTS master (player TEXT PRIMARY KEY COLLATE NOCASE, faction TEXT, rank TEXT);");
		$this->db->exec("CREATE TABLE IF NOT EXISTS confirm (player TEXT PRIMARY KEY COLLATE NOCASE, faction TEXT, invitedby TEXT, timestamp INT);");
		$this->db->exec("CREATE TABLE IF NOT EXISTS alliance (player TEXT PRIMARY KEY COLLATE NOCASE, faction TEXT, requestedby TEXT, timestamp INT);");
		$this->db->exec("CREATE TABLE IF NOT EXISTS motdrcv (player TEXT PRIMARY KEY, timestamp INT);");
		$this->db->exec("CREATE TABLE IF NOT EXISTS motd (faction TEXT PRIMARY KEY, message TEXT);");
		$this->db->exec("CREATE TABLE IF NOT EXISTS plots(faction TEXT PRIMARY KEY, x1 INT, z1 INT, x2 INT, z2 INT);");
		$this->db->exec("CREATE TABLE IF NOT EXISTS home(faction TEXT PRIMARY KEY, x INT, y INT, z INT, world TEXT);");
		$this->db->exec("CREATE TABLE IF NOT EXISTS strength(faction TEXT PRIMARY KEY, power INT);");
		$this->db->exec("CREATE TABLE IF NOT EXISTS allies(ID INT PRIMARY KEY,faction1 TEXT, faction2 TEXT);");
		$this->db->exec("CREATE TABLE IF NOT EXISTS alliescountlimit(faction TEXT PRIMARY KEY, count INT);");
		$this->db->exec("CREATE TABLE IF NOT EXISTS effects(faction TEXT PRIMARY KEY, effect TEXT);");
		$this->db->exec("CREATE TABLE IF NOT EXISTS inprocessdisbanding(faction TEXT PRIMARY KEY, status INT);");
		$this->db->exec("CREATE TABLE IF NOT EXISTS war(ID INT PRIMARY KEY,faction1 TEXT, faction2 TEXT);");
	}
    
		
	public function onCommand(CommandSender $sender, Command $command, $label, array $args) {
		$this->fCommand->onCommand($sender, $command, $label, $args);
	}
	public function isInFaction($player) {
		$result = $this->db->query("SELECT * FROM master WHERE player='$player';");
		$array = $result->fetchArray(SQLITE3_ASSOC);
		return empty($array) == false;
	}
    public function isDisbanding($faction){
        $result = $this->db->query("SELECT * FROM inprocessdisbanding;");
        $resultArr = $result->fetchArray(SQLITE3_ASSOC);
        return $resultArr['status'] == 1;
    }
    public function setOnDisband($faction){
        $stmt = $this->db->prepare("INSERT OR REPLACE INTO inprocessdisbanding (faction, status) VALUES (:faction, :status);");  
        $stmt->bindValue(":faction", $faction);
		$stmt->bindValue(":status", 1);
		$result = $stmt->execute();
    }
    public function addEffectTo($faction,$effect){
        $stmt = $this->db->prepare("INSERT OR REPLACE INTO effects (faction, effect) VALUES (:faction, :effect);");  
        $stmt->bindValue(":faction", $faction);
		$stmt->bindValue(":effect", $effect);
		$result = $stmt->execute();
    }
    public function getEffectOf($faction){
        $result = $this->db->query("SELECT * FROM effects WHERE faction = '$faction';");
        $resultArr = $result->fetchArray(SQLITE3_ASSOC);
        if(empty($resultArr)){
            return "none";
        }
        return $resultArr['effect'];
    }
    public function setFactionPower($faction,$power){
        if($power < 0){
            $power = 0;
        }
		$stmt = $this->db->prepare("INSERT OR REPLACE INTO strength (faction, power) VALUES (:faction, :power);");   
        $stmt->bindValue(":faction", $faction);
		$stmt->bindValue(":power", $power);
		$result = $stmt->execute();
    }
    public function setAllies($faction1, $faction2){
        $stmt = $this->db->prepare("INSERT INTO allies (faction1, faction2) VALUES (:faction1, :faction2);");  
        $stmt->bindValue(":faction1", $faction1);
		$stmt->bindValue(":faction2", $faction2);
		$result = $stmt->execute();
    }

    public function areAllies($faction1, $faction2){
        $result = $this->db->query("SELECT * FROM allies WHERE faction1 = '$faction1' AND faction2 = '$faction2';");
        $resultArr = $result->fetchArray(SQLITE3_ASSOC);
        if(empty($resultArr)==false){
            return true;
        } 
    }
    public function updateAllies($faction){
		$stmt = $this->db->prepare("INSERT OR REPLACE INTO alliescountlimit(faction, count) VALUES (:faction, :count);");   
        $stmt->bindValue(":faction", $faction);
        $result = $this->db->query("SELECT * FROM allies WHERE faction1='$faction';");
        $i = 0;
        while($resultArr = $result->fetchArray(SQLITE3_ASSOC)){
            $i = $i + 1;
        }
        $stmt->bindValue(":count", (int) $i);
		$result = $stmt->execute();
    }
    public function getAlliesCount($faction){
        
        $result = $this->db->query("SELECT * FROM alliescountlimit WHERE faction = '$faction';");
        $resultArr = $result->fetchArray(SQLITE3_ASSOC);
        return (int) $resultArr["count"];
    }
    public function getAlliesLimit(){
        return (int) $this->prefs->get("AllyLimitPerFaction");
    }
  
    public function deleteAllies($faction1, $faction2){
        $stmt = $this->db->prepare("DELETE FROM allies WHERE faction1 = '$faction1' AND faction2 = '$faction2';");   
		$result = $stmt->execute();
    }
    public function getFactionPower($faction){
        $result = $this->db->query("SELECT * FROM strength WHERE faction = '$faction';");
        $resultArr = $result->fetchArray(SQLITE3_ASSOC);
        return (int) $resultArr["power"];
    }
    public function addFactionPower($faction, $power){
        if($this->getFactionPower($faction) + $power < 0){
            $power = $this->getFactionPower($faction);
        }
		$stmt = $this->db->prepare("INSERT OR REPLACE INTO strength (faction, power) VALUES (:faction, :power);");   
        $stmt->bindValue(":faction", $faction);
		$stmt->bindValue(":power", $this->getFactionPower($faction) + $power);
		$result = $stmt->execute();
    }
    public function subtractFactionPower($faction,$power){
        if($this->getFactionPower($faction) - $power < 0){
            $power = $this->getFactionPower($faction);
        }
		$stmt = $this->db->prepare("INSERT OR REPLACE INTO strength (faction, power) VALUES (:faction, :power);");   
        $stmt->bindValue(":faction", $faction);
		$stmt->bindValue(":power", $this->getFactionPower($faction) - $power);
		$result = $stmt->execute();
    }
        
	public function isLeader($player) {
		$faction = $this->db->query("SELECT * FROM master WHERE player='$player';");
		$factionArray = $faction->fetchArray(SQLITE3_ASSOC);
		return $factionArray["rank"] == "Leader";
    }
   
	public function isOfficer($player) {
		$faction = $this->db->query("SELECT * FROM master WHERE player='$player';");
		$factionArray = $faction->fetchArray(SQLITE3_ASSOC);
		return $factionArray["rank"] == "Officer";
	}
	
	public function isMember($player) {
		$faction = $this->db->query("SELECT * FROM master WHERE player='$player';");
		$factionArray = $faction->fetchArray(SQLITE3_ASSOC);
		return $factionArray["rank"] == "Member";
	}
	public function getPlayersInFactionByRank($s,$faction,$rank){
         
        if($rank!="Leader"){
           $rankname = $rank.'s';
        } else {
           $rankname = $rank;
        }
        $team = "";
        $result = $this->db->query("SELECT * FROM master WHERE faction='$faction' AND rank='$rank';");
        $row = array();
        $i = 0;
        
        while($resultArr = $result->fetchArray(SQLITE3_ASSOC)){
            $row[$i]['player'] = $resultArr['player'];
            if($this->getServer()->getPlayerExact($row[$i]['player']) instanceof Player){
               $team .= TextFormat::ITALIC.TextFormat::AQUA.$row[$i]['player'].TextFormat::GREEN."[ON]".TextFormat::RESET.TextFormat::WHITE."||".TextFormat::RESET;
            } else {
               $team .= TextFormat::ITALIC.TextFormat::AQUA.$row[$i]['player'].TextFormat::RED."[OFF]".TextFormat::RESET.TextFormat::WHITE."||".TextFormat::RESET;
            }
            $i = $i + 1;
        }
        
        $s->sendMessage($this->formatMessage("~ *<$rankname> of |$faction|* ~",true));
        $s->sendMessage($team);
    }
    public function getAllAllies($s,$faction){
        
        $team = "";
        $result = $this->db->query("SELECT * FROM allies WHERE faction1='$faction';");
        $row = array();
        $i = 0;
        while($resultArr = $result->fetchArray(SQLITE3_ASSOC)){
            $row[$i]['faction2'] = $resultArr['faction2'];
            $team .= TextFormat::ITALIC.TextFormat::GOLD.$row[$i]['faction2'].TextFormat::RESET.TextFormat::WHITE."||".TextFormat::RESET;
            $i = $i + 1;
        }
        
        $s->sendMessage($this->formatMessage("~ Allies of *$faction* ~",true));
        $s->sendMessage($team);
    }
    public function sendListOfTop10FactionsTo($s){
        $result = $this->db->query("SELECT faction FROM strength ORDER BY power DESC LIMIT 10;");
        $row = array();
        $i = 0;
        $s->sendMessage($this->formatMessage("~ The first 10 most strengthful factions ~",true));
        while($resultArr = $result->fetchArray(SQLITE3_ASSOC)){
            $j = $i + 1;
            $cf = $resultArr['faction'];
            $pf = $this->getFactionPower($cf);
            $df = $this->getNumberOfPlayers($cf);
            $s->sendMessage(TextFormat::ITALIC.TextFormat::GOLD."$j -> ".TextFormat::GREEN."$cf".TextFormat::GOLD." with ".TextFormat::RED."$pf STR".TextFormat::GOLD." and ".TextFormat::LIGHT_PURPLE."$df PLAYERS".TextFormat::RESET);
            $i = $i + 1;
        } 
        
    }
    public function updateTagsAndEffectsOf($f){
        $result = $this->db->query("SELECT * from master WHERE faction='$f';");
        $i = 0;
        while($resultArr = $result->fetchArray(SQLITE3_ASSOC)){
            if($this->getServer()->getPlayerExact($resultArr['player']) instanceof Player){
                $this->getServer()->getPlayerExact($resultArr['player'])->removeAllEffects();
                $this->updateTag($resultArr['player']);
            }
            $i = $i + 1;
        } 
    }
    
	public function getPlayerFaction($player) {
		$faction = $this->db->query("SELECT * FROM master WHERE player='$player';");
		$factionArray = $faction->fetchArray(SQLITE3_ASSOC);
		return $factionArray["faction"];
	}
	
	public function getLeader($faction) {
		
