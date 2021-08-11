<?php 
return [
    "properties" =>  [
        "id" =>  [
            "type" => "long"
        ],
        "sku" =>  [
            "type" => "text",
            "fields" =>  [
                "keyword" =>  [
                    "type" => "keyword",
                    "ignore_above" => 256
                ]
            ]
        ],
        "type" =>  [
            "type" => "text",
            "fields" =>  [
                "keyword" =>  [
                    "type" => "keyword",
                    "ignore_above" => 256
                ]
            ]
        ],
        "attribute_set_id" =>  [
            "type" => "long"
        ],
        "status" =>  [
            "type" => "long"
        ],
        "created_at" =>  [
            "type" => "date"
        ],
        "updated_at" =>  [
            "type" => "date"
        ],
        "catalog_inventories"=> [
            "properties"=> [
                "created_at"=> [
                    "type"=> "date"
                ],
                "id"=> [
                    "type"=> "long"
                ],
                "is_in_stock"=> [
                    "type"=> "long"
                ],
                "manage_stock"=> [
                    "type"=> "long"
                ],
                "product_id"=> [
                    "type"=> "long"
                ],
                "quantity"=> [
                    "type"=> "text",
                    "fields"=> [
                        "keyword"=> [
                            "type"=> "keyword",
                            "ignore_above"=> 256
                        ]
                    ]
                ],
                "updated_at"=> [
                    "type"=> "date"
                ],
                "use_config_manage_stock"=> [
                    "type"=> "long"
                ],
                "website_id"=> [
                    "type"=> "long"
                ]
            ]
        ],
        "categories"=> [
            "properties"=> [
                "_lft"=> [
                    "type"=> "long"
                ],
                "_rgt"=> [
                    "type"=> "long"
                ],
                "created_at"=> [
                    "type"=> "date"
                ],
                "id"=> [
                    "type"=> "long"
                ],
                "position"=> [
                    "type"=> "long"
                ],
                "updated_at"=> [
                    "type"=> "date"
                ],
                "values"=> [
                    "properties"=> [
                        "attribute"=> [
                            "type"=> "text",
                            "fields"=> [
                                "keyword"=> [
                                    "type"=> "keyword",
                                    "ignore_above"=> 256
                                ]
                            ]
                        ],
                        "category_id"=> [
                            "type"=> "long"
                        ],
                        "created_at"=> [
                            "type"=> "date"
                        ],
                        "id"=> [
                            "type"=> "long"
                        ],
                        "scope"=> [
                            "type"=> "text",
                            "fields"=> [
                                "keyword"=> [
                                    "type"=> "keyword",
                                    "ignore_above"=> 256
                                ]
                            ]
                        ],
                        "scope_id"=> [
                            "type"=> "long"
                        ],
                        "updated_at"=> [
                            "type"=> "date"
                        ],
                        "value"=> [
                            "type"=> "text",
                            "fields"=> [
                                "keyword"=> [
                                    "type"=> "keyword",
                                    "ignore_above"=> 256
                                ]
                            ]
                        ]
                    ]
                ],
                "website_id"=> [
                    "type"=> "long"
                ]
            ]
        ],
        "product_attributes"=> [
            "type"=> "nested",
            "properties"=> [
                "attribute"=> [
                    "properties"=> [
                        "attribute_group_id"=> [
                            "type"=> "long"
                        ],
                        "attribute_options"=> [
                            "properties"=> [
                                "attribute_id"=> [
                                    "type"=> "long"
                                ],
                                "code"=> [
                                    "type"=> "text",
                                    "fields"=> [
                                        "keyword"=> [
                                            "type"=> "keyword",
                                            "ignore_above"=> 256
                                        ]
                                    ]
                                ],
                                "created_at"=> [
                                    "type"=> "date"
                                ],
                                "id"=> [
                                    "type"=> "long"
                                ],
                                "is_default"=> [
                                    "type"=> "long"
                                ],
                                "name"=> [
                                    "type"=> "text",
                                    "fields"=> [
                                        "keyword"=> [
                                            "type"=> "keyword",
                                            "ignore_above"=> 256
                                        ]
                                    ]
                                ],
                                "position"=> [
                                    "type"=> "long"
                                ],
                                "updated_at"=> [
                                    "type"=> "date"
                                ]
                            ]
                        ],
                        "comparable_on_storefront"=> [
                            "type"=> "long"
                        ],
                        "created_at"=> [
                            "type"=> "date"
                        ],
                        "id"=> [
                            "type"=> "long"
                        ],
                        "is_required"=> [
                            "type"=> "long"
                        ],
                        "is_searchable"=> [
                            "type"=> "long"
                        ],
                        "is_unique"=> [
                            "type"=> "long"
                        ],
                        "is_user_defined"=> [
                            "type"=> "long"
                        ],
                        "is_visible_on_storefront"=> [
                            "type"=> "long"
                        ],
                        "name"=> [
                            "type"=> "text",
                            "fields"=> [
                                "keyword"=> [
                                    "type"=> "keyword",
                                    "ignore_above"=> 256
                                ]
                            ]
                        ],
                        "position"=> [
                            "type"=> "long"
                        ],
                        "scope"=> [
                            "type"=> "text",
                            "fields"=> [
                                "keyword"=> [
                                    "type"=> "keyword",
                                    "ignore_above"=> 256
                                ]
                            ]
                        ],
                        "search_weight"=> [
                            "type"=> "long"
                        ],
                        "slug"=> [
                            "type"=> "text",
                            "fields"=> [
                                "keyword"=> [
                                    "type"=> "keyword",
                                    "ignore_above"=> 256
                                ]
                            ]
                        ],
                        "type"=> [
                            "type"=> "text",
                            "fields"=> [
                                "keyword"=> [
                                    "type"=> "keyword",
                                    "ignore_above"=> 256
                                ]
                            ]
                        ],
                        "type_validation"=> [
                            "type"=> "text",
                            "fields"=> [
                                "keyword"=> [
                                    "type"=> "keyword",
                                    "ignore_above"=> 256
                                ]
                            ]
                        ],
                        "updated_at"=> [
                            "type"=> "date"
                        ],
                        "use_in_layered_navigation"=> [
                            "type"=> "long"
                        ],
                        "validation"=> [
                            "type"=> "text",
                            "fields"=> [
                                "keyword"=> [
                                    "type"=> "keyword",
                                    "ignore_above"=> 256
                                ]
                            ]
                        ]
                    ]
                ],
                "value"=> [
                    "type"=> "integer",
                    "ignore_malformed"=> true
                ],
                "string_value" =>  [
                    "type" => "text",
                    "fields" =>  [
                        "keyword" =>  [
                            "type" => "keyword",
                            "ignore_above" => 256
                        ]
                    ]
                ],
                "boolean_value" =>  [
                    "type" => "long"
                ],
                "date_value" =>  [
                    "type" => "date",
                    "format"=> "yyyy-MM-dd HH:mm:ss||yyyy-MM-dd"
                ],
                "integer_value" =>  [
                    "type" => "date"
                ],
                "decimal_value" =>  [
                    "type" => "double"
                ],
                "scope"=> [
                    "type"=> "text",
                    "fields"=> [
                        "keyword"=> [
                            "type"=> "keyword",
                            "ignore_above"=> 256
                        ]
                    ]
                ],
                "scope_id"=> [
                    "type"=> "long"
                ]
            ]
        ],
        "product_images"=> [
            "properties"=> [
                "created_at"=> [
                    "type"=> "date"
                ],
                "id"=> [
                    "type"=> "long"
                ],
                "main_image"=> [
                    "type"=> "long"
                ],
                "path"=> [
                    "type"=> "text",
                    "fields"=> [
                        "keyword"=> [
                            "type"=> "keyword",
                            "ignore_above"=> 256
                        ]
                    ]
                ],
                "position"=> [
                    "type"=> "long"
                ],
                "product_id"=> [
                    "type"=> "long"
                ],
                "small_image"=> [
                    "type"=> "long"
                ],
                "small_image_url"=> [
                    "type"=> "text",
                    "fields"=> [
                        "keyword"=> [
                            "type"=> "keyword",
                            "ignore_above"=> 256
                        ]
                    ]
                ],
                "thumbnail"=> [
                    "type"=> "long"
                ],
                "thumbnail_url"=> [
                    "type"=> "text",
                    "fields"=> [
                        "keyword"=> [
                            "type"=> "keyword",
                            "ignore_above"=> 256
                        ]
                    ]
                ],
                "updated_at"=> [
                    "type"=> "date"
                ]
            ]
        ]
    ],

];
