<?php

/**
 * ╔══════════════════════════════════════════════════════════════════╗
 * ║                   SimpleEconomyExample                         ║
 * ║                                                                ║
 * ║  A demonstration plugin showing how to use the SimpleEconomy   ║
 * ║  API in your own plugins. Each command below is a real-world   ║
 * ║  example you can copy and adapt for your projects.             ║
 * ╚══════════════════════════════════════════════════════════════════╝
 *
 * Available commands:
 *   /wallet              - View your own balance
 *   /reward <player> <$> - Give money to a player (OP only)
 *   /fine <player> <$>   - Take money from a player (OP only)
 *   /richest             - Show top 5 richest players
 */

declare(strict_types=1);

namespace NhanAZ\SimpleEconomyExample;

use NhanAZ\SimpleEconomy\Main as SimpleEconomy;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat;

class Main extends PluginBase {

	/**
	 * The SimpleEconomy plugin instance.
	 * We store it here so we don't have to call getInstance() every time.
	 */
	private SimpleEconomy $economy;

	protected function onEnable(): void {
		// ──────────────────────────────────────────────
		//  Step 1: Get the SimpleEconomy plugin instance
		// ──────────────────────────────────────────────
		$economy = SimpleEconomy::getInstance();

		// Always check if SimpleEconomy is loaded
		if ($economy === null) {
			$this->getLogger()->error("SimpleEconomy is not loaded! Disabling...");
			$this->getServer()->getPluginManager()->disablePlugin($this);
			return;
		}

		$this->economy = $economy;

		// ──────────────────────────────────────────────
		//  Step 2: Register commands
		// ──────────────────────────────────────────────
		$map = $this->getServer()->getCommandMap();
		// (We use simple inline commands in onCommand() below)

		// ──────────────────────────────────────────────
		//  Step 3: Register event listeners
		// ──────────────────────────────────────────────
		$this->getServer()->getPluginManager()->registerEvents(
			new EventListener($this),
			$this,
		);

		$this->getLogger()->info("SimpleEconomyExample enabled! Try /wallet, /reward, /fine, /richest");
	}

