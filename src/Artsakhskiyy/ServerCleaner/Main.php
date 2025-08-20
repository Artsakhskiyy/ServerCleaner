<?php

declare(strict_types=1);

namespace Artsakhskiyy\ServerCleaner;

use pocketmine\plugin\PluginBase;
use pocketmine\entity\Entity;
use pocketmine\scheduler\Task;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\Config;
use pocketmine\item\Item;

class Main extends PluginBase {

    /** @var Config */
    private $config;
    /** @var array */
    private $mobExceptions = [];
    /** @var array */
    private $itemExceptions = [];
    /** @var int */
    private $time;

    public function onEnable() : void {
        $this->saveDefaultConfig();
        $this->config = $this->getConfig();
        $this->mobExceptions = $this->config->get("mob-exceptions", []);
        $this->itemExceptions = $this->config->get("item-exceptions", []);
        $this->time = (int) $this->config->get("clear-time", 300);

        $this->getScheduler()->scheduleRepeatingTask(new class($this) extends Task {
            private Main $plugin;
            private int $time;

            public function __construct(Main $plugin){
                $this->plugin = $plugin;
                $this->time = $plugin->time;
            }

            public function onRun() : void {
                $this->time--;
                if($this->time === 60 || $this->time === 30 || $this->time === 10 || $this->time <= 5 && $this->time > 0){
                    Server::getInstance()->broadcastMessage("§e[Cleaner] Очистка через §c{$this->time} §eсекунд(ы)!");
                }
                if($this->time <= 0){
                    $this->plugin->clearEntities();
                    $this->time = $this->plugin->time;
                }
            }
        }, 20);
    }

    public function clearEntities() : void {
        $removed = 0;

        foreach(Server::getInstance()->getWorldManager()->getWorlds() as $world){
            foreach($world->getEntities() as $entity){
                if($entity instanceof Player){
                    continue;
                }
                if(!in_array((string)$entity::getNetworkTypeId(), $this->mobExceptions, true)){
                    $entity->flagForDespawn();
                    $removed++;
                }
            }

            foreach($world->getDrops() as $item){
                if(!in_array((string)$item->getId(), $this->itemExceptions, true)){
                    $item->flagForDespawn();
                    $removed++;
                }
            }
        }

        Server::getInstance()->broadcastMessage("§e[Cleaner] §aОчищено объектов: §c{$removed}");
    }

    // пример: замена старого addXp()
    public function addExperience(Player $player, int $amount) : void {
        $player->getXpManager()->addXp($amount);
    }
}
