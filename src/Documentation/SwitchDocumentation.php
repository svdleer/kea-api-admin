<?php

namespace App\Documentation;

class SwitchDocumentation
{
    public static function getPaths(): array
    {
        return [
            '/switches' => [
                'get' => [
                    'tags' => ['Switches'],
                    'summary' => 'List all switches',
                    'description' => 'Retrieve a list of all switches with their BVI interface information',
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
                                            'data' => [
                                                'type' => 'array',
                                                'items' => [
                                                    '$ref' => '#/components/schemas/Switch'
                                                ]
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ],
                        '500' => [
                            'description' => 'Database error',
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
                    'tags' => ['Switches'],
                    'summary' => 'Create new switch',
                    'description' => 'Create a new switch with BVI interface',
                    'requestBody' => [
                        'required' => true,
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    '$ref' => '#/components/schemas/SwitchCreate'
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
                                                'example' => 'Switch and BVI interface created successfully'
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
                            'description' => 'Missing required fields',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        '$ref' => '#/components/schemas/Error'
                                    ]
                                ]
                            ]
                        ],
                        '500' => [
                            'description' => 'Database error',
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
            '/switches/{id}' => [
                'parameters' => [
                    [
                        'name' => 'id',
                        'in' => 'path',
                        'required' => true,
                        'description' => 'ID of the switch',
                        'schema' => [
                            'type' => 'integer'
                        ]
                    ]
                ],
                'get' => [
                    'tags' => ['Switches'],
                    'summary' => 'Get switch details',
                    'description' => 'Retrieve detailed information about a specific switch',
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
                                            'data' => [
                                                '$ref' => '#/components/schemas/Switch'
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ],
                        '404' => [
                            'description' => 'Switch not found',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        '$ref' => '#/components/schemas/Error'
                                    ]
                                ]
                            ]
                        ],
                        '500' => [
                            'description' => 'Database error',
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
                    'tags' => ['Switches'],
                    'summary' => 'Update switch',
                    'description' => 'Update hostname for a specific switch',
                    'requestBody' => [
                        'required' => true,
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    '$ref' => '#/components/schemas/SwitchUpdate'
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
                                                'example' => 'Switch updated successfully'
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ],
                        '400' => [
                            'description' => 'Hostname is required',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        '$ref' => '#/components/schemas/Error'
                                    ]
                                ]
                            ]
                        ],
                        '500' => [
                            'description' => 'Database error',
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
                    'tags' => ['Switches'],
                    'summary' => 'Delete switch',
                    'description' => 'Delete a switch and its related BVI interfaces',
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
                                                'example' => 'Switch and related BVI interfaces deleted successfully'
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ],
                        '500' => [
                            'description' => 'Database error',
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
            'Switch' => [
                'type' => 'object',
                'properties' => [
                    'id' => [
                        'type' => 'integer',
                        'description' => 'Unique identifier for the switch'
                    ],
                    'hostname' => [
                        'type' => 'string',
                        'description' => 'Hostname of the switch'
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
            'SwitchCreate' => [
                'type' => 'object',
                'required' => ['hostname', 'interface_number', 'ipv6_address'],
                'properties' => [
                    'hostname' => [
                        'type' => 'string',
                        'description' => 'Hostname of the switch'
                    ],
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
            'SwitchUpdate' => [
                'type' => 'object',
                'required' => ['hostname'],
                'properties' => [
                    'hostname' => [
                        'type' => 'string',
                        'description' => 'New hostname for the switch'
                    ]
                ]
            ],
            'Error' => [
                'type' => 'object',
                'properties' => [
                    'success' => [
                        'type' => 'boolean',
                        'example' => false
                    ],
                    'error' => [
                        'type' => 'string'
                    ]
                ]
            ]
        ];
    }
}
