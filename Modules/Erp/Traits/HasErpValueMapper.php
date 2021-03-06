<?php

namespace Modules\Erp\Traits;

use Exception;
use Carbon\Carbon;
use Illuminate\Bus\Batch;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Modules\Erp\Entities\ErpImport;
use Modules\Product\Entities\Product;
use Modules\Attribute\Entities\Attribute;
use Modules\Product\Entities\ProductImage;
use Modules\Product\Entities\ProductAttribute;
use Modules\Attribute\Entities\AttributeOption;
use Modules\Core\Entities\Channel;
use Modules\Core\Entities\Store;
use Modules\Core\Exceptions\SlugCouldNotBeGenerated;
use Modules\Erp\Entities\ErpImportDetail;
use Modules\Erp\Jobs\Mapper\ErpMigrateVariantJob;
use Modules\Erp\Jobs\Mapper\ErpMigrateVisibilityUpdateJob;
use Modules\Inventory\Entities\CatalogInventory;
use Modules\Erp\Jobs\Mapper\ErpMigratorJob;
use Modules\Inventory\Entities\CatalogInventoryItem;
use Modules\Product\Entities\AttributeConfigurableProduct;
use Modules\Product\Entities\AttributeOptionsChildProduct;
use Modules\Product\Entities\ProductAttributeString;

trait HasErpValueMapper
{
    public function importAll(): void
    {
        try
        {
            $erp_details = ErpImportDetail::whereErpImportId(2)->whereJsonContains("value->webAssortmentWeb_Active", true)->whereJsonContains("value->webAssortmentWeb_Setup", "SR")->get();
            $chunked = $erp_details->chunk(100);
            foreach ( $chunked as $chunk )
            {
                foreach ( $chunk as $detail )
                {
                    if ( $detail->status == 1 ) continue;
                    if ( $detail->value["webAssortmentWeb_Active"] == false ) continue;
                    if ( $detail->value["webAssortmentWeb_Setup"] != "SR" ) continue;
                    ErpMigratorJob::dispatch($detail)->onQueue("erp");
                }
            }
        }
        catch ( Exception $exception )
        {
            throw $exception;
        }
    }

    private function createOption(object $erp_product_iteration): void
    {
        try
        {
            $data = [
                "attribute_id" => $this->getAttributeId("color"),
                "name" => $erp_product_iteration->value["webAssortmentColor_Description"],
                "code" => $erp_product_iteration->value["webAssortmentColor_Code"]
            ];
            $match = $data;
            unset($match["name"]);
            if ( !empty($data["name"]) || !empty($data["code"]) ) AttributeOption::updateOrCreate($match,$data);
        }
        catch ( Exception $exception )
        {
            throw $exception;
        }
    }

