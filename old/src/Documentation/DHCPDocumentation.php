<?php

namespace App\Documentation;

class DHCPDocumentation
{
    public static function getPaths(): array
    {
        return [
            '/api/dhcp' => [
                'get' => [
                    'summary' => 'List all DHCP subnets',
                    'description' => 'Returns a list of all configured DHCP subnets',
                    'tags' => ['DHCP'],
                    'responses' => [
                        '200' => [
                            'description' => 'List of DHCP subnets',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'array',
                                        'items' => [
                                            '$ref' => '#/components/schemas/Subnet'
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
                'post' => [
                    'summary' => 'Create a new DHCP subnet',
                    'description' => 'Creates a new DHCP subnet with the specified configuration',
                    'tags' => ['DHCP'],
                    'requestBody' => [
                        'required' => true,
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    '$ref' => '#/components/schemas/SubnetCreate'
                                ]
                            ]
                        ]
                    ],
                    'responses' => [
                        '201' => [
                            'description' => 'Subnet created successfully'
                        ]
                    ]
                ]
            ],
            '/api/dhcp/{id}' => [
                'get' => [
                    'summary' => 'Get subnet by ID',
                    'description' => 'Returns details of a specific DHCP subnet',
                    'tags' => ['DHCP'],
                    'parameters' => [
                        [
                            'name' => 'id',
                            'in' => 'path',
                            'required' => true,
                            'schema' => ['type' => 'integer']
                        ]
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'Subnet details',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        '$ref' => '#/components/schemas/Subnet'
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
                'put' => [
                    'summary' => 'Update subnet',
                    'description' => 'Updates an existing DHCP subnet configuration',
                    'tags' => ['DHCP'],
                    'parameters' => [
                        [
                            'name' => 'id',
                            'in' => 'path',
                            'required' => true,
                            'schema' => ['type' => 'integer']
                        ]
                    ],
                    'requestBody' => [
                        'required' => true,
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    '$ref' => '#/components/schemas/SubnetUpdate'
                                ]
                            ]
                        ]
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'Subnet updated successfully'
                        ]
                    ]
                ],
                'delete' => [
                    'summary' => 'Delete subnet',
                    'description' => 'Deletes a DHCP subnet configuration',
                    'tags' => ['DHCP'],
                    'parameters' => [
                        [
                            'name' => 'id',
                            'in' => 'path',
                            'required' => true,
                            'schema' => ['type' => 'integer']
                        ]
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'Subnet deleted successfully'
                        ]
                    ]
                ]
            ],
            '/api/dhcp/leases' => [
                'get' => [
                    'summary' => 'Get DHCP leases',
                    'description' => 'Returns a list of all active DHCP leases',
                    'tags' => ['DHCP Leases'],
                    'responses' => [
                        '200' => [
                            'description' => 'List of DHCP leases',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'array',
                                        'items' => [
                                            '$ref' => '#/components/schemas/Lease'
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            '/api/dhcp/reservations' => [
                'get' => [
                    'summary' => 'Get DHCP reservations',
                    'description' => 'Returns a list of all DHCP static lease reservations',
                    'tags' => ['DHCP Reservations'],
                    'responses' => [
                        '200' => [
                            'description' => 'List of DHCP reservations',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'array',
                                        'items' => [
                                            '$ref' => '#/components/schemas/Reservation'
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
                'post' => [
                    'summary' => 'Create reservation',
                    'description' => 'Creates a new DHCP static lease reservation',
                    'tags' => ['DHCP Reservations'],
                    'requestBody' => [
                        'required' => true,
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    '$ref' => '#/components/schemas/ReservationCreate'
                                ]
                            ]
                        ]
                    ],
                    'responses' => [
                        '201' => [
                            'description' => 'Reservation created successfully'
                        ]
                    ]
                ]
            ]
        ];
    }

    public static function getSchemas(): array
    {
        return [
            'Subnet' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'subnet' => ['type' => 'string', 'format' => 'ipv6'],
                    'ccap_core_address' => ['type' => 'string', 'format' => 'ipv6'],
                    'pool_start' => ['type' => 'string', 'format' => 'ipv6'],
                    'pool_end' => ['type' => 'string', 'format' => 'ipv6']
                ]
            ],
            'SubnetCreate' => [
                'type' => 'object',
                'required' => ['subnet', 'ccap_core_address', 'pool_start', 'pool_end'],
                'properties' => [
                    'subnet' => ['type' => 'string', 'format' => 'ipv6'],
                    'ccap_core_address' => ['type' => 'string', 'format' => 'ipv6'],
                    'pool_start' => ['type' => 'string', 'format' => 'ipv6'],
                    'pool_end' => ['type' => 'string', 'format' => 'ipv6']
                ]
            ],
            'SubnetUpdate' => [
                'type' => 'object',
                'required' => ['subnet', 'ccap_core_address', 'pool_start', 'pool_end'],
                'properties' => [
                    'subnet' => ['type' => 'string', 'format' => 'ipv6'],
                    'ccap_core_address' => ['type' => 'string', 'format' => 'ipv6'],
                    'pool_start' => ['type' => 'string', 'format' => 'ipv6'],
                    'pool_end' => ['type' => 'string', 'format' => 'ipv6']
                ]
            ],
            'Lease' => [
                'type' => 'object',
                'properties' => [
                    'ipv6_address' => ['type' => 'string', 'format' => 'ipv6'],
                    'mac_address' => ['type' => 'string'],
                    'subnet' => ['type' => 'string', 'format' => 'ipv6'],
                    'start_time' => ['type' => 'string', 'format' => 'date-time'],
                    'end_time' => ['type' => 'string', 'format' => 'date-time'],
                    'status' => ['type' => 'string', 'enum' => ['active', 'expired']]
                ]
            ],
            'Reservation' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'ipv6_address' => ['type' => 'string', 'format' => 'ipv6'],
                    'mac_address' => ['type' => 'string'],
                    'subnet' => ['type' => 'string', 'format' => 'ipv6'],
                    'ccap_core' => ['type' => 'string', 'format' => 'ipv6']
                ]
            ],
            'ReservationCreate' => [
                'type' => 'object',
                'required' => ['subnet_id', 'mac_address', 'ipv6_address'],
                'properties' => [
                    'subnet_id' => ['type' => 'integer'],
                    'mac_address' => ['type' => 'string'],
                    'ipv6_address' => ['type' => 'string', 'format' => 'ipv6']
                ]
            ]
        ];
    }
}