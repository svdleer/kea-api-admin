<?php

use App\Controllers\Api\SwitchController;
use App\Controllers\Api\NetworkController;
use App\Controllers\Api\DHCPv6OptionsController;
use App\Controllers\KeaServerController;

// Kea Server Management API routes
$router->get('/api/kea-servers', [KeaServerController::class, 'getServers']);
$router->post('/api/kea-servers', [KeaServerController::class, 'create']);
$router->put('/api/kea-servers/{id}', [KeaServerController::class, 'update']);
$router->delete('/api/kea-servers/{id}', [KeaServerController::class, 'delete']);
$router->get('/api/kea-servers/{id}/test', [KeaServerController::class, 'testConnection']);

// CIN Switch routes
$router->get('/api/switches', [SwitchController::class, 'getAll']);
$router->get('/api/switches/{id}', [SwitchController::class, 'getById']);
$router->get('/api/switches/check-exists', [SwitchController::class, 'checkExists']);
$router->get('/api/switches/check-bvi', [SwitchController::class, 'checkBvi']);
$router->get('/api/switches/check-ipv6', [SwitchController::class, 'checkIpv6']);
$router->post('/api/switches', [SwitchController::class, 'create']);
$router->put('/api/switches/{id}', [SwitchController::class, 'update']);
$router->delete('/api/switches/{id}', [SwitchController::class, 'delete']);

// DHCP Options routes
$router->get('/api/dhcp/options', [DHCPv6OptionsController::class, 'list']);
$router->post('/api/dhcp/options', [DHCPv6OptionsController::class, 'create']);
$router->put('/api/dhcp/options/{code}', [DHCPv6OptionsController::class, 'update']);
$router->delete('/api/dhcp/options/{code}', [DHCPv6OptionsController::class, 'delete']);

// Network routes
$router->post('/api/subnets', [NetworkController::class, 'createSubnet']);
$router->put('/api/subnets/{subnetId}', [NetworkController::class, 'updateSubnet']);
$router->delete('/api/subnets/{subnetId}', [NetworkController::class, 'deleteSubnet']);

// Prefix routes
$router->post('/api/subnets/{subnetId}/prefixes', [NetworkController::class, 'createPrefix']);
$router->put('/api/subnets/{subnetId}/prefixes/{prefixId}', [NetworkController::class, 'updatePrefix']);
$router->delete('/api/subnets/{subnetId}/prefixes/{prefixId}', [NetworkController::class, 'deletePrefix']);
