<?php

namespace App\Controllers;

use App\Network\CinSwitch;
use App\Database\Database;

class ApiController {
    private $cinSwitch;
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
        $this->cinSwitch = new CinSwitch($this->db);
    }

public function checkHostname() {
    $hostname = filter_input(INPUT_GET, 'hostname', FILTER_SANITIZE_STRING);
    $exists = $this->cinSwitch->hostnameExists($hostname);
    
    header('Content-Type: application/json');
    echo json_encode(['exists' => $exists], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
}

public function checkIPv6() {
    $ipv6 = filter_input(INPUT_GET, 'ipv6', FILTER_SANITIZE_STRING);
    $exists = $this->cinSwitch->ipv6AddressExists($ipv6);
    
    header('Content-Type: application/json');
    echo json_encode(['exists' => $exists], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
}
}
