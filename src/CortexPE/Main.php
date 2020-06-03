<?php


declare(strict_types = 1);

namespace CortexPE;

use CortexPE\block\BlockManager;
use CortexPE\handlers\{
	EnchantHandler, PacketHandler
};
use CortexPE\inventory\BrewingManager;
use CortexPE\network\PacketManager;
use CortexPE\task\TickLevelsTask;
use CortexPE\tile\Tile;
use pocketmine\command\CommandSender;
use pocketmine\level\Level;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\plugin\PluginBase;
use pocketmine\plugin\PluginLogger;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;

class Main extends PluginBase {

	

	/** @var string */
	public const
		BASE_POCKETMINE_VERSION = "3.0.0",
		TESTED_MIN_POCKETMINE_VERSION = "3.0.0",
		TESTED_MAX_POCKETMINE_VERSION = "4.0.0";

	///////////////////////////////// START OF INSTANCE VARIABLES /////////////////////////////////
	/** @var Config */
	public static $config;
	/** @var Config */
	public static $cacheFile;
	/** @var int[] */
	public static $onPortal = [];
	/** @var string */
	public static $netherName = "nether";
	/** @var Level */
	public static $netherLevel;
	/** @var string */
	public static $endName = "ender";
	/** @var Level */
	public static $endLevel;
	/** @var bool */
	public static $lightningFire = false;
	/** @var Session[] */
	private $sessions = [];
	/** @var bool */
	private $disable = false;
	/** @var BrewingManager */
	private $brewingManager = null;
	/** @var Weather[] */
	public static $weatherData = [];
	/** @var Main */
	private static $instance;
	/** @var string */
	private static $sixCharCommitHash = "";
	////////////////////////////////// END OF INSTANCE VARIABLES //////////////////////////////////



	public static function getInstance(): Main{
		return self::$instance;
	}

	public static function sendVersion(CommandSender $sender, bool $separator = true){
		$logo = TextFormat::DARK_GREEN . "\x54\x65\x61" . TextFormat::GREEN . "\x53\x70\x6f\x6f\x6e";
		if(Splash::isValentines()){
			$logo = TextFormat::RED . "\x44\x65\x73\x73\x65\x72\x74" . TextFormat::DARK_RED . "\x53\x70\x6f\x6f\x6e";
		}elseif(Splash::isChristmastide()){
			$logo = TextFormat::RED . "\x54\x65\x61" . TextFormat::GREEN . "\x53\x70\x6f\x6f\x6e";
		}
		$sender->sendMessage("\x54\x68\x69\x73\x20\x73\x65\x72\x76\x65\x72\x20\x69\x73\x20\x72\x75\x6e\x6e\x69\x6e\x67\x20" . $logo . TextFormat::WHITE . "\x20\x76" . self::$instance->getDescription()->getVersion() . (Utils::isPhared() ? "" : "-dev") . "\x20\x66\x6f\x72\x20\x50\x6f\x63\x6b\x65\x74\x4d\x69\x6e\x65\x2d\x4d\x50\x20" . Server::getInstance()->getApiVersion());

		if(self::$sixCharCommitHash != ""){
			$sender->sendMessage("\x43\x6f\x6d\x6d\x69\x74\x3a\x20" . self::$sixCharCommitHash);
		}
		$sender->sendMessage("\x52\x65\x70\x6f\x73\x69\x74\x6f\x72\x79\x3a\x20\x68\x74\x74\x70\x73\x3a\x2f\x2f\x67\x69\x74\x68\x75\x62\x2e\x63\x6f\x6d\x2f\x43\x6f\x72\x74\x65\x78\x50\x45\x2f\x54\x65\x61\x53\x70\x6f\x6f\x6e");
		$sender->sendMessage("\x57\x65\x62\x73\x69\x74\x65\x3a\x20\x68\x74\x74\x70\x73\x3a\x2f\x2f\x43\x6f\x72\x74\x65\x78\x50\x45\x2e\x78\x79\x7a");
		if($separator){
			$sender->sendMessage("\x2d\x2d\x2d\x20\x2b\x20\x2d\x2d\x2d\x2d\x2d\x2d\x2d\x2d\x2d\x2d\x2d\x2d\x2d\x2d\x2d\x20\x2b\x20\x2d\x2d\x2d");
		}
	}

	public static function getPluginLogger(): PluginLogger{ // 2 lazy (#blameLarry)
		return self::$instance->getLogger();
	}

