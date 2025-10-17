<?php
namespace ChatCommandFiltering; // 使用原始命名空间确保兼容性

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerCommandPreprocessEvent;
use pocketmine\utils\TextFormat as TF;
use pocketmine\utils\Config;
use pocketmine\Player;

class Main extends PluginBase implements Listener {

    private $bannedWords = [
        'cn' => [],
        'en' => []
    ];
    
    private $cnReplaceChar = "*";
    private $enReplaceChar = "#";
    private $notifyPlayer = true;
    private $logFiltered = true;

    public function onEnable() {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->initConfig();
        $this->getLogger()->info(TF::GREEN . "聊天指令过滤插件 v1.0.1 已启用");
    }
    
    private function initConfig() {
        @mkdir($this->getDataFolder(), 0777, true);
        $this->saveDefaultConfig();
        $this->reloadConfig();
        
        $config = $this->getConfig();
        
        // 加载替换字符
        $replaceChars = $config->get("replace_chars", []);
        $this->cnReplaceChar = $replaceChars["cn"] ?? "*";
        $this->enReplaceChar = $replaceChars["en"] ?? "#";
        
        // 加载敏感词
        $bannedWords = $config->get("banned_words", []);
        $this->bannedWords = [
            'cn' => $bannedWords["cn"] ?? [],
            'en' => $bannedWords["en"] ?? []
        ];
        
        // 加载设置
        $settings = $config->get("settings", []);
        $this->notifyPlayer = $settings["notify_player"] ?? true;
        $this->logFiltered = $settings["log_filtered"] ?? true;
        
        $this->getLogger()->debug("已加载 " . count($this->bannedWords['cn']) . " 个中文敏感词");
    }

    public function onPlayerCommandPreprocess(PlayerCommandPreprocessEvent $event) {
        $message = $event->getMessage();
        $player = $event->getPlayer();
        
        $message = trim($message);
        if ($message === "") return;
        
        $cmdParts = explode(" ", $message, 2);
        $cmd = strtolower($cmdParts[0]);
        
        if (!in_array($cmd, ["/me", "/say", "/tell"])) {
            return;
        }
        
        $originalMessage = $cmdParts[1] ?? "";
        $filteredMessage = $this->filterText($originalMessage);
        
        if ($filteredMessage !== $originalMessage) {
            $newMessage = $cmd . " " . $filteredMessage;
            $event->setMessage($newMessage);
            
            if ($this->notifyPlayer && $player instanceof Player) {
                $player->sendMessage(TF::RED . "警告: 消息包含违规内容，已自动过滤!");
            }
            
            if ($this->logFiltered) {
                $name = $player instanceof Player ? $player->getName() : "控制台";
                $this->getLogger()->info("过滤了 {$name} 的指令: {$message} → {$newMessage}");
            }
        }
    }
    
    private function filterText($text) {
        $filteredText = $text;
        
        // 处理中文词汇
        foreach ($this->bannedWords['cn'] as $word) {
            if (!empty($word)) {
                $filteredText = str_replace($word, str_repeat($this->cnReplaceChar, $this->mb_strlen($word)), $filteredText);
            }
        }
        
        // 处理英文词汇
        foreach ($this->bannedWords['en'] as $word) {
            if (!empty($word)) {
                $filteredText = str_ireplace($word, str_repeat($this->enReplaceChar, strlen($word)), $filteredText);
            }
        }
        
        return $filteredText;
    }
    
    private function mb_strlen($str) {
        return function_exists('mb_strlen') ? mb_strlen($str, 'UTF-8') : strlen($str);
    }
    
    public function onDisable() {
        $this->getLogger()->info(TF::RED . "聊天指令过滤插件已禁用");
    }
}