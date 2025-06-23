<?php
require_once 'config.php';
require_once 'SteamAPI.class.php';

class ServerStats {
    private $steam_api;
    private $server_ip;
    private $server_port;
    private $db;
    private $cache_duration = 300;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->steam_api = new SteamAPI(Config::STEAM_API_KEY, "https://api.steampowered.com/");
        $this->server_ip = Config::RCON_HOST;
        $this->server_port = Config::RCON_PORT;
        $this->initializeDatabase();
    }

    private function initializeDatabase() {
        try {
            $sql = "CREATE TABLE IF NOT EXISTS server_stats_cache (
                id INT AUTO_INCREMENT PRIMARY KEY,
                data TEXT NOT NULL,
                timestamp INT NOT NULL,
                UNIQUE KEY unique_cache (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            $this->db->exec($sql);
            
            $sql = "CREATE TABLE IF NOT EXISTS server_stats (
                id INT AUTO_INCREMENT PRIMARY KEY,
                players INT NOT NULL DEFAULT 0,
                max_players INT NOT NULL DEFAULT 100,
                server_name VARCHAR(255) NOT NULL,
                map VARCHAR(255) NOT NULL,
                timestamp INT NOT NULL,
                INDEX idx_timestamp (timestamp)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            $this->db->exec($sql);
        } catch (PDOException $e) {
            error_log("Database initialization error: " . $e->getMessage());
        }
    }

    public function getServerInfo() {
        $cached = $this->getCachedStats();
        if ($cached) {
            return $cached;
        }

        $url = sprintf(
            "https://api.steampowered.com/IGameServersService/GetServerList/v1/?key=%s&filter=addr\\%s:%s",
            Config::STEAM_API_KEY,
            $this->server_ip,
            $this->server_port
        );
        
        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'user_agent' => 'VenomRP-ServerStats/1.0'
            ]
        ]);
        
        $response = @file_get_contents($url, false, $context);
        
        if ($response === false) {
            error_log("Failed to fetch server info from Steam API");
            return $this->getLastSavedStats();
        }

        $data = json_decode($response, true);

        if (isset($data['response']['servers'][0])) {
            $server = $data['response']['servers'][0];
            $stats = [
                'players' => (int)($server['players'] ?? 0),
                'max_players' => (int)($server['max_players'] ?? 100),
                'server_name' => $server['name'] ?? 'Venom Roleplay',
                'map' => $server['map'] ?? 'rp_downtown_v4c_v2',
                'timestamp' => time()
            ];
            
            $this->cacheStats($stats);
            return $stats;
        }
        
        return $this->getLastSavedStats();
    }

    private function getCachedStats() {
        try {
            $stmt = $this->db->prepare("SELECT data FROM server_stats_cache WHERE timestamp > ? ORDER BY timestamp DESC LIMIT 1");
            $stmt->execute([time() - $this->cache_duration]);
            $result = $stmt->fetch();
            
            if ($result && isset($result['data'])) {
                return json_decode($result['data'], true);
            }
        } catch (PDOException $e) {
            error_log("Cache retrieval error: " . $e->getMessage());
        }
        
        return null;
    }

    private function cacheStats($stats) {
        try {
            $stmt = $this->db->prepare("DELETE FROM server_stats_cache WHERE timestamp < ?");
            $stmt->execute([time() - ($this->cache_duration * 2)]);
            
            $stmt = $this->db->prepare("INSERT INTO server_stats_cache (data, timestamp) VALUES (?, ?)");
            $stmt->execute([json_encode($stats), time()]);
        } catch (PDOException $e) {
            error_log("Cache storage error: " . $e->getMessage());
        }
    }

    private function getLastSavedStats() {
        $defaultStats = [
            'players' => 0,
            'max_players' => 100,
            'server_name' => 'Venom Roleplay',
            'map' => 'rp_downtown_v4c_v2',
            'timestamp' => time()
        ];

        try {
            $stmt = $this->db->prepare("SELECT data FROM server_stats_cache ORDER BY timestamp DESC LIMIT 1");
            $stmt->execute();
            $result = $stmt->fetch();
            
            if ($result && isset($result['data'])) {
                return json_decode($result['data'], true) ?: $defaultStats;
            }
            
            $stmt = $this->db->prepare("SELECT * FROM server_stats ORDER BY timestamp DESC LIMIT 1");
            $stmt->execute();
            $result = $stmt->fetch();
            
            if ($result) {
                return [
                    'players' => (int)$result['players'],
                    'max_players' => (int)$result['max_players'],
                    'server_name' => $result['server_name'],
                    'map' => $result['map'],
                    'timestamp' => (int)$result['timestamp']
                ];
            }
            
            return $defaultStats;
        } catch (PDOException $e) {
            error_log("Error retrieving last saved stats: " . $e->getMessage());
            return $defaultStats;
        }
    }
    public function saveStats($stats) {
        if (!$stats || !is_array($stats)) {
            return false;
        }

        try {
            $stmt = $this->db->prepare("INSERT INTO server_stats (players, max_players, server_name, map, timestamp) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                (int)($stats['players'] ?? 0),
                (int)($stats['max_players'] ?? 100),
                $stats['server_name'] ?? 'Venom Roleplay',
                $stats['map'] ?? 'unknown',
                (int)($stats['timestamp'] ?? time())
            ]);
            return true;
        } catch (PDOException $e) {
            error_log("Save stats error: " . $e->getMessage());
            return false;
        }
    }

    public function getHistoricalData($hours = 24) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    FLOOR(AVG(players)) as players,
                    UNIX_TIMESTAMP(DATE_FORMAT(FROM_UNIXTIME(timestamp), '%Y-%m-%d %H:00:00')) as hour_timestamp
                FROM server_stats 
                WHERE timestamp > ?
                GROUP BY hour_timestamp
                ORDER BY hour_timestamp ASC
            ");
            $stmt->execute([time() - ($hours * 3600)]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Historical data error: " . $e->getMessage());
            return [];
        }
    }

    public function getDailyMaxPlayers($days = 7) {
        try {
            $query = "SELECT 
                DATE(FROM_UNIXTIME(timestamp)) as date,
                MAX(CAST(JSON_EXTRACT(data, '$.players') AS UNSIGNED)) as players
            FROM server_stats_cache 
            WHERE timestamp >= UNIX_TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL ? DAY))
            GROUP BY DATE(FROM_UNIXTIME(timestamp))
            ORDER BY date ASC";
                     
            $stmt = $this->db->prepare($query);
            $stmt->execute([$days]);
            
            $results = $stmt->fetchAll();
            
            if (empty($results)) {
                return [
                    "players" => [
                        ["date" => date("Y-m-d"), "players" => 0]
                    ]
                ];
            }
            
            return ["players" => $results];
            
        } catch (PDOException $e) {
            error_log("Daily max players error: " . $e->getMessage());
            return [
                "players" => [
                    ["date" => date("Y-m-d"), "players" => 0]
                ]
            ];
        }
    }

    public function cleanupOldData($days = 30) {
        try {
            $stmt = $this->db->prepare("DELETE FROM server_stats WHERE timestamp < ?");
            $stmt->execute([time() - ($days * 24 * 3600)]);
            
            $stmt = $this->db->prepare("DELETE FROM server_stats_cache WHERE timestamp < ?");
            $stmt->execute([time() - ($this->cache_duration * 10)]);
            
            return true;
        } catch (PDOException $e) {
            error_log("Cleanup error: " . $e->getMessage());
            return false;
        }
    }
}

if (php_sapi_name() === 'cli') {
    try {
        $stats = new ServerStats();
        $serverInfo = $stats->getServerInfo();
        
        if ($serverInfo) {
            $stats->saveStats($serverInfo);
            echo "Server stats updated successfully\n";
        } else {
            echo "Failed to retrieve server stats\n";
        }
    } catch (Exception $e) {
        error_log("CLI execution error: " . $e->getMessage());
        echo "Error: " . $e->getMessage() . "\n";
    }
}