	public function onLoad(){
		$this->getLogger()->info("Loading Resources...");

		// Load Resources //
		if(!file_exists($this->getDataFolder())){
			@mkdir($this->getDataFolder());
		}
		$this->saveDefaultConfig();
		self::$config = new Config($this->getDataFolder() . "config.yml", Config::YAML);
		self::$cacheFile = new Config($this->getDataFolder() . "cache.json", Config::JSON);

		

		// Pre-Enable Checks //
		if(Utils::isPhared()){ // unphared = dev
			$meta = (new \Phar(\Phar::running(false)))->getMetadata(); // https://github.com/poggit/poggit/blob/beta/src/poggit/ci/builder/ProjectBuilder.php#L227-L236
			if(!isset($meta["builderName"]) || !is_array($meta)){
				$this->getLogger()->error("Only use TeaSpoon Builds from Poggit: https://poggit.pmmp.io/ci/CortexPE/TeaSpoon/~");
				$this->disable = true;

				return;
			}
			self::$sixCharCommitHash = substr($meta["fromCommit"], 0, 6);
		}else{
			$this->getLogger()->warning("You're using a developer's build of TeaSpoon. For better performance and stability, please get a pre-packaged version here: https://poggit.pmmp.io/ci/CortexPE/TeaSpoon/~");
		}

		self::$instance = $this;
	}

	public function onEnable(){
		// Yes compatibility checks (the ones with setEnabled(false)) are repeated because they should still look good in CLI...
		if($this->disable){
			$this->setEnabled(false);

			return;
		}

		$yr = 2017 . ((2017 != date('Y')) ? '-' . date('Y') : '');
		$stms = TextFormat::DARK_GREEN . "\n\nMMP\"\"MM\"\"YMM              " . TextFormat::GREEN . " .M\"\"\"bgd                                        " . TextFormat::DARK_GREEN . "\nP'   MM   `7             " . TextFormat::GREEN . " ,MI    \"Y                                        " . TextFormat::DARK_GREEN . "\n     MM  .gP\"Ya   ,6\"Yb.  " . TextFormat::GREEN . "`MMb.   `7MMpdMAo.  ,pW\"Wq.   ,pW\"Wq.`7MMpMMMb.  " . TextFormat::DARK_GREEN . "\n     MM ,M'   Yb 8)   MM" . TextFormat::GREEN . "    `YMMNq. MM   `Wb 6W'   `Wb 6W'   `Wb MM    MM  " . TextFormat::DARK_GREEN . "\n     MM 8M\"\"\"\"\"\"  ,pm9MM " . TextFormat::GREEN . " .     `MM MM    M8 8M     M8 8M     M8 MM    MM  " . TextFormat::DARK_GREEN . "\n     MM YM.    , 8M   MM  " . TextFormat::GREEN . "Mb     dM MM   ,AP YA.   ,A9 YA.   ,A9 MM    MM  " . TextFormat::DARK_GREEN . "\n   .JMML.`Mbmmd' `Moo9^Yo." . TextFormat::GREEN . "P\"Ybmmd\"  MMbmmd'   `Ybmd9'   `Ybmd9'.JMML  JMML." . TextFormat::GREEN . "\n                                    MM                                     \n                                  .JMML.  " . TextFormat::YELLOW . Splash::getRandomSplash() . TextFormat::RESET . "\nCopyright (C) CortexPE " . $yr . "\n";
		switch(true){ // todo: add more events?
			case (Splash::isValentines()):
				$stms = TextFormat::RED . "\n\n   .-.                                        " . TextFormat::DARK_RED . "       .-.                    " . TextFormat::RESET . "\n" . TextFormat::RED . "  (_) )-.                                  /  " . TextFormat::DARK_RED . " .--.-'                       " . TextFormat::RESET . "\n" . TextFormat::RED . "     /   \    .-.  .    .    .-.  ).--.---/---" . TextFormat::DARK_RED . "(  (_).-.  .-._..-._..  .-.   " . TextFormat::RESET . "\n" . TextFormat::RED . "    /     \ ./.-'_/ \  / \ ./.-'_/       /    " . TextFormat::DARK_RED . " `-.  /  )(   )(   )  )/   )  " . TextFormat::RESET . "\n" . TextFormat::RED . " .-/.      )(__.'/ ._)/ ._)(__.'/       /    " . TextFormat::DARK_RED . "_    )/`-'  `-'  `-'  '/   (   " . TextFormat::RESET . "\n" . TextFormat::RED . "(_/  `----'     /    /                      " . TextFormat::DARK_RED . "(_.--'/                      `- " . TextFormat::RESET . "\n                                              " . TextFormat::YELLOW . Splash::getRandomSplash() . TextFormat::RESET . "\nCopyright (C) CortexPE " . $yr . "\n";
				break;
			case (Splash::isChristmastide()):
				$stms = TextFormat::RED . "\n\nMMP\"\"MM\"\"YMM              " . TextFormat::GREEN . " .M\"\"\"bgd                                        " . TextFormat::RED . "\nP'   MM   `7             " . TextFormat::GREEN . " ,MI    \"Y                                        " . TextFormat::RED . "\n     MM  .gP\"Ya   ,6\"Yb.  " . TextFormat::GREEN . "`MMb.   `7MMpdMAo.  ,pW\"Wq.   ,pW\"Wq.`7MMpMMMb.  " . TextFormat::RED . "\n     MM ,M'   Yb 8)   MM" . TextFormat::GREEN . "    `YMMNq. MM   `Wb 6W'   `Wb 6W'   `Wb MM    MM  " . TextFormat::RED . "\n     MM 8M\"\"\"\"\"\"  ,pm9MM " . TextFormat::GREEN . " .     `MM MM    M8 8M     M8 8M     M8 MM    MM  " . TextFormat::RED . "\n     MM YM.    , 8M   MM  " . TextFormat::GREEN . "Mb     dM MM   ,AP YA.   ,A9 YA.   ,A9 MM    MM  " . TextFormat::RED . "\n   .JMML.`Mbmmd' `Moo9^Yo." . TextFormat::GREEN . "P\"Ybmmd\"  MMbmmd'   `Ybmd9'   `Ybmd9'.JMML  JMML." . TextFormat::GREEN . "\n                                    MM                                     \n                                  .JMML.  " . TextFormat::YELLOW . Splash::getRandomSplash() . TextFormat::RESET . "\nCopyright (C) CortexPE " . $yr . "\n";
				break;
		}
		$this->getLogger()->info("Loading..." . $stms);

		$this->loadEverythingElseThatMakesThisPluginFunctionalAndNotBrokLMAO();
		$this->getLogger()->info("TeaSpoon is distributed under the AGPL License");
		$this->checkConfigVersion();
	}

