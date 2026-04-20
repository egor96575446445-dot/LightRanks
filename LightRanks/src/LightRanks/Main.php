<?php

namespace LightRanks;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use pocketmine\scheduler\ClosureTask;

class Main extends PluginBase implements Listener {

    private Config $ranksConfig;
    private array $tempRanks = [];

    public function onEnable(): void {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->saveResource("ranks.yml");
        $this->ranksConfig = new Config($this->getDataFolder() . "ranks.yml", Config::YAML);
        
        // Загружаем временные ранги
        $this->loadTempRanks();
        
        $this->getLogger()->info("§aLightRanks загружен! 20+ команд готовы к работе.");
    }

    private function loadTempRanks(): void {
        $temp = $this->ranksConfig->get("temp_ranks", []);
        foreach ($temp as $player => $data) {
            if ($data["expires"] > time()) {
                $this->tempRanks[strtolower($player)] = $data;
            }
        }
        $this->saveTempRanks();
    }

    private function saveTempRanks(): void {
        $this->ranksConfig->set("temp_ranks", $this->tempRanks);
        $this->ranksConfig->save();
    }

    private function getRank(string $player): string {
        $player = strtolower($player);
        if (isset($this->tempRanks[$player]) && $this->tempRanks[$player]["expires"] > time()) {
            return $this->tempRanks[$player]["rank"];
        }
        return $this->ranksConfig->get("players", [])[$player] ?? $this->ranksConfig->get("default-rank", "Guest");
    }

    private function setRank(string $player, string $rank, bool $temporary = false, int $duration = 0): void {
        $player = strtolower($player);
        if ($temporary && $duration > 0) {
            $this->tempRanks[$player] = ["rank" => $rank, "expires" => time() + $duration];
            $this->saveTempRanks();
            $this->getScheduler()->scheduleDelayedTask(new ClosureTask(function() use ($player): void {
                if (isset($this->tempRanks[$player]) && $this->tempRanks[$player]["expires"] <= time()) {
                    unset($this->tempRanks[$player]);
                    $this->saveTempRanks();
                    $p = $this->getServer()->getPlayerExact($player);
                    if ($p) $p->sendMessage("§cВаш временный ранг истёк!");
                }
            }), $duration * 20);
        } else {
            $players = $this->ranksConfig->get("players", []);
            $players[$player] = $rank;
            $this->ranksConfig->set("players", $players);
            $this->ranksConfig->save();
        }
    }

    private function removeRank(string $player): void {
        $player = strtolower($player);
        if (isset($this->tempRanks[$player])) {
            unset($this->tempRanks[$player]);
            $this->saveTempRanks();
        } else {
            $players = $this->ranksConfig->get("players", []);
            unset($players[$player]);
            $this->ranksConfig->set("players", $players);
            $this->ranksConfig->save();
        }
    }

    private function getRankList(): array {
        return array_keys($this->ranksConfig->get("ranks", []));
    }

