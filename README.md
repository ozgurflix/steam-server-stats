# steam-server-stats
This is a PHP based system to monitor and cache the live stats from a steam game server using the Steam API.
## Features

### Server Monitoring
- Real-time player count tracking
- Server status monitoring via Steam API
- Automatic failover to cached/historical data
- Map and server name tracking

### Analytics & Reporting
- Historical player data with hourly aggregation
- Daily maximum player statistics
- Market transaction analytics
- Revenue tracking and unique buyer metrics

### Performance
- Caching system (5-minute cache duration)
- Singleton database connection pattern
- Automatic database table creation
- Data cleanup and maintenance functions

## Installation

### Prerequisites
- PHP 7.4 or higher
- MySQL 5.7 or higher / MariaDB 10.2+
- Steam Web API Key
- Server RCON access

### Setup Steps

1. **Clone/Download Files**
   ```bash
   git clone https://github.com/ozgurflix/steam-server-stats/
   cd steam-server-stats
   ```

2. **Configure Database**
   - Create MySQL database: `venomrpc_test`
   - Create user with appropriate permissions
   - Update `config.php` with your credentials

3. **Update Configuration**
   Edit `config.php` and update:
   ```php
   const DB_HOST = 'your_host';
   const DB_NAME = 'your_database';
   const DB_USER = 'your_username';
   const DB_PASS = 'your_password';
   const STEAM_API_KEY = 'your_steam_api_key';
   const RCON_HOST = 'your_server_ip';
   const RCON_PORT = 27015; // Your server port
   ```

4. **Install Dependencies**
   - Ensure `SteamAPI.class.php` is in the same directory
   - Verify PHP PDO MySQL extension is enabled

## Usage

### Basic Implementation

```php
require_once 'config.php';
require_once 'server_stats.php';

// Initialize
$stats = new ServerStats();

// Get current server info
$serverInfo = $stats->getServerInfo();
echo "Players: " . $serverInfo['players'] . "/" . $serverInfo['max_players'];

// Get market statistics
$marketStats = $stats->getMarketStats();
echo "Daily Revenue: $" . $marketStats['total_revenue'];
```

### CLI Usage (Cron Jobs)

```bash
# Update server stats every 5 minutes
*/5 * * * * /usr/bin/php /path/to/server_stats.php

# Daily cleanup (optional)
0 2 * * * /usr/bin/php -r "require 'config.php'; require 'server_stats.php'; (new ServerStats())->cleanupOldData();"
```

### Web Integration

```php
// For web dashboard
$stats = new ServerStats();

// Real-time data
$current = $stats->getServerInfo();

// Historical charts
$historical = $stats->getHistoricalData(24); // Last 24 hours
$daily = $stats->getDailyMaxPlayers(7); // Last 7 days

// Market metrics
$market = $stats->getMarketStats();
```

## API Methods

### Core Methods

#### `getServerInfo()`
Returns current server status with intelligent caching
```php
[
    'players' => 45,
    'max_players' => 100,
    'server_name' => 'Venom Roleplay',
    'map' => 'rp_downtown_v4c_v2',
    'timestamp' => 1719264000
]
```

#### `getHistoricalData($hours = 24)`
Returns hourly aggregated player data
```php
[
    ['players' => 34, 'hour_timestamp' => 1719260400],
    ['players' => 42, 'hour_timestamp' => 1719264000]
]
```

#### `getDailyMaxPlayers($days = 7)`
Returns daily maximum player counts
```php
[
    'players' => [
        ['date' => '2024-06-24', 'players' => 78],
        ['date' => '2024-06-25', 'players' => 82]
    ]
]
```

### Maintenance Methods

#### `saveStats($stats)`
Manually save server statistics to database

#### `cleanupOldData($days = 30)`
Remove old data to maintain database performance

## Database Schema

### Auto-Created Tables

**server_stats_cache**
- `id` - Primary key
- `data` - JSON cached server data
- `timestamp` - Cache timestamp

**server_stats**
- `id` - Primary key
- `players` - Current player count
- `max_players` - Server capacity
- `server_name` - Server display name
- `map` - Current map
- `timestamp` - Record timestamp

## Configuration Options

### Cache Settings
```php
private $cache_duration = 300; // 5 minutes in seconds
```

### Database Optimization
- Automatic index creation on timestamp columns
- UTF8MB4 charset for emoji and international character support
- InnoDB engine for better performance and reliability

## Error Handling

### Automatic Fallbacks
1. **Steam API Failure** → Cache lookup
2. **Cache Miss** → Historical data
3. **Database Error** → Default values
4. **Complete Failure** → Graceful degradation

### Logging
All errors are logged via `error_log()` for debugging:
- API connection failures
- Database connection issues
- Cache operation errors
- Data validation problems

## Performance Considerations

### Caching Strategy
- 5-minute cache prevents API spam
- Automatic cache cleanup
- Memory-efficient singleton pattern

### Database Optimization
- Indexed timestamp columns
- Prepared statements
- Connection pooling via singleton

### Recommended Cron Schedule
```bash
# Server stats update
*/5 * * * * php /path/to/server_stats.php

# Daily maintenance
0 2 * * * php -r "require 'config.php'; (new ServerStats())->cleanupOldData();"
```

## Security Features

- SQL injection protection via prepared statements
- Input validation and type casting
- Secure session configuration
- Error message sanitization for production

## Troubleshooting

### Common Issues

**Steam API Connection Failed**
- Verify API key is valid
- Check server IP/port configuration
- Ensure firewall allows outbound HTTPS

**Database Connection Error**
- Verify credentials in config.php
- Check MySQL service status
- Confirm database exists and user has permissions

**Cache Not Working**
- Check write permissions on database
- Verify table creation succeeded
- Review error logs for PDO exceptions

### Debug Mode
Enable detailed logging by checking your PHP error log after operations.

---

**Last Updated:** June 2025  
**Version:** 0.7  
**PHP Requirements:** 7.4+  
**Database:** MySQL 5.7+ / MariaDB 10.2+
