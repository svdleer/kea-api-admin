<?php

namespace App\Models;

class DHCPv6LeaseModel {
    private $db;
    private $keaApi;

    public function __construct($db, $keaApi) {
        $this->db = $db;
        $this->keaApi = $keaApi;
    }

    public function getLeases($subnetId, $page = 1, $limit = 10) {
        $command = [
            "command" => "lease6-get-page",
            "service" => ["dhcp6"],
            "arguments" => [
                "subnet-id" => $subnetId,
                "from" => ($page - 1) * $limit,
                "limit" => $limit
            ]
        ];

        return $this->keaApi->sendCommand($command);
    }

    public function deleteLease($ipv6Address) {
        $command = [
            "command" => "lease6-del",
            "service" => ["dhcp6"],
            "arguments" => [
                "ip-address" => $ipv6Address
            ]
        ];

        return $this->keaApi->sendCommand($command);
    }
}
