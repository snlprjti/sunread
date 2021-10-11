<?php
return [
    [
        "title" => "Feature",
        "slug" => "feature",
        "mainGroups" => [
            [
                "title" => "Section Settings",
                "slug" => "section_settings",
                "type" => "section",
                "groups" => [
                    [
                        "title" => "General",
                        "slug" => "general",
                        "hasChildren" => 1,
                        "attributes" => [
                            [
                                "title" => "Admin Title",
                                "slug" => "admin_title",
                                "hasChildren" => 0,
                                "type" => "text",
                                "provider" => "",
                                "pluck" => [],
                                "default" => "",
                                "options" => [],
                                "description" => "",
                                "conditions" => [],
                                "rules" => "",
                                "is_required" => 1
                            ],
                            [
                                "title" => "Status",
                                "slug" => "status",
                                "hasChildren" => 0,
                                "type" => "select",
                                "provider" => "",
                                "pluck" => [],
                                "default" => "",
                                "options" => [
                                    [ "value" => 1, "label" => "Enabled" ],
                                    [ "value" => 2, "label" => "Disabled" ],
                                ],
                                "description" => "",
                                "conditions" => [],
                                "rules" => "in:1,2",
                                "is_required" => 1
                            ]
                        ]
                    ],
                    [
                        "title" => "Background",
                        "slug" => "section_background",
                        "hasChildren" => 1,
                        "attributes" => [
                            [
                                "title" => "Background Color",
                                "slug" => "section_background_color",
                                "hasChildren" => 0,
                                "type" => "color_picker",
                                "provider" => "",
                                "pluck" => [],
                                "default" => "",
                                "options" => [],
                                "description" => "",
                                "conditions" => [],
                                "rules" => "",
                                "is_required" => 0
                            ],
                            [
                                "title" => "Background Image",
                                "slug" => "section_background_image",
                                "hasChildren" => 0,
                                "type" => "file",
                                "provider" => "",
                                "pluck" => [],
                                "default" => "",
                                "options" => [],
                                "description" => "",
                                "conditions" => [],
                                "rules" => "mimes:jpeg,jpg,bmp,png",
                                "is_required" => 1
                            ],
                            [
                                "title" => "Background Position",
                                "slug" => "section_background_position",
                                "hasChildren" => 0,
                                "type" => "select",
                                "provider" => "",
                                "pluck" => [],
                                "default" => "",
                                "options" => [
                                    [ "value" => "no-repeat;left top;;", "label" => "Left Top | no-repeat" ],
                                    [ "value" => "repeat;left top;;", "label" => "Left Top | repeat" ],
                                ],
                                "description" => "",
                                "conditions" => [],
                                "rules" => "",
                                "is_required" => 1
                            ],
                            [
                                "title" => "Background Size",
                                "slug" => "section_background_size",
                                "hasChildren" => 0,
                                "type" => "select",
                                "provider" => "",
                                "pluck" => [],
                                "default" => "",
                                "options" => [
                                    [ "value" => "auto", "label" => "Auto" ],
                                    [ "value" => "contain", "label" => "Contain" ],
                                    [ "value" => "cover", "label" => "Cover" ]
                                ],
                                "description" => "",
                                "conditions" => [],
                                "rules" => "",
                                "is_required" => 1
                            ],
                            [
                                "title" => "Background Video Link",
                                "slug" => "section_background_video_link",
                                "hasChildren" => 0,
                                "type" => "link",
                                "provider" => "",
                                "pluck" => [],
                                "default" => "",
                                "options" => [],
                                "description" => "",
                                "conditions" => [],
                                "rules" => "",
                                "is_required" => 1
                            ]
                        ]
                    ],
                    [
                        "title" => "Layout",
                        "slug" => "layout",
                        "hasChildren" => 1,
                        "attributes" => [
                            [
                                "title" => "Padding Top",
                                "slug" => "padding_top",
                                "hasChildren" => 0,
                                "type" => "text",
                                "provider" => "",
                                "pluck" => [],
                                "default" => "",
                                "options" => [],
                                "description" => "",
                                "conditions" => [],
                                "rules" => "",
                                "is_required" => 1
                            ],
                            [
                                "title" => "Padding Bottom",
                                "slug" => "padding_bottom",
                                "hasChildren" => 0,
                                "type" => "text",
                                "provider" => "",
                                "pluck" => [],
                                "default" => "",
                                "options" => [],
                                "description" => "",
                                "conditions" => [],
                                "rules" => "",
                                "is_required" => 1
                            ],
                            [
                                "title" => "Padding Left/Right",
                                "slug" => "padding_left_right",
                                "hasChildren" => 0,
                                "type" => "text",
                                "provider" => "",
                                "pluck" => [],
                                "default" => "",
                                "options" => [],
                                "description" => "",
                                "conditions" => [],
                                "rules" => "",
                                "is_required" => 1
                            ],
                        ]
                    ],
                    [
                        "title" => "Advanced",
                        "slug" => "section_advanced",
                        "hasChildren" => 1,
                        "attributes" => [
                            [
                                "title" => "Custom Classes",
                                "slug" => "section_custom_classes",
                                "hasChildren" => 0,
                                "type" => "text",
                                "provider" => "",
                                "pluck" => [],
                                "default" => "",
                                "options" => [],
                                "description" => "",
                                "conditions" => [],
                                "rules" => "",
                                "is_required" => 1
                            ],
                            [
                                "title" => "Custom ID",
                                "slug" => "section_custom_id",
                                "hasChildren" => 0,
                                "type" => "text",
                                "provider" => "",
                                "pluck" => [],
                                "default" => "",
                                "options" => [],
                                "description" => "",
                                "conditions" => [],
                                "rules" => "",
                                "is_required" => 1
                            ],
                        ]
                    ],

                ]
            ],
            [
                "title" => "Row Settings",
                "slug" => "row_settings",
                "type" => "row",
                "groups" => [
                    [
                        "title" => "Background",
                        "slug" => "row_background",
                        "hasChildren" => 1,
                        "attributes" => [
                            [
                                "title" => "Background Color",
                                "slug" => "row_background_color",
                                "hasChildren" => 0,
                                "type" => "color_picker",
                                "provider" => "",
                                "pluck" => [],
                                "default" => "",
                                "options" => [],
                                "description" => "",
                                "conditions" => [],
                                "rules" => "",
                                "is_required" => 1
                            ],
                            [
                                "title" => "Background Image",
                                "slug" => "row_background_image",
                                "hasChildren" => 0,
                                "type" => "file",
                                "provider" => "",
                                "pluck" => [],
                                "default" => "",
                                "options" => [],
                                "description" => "",
                                "conditions" => [],
                                "rules" => "mimes:jpeg,jpg,bmp,png",
                                "is_required" => 1
                            ],
                            [
                                "title" => "Background Position",
                                "slug" => "row_background_position",
                                "hasChildren" => 0,
                                "type" => "select",
                                "provider" => "",
                                "pluck" => [],
                                "default" => "",
                                "options" => [
                                    [ "value" => "no-repeat;left top;;", "label" => "Left Top | no-repeat" ],
                                    [ "value" => "repeat;left top;;", "label" => "Left Top | repeat" ],
                                ],
                                "description" => "",
                                "conditions" => [],
                                "rules" => "",
                                "is_required" => 1
                            ],
                            [
                                "title" => "Background Size",
                                "slug" => "row_background_size",
                                "hasChildren" => 0,
                                "type" => "select",
                                "provider" => "",
                                "pluck" => [],
                                "default" => "",
                                "options" => [
                                    [ "value" => "auto", "label" => "Auto" ],
                                    [ "value" => "contain", "label" => "Contain" ],
                                    [ "value" => "cover", "label" => "Cover" ]
                                ],
                                "description" => "",
                                "conditions" => [],
                                "rules" => "",
                                "is_required" => 1
                            ]
                        ]
                    ],
                    [
                        "title" => "Advanced",
                        "slug" => "row_advanced",
                        "hasChildren" => 1,
                        "attributes" => [
                            [
                                "title" => "Custom Classes",
                                "slug" => "row_custom_classes",
                                "hasChildren" => 0,
                                "type" => "text",
                                "provider" => "",
                                "pluck" => [],
                                "default" => "",
                                "options" => [],
                                "description" => "",
                                "conditions" => [],
                                "rules" => "",
                                "is_required" => 1
                            ],
                            [
                                "title" => "Custom ID",
                                "slug" => "row_custom_id",
                                "hasChildren" => 0,
                                "type" => "text",
                                "provider" => "",
                                "pluck" => [],
                                "default" => "",
                                "options" => [],
                                "description" => "",
                                "conditions" => [],
                                "rules" => "",
                                "is_required" => 1
                            ],
                        ]
                    ],
                ]
            ],
            [
                "title" => "Module",
                "slug" => "module",
                "type" => "module",
                "subGroups" => [
                    [
                        "title" => "Content",
                        "slug" => "content_module",
                        "column" => "12",
                        "groups" => [
                            [
                                "title" => "Content",
                                "slug" => "sub_content_module",
                                "hasChildren" => 1,
                                "attributes" => [
                                    [
                                        "title" => "Title",
                                        "slug" => "content_module_title",
                                        "hasChildren" => 0,
                                        "type" => "text",
                                        "provider" => "",
                                        "pluck" => [],
                                        "default" => "",
                                        "options" => [],
                                        "description" => "Enter top padding in <em>px</em>",
                                        "conditions" => [],
                                        "rules" => "",
                                        "is_required" => 0
                                    ],
                                    [
                                        "title" => "Heading Tag",
                                        "slug" => "heading_tag",
                                        "hasChildren" => 0,
                                        "type" => "select",
                                        "provider" => "",
                                        "pluck" => [],
                                        "default" => "",
                                        "options" => [
                                            [ "value" => "h1", "label" => "H1" ],
                                            [ "value" => "h2", "label" => "H2" ],
                                            [ "value" => "h3", "label" => "H3" ],
                                            [ "value" => "h4", "label" => "H4" ],
                                            [ "value" => "h5", "label" => "H5" ],
                                            [ "value" => "h6", "label" => "H6" ],
                                        ],
                                        "description" => "Enter bottom padding in <em>px</em>",
                                        "conditions" => [],
                                        "rules" => "in:h1,h2,h3,h4,h5,h6",
                                        "is_required" => 0
                                    ],
                                    [
                                        "title" => "Description",
                                        "slug" => "content_module_description",
                                        "hasChildren" => 0,
                                        "type" => "editor",
                                        "provider" => "",
                                        "pluck" => [],
                                        "default" => "",
                                        "options" => [],
                                        "description" => "",
                                        "conditions" => [],
                                        "rules" => "",
                                        "is_required" => 0
                                    ]
                                ]
                            ]
                        ]
                    ],
                    [
                        "title" => "Feature",
                        "slug" => "feature_module",
                        "column" => "12",
                        "groups" => [
                            [
                                "title" => "Feature",
                                "slug" => "sub_feature_module",
                                "hasChildren" => 1,
                                "attributes" => [
                                    [
                                        "title" => "Feature",
                                        "slug" => "feature_repeater",
                                        "hasChildren" => 1,
                                        "type" => "repeater",
                                        "conditions" => [],
                                        "description" => "",
                                        "rules" => "array",
                                        "is_required" => 0,
                                        "attributes" => [
                                            [
                                                [
                                                    "title" => "Icon",
                                                    "slug" => "feature_repeater_icon",
                                                    "hasChildren" => 0,
                                                    "type" => "file",
                                                    "provider" => "",
                                                    "pluck" => [],
                                                    "default" => "",
                                                    "options" => [],
                                                    "conditions" => [],
                                                    "description" => "mimes:jpeg,jpg,bmp,png",
                                                    "rules" => "",
                                                    "is_required" => 1
                                                ],
                                                [
                                                    "title" => "Title",
                                                    "slug" => "feature_repeater_title",
                                                    "hasChildren" => 0,
                                                    "type" => "text",
                                                    "provider" => "",
                                                    "pluck" => [],
                                                    "default" => "",
                                                    "options" => [],
                                                    "conditions" => [],
                                                    "description" => "",
                                                    "rules" => "",
                                                    "is_required" => 1
                                                ],
                                                [
                                                    "title" => "Description",
                                                    "slug" => "feature_repeater_description",
                                                    "hasChildren" => 0,
                                                    "type" => "textarea",
                                                    "provider" => "",
                                                    "pluck" => [],
                                                    "default" => "",
                                                    "options" => [],
                                                    "conditions" => [],
                                                    "description" => "",
                                                    "rules" => "",
                                                    "is_required" => 0
                                                ]
                                            ]
                                        ]
                                    ],
                                    [
                                        "title" => "Items per row",
                                        "slug" => "feature_items_per_row",
                                        "hasChildren" => 0,
                                        "type" => "select",
                                        "provider" => "",
                                        "pluck" => [],
                                        "default" => "",
                                        "options" => [
                                            [ "value" => 2, "label" => "Two" ],
                                            [ "value" => 3, "label" => "Three" ],
                                            [ "value" => 4, "label" => "Four" ]
                                        ],
                                        "conditions" => [],
                                        "description" => "",
                                        "rules" => "in:2,3,4",
                                        "is_required" => 1
                                    ],
        
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ]
    ],
    [
        "title" => "Leading Text",
        "slug" => "leading_text",
        "mainGroups" => [
            [
                "title" => "Section Settings",
                "slug" => "section_settings",
                "type" => "section",
                "groups" => [
                    [
                        "title" => "General",
                        "slug" => "general",
                        "hasChildren" => 1,
                        "attributes" => [
                            [
                                "title" => "Admin Title",
                                "slug" => "admin_title",
                                "hasChildren" => 0,
                                "type" => "text",
                                "provider" => "",
                                "pluck" => [],
                                "default" => "",
                                "options" => [],
                                "description" => "",
                                "conditions" => [],
                                "rules" => "",
                                "is_required" => 1
                            ],
                            [
                                "title" => "Status",
                                "slug" => "status",
                                "hasChildren" => 0,
                                "type" => "select",
                                "provider" => "",
                                "pluck" => [],
                                "default" => "",
                                "options" => [
                                    [ "value" => 1, "label" => "Enabled" ],
                                    [ "value" => 2, "label" => "Disabled" ],
                                ],
                                "description" => "",
                                "conditions" => [],
                                "rules" => "in:1,2",
                                "is_required" => 1
                            ]
                        ]
                    ],
                    [
                        "title" => "Background",
                        "slug" => "section_background",
                        "hasChildren" => 1,
                        "attributes" => [
                            [
                                "title" => "Background Color",
                                "slug" => "section_background_color",
                                "hasChildren" => 0,
                                "type" => "color_picker",
                                "provider" => "",
                                "pluck" => [],
                                "default" => "",
                                "options" => [],
                                "description" => "",
                                "conditions" => [],
                                "rules" => "",
                                "is_required" => 1
                            ],
                            [
                                "title" => "Background Image",
                                "slug" => "section_background_image",
                                "hasChildren" => 0,
                                "type" => "file",
                                "provider" => "",
                                "pluck" => [],
                                "default" => "",
                                "options" => [],
                                "description" => "",
                                "conditions" => [],
                                "rules" => "mimes:jpeg,jpg,bmp,png",
                                "is_required" => 1
                            ],
                            [
                                "title" => "Background Position",
                                "slug" => "section_background_position",
                                "hasChildren" => 0,
                                "type" => "select",
                                "provider" => "",
                                "pluck" => [],
                                "default" => "",
                                "options" => [
                                    [ "value" => "no-repeat;left top;;", "label" => "Left Top | no-repeat" ],
                                    [ "value" => "repeat;left top;;", "label" => "Left Top | repeat" ],
                                ],
                                "description" => "",
                                "conditions" => [],
                                "rules" => "",
                                "is_required" => 1
                            ],
                            [
                                "title" => "Background Size",
                                "slug" => "section_background_size",
                                "hasChildren" => 0,
                                "type" => "select",
                                "provider" => "",
                                "pluck" => [],
                                "default" => "",
                                "options" => [
                                    [ "value" => "auto", "label" => "Auto" ],
                                    [ "value" => "contain", "label" => "Contain" ],
                                    [ "value" => "cover", "label" => "Cover" ]
                                ],
                                "description" => "",
                                "conditions" => [],
                                "rules" => "",
                                "is_required" => 1
                            ],
                            [
                                "title" => "Background Video Link",
                                "slug" => "section_background_video_link",
                                "hasChildren" => 0,
                                "type" => "link",
                                "provider" => "",
                                "pluck" => [],
                                "default" => "",
                                "options" => [],
                                "description" => "",
                                "conditions" => [],
                                "rules" => "",
                                "is_required" => 1
                            ]
                        ]
                    ],
                    [
                        "title" => "Layout",
                        "slug" => "layout",
                        "hasChildren" => 1,
                        "attributes" => [
                            [
                                "title" => "Padding Top",
                                "slug" => "padding_top",
                                "hasChildren" => 0,
                                "type" => "text",
                                "provider" => "",
                                "pluck" => [],
                                "default" => "",
                                "options" => [],
                                "description" => "",
                                "conditions" => [],
                                "rules" => "",
                                "is_required" => 1
                            ],
                            [
                                "title" => "Padding Bottom",
                                "slug" => "padding_bottom",
                                "hasChildren" => 0,
                                "type" => "text",
                                "provider" => "",
                                "pluck" => [],
                                "default" => "",
                                "options" => [],
                                "description" => "",
                                "conditions" => [],
                                "rules" => "",
                                "is_required" => 1
                            ],
                            [
                                "title" => "Padding Left/Right",
                                "slug" => "padding_left_right",
                                "hasChildren" => 0,
                                "type" => "text",
                                "provider" => "",
                                "pluck" => [],
                                "default" => "",
                                "options" => [],
                                "description" => "",
                                "conditions" => [],
                                "rules" => "",
                                "is_required" => 1
                            ],
                        ]
                    ],
                    [
                        "title" => "Advanced",
                        "slug" => "section_advanced",
                        "hasChildren" => 1,
                        "attributes" => [
                            [
                                "title" => "Custom Classes",
                                "slug" => "section_custom_classes",
                                "hasChildren" => 0,
                                "type" => "text",
                                "provider" => "",
                                "pluck" => [],
                                "default" => "",
                                "options" => [],
                                "description" => "",
                                "conditions" => [],
                                "rules" => "",
                                "is_required" => 1
                            ],
                            [
                                "title" => "Custom ID",
                                "slug" => "section_custom_id",
                                "hasChildren" => 0,
                                "type" => "text",
                                "provider" => "",
                                "pluck" => [],
                                "default" => "",
                                "options" => [],
                                "description" => "",
                                "conditions" => [],
                                "rules" => "",
                                "is_required" => 1
                            ],
                        ]
                    ],

                ]
            ],
            [
                "title" => "Row Settings",
                "slug" => "row_settings",
                "type" => "row",
                "groups" => [
                    [
                        "title" => "Background",
                        "slug" => "row_background",
                        "hasChildren" => 1,
                        "attributes" => [
                            [
                                "title" => "Background Color",
                                "slug" => "row_background_color",
                                "hasChildren" => 0,
                                "type" => "color_picker",
                                "provider" => "",
                                "pluck" => [],
                                "default" => "",
                                "options" => [],
                                "description" => "",
                                "conditions" => [],
                                "rules" => "",
                                "is_required" => 1
                            ],
                            [
                                "title" => "Background Image",
                                "slug" => "row_background_image",
                                "hasChildren" => 0,
                                "type" => "file",
                                "provider" => "",
                                "pluck" => [],
                                "default" => "",
                                "options" => [],
                                "description" => "",
                                "conditions" => [],
                                "rules" => "mimes:jpeg,jpg,bmp,png",
                                "is_required" => 1
                            ],
                            [
                                "title" => "Background Position",
                                "slug" => "row_background_position",
                                "hasChildren" => 0,
                                "type" => "select",
                                "provider" => "",
                                "pluck" => [],
                                "default" => "",
                                "options" => [
                                    [ "value" => "no-repeat;left top;;", "label" => "Left Top | no-repeat" ],
                                    [ "value" => "repeat;left top;;", "label" => "Left Top | repeat" ],
                                ],
                                "description" => "",
                                "conditions" => [],
                                "rules" => "",
                                "is_required" => 1
                            ],
                            [
                                "title" => "Background Size",
                                "slug" => "row_background_size",
                                "hasChildren" => 0,
                                "type" => "select",
                                "provider" => "",
                                "pluck" => [],
                                "default" => "",
                                "options" => [
                                    [ "value" => "auto", "label" => "Auto" ],
                                    [ "value" => "contain", "label" => "Contain" ],
                                    [ "value" => "cover", "label" => "Cover" ]
                                ],
                                "description" => "",
                                "conditions" => [],
                                "rules" => "",
                                "is_required" => 1
                            ]
                        ]
                    ],
                    [
                        "title" => "Advanced",
                        "slug" => "row_advanced",
                        "hasChildren" => 1,
                        "attributes" => [
                            [
                                "title" => "Custom Classes",
                                "slug" => "row_custom_classes",
                                "hasChildren" => 0,
                                "type" => "text",
                                "provider" => "",
                                "pluck" => [],
                                "default" => "",
                                "options" => [],
                                "description" => "",
                                "conditions" => [],
                                "rules" => "",
                                "is_required" => 1
                            ],
                            [
                                "title" => "Custom ID",
                                "slug" => "row_custom_id",
                                "hasChildren" => 0,
                                "type" => "text",
                                "provider" => "",
                                "pluck" => [],
                                "default" => "",
                                "options" => [],
                                "description" => "",
                                "conditions" => [],
                                "rules" => "",
                                "is_required" => 1
                            ],
                        ]
                    ],
                ]
            ],
            [
                "title" => "Module",
                "slug" => "module",
                "type" => "module",
                "subGroups" => [
                    [
                        "title" => "Content",
                        "slug" => "content_module",
                        "column" => "12",
                        "groups" => [
                            [
                                "title" => "Content",
                                "slug" => "sub_content_module",
                                "hasChildren" => 1,
                                "attributes" => [
                                    [
                                        "title" => "Title",
                                        "slug" => "content_module_title",
                                        "hasChildren" => 0,
                                        "type" => "text",
                                        "provider" => "",
                                        "pluck" => [],
                                        "default" => "",
                                        "options" => [],
                                        "description" => "Enter top padding in <em>px</em>",
                                        "conditions" => [],
                                        "rules" => "",
                                        "is_required" => 0
                                    ],
                                    [
                                        "title" => "SubTitle",
                                        "slug" => "content_module_sub_title",
                                        "hasChildren" => 0,
                                        "type" => "text",
                                        "provider" => "",
                                        "pluck" => [],
                                        "default" => "",
                                        "options" => [],
                                        "description" => "Enter bottom padding in <em>px</em>",
                                        "conditions" => [],
                                        "rules" => "",
                                        "is_required" => 0
                                    ],
                                    [
                                        "title" => "Content",
                                        "slug" => "content_module_content",
                                        "hasChildren" => 0,
                                        "type" => "editor",
                                        "provider" => "",
                                        "pluck" => [],
                                        "default" => "",
                                        "options" => [],
                                        "description" => "",
                                        "conditions" => [],
                                        "rules" => "",
                                        "is_required" => 0
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ]
    ]
];