	private function loadEverythingElseThatMakesThisPluginFunctionalAndNotBrokLMAO(){
		// Initialize ze managars //
		
		BlockManager::init();
		$this->getServer()->getPluginManager()->registerEvents(new PacketHandler($this), $this);
	}

	private function checkConfigVersion(){
		if(Utils::isPhared()){
			$ver = self::$config->get("version");
			if($ver === null || $ver === false || $ver < self::CONFIG_VERSION){
				$this->getLogger()->critical("Your configuration file is Outdated! Keep a backup of it and delete the outdated file.");
			}elseif($ver > self::CONFIG_VERSION){
				$this->getLogger()->critical("Your configuration file is from a higher version of TeaSpoon! Please update the plugin from https://github.com/CortexPE/TeaSpoon");
			}
		}

		if(self::$cacheFile->get("date", "") != strval(date("d-m-y"))){
			self::$cacheFile->set("date", strval(date("d-m-y")));
		}
	}

	public function onDisable(){
		self::$cacheFile->save();
	}

	public function createSession(Player $player): bool{
		if(!isset($this->sessions[$player->getId()])){
			$this->sessions[$player->getId()] = new Session($player);
			$this->getLogger()->debug("Created " . $player->getName() . "'s Session");

			return true;
		}

		return false;
	}

	public function destroySession(Player $player): bool{
		if(isset($this->sessions[$player->getId()])){
			unset($this->sessions[$player->getId()]);
			$this->getLogger()->debug("Destroyed " . $player->getName() . "'s Session");

			return true;
		}

		return false;
	}

	public function getSessionById(int $id){
		if(isset($this->sessions[$id])){
			return $this->sessions[$id];
		}else{
			return null;
		}
	}

	public function getSessionByName(string $name){ // why nawt?
		foreach($this->sessions as $session){
			if($session->getPlayer()->getName() == $name){
				return $session;
			}
		}

		return null;
	}

	public function getBrewingManager(): BrewingManager{
		return $this->brewingManager;
	}
}
