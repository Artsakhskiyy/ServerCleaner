<?php

declare(strict_types=1);

namespace Artsakhskiyy\ServerCleaner;

use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use pocketmine\world\World;
use pocketmine\entity\Entity;
use pocketmine\item\Item;
use pocketmine\player\Player;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;

class Main extends PluginBase {

    private array $countdownConfig;
    private array $completedConfig;
    private int $interval;

    private bool $clearMobs = true;
    private bool $clearItems = true;
    private array $mobExceptions = [];
    private array $itemExceptions = [];

    private bool $countdownRunning = false;

    public function onEnable(): void {
        $this->saveDefaultConfig();

        $config = $this->getConfig();
        $this->interval = (int)$config->getNested("settings.interval", 300);
        $this->countdownConfig = $config->getNested("settings.countdown", []);
        $this->completedConfig = $config->getNested("settings.completed", []);
        $this->clearMobs = (bool)$config->getNested("clear-options.clear-mobs", true);
        $this->clearItems = (bool)$config->getNested("clear-options.clear-items", true);
        $this->mobExceptions = $config->getNested("clear-options.mob-exceptions", []);
        $this->itemExceptions = $config->getNested("clear-options.item-exceptions", []);

        $this->getLogger()->info("ServerCleaner включен!");

        $this->startCountdown();
    }

    private function startCountdown(): void {
        if ($this->countdownRunning) return;
        $this->countdownRunning = true;

        $times = array_map('intval', array_keys($this->countdownConfig));
        rsort($times);
        $totalTime = $times[0] ?? 60;

        $currentTick = 0;
        $taskHandler = null;

        $task = new ClosureTask(function() use (&$currentTick, $totalTime, $times, &$taskHandler): void {
            $remaining = $totalTime - $currentTick;

            if (in_array($remaining, $times)) {
                $config = $this->countdownConfig[(string)$remaining] ?? [];
                $message = $config['message'] ?? "";
                $type = $config['type'] ?? "tip";
                $this->broadcast($message, $type);
            }

            if ($remaining <= 0) {
                if ($taskHandler !== null) {
                    $taskHandler->cancel();
                }

                $this->clearWorlds();
                $this->broadcast($this->completedConfig['message'] ?? "", $this->completedConfig['type'] ?? "tip");

                $this->countdownRunning = false;

                $this->getScheduler()->scheduleDelayedTask(new ClosureTask(function(): void {
                    $this->startCountdown();
                }), $this->interval * 20);

                return;
            }

            $currentTick++;
        });

        $taskHandler = $this->getScheduler()->scheduleRepeatingTask($task, 20);
    }

    private function broadcast(string $message, string $type = "tip"): void {
        if ($message === "") return;

        foreach ($this->getServer()->getOnlinePlayers() as $player) {
            switch ($type) {
                case "tip":
                    $player->sendActionBarMessage($message);
                    break;
                case "message":
                    $player->sendMessage($message);
                    break;
                case "toast":
                    $player->sendToastNotification($message, "");
                    break;
            }
        }
    }

    private function clearWorlds(): void {
        foreach ($this->getServer()->getWorldManager()->getWorlds() as $world) {
            if ($this->clearMobs) $this->clearEntities($world);
            if ($this->clearItems) $this->clearItems($world);
        }
    }

    private function clearEntities(World $world): void {
        foreach ($world->getEntities() as $entity) {
            if (!$entity instanceof Player) {
                if (!in_array($entity::getNetworkTypeId(), $this->mobExceptions, true)) {
                    $entity->flagForDespawn();
                }
            }
        }
    }

    private function clearItems(World $world): void {
        foreach ($world->getEntities() as $entity) {
            if ($entity instanceof Item) {
                if (!in_array($entity->getId(), $this->itemExceptions, true)) {
                    $entity->flagForDespawn();
                }
            }
        }
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
        if (strtolower($command->getName()) === "serverclear") {
            if (!$sender->hasPermission("servercleaner.use")) {
                $sender->sendMessage($this->getConfig()->getNested("messages.no_permission") ?? "");
                return false;
            }

            $this->clearWorlds();
            $this->broadcast($this->completedConfig['message'] ?? "", $this->completedConfig['type'] ?? "tip");
            $sender->sendMessage($this->getConfig()->getNested("messages.manual_start") ?? "");

            return true;
        }
        return false;
    }
}
