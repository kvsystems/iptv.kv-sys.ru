<?php
namespace Evie\Rest\System\OpenApi;

class DefaultOpenApiDefinition  {

    protected $root = [
        "openapi" => "3.0.0",
        "info" => [
            "title" => "EVIE-REST-API",
            "version" => "1.0.0",
        ],
        "paths" => [],
        "components" => [
            "schemas" => [
                "Category" => [
                    "type" => "object",
                    "properties" => [
                        "id" => [
                            "type" => "integer",
                            "format" => "int64",
                        ],
                        "name" => [
                            "type" => "string",
                        ],
                    ],
                ],
                "Tag" => [
                    "type" => "object",
                    "properties" => [
                        "id" => [
                            "type" => "integer",
                            "format" => "int64",
                        ],
                        "name" => [
                            "type" => "string",
                        ],
                    ],
                ],
            ],
        ],
    ];

}