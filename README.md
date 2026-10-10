# SimpleEconomyExample

An example plugin demonstrating how to use the **SimpleEconomy API** in your own Axolotl-PM plugins.

> [!WARNING]
> This example is a learning resource and has not passed the checks required for a production release. Its SimpleEconomy dependency is still being updated.

## Requirements

- [Axolotl-PM](https://github.com/axolotl-pm/PocketMine-MP) with plugin API 5.0.0 as declared by [plugin.yml](plugin.yml). Runtime compatibility has not yet been verified.
- [SimpleEconomy](https://github.com/NhanAZ-Plugins/SimpleEconomy) installed and enabled. The consumer must be retested after the producer's dependency and transaction gates pass.

The current source has 13 PHPStan level max findings against the checked out SimpleEconomy source and pinned server symbols. No hosted CI, DevTools PHAR or runtime result establishes compatibility for a release. The economy methods returning `bool` report immediate in-memory acceptance and do not confirm that an asynchronous database save completed.

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
| `setMoney($name, $amount)` | Set balance to an exact value | Example in code |
| `addMoney($name, $amount)` | Add money to a player | `/reward` command |
| `reduceMoney($name, $amount)` | Remove money from a player | `/fine` command |
| `getTopBalances($limit, $offset)` | Get the richest players | `/richest` command |
| `formatMoney($amount)` | Format number with currency symbol | All commands |

### Events (for beginners)

| Event | When It Fires | Can Cancel? |
|---|---|---|
| `TransactionSubmitEvent` | **Before** a transaction happens | Yes |
| `TransactionSuccessEvent` | After an in-memory balance change, before persistence is confirmed | No (read-only) |

### Advanced API (for experienced developers)

| API Method | What It Does |
|---|---|
| `getMoneyAsync($name, $callback)` | Get balance of offline players (async callback) |
| `getPlayerRank($name)` | Get leaderboard position |
| Combined API calls | Check balance → modify → react (e.g. gambling) |

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

// Add money
$success = $economy->addMoney("Steve", 500);    // Returns bool

// Remove money
$success = $economy->reduceMoney("Steve", 200); // Returns bool (false if not enough)

// Format for display
$text = $economy->formatMoney(1500000);          // Returns "$1,500,000"
```

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
