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
use pocketmine\scheduler\PluginTask;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\utils\Config;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerRespawnEvent;
class FactionListener implements Listener {
	
	public $plugin;
	
	public function __construct(FactionMain $pg) {
		$this->plugin = $pg;
	}
	
	public function factionChat(PlayerChatEvent $PCE) {
		
		$player = $PCE->getPlayer()->getName();
		if($this->plugin->motdWaiting($player)) {
			if(time() - $this->plugin->getMOTDTime($player) > 30) {
				$PCE->getPlayer()->sendMessage($this->plugin->formatMessage("Timed out. Please use /f desc again."));
				$this->plugin->db->query("DELETE FROM motdrcv WHERE player='$player';");
				$PCE->setCancelled(true);
				return true;
			} else {
				$motd = $PCE->getMessage();
				$faction = $this->plugin->getPlayerFaction($player);
				$this->plugin->setMOTD($faction, $player, $motd);
				$PCE->setCancelled(true);
				$PCE->getPlayer()->sendMessage($this->plugin->formatMessage("Successfully updated the faction description! Type /f info.", true));
			}
			return true;
		}

	}
	
	public function factionPVP(EntityDamageEvent $factionDamage) {
		if($factionDamage instanceof EntityDamageByEntityEvent) {
			if(!($factionDamage->getEntity() instanceof Player) or !($factionDamage->getDamager() instanceof Player)) {
				return true;
			}
			if(($this->plugin->isInFaction($factionDamage->getEntity()->getPlayer()->getName()) == false) or ($this->plugin->isInFaction($factionDamage->getDamager()->getPlayer()->getName()) == false)) {
				return true;
			}
			if(($factionDamage->getEntity() instanceof Player) and ($factionDamage->getDamager() instanceof Player)) {
				$player1 = $factionDamage->getEntity()->getPlayer()->getName();
				$player2 = $factionDamage->getDamager()->getPlayer()->getName();
                $f1 = $this->plugin->getPlayerFaction($player1);
                $f2 = $this->plugin->getPlayerFaction($player2);
				if($this->plugin->sameFaction($player1, $player2) == true or $this->plugin->areAllies($f1,$f2)) {
					$factionDamage->setCancelled(true);
				}
			}
		}
	}
	public function factionBlockBreakProtect(BlockBreakEvent $event) {
		if($this->plugin->isInPlot($event->getPlayer())) {
			if($this->plugin->inOwnPlot($event->getPlayer())) {
				return true;
			} else {
				$event->setCancelled(true);
				$event->getPlayer()->sendMessage($this->plugin->formatMessage("You cannot break blocks here. This is already a property of a faction. Type /f plotinfo for details."));
				return true;
			}
		}
	}
	
	public function factionBlockPlaceProtect(BlockPlaceEvent $event) {
		if($this->plugin->isInPlot($event->getPlayer())) {
			if($this->plugin->inOwnPlot($event->getPlayer())) {
				return true;
			} else {
				$event->setCancelled(true);
				$event->getPlayer()->sendMessage($this->plugin->formatMessage("You cannot place blocks here. This is already a property of a faction. Type /f plotinfo for details."));
				return true;
			}
		}
	}
	public function onKill(PlayerDeathEvent $event){
        $ent = $event->getEntity();
        $cause = $event->getEntity()->getLastDamageCause();
        if($cause instanceof EntityDamageByEntityEvent){
            $killer = $cause->getDamager();
            if($killer instanceof Player and $ent instanceof Player){
                $p = $killer->getPlayer()->getName();
                if($this->plugin->isInFaction($p)){
                    $f = $this->plugin->getPlayerFaction($p);
                    $e = $this->plugin->prefs->get("PowerGainedPerKillingAnEnemy");
                    if($ent instanceof Player){
                        if($this->plugin->isInFaction($ent->getPlayer()->getName())){
                            $this->plugin->addFactionPower($f,$e);
                            $killer->sendMessage(TextFormat::ITALIC.TextFormat::GREEN."+$e STR");
                        } else {
                            $s = $e/2;
                            $this->plugin->addFactionPower($f,$e/2);
                            $killer->sendMessage(TextFormat::ITALIC.TextFormat::GREEN."+$s STR");
                        }
                    }
                }
                $e = $ent->getPlayer()->getName();
                if($this->plugin->isInFaction($e)){
                    $f = $this->plugin->getPlayerFaction($e);
                    $e = $this->plugin->prefs->get("PowerGainedPerKillingAnEnemy");
                    if($ent->getLastDamageCause()->getDamager() instanceof Player){
                        if($this->plugin->isInFaction($ent->getLastDamageCause()->getDamager()->getPlayer()->getName())){    
                            $s = $e*2;  
                            $this->plugin->subtractFactionPower($f,$e*2);
                            $ent->sendMessage(TextFormat::ITALIC.TextFormat::RED."-$s STR");
                        } else {
                            $this->plugin->subtractFactionPower($f,$e);
                            $ent->sendMessage(TextFormat::ITALIC.TextFormat::RED."-$e STR");
                        }
                    }
                }
            }
        }
    }
    
	public function onPlayerJoin(PlayerJoinEvent $event) {
        $ent = $event->getPlayer();
        $this->plugin->updateTag($ent->getName());
    }
}
