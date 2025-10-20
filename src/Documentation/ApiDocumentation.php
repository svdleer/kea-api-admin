<?php

namespace App\Documentation;

class ApiDocumentation
{
    public static function getSpecification(): array
    {
        return [
            'openapi' => '3.0.0',
            'info' => [
                'title' => 'VFZ RPD Infrastructure Management API Documentation',
                'version' => '1.0.0',
                'description' => 'API documentation for VFZ RPD Infrastructure Management'
            ],
            'servers' => [
                [
                    'url' => '/api',
                    'description' => 'API Server'
                ]
            ],
            'paths' => array_merge(
                SwitchDocumentation::getPaths(),
                BVIDocumentation::getPaths(),
                UserDocumentation::getPaths()
            ),
            'components' => [
                'schemas' => array_merge(
                    SwitchDocumentation::getSchemas(),
                    BVIDocumentation::getSchemas(),
                    UserDocumentation::getSchemas()
                ),
                'securitySchemes' => [
                    'ApiKeyAuth' => [
                        'type' => 'apiKey',
                        'in' => 'header',
                        'name' => 'X-API-Key',
                        'description' => 'API Key for authentication'
                    ]
                ]
            ],
            'security' => [
                ['ApiKeyAuth' => []]
            ]
        ];
    }
}
