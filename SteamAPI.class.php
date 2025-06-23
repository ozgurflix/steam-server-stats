<?php
class SteamAPI {
    private $steamids;
    private $api_key;
    private $api_url = "https://api.steampowered.com/";

    public function __construct($steamids, $api_key) {
        $this->steamids = is_array($steamids) ? $steamids : array($steamids);
        $this->api_key = $api_key;
    }

    public function set_api_key($api_key) {
        $this->api_key = $api_key;
    }

    public function add_steam_id($id) {
        array_push($this->steamids, $id);
    }

    public function GetPlayerInfo() {
        $data = array();
        $steamids = implode(",", $this->steamids);
        $url = $this->api_url . "ISteamUser/GetPlayerSummaries/v0002/?key=" . $this->api_key . "&steamids=" . $steamids;
        $json = file_get_contents($url);
        $json = json_decode($json, true);

        foreach ($json['response']['players'] as $player) {
            $personastate = "";
            switch ($player['personastate']) {
                case 0:
                    $personastate = "Offline";
                    break;
                case 1:
                    $personastate = "Online";
                    break;
                case 2:
                    $personastate = "Busy";
                    break;
                case 3:
                    $personastate = "Away";
                    break;
                case 4:
                    $personastate = "Snooze";
                    break;
                case 5:
                    $personastate = "LookingToTrade";
                    break;
                case 6:
                    $personastate = "LookingToPlay";
                    break;
            }

            $communityvisibilitystate = "";
            switch ($player['communityvisibilitystate']) {
                case 1:
                    $communityvisibilitystate = "Private";
                    break;
                case 2:
                    $communityvisibilitystate = "FriendsOnly";
                    break;
                case 3:
                    $communityvisibilitystate = "FriendsOfFriends";
                    break;
                case 4:
                    $communityvisibilitystate = "UsersOnly";
                    break;
                case 5:
                    $communityvisibilitystate = "Public";
                    break;
            }

            $temp = array(
                'steamid' => $player['steamid'],
                'personaname' => $player['personaname'],
                'profileurl' => $player['profileurl'],
                'avatar' => $player['avatar'],
                'avatarmedium' => $player['avatarmedium'],
                'avatarfull' => $player['avatarfull'],
                'personastate' => $personastate,
                'communityvisibilitystate' => $communityvisibilitystate,
                'profilestate' => $player['profilestate'],
                'lastlogoff' => date("m-d-Y H:i:s", $player['lastlogoff']),
                'realname' => isset($player['realname']) ? $player['realname'] : null,
                'primaryclanid' => isset($player['primaryclanid']) ? $player['primaryclanid'] : null,
                'timecreated' => isset($player['timecreated']) ? date("m-d-Y H:i:s", $player['timecreated']) : null,
                'gameid' => isset($player['gameid']) ? $player['gameid'] : null,
                'gameserverip' => isset($player['gameserverip']) ? $player['gameserverip'] : null,
                'gameextrainfo' => isset($player['gameextrainfo']) ? $player['gameextrainfo'] : null,
                'cityid' => isset($player['cityid']) ? $player['cityid'] : null,
                'loccountrycode' => isset($player['loccountrycode']) ? $player['loccountrycode'] : null,
                'locstatecode' => isset($player['locstatecode']) ? $player['locstatecode'] : null,
                'loccityid' => isset($player['loccityid']) ? $player['loccityid'] : null
            );

            array_push($data, $temp);
        }

        return $data;
    }

    public function GetPlayerBans() {
        $steamids = implode(",", $this->steamids);
        $url = $this->api_url . "ISteamUser/GetPlayerBans/v1/?key=" . $this->api_key . "&steamids=" . $steamids;
        $json = file_get_contents($url);
        return json_decode($json, true);
    }
} 
