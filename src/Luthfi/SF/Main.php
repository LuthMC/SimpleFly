<?php

namespace Luthfi\SF;

use pocketmine\plugin\PluginBase;
use pocketmine\player\Player;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat;
use pocketmine\event\Listener;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\player\PlayerMoveEvent;

class Main extends PluginBase implements Listener {

    private $flyTimers = [];
    private $noFlyWorlds = [];
    private $combatDisabled = false;

    public function onEnable(): void {
        $this->saveDefaultConfig();
        $this->noFlyWorlds = $this->getConfig()->get("no_fly_worlds", []);
        $this->combatDisabled = $this->getConfig()->get("anti_fly_combat", true);
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
        if ($command->getName() === "fly") {
            if (!$sender instanceof Player) {
                $sender->sendMessage(TextFormat::RED . "This command can only be used in-game!");
                return true;
            }
            
            if (count($args) == 0) {
                $this->toggleFly($sender);
            } else if (count($args) >= 1 && $args[0] == "time") {
                $timeArg = array_shift($args);
                $timeDuration = array_shift($args);
                $targetPlayer = $sender;

                if (count($args) >= 1) {
                    $targetPlayer = $this->getServer()->getPlayerExact($args[0]);
                    if ($targetPlayer === null) {
                        $sender->sendMessage(TextFormat::RED . "Players not found!");
                        return true;
                    }
                }

                $this->setFlyTime($targetPlayer, $timeDuration);
            }
            return true;
        }
        return false;
    }

    private function toggleFly(Player $player): void {
        if (in_array($player->getWorld()->getFolderName(), $this->noFlyWorlds)) {
            $player->sendMessage(TextFormat::RED . $this->getConfig()->get("no_fly_message", "Flight is disabled in this world."));
            return;
        }

        if ($player->getAllowFlight()) {
            $player->setAllowFlight(false);
            $player->sendMessage(TextFormat::YELLOW . $this->getConfig()->get("fly_disabled_message", "Flight Disabled!"));
        } else {
            $player->setAllowFlight(true);
            $player->sendMessage(TextFormat::GREEN . $this->getConfig()->get("fly_enabled_message", "Flight Enabled!"));
        }
    }

    private function setFlyTime(Player $player, string $time): void {
        $timeInSeconds = $this->convertToSeconds($time);
        if ($timeInSeconds === 0) {
            $player->sendMessage(TextFormat::RED . "Invalid time format! Use something like '1h' or '30m'.");
            return;
        }

        $this->flyTimers[$player->getName()] = time() + $timeInSeconds;
        $player->setAllowFlight(true);
        $player->sendMessage(TextFormat::GREEN . "Flight Enabled For $time!");
    }

    private function convertToSeconds(string $time): int {
        if (preg_match('/(\d+)([hm])/', $time, $matches)) {
            $value = (int)$matches[1];
            $unit = $matches[2];
            if ($unit === 'h') {
                return $value * 3600;
            } elseif ($unit === 'm') {
                return $value * 60;
            }
        }
        return 0;
    }

    public function onPlayerDamage(EntityDamageEvent $event): void {
        $entity = $event->getEntity();
        if ($entity instanceof Player && $this->combatDisabled) {
            if ($entity->getAllowFlight()) {
                $entity->setAllowFlight(false);
                $entity->sendMessage(TextFormat::RED . "Flight disabled due to combat!");
            }
        }
    }

    public function onPlayerMove(PlayerMoveEvent $event): void {
        $player = $event->getPlayer();
        if (isset($this->flyTimers[$player->getName()])) {
            if (time() > $this->flyTimers[$player->getName()]) {
                $player->setAllowFlight(false);
                unset($this->flyTimers[$player->getName()]);
                $player->sendMessage(TextFormat::RED . "Your flight time has expired!");
            }
        }
    }
}