    private function createAttributeValue(object $product, object $erp_product_iteration, bool $ean_code_value = true, int $visibility = 8, mixed $variant = null): void
    {
        try
        {
            $ean_code = $this->getDetailCollection("eanCodes", $erp_product_iteration->sku);
            $variants = $this->getDetailCollection("productVariants", $erp_product_iteration->sku);

            if ( $ean_code_value ) {
                $pluck_code = $this->getValue($variants)->first()["code"];
                $ean_code_value = $this->getValue($ean_code)->where("variantCode", $pluck_code)->first()["crossReferenceNo"] ?? "";
            } else {
                $ean_code_value = $this->getValue($ean_code)->first()["crossReferenceNo"] ?? "";
            }

            $description_value = $this->getDetailCollection("productDescriptions", $erp_product_iteration->sku);
            $description = ($description_value->count() > 0) ? json_decode($this->getValue($description_value)->first(), true)["description"] ?? "" : "";

            // get price for specific product
            $price = $this->getDetailCollection("salePrices", $erp_product_iteration->sku);
            $default_price_data = [
                "unitPrice" => 0.0,
                "startingDate" => "",
                "endingDate" => ""
            ];
            if ($product->type == "simple") $this->storeScopeWiseValue($price, $product);
            $price_value = ($price->count() > 0) ? $this->getValue($price)->where("currencyCode", "")->where("salesCode", "WEB")->first() ?? $default_price_data : $default_price_data;

            // Condition for invalid date/times
            $max_time = strtotime("2030-12-28");
            $start_time = abs(strtotime($price_value["startingDate"]));
            $end_time = abs(strtotime($price_value["endingDate"]));

            $start_time = $start_time < $max_time ? $start_time : $max_time - 1;
            $end_time = $end_time < $max_time ? $end_time : $max_time;

            $attribute_data = [
                [
                    "attribute_id" => $this->getAttributeId("name"),
                    "value" => $erp_product_iteration->value["description"]
                ],
                [
                    "attribute_id" => $this->getAttributeId("price"),
                    "value" => ($product->type == "simple") ? $price_value["unitPrice"] : "",
                ],
                [
                    "attribute_id" => $this->getAttributeId("cost"),
                    "value" => ($product->type == "simple") ? $price_value["unitPrice"] : "",
                ],
                [
                    "attribute_id" => $this->getAttributeId("special_from_date"),
                    "value" => ($product->type == "simple") ? Carbon::parse(date("Y-m-d", $start_time)) : "",
                ],
                [
                    "attribute_id" => $this->getAttributeId("special_to_date"),
                    "value" => ($product->type == "simple") ? Carbon::parse(date("Y-m-d", $end_time)) : "",
                ],
                [
                    "attribute_id" => $this->getAttributeId("tax_class_id"),
                    "value" => 1,
                ],
                [
                    "attribute_id" => $this->getAttributeId("visibility"),
                    "value" => $visibility,
                ],
                [
                    "attribute_id" => $this->getAttributeId("has_weight"),
                    "value" => 4,
                ],
                [
                    "attribute_id" => $this->getAttributeId("description"),
                    "value" => empty($description) ? $erp_product_iteration->value["description"] : $description,
                ],
                [
                    "attribute_id" => $this->getAttributeId("short_description"),
                    "value" => Str::limit($description, 100),
                ],
                [
                    "attribute_id" => $this->getAttributeId("url_key"),
                    "value" => $this->createSlug($erp_product_iteration->value["description"]),
                ],
                [
                    "attribute_id" => $this->getAttributeId("meta_keywords"),
                    "value" => empty($description) ? $erp_product_iteration->value["description"] : $description,
                ],
                [
                    "attribute_id" => $this->getAttributeId("meta_title"),
                    "value" => $erp_product_iteration->value["description"],
                ],
                [
                    "attribute_id" => $this->getAttributeId("meta_description"),
                    "value" => empty($description) ? $erp_product_iteration->value["description"] : $description,
                ],
                [
                    "attribute_id" => $this->getAttributeId("status"),
                    "value" => 1,
                ],
                [
                    "attribute_id" => $this->getAttributeId("color"),
                    "value" => $this->getOptionValue($product, $variant, "color"),
                ],
                [
                    "attribute_id" => $this->getAttributeId("size"),
                    "value" => $this->getOptionValue($product, $variant, "size"),
                ],
                [
                    "attribute_id" => $this->getAttributeId("features"),
                    "value" => $this->getAttributeValue($product, $erp_product_iteration ,"Features" ),
                ],
                [
                    "attribute_id" => $this->getAttributeId("size-and-care"),
                    "value" => $this->getAttributeValue($product, $erp_product_iteration ,"Size and care" ),
                ],
                [
                    "attribute_id" => $this->getAttributeId("ean-code"),
                    "value" => $ean_code_value,
                ],
            ];

            foreach ( $attribute_data as $attributeData )
            {
                if (empty($attributeData["value"])) continue;
                $attribute = Attribute::find($attributeData["attribute_id"]);
                $attribute_type = config("attribute_types")[$attribute->type ?? "string"];
                $value = $attribute_type::create(["value" => $attributeData["value"]]);

                $product_attribute_data = [
                    "attribute_id" => $attribute->id,
                    "product_id"=> $product->id,
                    "value_type" => $attribute_type,
                    "value_id" => $value->id,
                    "scope" => "website",
                    "scope_id" => 1
                ];
                $match = $product_attribute_data;
                unset($match["value_id"]);

                ProductAttribute::updateOrCreate($match, $product_attribute_data);
            }

        }
        catch ( Exception $exception )
        {
            throw $exception;
        }
    }

