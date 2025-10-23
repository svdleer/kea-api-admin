<?php
namespace App\Controllers\Api;

use App\Models\KeaModel;
use Exception;

class KeaController
{
    private KeaModel $keaModel;

    public function __construct(string $keaApiUrl, array $keaService)
    {
        $this->keaModel = new KeaModel($keaApiUrl, $keaService);
    }

    protected function sendKEACommand( $command,  $arguments = [])
    {
        error_log("Sending KEA command: " . $command . " with arguments: " . print_r($arguments, true));
        return $this->keaModel->sendKEACommand($command, $arguments);
    }
}
