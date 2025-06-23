<?php
require_once 'config.php';
require_once 'SteamAPI.class.php';

class ServerStats {
    private $steam_api;
    private $server_ip;
    private $server_port;
    private $db;
    private $cache_duration = 300;

    public function __construct($db) {
        $this->db = $db;
        $this->steam_api = new SteamAPI(STEAM_API_KEY, "https://api.steampowered.com/");
        $this->server_ip = "X.X.X.X";
        $this->server_port = "PORT"; // default port should be 27015
    }

    public function getServerInfo() {
        $cached = $this->getCachedStats();
        if ($cached) {
            return $cached;
        }

        $url = "https://api.steampowered.com/IGameServersService/GetServerList/v1/?key=" . STEAM_API_KEY . "&filter=addr\\" . $this->server_ip . ":" . $this->server_port;
        $response = @file_get_contents($url);
        
        if ($response === false) {
            return $this->getLastSavedStats();
        }

        $data = json_decode($response, true);

        if (isset($data['response']['servers'][0])) {
            $server = $data['response']['servers'][0];
            $stats = [
                'players' => $server['players'],
                'max_players' => $server['max_players'],
                'server_name' => $server['name'],
                'map' => $server['map'],
                'timestamp' => time()
            ];
            
            $this->cacheStats($stats);
            return $stats;
        }
        
        return $this->getLastSavedStats();
    }

    private function getCachedStats() {
        $stmt = $this->db->prepare("SELECT * FROM server_stats_cache WHERE timestamp > ?");
        $stmt->execute([time() - $this->cache_duration]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            return json_decode($result['data'], true);
        }
        return null;
    }

    private function cacheStats($stats) {
        $stmt = $this->db->prepare("INSERT INTO server_stats_cache (data, timestamp) VALUES (?, ?) 
                                   ON DUPLICATE KEY UPDATE data = VALUES(data), timestamp = VALUES(timestamp)");
        $stmt->execute([json_encode($stats), time()]);
    }

    private function getLastSavedStats() {
        try {
            $stmt = $this->db->prepare("SELECT data FROM server_stats_cache ORDER BY timestamp DESC LIMIT 1");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result && isset($result['data'])) {
                return json_decode($result['data'], true);
            }
            
            $stmt = $this->db->prepare("SELECT * FROM server_stats ORDER BY timestamp DESC LIMIT 1");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                return [
                    'players' => $result['players'],
                    'max_players' => $result['max_players'],
                    'server_name' => $result['server_name'],
                    'map' => $result['map'],
                    'timestamp' => $result['timestamp']
                ];
            }
            
            return [
                'players' => 0,
                'max_players' => 100,
                'server_name' => 'Venom Roleplay',
                'map' => 'rp_downtown_v4c_v2',
                'timestamp' => time()
            ];
        } catch (PDOException $e) {
            error_log("Error in getLastSavedStats: " . $e->getMessage());
            return [
                'players' => 0,
                'max_players' => 100,
                'server_name' => 'Venom Roleplay',
                'map' => 'rp_downtown_v4c_v2',
                'timestamp' => time()
            ];
        }
    }

    public function getMarketStats() {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    (SELECT COUNT(*) FROM orders WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) as total_orders,
                    (SELECT COALESCE(SUM(price), 0) FROM orders WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) as total_revenue,
                    (SELECT COUNT(DISTINCT user_id) FROM orders WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) as unique_buyers,
                    (SELECT COUNT(*) FROM products WHERE active = 1) as active_products
            ");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return array_map(function($value) {
                return $value === null ? 0 : $value;
            }, $result);
        } catch (PDOException $e) {
            return [
                'total_orders' => 0,
                'total_revenue' => 0,
                'unique_buyers' => 0,
                'active_products' => 0
            ];
        }
    }

    public function saveStats($stats) {
        if ($stats) {
            $stmt = $this->db->prepare("INSERT INTO server_stats (players, max_players, server_name, map, timestamp) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                $stats['players'],
                $stats['max_players'],
                $stats['server_name'],
                $stats['map'],
                $stats['timestamp']
            ]);
        }
    }

    public function getHistoricalData($hours = 24) {
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
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
            
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($results)) {
                return [
                    "players" => [
                        ["date" => date("Y-m-d"), "players" => 0]
                    ]
                ];
            }
            
            return ["players" => $results];
            
        } catch (PDOException $e) {
            error_log("Error in getDailyMaxPlayers: " . $e->getMessage());
            return [
                "players" => [
                    ["date" => date("Y-m-d"), "players" => 0]
                ]
            ];
        }
    }
}

$sql = "CREATE TABLE IF NOT EXISTS server_stats_cache (
    id INT AUTO_INCREMENT PRIMARY KEY,
    data TEXT NOT NULL,
    timestamp INT NOT NULL,
    UNIQUE KEY (id)
)";

try {
    $db->exec($sql);
} catch (PDOException $e) {
}

if (php_sapi_name() == 'cli') {
    $stats = new ServerStats($db);
    $serverInfo = $stats->getServerInfo();
    $stats->saveStats($serverInfo);
}