    public function createSlug(string $title, int $id = 0): string
    {
       try
       {
            // Slugify
            $slug = Str::slug($title);
            $original_slug = $slug;

            // Throw Error if slug could not be generated
            if ($slug == "") throw new SlugCouldNotBeGenerated();

            // Get any that could possibly be related.
            // This cuts the queries down by doing it once.
            $allSlugs = $this->getRelatedSlugs($slug, $id);

            // If we haven't used it before then we are all good.
            if (!$allSlugs->contains('value', $slug)) return $slug;

            //if used,then count them
            $count = $allSlugs->count();

            // Loop through generated slugs
            while ($this->checkIfSlugExist($slug, $id) && $slug != "") {
                $slug = "{$original_slug}-{$count}";
                $count++;
            }
       }
       catch ( Exception $exception )
       {
           throw $exception;
       }

        // Finally return Slug
        return $slug;
    }

    private function getRelatedSlugs(string $slug, int $id = 0): object
    {
        return ProductAttributeString::whereRaw("value RLIKE '^{$slug}(-[0-9]+)?$'")
            ->where('id', '<>', $id)
            ->get();
    }

    private function checkIfSlugExist(string $slug, int $id = 0): ?bool
    {
        return ProductAttributeString::select('value')->where('value', $slug)
            ->where('id', '<>', $id)
            ->exists();
    }

    public function getAttributeId(string $slug): ?int
    {
        try
        {
            $attribute_id = Attribute::whereSlug($slug)->first()?->id;
        }
        catch ( Exception $exception )
        {
            throw $exception;
        }

        return $attribute_id;
    }

    private function storeScopeWiseValue(mixed $prices, object $product): bool
    {
        try
        {
            $price_data = $this->getValue($prices)->filter(function ($price_value) {
                return $price_value["salesCode"] == "WEB";
            })->map(function ($price_value) {

                // Condition for invalid date/times
                $max_time = strtotime("2030-12-28");
                $start_time = abs(strtotime($price_value["startingDate"]));
                $end_time = abs(strtotime($price_value["endingDate"]));

                $start_time = $start_time < $max_time ? $start_time : $max_time - 1;
                $end_time = $end_time < $max_time ? $end_time : $max_time;

                $channel_code = 'se';

                if ( isset( $price_value["currencyCode"] ) && $price_value["currencyCode"] != '' && strtolower( $price_value["currencyCode"] ) == 'eur' ) {
                    $channel_code = 'fl';
                }
                elseif ( isset( $price_value["currencyCode"] ) && $price_value["currencyCode"] != '' && strtolower( $price_value["currencyCode"] ) == 'eur') {
                    $channel_code = 'Eu';
                }

                return [
                    [
                        "attribute_id" => $this->getAttributeId("price"),
                        "value" => $price_value["unitPrice"],
                        "channel_code" => $channel_code
                    ],
                    [
                        "attribute_id" => $this->getAttributeId("cost"),
                        "value" => $price_value["unitPrice"],
                        "channel_code" => $channel_code
                    ],
                    [
                        "attribute_id" => $this->getAttributeId("special_from_date"),
                        "value" => Carbon::parse(date("Y-m-d", $start_time)),
                        "channel_code" => $channel_code
                    ],
                    [
                        "attribute_id" => $this->getAttributeId("special_to_date"),
                        "value" => Carbon::parse(date("Y-m-d", $end_time)),
                        "channel_code" => $channel_code
                    ]
                ];
            });
            foreach ( $price_data as $price )
            {
                foreach ($price as $attributeData)
                {
                    $channel_id  = $this->getChannel($attributeData["channel_code"])?->id ?? 1;
                    $attribute = Attribute::find($attributeData["attribute_id"]);
                    $attribute_type = config("attribute_types")[$attribute->type ?? "string"];
                    $value = $attribute_type::create(["value" => $attributeData["value"]]);

                    $product_attribute_data = [
                        "attribute_id" => $attribute->id,
                        "product_id"=> $product->id,
                        "value_type" => $attribute_type,
                        "value_id" => $value->id,
                        "scope" => "channel",
                        "scope_id" => $channel_id
                    ];
                    $match = $product_attribute_data;
                    unset($match["value_id"]);
                    ProductAttribute::updateOrCreate($match, $product_attribute_data);
                }
            }
        }
        catch ( Exception $exception )
        {
            throw $exception;
        }

        return true;
    }

