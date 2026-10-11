# SimpleEconomyExample

An example plugin demonstrating how to use the **SimpleEconomy API** in your own Axolotl-PM plugins.

> [!WARNING]
> This example is a learning resource. The [evaluation prerelease](https://github.com/NhanAZ-Plugins/SimpleEconomyExample/releases/tag/v1.0.1-beta.1) depends on a SimpleEconomy prerelease; production deployment remains unverified.

## Requirements

- [Axolotl-PM](https://github.com/axolotl-pm/PocketMine-MP) with plugin API 5.0.0 as declared by [plugin.yml](plugin.yml). An isolated server test with a headless Bedrock protocol client exercised all four commands. The real game UI remains unverified.
- [SimpleEconomy prerelease `v1.1.0-beta.2`](https://github.com/NhanAZ-Plugins/SimpleEconomy/releases/tag/v1.1.0-beta.2) installed and enabled. This is the verified revision that supplies the confirmed balance API. The build workflow pins its source and SimpleSQL dependency for static analysis.

The [build workflow](.github/workflows/build.yml) runs PHPStan at maximum level against pinned SimpleEconomy and Axolotl-PM source, then uses [DevTools](https://github.com/NhanAZ/DevTools) to build and verify the example PHAR. See the [Actions results](https://github.com/NhanAZ-Plugins/SimpleEconomyExample/actions/workflows/build.yml) for the exact revisions tested. A connected headless Bedrock protocol client exercised the current `/wallet`, `/reward`, `/fine` and `/richest` commands with the producer prerelease PHAR and the example CI artifact. Reward and fine reported success after SQL confirmation; SQLite ended at balance 1,000 and revision 3 after shutdown. A second client session checked an offline reward, an unaffordable fine and a zero amount. These commands reported failure, and the saved balance and revision did not change. An injected SQLite write failure also produced the reward failure message and preserved the stored row. SimpleSQL logged the expected SQL error and wrote a recovery snapshot when the server stopped under that fault. The YAML mirror is queued separately from SQL confirmation. A production release remains pending. SimpleEconomy's `/pay` command uses a separately verified paired database write. This example does not call that command.

## Commands

| Command | Description | Permission |
|---|---|---|
| `/wallet` | View your own balance | Everyone |
| `/reward <player> <amount>` | Give money to a player | OP only |
| `/fine <player> <amount>` | Take money from a player | OP only |
| `/richest` | Show the top 5 richest players | Everyone |

## What This Plugin Demonstrates

### Basic API (for beginners)

| API Method | What It Does | Used In |
|---|---|---|
| `getMoney($name)` | Get a player's balance | `/wallet` command |
| `setMoney($name, $amount)` | Set balance to an exact value | Quick start below |
| `addMoneyConfirmed($name, $amount, $callback)` | Add money and report confirmed SQL save | `/reward` command |
| `reduceMoneyConfirmed($name, $amount, $callback)` | Remove money and report confirmed SQL save | `/fine` command |
| `getTopBalances($limit, $offset)` | Get the richest players | `/richest` command |
| `formatMoney($amount)` | Format number with currency symbol | All commands |

### Events (for beginners)

| Event | When It Fires | Can Cancel? |
|---|---|---|
| `TransactionSubmitEvent` | **Before** a transaction happens | Yes |
| `TransactionSuccessEvent` | After an immediate API balance change, or after confirmed SQL save for a confirmed API call | No (read-only) |

### Advanced API (for experienced developers)

| API Method | What It Does |
|---|---|
| `getMoneyAsync($name, $callback)` | Get balance of offline players (async callback) |
| `getPlayerRank($name)` | Get leaderboard position |

These methods support offline lookups and cached leaderboard reads. `getMoneyAsync()` can raise `InvalidBalanceException` if stored data is malformed. Do not chain debit and credit API calls as a payment or wager, even with confirmed callbacks: two separately saved balances are not an atomic transfer. SimpleEconomy's `/pay` command implements the verified paired database write.

```php
$economy->getMoneyAsync("Steve", function (?int $balance): void {
    $this->getLogger()->info($balance === null ? "No balance record" : "Steve has $balance");
});

$rank = $economy->getPlayerRank("Steve"); // Null when absent from the leaderboard cache.
```

## Quick Start: Using SimpleEconomy in Your Plugin

### Step 1: Add `depend` to your `plugin.yml`

```yaml
depend: SimpleEconomy
```

### Step 2: Get the SimpleEconomy instance

```php
use NhanAZ\SimpleEconomy\Main as SimpleEconomy;

$economy = SimpleEconomy::getInstance();
if ($economy === null) {
    // SimpleEconomy is not loaded
    return;
}
```

### Step 3: Use the API

```php
// Get balance
$balance = $economy->getMoney("Steve");    // Returns ?int (null if offline)

// Add money and wait for SQL save confirmation
$economy->addMoneyConfirmed("Steve", 500, function (bool $saved): void {
    $this->getLogger()->info($saved ? "Reward saved" : "Reward failed");
});

// Set an exact balance with immediate in-memory acceptance only
$accepted = $economy->setMoney("Steve", 5000); // Returns bool

// Remove money and wait for SQL save confirmation
$economy->reduceMoneyConfirmed("Steve", 200, function (bool $saved): void {
    $this->getLogger()->info($saved ? "Fine saved" : "Fine failed");
});

// Format for display
$text = $economy->formatMoney(1500000);          // Returns "$1,500,000"
```

Balance reads can raise `InvalidBalanceException` when a stored value is malformed. Handle that error without writing a replacement balance. The running plugin's four commands are implemented in [Main.php](src/NhanAZ/SimpleEconomyExample/Main.php), while [EventListener.php](src/NhanAZ/SimpleEconomyExample/EventListener.php) contains the active event examples.

### Step 4 (Optional): Listen to events

```php
use NhanAZ\SimpleEconomy\event\TransactionSuccessEvent;

public function onTransaction(TransactionSuccessEvent $event): void {
    $player = $event->playerName;
    $old = $event->oldBalance;
    $new = $event->newBalance;
    $type = $event->type;        // "set", "add", "reduce", or "pay"
    $amount = $event->getAmount();
}
```

## File Structure

```
SimpleEconomyExample/
├── plugin.yml                                    # Plugin metadata + commands
├── .poggit.yml                                   # Poggit CI config
├── LICENSE                                       # MIT License
├── README.md                                     # This file
└── src/NhanAZ/SimpleEconomyExample/
    ├── Main.php                                  # Main plugin + command examples
    └── EventListener.php                         # Event listener examples
```

## License

This example is part of the [SimpleEconomy](https://github.com/NhanAZ-Plugins/SimpleEconomy) project.