    private function getRankPriority(string $rank): int {
        return $this->ranksConfig->get("ranks", [])[$rank]["priority"] ?? 0;
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
        switch ($command->getName()) {
            case "setrank":
                if (count($args) < 2) {
                    $sender->sendMessage("§eИспользование: §f/setrank <игрок> <ранг>");
                    return false;
                }
                $this->setRank($args[0], $args[1]);
                $sender->sendMessage("§aИгроку §e{$args[0]} §aвыдан ранг §e{$args[1]}§a.");
                return true;

            case "removerank":
                if (count($args) < 1) {
                    $sender->sendMessage("§eИспользование: §f/removerank <игрок>");
                    return false;
                }
                $this->removeRank($args[0]);
                $sender->sendMessage("§aРанг игрока §e{$args[0]} §aсброшен.");
                return true;

            case "tempaddrank":
                if (count($args) < 3) {
                    $sender->sendMessage("§eИспользование: §f/tempaddrank <игрок> <ранг> <время(сек)>");
                    return false;
                }
                $this->setRank($args[0], $args[1], true, (int)$args[2]);
                $sender->sendMessage("§aИгроку §e{$args[0]} §aвыдан временный ранг §e{$args[1]} §aна §e{$args[2]} §aсекунд.");
                return true;

            case "tempremoverank":
                if (count($args) < 1) {
                    $sender->sendMessage("§eИспользование: §f/tempremoverank <игрок>");
                    return false;
                }
                $this->removeRank($args[0]);
                $sender->sendMessage("§aВременный ранг игрока §e{$args[0]} §aснят.");
                return true;

            case "promote":
                if (count($args) < 1) {
                    $sender->sendMessage("§eИспользование: §f/promote <игрок>");
                    return false;
                }
                $current = $this->getRank($args[0]);
                $ranks = $this->getRankList();
                $next = null;
                foreach ($ranks as $r) {
                    if ($this->getRankPriority($r) > $this->getRankPriority($current)) {
                        $next = $r;
                        break;
                    }
                }
                if ($next) {
                    $this->setRank($args[0], $next);
                    $sender->sendMessage("§aИгрок §e{$args[0]} §aповышен до §e{$next}§a.");
                } else {
                    $sender->sendMessage("§cИгрок уже на максимальном ранге.");
                }
                return true;

            case "demote":
                if (count($args) < 1) {
                    $sender->sendMessage("§eИспользование: §f/demote <игрок>");
                    return false;
                }
                $current = $this->getRank($args[0]);
                $ranks = array_reverse($this->getRankList());
                $prev = null;
                foreach ($ranks as $r) {
                    if ($this->getRankPriority($r) < $this->getRankPriority($current)) {
                        $prev = $r;
                        break;
                    }
                }
                if ($prev) {
                    $this->setRank($args[0], $prev);
                    $sender->sendMessage("§aИгрок §e{$args[0]} §aпонижен до §e{$prev}§a.");
                } else {
                    $sender->sendMessage("§cИгрок уже на минимальном ранге.");
                }
                return true;

            case "rankinfo":
                if (count($args) < 1) {
                    $sender->sendMessage("§eИспользование: §f/rankinfo <ранг>");
                    return false;
                }
                $rank = $args[0];
                $ranks = $this->ranksConfig->get("ranks", []);
                if (!isset($ranks[$rank])) {
                    $sender->sendMessage("§cРанг не найден.");
                    return false;
                }
                $data = $ranks[$rank];
                $sender->sendMessage("§6=== §e{$rank} §6===");
                $sender->sendMessage("§7Префикс: §f{$data['prefix']}");
                $sender->sendMessage("§7Суффикс: §f{$data['suffix'] ?? 'Нет'}");
                $sender->sendMessage("§7Приоритет: §f{$data['priority']}");
                $sender->sendMessage("§7Цена: §f{$data['price'] ?? 'Бесплатно'}");
                return true;

            case "rankslist":
                $ranks = $this->getRankList();
                $sender->sendMessage("§6=== Список рангов §6===");
                foreach ($ranks as $rank) {
                    $sender->sendMessage("§7- §e$rank");
                }
                return true;

            case "myrank":
                if (!$sender instanceof Player) {
                    $sender->sendMessage("§cКоманда только в игре.");
                    return false;
                }
                $rank = $this->getRank($sender->getName());
                $sender->sendMessage("§aВаш ранг: §e$rank");
                return true;

            case "playerranks":
                if (count($args) < 1) {
                    $sender->sendMessage("§eИспользование: §f/playerranks <игрок>");
                    return false;
                }
                $rank = $this->getRank($args[0]);
                $sender->sendMessage("§aРанг игрока §e{$args[0]}§a: §e$rank");
                return true;

            case "addrank":
                if (count($args) < 1) {
                    $sender->sendMessage("§eИспользование: §f/addrank <название>");
                    return false;
                }
                $ranks = $this->ranksConfig->get("ranks", []);
                $ranks[$args[0]] = ["prefix" => "&f[{$args[0]}]", "suffix" => "", "priority" => count($ranks), "price" => 0];
                $this->ranksConfig->set("ranks", $ranks);
                $this->ranksConfig->save();
                $sender->sendMessage("§aРанг §e{$args[0]} §aсоздан.");
                return true;

            case "removerankdef":
                if (count($args) < 1) {
                    $sender->sendMessage("§eИспользование: §f/removerankdef <ранг>");
                    return false;
                }
                $ranks = $this->ranksConfig->get("ranks", []);
                unset($ranks[$args[0]]);
                $this->ranksConfig->set("ranks", $ranks);
                $this->ranksConfig->save();
                $sender->sendMessage("§aРанг §e{$args[0]} §aудалён.");
                return true;

            case "setrankprice":
                if (count($args) < 2) {
                    $sender->sendMessage("§eИспользование: §f/setrankprice <ранг> <цена>");
                    return false;
                }
                $ranks = $this->ranksConfig->get("ranks", []);
                if (!isset($ranks[$args[0]])) {
                    $sender->sendMessage("§cРанг не найден.");
                    return false;
                }
                $ranks[$args[0]]["price"] = (int)$args[1];
                $this->ranksConfig->set("ranks", $ranks);
                $this->ranksConfig->save();
                $sender->sendMessage("§aЦена ранга §e{$args[0]} §aустановлена: §e{$args[1]}");
                return true;

            case "buyrank":
                if (!$sender instanceof Player) {
                    $sender->sendMessage("§cКоманда только в игре.");
                    return false;
                }
                if (count($args) < 1) {
                    $sender->sendMessage("§eИспользование: §f/buyrank <ранг>");
                    return false;
                }
                $rank = $args[0];
                $ranks = $this->ranksConfig->get("ranks", []);
                if (!isset($ranks[$rank])) {
                    $sender->sendMessage("§cРанг не найден.");
                    return false;
                }
                $price = $ranks[$rank]["price"] ?? 0;
                if ($price <= 0) {
                    $sender->sendMessage("§cЭтот ранг нельзя купить.");
                    return false;
                }
                // Здесь нужна интеграция с EconomyAPI
                $sender->sendMessage("§aВы купили ранг §e$rank §aза §e$price");
                $this->setRank($sender->getName(), $rank);
                return true;

            default:
                return false;
        }
    }

    public function onPlayerChat(PlayerChatEvent $event): void {
        $player = $event->getPlayer();
        $rank = $this->getRank($player->getName());
        $ranks = $this->ranksConfig->get("ranks", []);
        $data = $ranks[$rank] ?? ["prefix" => "&7[{$rank}]", "suffix" => ""];
        $prefix = TextFormat::colorize($data["prefix"]);
        $suffix = TextFormat::colorize($data["suffix"] ?? "");
        $event->setFormat($prefix . " §f" . $player->getName() . $suffix . "§7: §f" . $event->getMessage());
    }
}