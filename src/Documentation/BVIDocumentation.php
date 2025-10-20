<?php

namespace App\Documentation;

class BVIDocumentation
{
    public static function getPaths(): array
    {
        return [
            '/switches/{switchId}/bvi' => [
                'parameters' => [
                    [
                        'name' => 'switchId',
                        'in' => 'path',
                        'required' => true,
                        'description' => 'ID of the switch',
                        'schema' => [
                            'type' => 'integer'
                        ]
                    ]
                ],
                'get' => [
                    'tags' => ['BVI Interfaces'],
                    'summary' => 'List all BVI interfaces',
                    'description' => 'Retrieve all BVI interfaces for a specific switch',
                    'responses' => [
                        '200' => [
                            'description' => 'Success',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'array',
                                        'items' => [
                                            '$ref' => '#/components/schemas/BVI'
                                        ]
                                    ]
                                ]
                            ]
                        ],
                        '500' => [
                            'description' => 'Internal server error',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        '$ref' => '#/components/schemas/Error'
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
                'post' => [
                    'tags' => ['BVI Interfaces'],
                    'summary' => 'Create new BVI interface',
                    'description' => 'Create a new BVI interface for a specific switch',
                    'requestBody' => [
                        'required' => true,
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    '$ref' => '#/components/schemas/BVICreate'
                                ]
                            ]
                        ]
                    ],
                    'responses' => [
                        '201' => [
                            'description' => 'Created successfully',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'success' => [
                                                'type' => 'boolean',
                                                'example' => true
                                            ],
                                            'message' => [
                                                'type' => 'string',
                                                'example' => 'BVI interface created successfully'
                                            ],
                                            'id' => [
                                                'type' => 'integer'
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ],
                        '400' => [
                            'description' => 'Invalid JSON data',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        '$ref' => '#/components/schemas/Error'
                                    ]
                                ]
                            ]
                        ],
                        '500' => [
                            'description' => 'Internal server error',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        '$ref' => '#/components/schemas/Error'
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            '/switches/{switchId}/bvi/{bviId}' => [
                'parameters' => [
                    [
                        'name' => 'switchId',
                        'in' => 'path',
                        'required' => true,
                        'description' => 'ID of the switch',
                        'schema' => [
                            'type' => 'integer'
                        ]
                    ],
                    [
                        'name' => 'bviId',
                        'in' => 'path',
                        'required' => true,
                        'description' => 'ID of the BVI interface',
                        'schema' => [
                            'type' => 'integer'
                        ]
                    ]
                ],
                'get' => [
                    'tags' => ['BVI Interfaces'],
                    'summary' => 'Get BVI interface details',
                    'description' => 'Retrieve details of a specific BVI interface',
                    'responses' => [
                        '200' => [
                            'description' => 'Success',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        '$ref' => '#/components/schemas/BVI'
                                    ]
                                ]
                            ]
                        ],
                        '404' => [
                            'description' => 'BVI interface not found',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        '$ref' => '#/components/schemas/Error'
                                    ]
                                ]
                            ]
                        ],
                        '500' => [
                            'description' => 'Internal server error',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        '$ref' => '#/components/schemas/Error'
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
                'put' => [
                    'tags' => ['BVI Interfaces'],
                    'summary' => 'Update BVI interface',
                    'description' => 'Update a specific BVI interface',
                    'requestBody' => [
                        'required' => true,
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    '$ref' => '#/components/schemas/BVIUpdate'
                                ]
                            ]
                        ]
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'Success',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'success' => [
                                                'type' => 'boolean',
                                                'example' => true
                                            ],
                                            'message' => [
                                                'type' => 'string',
                                                'example' => 'BVI interface updated successfully'
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ],
                        '404' => [
                            'description' => 'BVI interface not found',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        '$ref' => '#/components/schemas/Error'
                                    ]
                                ]
                            ]
                        ]
                    ]
                ],
                'delete' => [
                    'tags' => ['BVI Interfaces'],
                    'summary' => 'Delete BVI interface',
                    'description' => 'Delete a specific BVI interface',
                    'responses' => [
                        '200' => [
                            'description' => 'Success',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'success' => [
                                                'type' => 'boolean',
                                                'example' => true
                                            ],
                                            'message' => [
                                                'type' => 'string',
                                                'example' => 'BVI interface deleted successfully'
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ],
                        '404' => [
                            'description' => 'BVI interface not found',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        '$ref' => '#/components/schemas/Error'
                                    ]
                                ]
                            ]
                        ],
                        '500' => [
                            'description' => 'Internal server error',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        '$ref' => '#/components/schemas/Error'
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            '/switches/{switchId}/bvi/check' => [
                'parameters' => [
                    [
                        'name' => 'switchId',
                        'in' => 'path',
                        'required' => true,
                        'description' => 'ID of the switch',
                        'schema' => [
                            'type' => 'integer'
                        ]
                    ],
                    [
                        'name' => 'interface_number',
                        'in' => 'query',
                        'required' => true,
                        'description' => 'BVI interface number to check',
                        'schema' => [
                            'type' => 'integer'
                        ]
                    ],
                    [
                        'name' => 'exclude_id',
                        'in' => 'query',
                        'required' => false,
                        'description' => 'BVI ID to exclude from check',
                        'schema' => [
                            'type' => 'integer'
                        ]
                    ]
                ],
                'get' => [
                    'tags' => ['BVI Interfaces'],
                    'summary' => 'Check if BVI interface exists',
                    'description' => 'Check if a BVI interface number already exists for a switch',
                    'responses' => [
                        '200' => [
                            'description' => 'Success',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'exists' => [
                                                'type' => 'boolean'
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ],
                        '500' => [
                            'description' => 'Internal server error',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        '$ref' => '#/components/schemas/Error'
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ],
            '/switches/bvi/check-ipv6' => [
                'parameters' => [
                    [
                        'name' => 'ipv6',
                        'in' => 'query',
                        'required' => true,
                        'description' => 'IPv6 address to check',
                        'schema' => [
                            'type' => 'string'
                        ]
                    ]
                ],
                'get' => [
                    'tags' => ['BVI Interfaces'],
                    'summary' => 'Check if IPv6 address exists',
                    'description' => 'Check if an IPv6 address is already in use',
                    'responses' => [
                        '200' => [
                            'description' => 'Success',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'exists' => [
                                                'type' => 'boolean'
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ],
                        '500' => [
                            'description' => 'Internal server error',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        '$ref' => '#/components/schemas/Error'
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }

    public static function getSchemas(): array
    {
        return [
            'BVI' => [
                'type' => 'object',
                'properties' => [
                    'id' => [
                        'type' => 'integer',
                        'description' => 'Unique identifier for the BVI interface'
                    ],
                    'switch_id' => [
                        'type' => 'integer',
                        'description' => 'ID of the switch this BVI belongs to'
                    ],
                    'interface_number' => [
                        'type' => 'integer',
                        'description' => 'BVI interface number'
                    ],
                    'ipv6_address' => [
                        'type' => 'string',
                        'description' => 'IPv6 address of the BVI interface'
                    ]
                ]
            ],
            'BVICreate' => [
                'type' => 'object',
                'required' => ['interface_number', 'ipv6_address'],
                'properties' => [
                    'interface_number' => [
                        'type' => 'integer',
                        'description' => 'BVI interface number'
                    ],
                    'ipv6_address' => [
                        'type' => 'string',
                        'description' => 'IPv6 address for the BVI interface'
                    ]
                ]
            ],
            'BVIUpdate' => [
                'type' => 'object',
                'properties' => [
                    'interface_number' => [
                        'type' => 'integer',
                        'description' => 'New BVI interface number'
                    ],
                    'ipv6_address' => [
                        'type' => 'string',
                        'description' => 'New IPv6 address'
                    ]
                ]
            ],
            'Error' => [
                'type' => 'object',
                'properties' => [
                    'error' => [
                        'type' => 'string'
                    ]
                ]
            ]
        ];
    }
}
