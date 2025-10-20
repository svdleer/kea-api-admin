<?php

namespace App\Documentation;

class UserDocumentation
{
    public static function getPaths(): array
    {
        return [
            '/users' => [
                'get' => [
                    'tags' => ['Users'],
                    'summary' => 'List all users',
                    'description' => 'Retrieve all users',
                    'responses' => [
                        '200' => [
                            'description' => 'Success',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'array',
                                        'items' => [
                                            '$ref' => '#/components/schemas/User'
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
                    'tags' => ['Users'],
                    'summary' => 'Create new user',
                    'description' => 'Create a new user',
                    'requestBody' => [
                        'required' => true,
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    '$ref' => '#/components/schemas/UserCreate'
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
                                                'example' => 'User created successfully'
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
                            'description' => 'Invalid input',
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
            '/users/{id}' => [
                'parameters' => [
                    [
                        'name' => 'id',
                        'in' => 'path',
                        'required' => true,
                        'description' => 'ID of the user',
                        'schema' => [
                            'type' => 'integer'
                        ]
                    ]
                ],
                'get' => [
                    'tags' => ['Users'],
                    'summary' => 'Get user details',
                    'description' => 'Retrieve details of a specific user',
                    'responses' => [
                        '200' => [
                            'description' => 'Success',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        '$ref' => '#/components/schemas/User'
                                    ]
                                ]
                            ]
                        ],
                        '404' => [
                            'description' => 'User not found',
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
                    'tags' => ['Users'],
                    'summary' => 'Update user',
                    'description' => 'Update a specific user',
                    'requestBody' => [
                        'required' => true,
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    '$ref' => '#/components/schemas/UserUpdate'
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
                                                'example' => 'User updated successfully'
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ],
                        '404' => [
                            'description' => 'User not found',
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
                'delete' => [
                    'tags' => ['Users'],
                    'summary' => 'Delete user',
                    'description' => 'Delete a specific user',
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
                                                'example' => 'User deleted successfully'
                                            ]
                                        ]
                                    ]
                                ]
                            ]
                        ],
                        '404' => [
                            'description' => 'User not found',
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
            ]
        ];
    }

    public static function getSchemas(): array
    {
        return [
            'User' => [
                'type' => 'object',
                'properties' => [
                    'id' => [
                        'type' => 'integer',
                        'description' => 'Unique identifier for the user'
                    ],
                    'username' => [
                        'type' => 'string',
                        'description' => 'Username'
                    ],
                    'email' => [
                        'type' => 'string',
                        'format' => 'email',
                        'description' => 'User email address'
                    ],
                    'role' => [
                        'type' => 'string',
                        'enum' => ['admin', 'user'],
                        'description' => 'User role'
                    ]
                ]
            ],
            'UserCreate' => [
                'type' => 'object',
                'required' => ['username', 'email', 'password', 'role'],
                'properties' => [
                    'username' => [
                        'type' => 'string',
                        'description' => 'Username'
                    ],
                    'email' => [
                        'type' => 'string',
                        'format' => 'email',
                        'description' => 'User email address'
                    ],
                    'password' => [
                        'type' => 'string',
                        'format' => 'password',
                        'description' => 'User password'
                    ],
                    'role' => [
                        'type' => 'string',
                        'enum' => ['admin', 'user'],
                        'description' => 'User role'
                    ]
                ]
            ],
            'UserUpdate' => [
                'type' => 'object',
                'properties' => [
                    'username' => [
                        'type' => 'string',
                        'description' => 'New username'
                    ],
                    'email' => [
                        'type' => 'string',
                        'format' => 'email',
                        'description' => 'New email address'
                    ],
                    'password' => [
                        'type' => 'string',
                        'format' => 'password',
                        'description' => 'New password'
                    ],
                    'role' => [
                        'type' => 'string',
                        'enum' => ['admin', 'user'],
                        'description' => 'New user role'
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