	/**
	 * Handle all commands in one place for simplicity.
	 */
	public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
		switch ($command->getName()) {
			case "wallet":
				return $this->handleWallet($sender);

			case "reward":
				return $this->handleReward($sender, $args);

			case "fine":
				return $this->handleFine($sender, $args);

			case "richest":
				return $this->handleRichest($sender);

			default:
				return false;
		}
	}

	// ══════════════════════════════════════════════════
	//  BASIC API EXAMPLES
	//  (Copy these patterns into your own plugins!)
	// ══════════════════════════════════════════════════

	/**
	 * ┌─────────────────────────────────────────────┐
	 * │  Example 1: getMoney() + formatMoney()      │
	 * │  Get a player's balance and display it.     │
	 * └─────────────────────────────────────────────┘
	 *
	 * API used:
	 *   $this->economy->getMoney(string $name): ?int
	 *   $this->economy->formatMoney(int $amount): string
	 */
	private function handleWallet(CommandSender $sender): bool {
		// This command can only be used by players (not console)
		if (!$sender instanceof Player) {
			$sender->sendMessage("This command can only be used in-game.");
			return true;
		}

		// getMoney() returns the player's balance, or null if offline/not found
		$balance = $this->economy->getMoney($sender->getName());

		if ($balance === null) {
			$sender->sendMessage(TextFormat::RED . "Could not load your balance. Try again later.");
			return true;
		}

		// formatMoney() formats the number with the server's currency symbol
		// Example: 1500000 → "$1,500,000" or "$1.5M" (depends on server config)
		$formatted = $this->economy->formatMoney($balance);

		$sender->sendMessage(TextFormat::GREEN . "Your balance: " . TextFormat::WHITE . $formatted);
		return true;
	}

	/**
	 * ┌─────────────────────────────────────────────┐
	 * │  Example 2: addMoney()                      │
	 * │  Give money to a player.                    │
	 * └─────────────────────────────────────────────┘
	 *
	 * API used:
	 *   $this->economy->addMoney(string $name, int $amount): bool
	 *
	 * Returns true if successful, false if:
	 *   - Player is offline
	 *   - Another plugin cancelled the transaction (via TransactionSubmitEvent)
	 */
	private function handleReward(CommandSender $sender, array $args): bool {
		// Check usage: /reward <player> <amount>
		if (count($args) < 2) {
			$sender->sendMessage(TextFormat::YELLOW . "Usage: /reward <player> <amount>");
			return true;
		}

		$playerName = $args[0];
		$amount = (int) $args[1];

		// Validate the amount
		if ($amount <= 0) {
			$sender->sendMessage(TextFormat::RED . "Amount must be greater than 0.");
			return true;
		}

		// addMoney() adds money to the player's balance
		$success = $this->economy->addMoney($playerName, $amount);

		if ($success) {
			$formatted = $this->economy->formatMoney($amount);
			$sender->sendMessage(TextFormat::GREEN . "Gave " . $formatted . " to " . $playerName . "!");
		} else {
			// This happens when:
			// - The player is not online
			// - Another plugin cancelled the transaction
			$sender->sendMessage(TextFormat::RED . "Failed! Is the player online?");
		}

		return true;
	}

	/**
	 * ┌─────────────────────────────────────────────┐
	 * │  Example 3: reduceMoney()                   │
	 * │  Take money from a player.                  │
	 * └─────────────────────────────────────────────┘
	 *
	 * API used:
	 *   $this->economy->reduceMoney(string $name, int $amount): bool
	 *
	 * Returns true if successful, false if:
	 *   - Player is offline
	 *   - Player doesn't have enough money
	 *   - Another plugin cancelled the transaction
	 */
	private function handleFine(CommandSender $sender, array $args): bool {
		// Check usage: /fine <player> <amount>
		if (count($args) < 2) {
			$sender->sendMessage(TextFormat::YELLOW . "Usage: /fine <player> <amount>");
			return true;
		}

		$playerName = $args[0];
		$amount = (int) $args[1];

		if ($amount <= 0) {
			$sender->sendMessage(TextFormat::RED . "Amount must be greater than 0.");
			return true;
		}

		// reduceMoney() removes money from the player's balance
		// It will NOT go below 0 - returns false if insufficient funds
		$success = $this->economy->reduceMoney($playerName, $amount);

		if ($success) {
			$formatted = $this->economy->formatMoney($amount);
			$sender->sendMessage(TextFormat::GREEN . "Fined " . $playerName . " " . $formatted . "!");
		} else {
			$sender->sendMessage(TextFormat::RED . "Failed! Player may be offline or doesn't have enough money.");
		}

		return true;
	}

	/**
	 * ┌─────────────────────────────────────────────┐
	 * │  Example 4: getTopBalances()                │
	 * │  Show the richest players.                  │
	 * └─────────────────────────────────────────────┘
	 *
	 * API used:
	 *   $this->economy->getTopBalances(int $limit, int $offset): array
	 *
	 * Returns an array like:
	 *   [
	 *     ["name" => "Steve",  "balance" => 50000],
	 *     ["name" => "Alex",   "balance" => 30000],
	 *     ...
	 *   ]
	 */
	private function handleRichest(CommandSender $sender): bool {
		// Get top 5 players (you can change the limit)
		$topPlayers = $this->economy->getTopBalances(limit: 5, offset: 0);

		if (count($topPlayers) === 0) {
			$sender->sendMessage(TextFormat::YELLOW . "No players found on the leaderboard.");
			return true;
		}

		$sender->sendMessage(TextFormat::GOLD . "=== Top 5 Richest Players ===");

		$rank = 1;
		foreach ($topPlayers as $entry) {
			$name = $entry["name"];
			$balance = $this->economy->formatMoney($entry["balance"]);

			$sender->sendMessage(
				TextFormat::YELLOW . "#" . $rank . " " .
				TextFormat::WHITE . $name . ": " .
				TextFormat::GREEN . $balance
			);
			$rank++;
		}

		return true;
	}

	/**
	 * ┌─────────────────────────────────────────────────────────┐
	 * │  Example 5: setMoney()                                 │
	 * │  Directly set a player's balance to a specific value.  │
	 * └─────────────────────────────────────────────────────────┘
	 *
	 * Unlike addMoney/reduceMoney which modify the current balance,
	 * setMoney() replaces it entirely. Use with caution!
	 *
	 * API used:
	 *   $this->economy->setMoney(string $name, int $amount): bool
	 */
	private function exampleSetMoney(): void {
		// Set Steve's balance to exactly 5000
		$success = $this->economy->setMoney("Steve", 5000);

		if ($success) {
			// Balance is now 5000, regardless of what it was before
		}
	}

	// ══════════════════════════════════════════════════
	//  FOR ADVANCED DEVELOPERS
	//  (These examples use more complex patterns)
	// ══════════════════════════════════════════════════

	/**
	 * ┌─────────────────────────────────────────────────────────┐
	 * │  Advanced Example 1: getMoneyAsync()                    │
	 * │  Check balance of OFFLINE players.                     │
	 * └─────────────────────────────────────────────────────────┘
	 *
	 * getMoney() only works for ONLINE players.
	 * getMoneyAsync() works for BOTH online AND offline players.
	 *
	 * The result comes through a callback because loading offline
	 * data takes time (it needs to read from the database).
	 *
	 * API used:
	 *   $this->economy->getMoneyAsync(string $name, Closure $callback): void
	 */
	private function exampleGetMoneyAsync(): void {
		$this->economy->getMoneyAsync("Steve", function (?int $balance): void {
			if ($balance !== null) {
				// Player exists, balance is available
				$this->getLogger()->info("Steve's balance: " . $balance);
			} else {
				// Player has never joined the server
				$this->getLogger()->info("Steve has never played on this server.");
			}
		});
	}

	/**
	 * ┌─────────────────────────────────────────────────────────┐
	 * │  Advanced Example 2: getPlayerRank()                   │
	 * │  Check a player's position on the leaderboard.         │
	 * └─────────────────────────────────────────────────────────┘
	 *
	 * API used:
	 *   $this->economy->getPlayerRank(string $name): ?int
	 *
	 * Returns the rank (1 = richest), or null if the player is not
	 * in the leaderboard cache.
	 */
	private function exampleGetPlayerRank(): void {
		$rank = $this->economy->getPlayerRank("Steve");

		if ($rank !== null) {
			$this->getLogger()->info("Steve is ranked #" . $rank . " on the leaderboard.");
		} else {
			$this->getLogger()->info("Steve is not on the leaderboard.");
		}
	}

	/**
	 * ┌─────────────────────────────────────────────────────────┐
	 * │  Advanced Example 3: Combining multiple API calls      │
	 * │  Check balance → do something → modify balance         │
	 * └─────────────────────────────────────────────────────────┘
	 *
	 * A practical example: "Double or Nothing" gamble.
	 * Player pays 100 to play, 50% chance to win 200 back.
	 */
	private function exampleCombinedUsage(Player $player): void {
		$name = $player->getName();
		$cost = 100;

		// Step 1: Check if the player has enough money
		$balance = $this->economy->getMoney($name);
		if ($balance === null || $balance < $cost) {
			$player->sendMessage("You need at least " . $this->economy->formatMoney($cost) . " to play!");
			return;
		}

		// Step 2: Take the entry fee
		$paid = $this->economy->reduceMoney($name, $cost);
		if (!$paid) {
			$player->sendMessage("Payment failed. Try again.");
			return;
		}

		// Step 3: 50/50 chance
		$won = (mt_rand(0, 1) === 1);

		if ($won) {
			// Step 4a: Player wins, give them double
			$this->economy->addMoney($name, $cost * 2);
			$player->sendMessage(TextFormat::GREEN . "You won " . $this->economy->formatMoney($cost * 2) . "!");
		} else {
			// Step 4b: Player loses, money is already taken
			$player->sendMessage(TextFormat::RED . "You lost " . $this->economy->formatMoney($cost) . "!");
		}
	}
}
