<?php

use App\Controllers\Api\SwitchController;
use App\Controllers\Api\NetworkController;

// CIN Switch routes
$router->get('/api/switches', [SwitchController::class, 'getAll']);
$router->get('/api/switches/{id}', [SwitchController::class, 'getById']);
$router->get('/api/switches/check-exists', [SwitchController::class, 'checkExists']);
$router->get('/api/switches/check-bvi', [SwitchController::class, 'checkBvi']);
$router->get('/api/switches/check-ipv6', [SwitchController::class, 'checkIpv6']);
$router->post('/api/switches', [SwitchController::class, 'create']);
$router->put('/api/switches/{id}', [SwitchController::class, 'update']);
$router->delete('/api/switches/{id}', [SwitchController::class, 'delete']);

// Network routes
$router->post('/api/subnets', [NetworkController::class, 'createSubnet']);
$router->put('/api/subnets/{subnetId}', [NetworkController::class, 'updateSubnet']);
$router->delete('/api/subnets/{subnetId}', [NetworkController::class, 'deleteSubnet']);

// Prefix routes
$router->post('/api/subnets/{subnetId}/prefixes', [NetworkController::class, 'createPrefix']);
$router->put('/api/subnets/{subnetId}/prefixes/{prefixId}', [NetworkController::class, 'updatePrefix']);
$router->delete('/api/subnets/{subnetId}/prefixes/{prefixId}', [NetworkController::class, 'deletePrefix']);
