<?php

use App\Controllers\Api\CinSwitch;
use App\Controllers\Api\NetworkController;
use App\Controllers\Api\DHCPv6OptionsController;
use App\Controllers\Api\RadiusController;
use App\Controllers\KeaServerController;

// Kea Server Management API routes
$router->get('/api/kea-servers', [KeaServerController::class, 'getServers']);
$router->post('/api/kea-servers', [KeaServerController::class, 'create']);
$router->put('/api/kea-servers/{id}', [KeaServerController::class, 'update']);
$router->delete('/api/kea-servers/{id}', [KeaServerController::class, 'delete']);
$router->get('/api/kea-servers/{id}/test', [KeaServerController::class, 'testConnection']);

// CIN Switch routes
$router->get('/api/switches', [CinSwitch::class, 'getAll']);
$router->get('/api/switches/{id}', [CinSwitch::class, 'getById']);
$router->get('/api/switches/check-exists', [CinSwitch::class, 'checkExists']);
$router->get('/api/switches/check-bvi', [CinSwitch::class, 'checkBvi']);
$router->get('/api/switches/check-ipv6', [CinSwitch::class, 'checkIpv6']);
$router->post('/api/switches', [CinSwitch::class, 'create']);
$router->put('/api/switches/{id}', [CinSwitch::class, 'update']);
$router->delete('/api/switches/{id}', [CinSwitch::class, 'delete']);

// BVI Interface routes
$router->get('/api/radius/clients', [RadiusController::class, 'getAllClients']);
$router->get('/api/radius/clients/{id}', [RadiusController::class, 'getClientById']);
$router->post('/api/radius/clients', [RadiusController::class, 'createClient']);
$router->put('/api/radius/clients/{id}', [RadiusController::class, 'updateClient']);
$router->delete('/api/radius/clients/{id}', [RadiusController::class, 'deleteClient']);
$router->post('/api/radius/sync-bvi', [RadiusController::class, 'syncBviInterfaces']);
$router->get('/api/radius/global-secret', [RadiusController::class, 'getGlobalSecret']);
$router->put('/api/radius/global-secret', [RadiusController::class, 'updateGlobalSecret']);
$router->get('/api/radius/servers/status', [RadiusController::class, 'getServersStatus']);
$router->post('/api/radius/servers/sync', [RadiusController::class, 'forceSyncServers']);
$router->get('/api/radius/servers/config', [RadiusController::class, 'getServersConfig']);
$router->put('/api/radius/servers/config', [RadiusController::class, 'updateServerConfig']);
$router->post('/api/radius/servers/test', [RadiusController::class, 'testServerConnection']);

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

// DHCPv6 Lease/Reservation routes
$router->put('/api/dhcp/static', [\App\Controllers\Api\DHCPv6LeaseController::class, 'updateReservation']);