    private function getChannel(string $code): ?object
    {
        try
        {
            $_channel = Channel::whereCode($code)->first();

            if( $_channel ) return $_channel;

            $data = [
                "name" => $code,
                "code" => $code,
                "hostname" => "{$code}.xyz.co",
                "description" => "{$code} channel",
                "website_id" => 1
            ];
            $match = $data;
            unset($match["description"], $match["name"], $match["code"]);
            $channel = Channel::updateOrCreate($match, $data);

            $store_data = [
                "name" => $code,
                "code" => $code,
                "channel_id" => $channel->id,
                "position" => 1
            ];
            $store_match = $store_data;
            unset($store_match["position"], $store_match["position"], $store_match["name"]);
            $store = Store::updateOrCreate($store_match, $store_data);
            $update_channel = $channel->update(["default_store_id" => $store->id]);
        }
        catch ( Exception $exception )
        {
            throw $exception;
        }

        return $channel;
    }

    private function getOptionValue(object $product, mixed $variant_iteration, string $attribute_slug): ?int
    {
        if ($product->type == "simple" && $product->parent_id) {
           $option = $this->getAttributeOptionValue($variant_iteration, $attribute_slug);
        }
        elseif ($product->type == "simple" && $product->parent_id == null) {
            $variant = $this->getValue($this->getDetailCollection("productVariants", $product->sku))->first();
            $option = $this->getAttributeOptionValue($variant, $attribute_slug);
        }

        return isset($option) ? $option : null;
    }

    private function getAttributeOptionValue(mixed $variant_iteration, string $attribute_slug): ?int
    {
        try
        {
            switch ($attribute_slug) {
                case 'color':
                    $attribute_option = AttributeOption::whereCode($variant_iteration["pfVerticalComponentCode"])->first();
                break;

                case 'size':
                    $data = [
                        "attribute_id" => $this->getAttributeId("size"),
                        "name" => $variant_iteration["pfHorizontalComponentCode"]
                    ];
                    if ( !empty($data["name"]) ) $attribute_option = AttributeOption::updateOrCreate($data);
                break;
            }
        }
        catch ( Exception $exception )
        {
            throw $exception;
        }

        return isset($attribute_option) ? $attribute_option?->id : null;
    }

    //This fn is for concat features and size and care values
    private function getAttributeValue(object $product, object $erp_product_iteration, string $attribute_name): string
    {
        try
        {
            $attribute_groups = $this->getDetailCollection("attributeGroups", $erp_product_iteration->sku);
            $attach_value = "";
            if ( $attribute_groups->count() > 1 )
            {
                $this->getValue($attribute_groups, function ($value) use (&$attach_value, $attribute_name) {
                    if ( $value["attributetype"] == $attribute_name ) if (!empty($value["description"])) $attach_value .= Str::finish($value["description"], ".\r\n ");
                });
            }
        }
        catch ( Exception $exception )
        {
            throw $exception;
        }
        return $attach_value;
    }

    // This fn will get value form erp detail value field
    private function getValue(mixed $values, callable $callback = null): mixed
    {
        try
        {
            $data = $values->map( function ($value) use($callback){
                if ( $callback ) $data = $callback($value->value);
                return ( $callback ) ? $data : $value->value;
            });
        }
        catch ( Exception $exception )
        {
            throw $exception;
        }

        return $data;
    }

    private function mapstoreImages( object $product, object $erp_product_iteration, array $variant = [] ): void
    {
        try
        {
            $product_images = $this->getDetailCollection("productImages", $erp_product_iteration->sku);
            $images = $this->getValue($product_images, function ($value) {
                return is_array($value) ? $value : json_decode($value, true) ?? $value;
            });

            if ( isset($variant["pfVerticalComponentCode"]) ) $images = $images->where("color_code", $variant["pfVerticalComponentCode"]);

            if ( $images->count() > 0 )
            {
                if ($product->type == "configurable")
                {
                    $configurable_images = [];
                    foreach ($images->groupBy("color_code") as $color_images) {
                        $configurable_images[] = $color_images->first();
                    }
                    $images = $configurable_images;
                }
                $position = 0;
                foreach( $images as $image )
                {
                    $data["path"] = $image["url"];
                    $data["position"] = $position;
                    $data["product_id"] = $product->id;
                    if ($position == 0) {
                        $type_ids = [1,2,3];
                    }
                    else {
                        $type_ids = 5;
                    }
                    $position++;
                    $product_image = ProductImage::updateOrCreate($data);
                    $product_image->types()->sync($type_ids);
                }
            }
        }
        catch ( Exception $exception )
        {
            throw $exception;
        }

    }

