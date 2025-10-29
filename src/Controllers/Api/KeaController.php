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
        return $this->keaModel->sendKEACommand($command, $arguments);
    }
}
