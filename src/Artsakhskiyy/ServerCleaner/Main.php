<?php

declare(strict_types=1);

namespace Artsakhskiyy\ServerCleaner;

use pocketmine\plugin\PluginBase;
use pocketmine\entity\object\ItemEntity;
use pocketmine\scheduler\Task;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\Config;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;

class Main extends PluginBase {

    private Config $config;
    private array $mobExceptions = [];
    private array $itemExceptions = [];
    private int $time;
    private array $countdownMessages = [];
    private array $clearOptions = [];
    private string $toastTitle;

    public function onEnable() : void {
        $this->saveDefaultConfig();
        $this->config = $this->getConfig();

        $this->time = (int)$this->config->getNested("settings.interval", 300);
        $this->countdownMessages = $this->config->getNested("settings.countdown", []);
        $this->clearOptions = $this->config->get("clear-options", []);
        $this->mobExceptions = $this->clearOptions["mob-exceptions"] ?? [];
        $this->itemExceptions = $this->clearOptions["item-exceptions"] ?? [];
        $this->toastTitle = $this->config->getNested("settings.toast-title", "ServerCleaner");

        $this->getScheduler()->scheduleRepeatingTask(new class($this) extends Task {
            private Main $plugin;
            private int $time;

            public function __construct(Main $plugin){
                $this->plugin = $plugin;
                $this->time = $plugin->getTime();
            }

            public function onRun() : void {
                $this->time--;

                if(isset($this->plugin->getCountdownMessages()[(string)$this->time])){
                    $data = $this->plugin->getCountdownMessages()[(string)$this->time];
                    $this->plugin->sendMessageToAll($data["message"], $data["type"]);
                }

                if($this->time <= 0){
                    $this->plugin->clearEntities();
                    $this->time = $this->plugin->getTime();
                }
            }
        }, 20);
    }

    public function getTime() : int {
        return $this->time;
    }

    public function getCountdownMessages() : array {
        return $this->countdownMessages;
    }

    public function clearEntities() : void {
        foreach(Server::getInstance()->getWorldManager()->getWorlds() as $world){
            if(($this->clearOptions["clear-mobs"] ?? true) === true){
                foreach($world->getEntities() as $entity){
                    if($entity instanceof Player) continue;
                    if(!in_array((string)$entity::getNetworkTypeId(), $this->mobExceptions, true)){
                        $entity->flagForDespawn();
                    }
                }
            }

            if(($this->clearOptions["clear-items"] ?? true) === true){
                foreach($world->getEntities() as $entity){
                    if($entity instanceof ItemEntity){
                        $item = $entity->getItem();
                        $identifier = $item->getVanillaName();
                        if(!in_array($identifier, $this->itemExceptions, true)){
                            $entity->flagForDespawn();
                        }
                    }
                }
            }
        }

        $completedMessage = $this->config->getNested("settings.completed.message", null);
        $completedType = $this->config->getNested("settings.completed.type", "message");
        if($completedMessage !== null){
            $this->sendMessageToAll($completedMessage, $completedType);
        }
    }

    public function sendMessageToAll(string $message, string $type = "message") : void {
        foreach(Server::getInstance()->getOnlinePlayers() as $player){
            switch(strtolower($type)){
                case "tip":
                    $player->sendTip($message);
                    break;
                case "toast":
                    $player->sendToastNotification($this->toastTitle, $message);
                    break;
                default:
                    $player->sendMessage($message);
                    break;
            }
        }
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool {
        if(strtolower($command->getName()) === "serverclear"){
            if(!$sender->hasPermission("servercleaner.use")){
                $sender->sendMessage($this->config->getNested("messages.no_permission"));
                return true;
            }
            $this->clearEntities();
            return true;
        }
        return false;
    }

    public function addExperience(Player $player, int $amount) : void {
        $player->getXpManager()->addXp($amount);
    }
}