    // This fn create variants based on parent product
    private function createVariants( object $product, object $erp_product_iteration ): void
    {
        try
        {
            $variants = $this->getDetailCollection("productVariants", $erp_product_iteration->sku);
            $variants = $this->getValue($variants)->filter(fn ($variant) => $variant["webActive"] == true);
            if ( $variants->count() > 1 ) {
                $jobs = [];
                foreach ( $variants as $variant ) {
                    $jobs[] = new ErpMigrateVariantJob($product, $variant, $erp_product_iteration);
                }
                Bus::batch($jobs)->then(function (Batch $batch) use ($product) {
                    ErpMigrateVisibilityUpdateJob::dispatch($product)->onQueue('erp');
                })->allowFailures()->onQueue('erp')->dispatch();
            }
        }
        catch ( Exception $exception )
        {
            throw $exception;
        }
    }

    private function updateVisibility(object $product): void
    {
        try
        {
            $attribute_configurable_product = AttributeConfigurableProduct::whereProductId($product->id)->get();
            if ( $attribute_configurable_product->count() == 1)
            {
                $product->product_attributes->where("attribute_id", $this->getAttributeId("visibility"))->first()?->value->update(["value" => 8]);
                $variant_products = Product::whereIn("id", $product->variants->pluck("id")->toArray())->with(["product_attributes"])->get();
                foreach ( $variant_products as $variant_pro )
                {
                    $value_id = $variant_pro->product_attributes->where("attribute_id", $this->getAttributeId("visibility"))->first()?->value->id;
                    ProductAttributeString::whereId($value_id)->update(["value" => 5]);
                }
            }
            $attr_option_products = AttributeOptionsChildProduct::whereIn("product_id", $product->variants->pluck("id")->toArray())
                ->with(["attribute_option", "attribute_option.attribute", "variant_product.product_attributes"])
                ->get()
                ->filter(function ($filter_attribute_option) {
                    return $filter_attribute_option->attribute_option->attribute->id == $this->getAttributeId("color");
                })->groupBy("attribute_option_id");

            foreach ( $attr_option_products as $attr_option_product )
            {
                foreach ($attr_option_product->pluck("variant_product")->sortBy("id") as $key => $variant_product)
                {
                    if ($key == 0) continue;
                    $value_id = $variant_product->product_attributes->where("attribute_id", $this->getAttributeId("visibility"))->first()?->value->id;
                    $value = ProductAttributeString::whereId($value_id)->first()->update(["value" => 5]);
                }
            }
        }
        catch ( Exception $exception )
        {
            throw $exception;
        }
    }

    private function createInventory(object $product, object $erp_product_iteration, mixed $variant = null): void
    {
        try
        {
            $inventory = $this->getDetailCollection("webInventories", $erp_product_iteration->sku);

            if ( $inventory->count() > 1 )
            {
                $value = array_sum($this->getValue($inventory)->pluck("Inventory")->toArray());
                if ( $variant ) $value = array_sum($this->getValue($inventory)->where("Code", $variant["code"])->pluck("Inventory")->toArray());

                $data = [
                    "quantity" => $value,
                    "use_config_manage_stock" => 1,
                    "product_id" => $product->id,
                    "website_id" => $product->website_id,
                    "manage_stock" =>  0,
                    "is_in_stock" => ($value > 0) ? 1 : 0,
                ];

                $match = [
                    "product_id" => $product->id,
                    "website_id" => $product->website_id
                ];

                $catalog_inventory = CatalogInventory::updateOrCreate($match, $data);
                $catalog_inventory_item_data = [
                    "catalog_inventory_id" => $catalog_inventory->id,
                    "event" => "ERP Addition",
                    "adjustment_type" => "addition",
                    "quantity" => $value
                ];
                $catalog_inventory_item_match = [
                    "catalog_inventory_id" => $catalog_inventory->id,
                    "quantity" => $value
                ];
                CatalogInventoryItem::updateOrCreate($catalog_inventory_item_match, $catalog_inventory_item_data);
            }
        }
        catch ( Exception $exception )
        {
            throw $exception;
        }
    }

    private function getDetailCollection(string $slug, string $sku): Collection
    {
        return ErpImport::where("type", $slug)->first()->erp_import_details()->where("sku", $sku)->get();
    }
}
