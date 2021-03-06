<?php

namespace Modules\Product\Repositories\StoreFront;

use Exception;
use Illuminate\Support\Facades\Storage;
use Modules\Attribute\Entities\Attribute;
use Illuminate\Validation\ValidationException;
use Modules\Attribute\Entities\AttributeSet;
use Modules\Category\Entities\Category;
use Modules\Category\Entities\CategoryValue;
use Modules\Category\Exceptions\CategoryNotFoundException;
use Modules\Category\Repositories\StoreFront\CategoryRepository;
use Modules\Category\Transformers\StoreFront\CategoryResource;
use Modules\Core\Entities\Channel;
use Modules\Core\Entities\Store;
use Modules\Core\Entities\Website;
use Modules\Core\Facades\PriceFormat;
use Modules\Core\Repositories\BaseRepository;
use Modules\Product\Entities\AttributeOptionsChildProduct;
use Modules\Product\Entities\Product;
use Modules\Product\Entities\ProductAttribute;
use Modules\Product\Exceptions\ProductNotFoundIndividuallyException;
use Modules\Product\Repositories\ProductSearchRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Modules\Page\Repositories\StoreFront\PageRepository;
use Modules\Product\Repositories\ProductFormatRepository;
use Modules\Product\Repositories\StoreFront\ProductFormatRepository as StoreFrontProductFormatRepository;
use Modules\Tax\Facades\TaxPrice;

class ProductRepository extends BaseRepository
{
    public $search_repository, $categoryRepository, $page_groups, $config_fields, $count, $mainAttribute, $nested_product = [], $config_products = [], $pageRepository, $final_product_val = [], $product_format_repo;

    public function __construct(Product $product, ProductSearchRepository $search_repository, CategoryRepository $categoryRepository, PageRepository $pageRepository, StoreFrontProductFormatRepository $product_format_repo)
    {
        $this->model = $product;
        $this->search_repository = $search_repository;
        $this->categoryRepository = $categoryRepository;
        $this->model_name = "Product";
        $this->page_groups = ["hero_banner", "usp_banner_1", "usp_banner_2", "usp_banner_3"];
        $this->config_fields = config("category.attributes");
        $this->pageRepository = $pageRepository;
        $this->product_format_repo = $product_format_repo;
        $this->count = 0;
        $this->mainAttribute = [ "name", "sku", "type", "url_key", "quantity", "visibility", "price", "special_price", "special_from_date", "special_to_date", "short_description", "description", "meta_title", "meta_keywords", "meta_description", "new_from_date", "new_to_date", "animated_image", "disable_animation"];
    }

    public function show(object $request, mixed $identifier, ?int $parent_identifier = null): ?array
    {
        try
        {
            $coreCache = $this->getCoreCache($request);

            $relations = [
                "catalog_inventories",
                "images",
                "images.types",
                "categories",
                "categories.values",
                "product_attributes",
                "product_attributes.attribute",
                "attribute_configurable_products",
                "attribute_configurable_products.attribute",
                "variants",
                "parent.variants.attribute_options_child_products.attribute_option",
                "parent.variants.product_attributes",
                "variants.attribute_configurable_products",
                "variants.attribute_configurable_products.attribute",
                "variants.attribute_configurable_products.attribute_option",
                "attribute_options_child_products"

            ];
            if(!$parent_identifier) {
                $product_attr = ProductAttribute::query()->with(["value"]);
                $attribute_id = Attribute::whereSlug("url_key")->first()?->id;
                $product_attr = $product_attr->whereAttributeId($attribute_id)->get()->filter( fn ($attr_product) => $attr_product->value->value == $identifier)->first();
            
                if(isset($product_attr->scope)) {
                    if(in_array($product_attr->scope, ["channel", "website"])) $this->checkScopeForUrlKey($product_attr?->product_id, $attribute_id, $coreCache, $product_attr?->scope);
                    if($product_attr->scope == "store" && $product_attr?->scope_id != $coreCache->store->id) throw new ProductNotFoundIndividuallyException();
                }
                
                $product = Product::whereId($identifier)
                ->orWhere("id", $product_attr?->product_id)
                ->whereWebsiteId($coreCache->website->id)
                ->whereStatus(1)->with($relations)->firstOrFail();
            }
            else {
                $product = Product::whereId($identifier)
                ->whereParentId($parent_identifier)
                ->whereWebsiteId($coreCache->website->id)
                ->whereStatus(1)->with($relations)->firstOrFail();
            }

            $channel_status = $product->channels()->whereChannelId($coreCache->store?->channel_id)->first();
            if($channel_status) throw new ProductNotFoundIndividuallyException();

            $cache_name = "product_details_{$product->id}_{$coreCache->channel->id}_{$coreCache->store->id}";

            $product_details = json_decode(Redis::get($cache_name));

            if(!$product_details) {
                $product_details = $this->productDetail($request, $product, $coreCache, $parent_identifier);
                Redis::set($cache_name, json_encode($product_details));
            }
            else  {
                $product_details = collect($product_details)->toArray();
            }

            
            $this->nested_product = [];
            $this->config_products = [];
            $this->final_product_val = [];

            if ( $product->type == "configurable" || ($product->type == "simple" && isset($product->parent_id))) {
                $product_details["is_group_by_attribute"] = $this->checkGroupByAttributes($product);
                
                $this->getConfigurableAttributes($product, $coreCache->store);
                $product_details["configurable_products"] = $this->final_product_val;
            }
        }
        catch(Exception $exception)
        {
            throw $exception;
        }

        return $product_details;
    }

    public function checkScopeForUrlKey(?int $product_id, int $attribute_id, object $coreCache, ?string $custom_scope): void
    {
        try
        {
            if($custom_scope == "channel") {
                $scope_product_attr = ProductAttribute::whereAttributeId($attribute_id)->whereProductId($product_id)->whereScope("store")->whereScopeId($coreCache->store->id)->first();
                if($scope_product_attr) throw new ProductNotFoundIndividuallyException();
            }
            if($custom_scope == "website") {
                $scope_product_attr = ProductAttribute::whereAttributeId($attribute_id)->whereProductId($product_id)->whereScope("channel")->whereScopeId($coreCache->channel->id)->first();
                if($scope_product_attr) throw new ProductNotFoundIndividuallyException();
                else $this->checkScopeForUrlKey($product_id, $attribute_id, $coreCache, "channel");
            }
        }
        catch(Exception $exception)
        {
            throw $exception;
        }
    }

    public function productDetail(object $request, object $product, object $coreCache, ?int $parent_identifier = null): ?array
    {
        try
        {
            $store = $coreCache->store;

            $attribute_set = AttributeSet::where("id", $product->attribute_set_id)->first();
            $attributes = $attribute_set->attribute_groups->map(function($attributeGroup){
                return $attributeGroup->attributes;
            })->flatten(1);
            $data = [];

            foreach($attributes as $attribute)
            {
                if(in_array($attribute->slug, ["sku", "status", "quantity_and_stock_status", "has_weight", "weight", "cost", "category_ids"])) continue;

                $match = [
                    "scope" => "store",
                    "scope_id" => $store->id,
                    "attribute_id" => $attribute->id
                ];
                $values = $product->value($match);

                if ( !$parent_identifier && $attribute->slug == "visibility" && $values?->name == "Not Visible Individually" ) throw new ProductNotFoundIndividuallyException();

                if($attribute->slug == "gallery") {
                    $data = $this->getProductImages($product, $data);
                    continue;
                }

                if($attribute->slug == "component") {
                    $product_builders = $this->getProductComponents($product, $store, $match);
                    $data["product_builder"] = $product_builders ? $this->pageRepository->getComponent($coreCache, $product_builders) : [];
                    continue;
                }

                if($product->type == "configurable" && ($attribute->slug == "price" ||  $attribute->slug == "special_price")) {
                    $first_variant = $product->variants->first();
                    $data[$attribute->slug] = $first_variant->value($match);
                    continue;
                }

                if(in_array($attribute->type, [ "select", "multiselect", "checkbox" ]))
                {
                    if ($values instanceof Collection) $data[$attribute->slug] = $values->pluck("name")->toArray();
                    else {
                        $data[$attribute->slug] = $values?->name;
                        if($attribute->slug == "tax_class_id") $data["tax_class"] = $values?->id;
                    }
                    continue;
                }

                if(!in_array($attribute->slug, $this->mainAttribute)) $data["attributes"][$attribute->slug] = $values;
                else $data[$attribute->slug] = $values;

                if(($attribute->type == "image" || $attribute->type == "file") && $values) $data[$attribute->slug] = Storage::url($values);

            }

            $fetched = $product->only(["id", "sku", "status", "website_id", "parent_id", "type"]);
            $inventory = $product->catalog_inventories()->select("quantity", "is_in_stock")->first()?->toArray();
            if(!$inventory) $inventory = [
                "quantity" => 0,
                "is_in_stock" => 0
            ];
            $fetched = array_merge($fetched, $inventory, $data);

            $fetched = $this->product_format_repo->getProductInFormat($fetched, $request, $store);

            if(isset($fetched["disable_animation"])  && isset($fetched["animated_image"])) $fetched = $this->getAnimatedImage($fetched);
        }
        catch (Exception $exception)
        {
            throw $exception;
        }

        return $fetched;
    }

    public function checkGroupByAttributes(object $product): ?int
    {
        try
        {
            if($product->parent_id) $product = $product->parent;
            $group_by_attributes = $product->attribute_configurable_products()->whereUsedInGrouping(1)->first();  
            $bool = $group_by_attributes ? 1 : 0;    
        }
        catch(Exception $exception)
        {
            throw $exception;
        }

        return $bool;
    }

    public function getConfigurableAttributes(object $product, object $store): void
    {
        try
        {
            $elastic_variant_products = $this->getConfigurableAttributesFromElasticSearch($product, $store);
            $this->config_products = $elastic_variant_products;

            $this->getVariations($elastic_variant_products);
            
        }
        catch(Exception $exception)
        {
            throw $exception;
        }
    }

    public function getProductComponents(object $product, object $store, array $match): object
    {
        try
        {
            $product_builders = $product->productBuilderValues()->whereScope("store")->whereScopeId($store->id)->get();
            if($product_builders->isEmpty()) $product_builders = $product->getBuilderParentValues($match);

            //fetch from parent product
            if($product_builders->isEmpty() && $product->parent_id) {
                $product_builders = $product->parent->productBuilderValues()->whereScope("store")->whereScopeId($store->id)->get();
                if($product_builders->isEmpty()) $product_builders = $product->parent->getBuilderParentValues($match);
            }
        }
        catch(Exception $exception)
        {
            throw $exception;
        }

        return $product_builders;
    }

    public function getProductImages(object $product, array $data): array
    {
        try
        {
            $data["image"] = $this->getBaseImage($product);

            $data["rollover_image"] = $this->getImages($product, "rollover_image");

            $data["gallery"] = $product->images()->wherehas("types", function($query) {
                $query->whereSlug("gallery");
            })->get()->map(function ($gallery) {
                return [
                    "url" => Storage::url($gallery->path),
                    "background_color" => $gallery->background_color
                ];
            })->toArray();
        }
        catch(Exception $exception)
        {
            throw $exception;
        }

        return $data;
    }

    public function getAnimatedImage(array $fetched): array
    {
        try
        {
            $fetched["animated_images"] = [
                "animated_image" => $fetched["animated_image"],
                "disable_animation" => $fetched["disable_animation"]
            ];
            unset($fetched["animated_image"], $fetched["disable_animation"] );
        }
        catch(Exception $exception)
        {
            throw $exception;
        }

        return $fetched;
    }

    public function getConfigurableAttributesFromElasticSearch(object $product, object $store): array
    {
        try
        {
            if(isset($product->parent_id)) $product = $product->parent;
            $variant_ids = $product->variants->pluck("id")->toArray();
            $elastic_fetched = [
                "_source" => ["show_configurable_attributes"],
                "size" => count($variant_ids),
                "query"=> [
                    "bool" => [
                        "must" => [
                            $this->search_repository->terms("id", $variant_ids)
                        ]
                    ]
                ],
            ];
            $elastic_data =  $this->search_repository->searchIndex($elastic_fetched, $store);
            $elastic_variant_products = isset($elastic_data["hits"]["hits"]) ? collect($elastic_data["hits"]["hits"])->pluck("_source.show_configurable_attributes")->flatten(1)->toArray() : [];
        }
        catch(Exception $exception)
        {
            throw $exception;
        }

        return $elastic_variant_products;
    }

    public function getVariations(array $elastic_variant_products, ?string $key = null)
    {
        try
        {
            $attribute_slugs = collect($elastic_variant_products)->pluck("attribute_slug")->unique()->values()->toArray();
            foreach($attribute_slugs as $attribute_slug)
            {
                $state = [];

                $attribute_values = collect($elastic_variant_products)->where("attribute_slug", $attribute_slug)->values()->toArray();
                $variations = collect($elastic_variant_products)->where("attribute_slug", "!=", $attribute_slug)->values()->toArray();
                //$count = collect($attribute_values)->unique("id")->count();

                $j = 0;
                foreach($attribute_values as $attribute_value)
                {
                    $append_key = $key ? "$key.{$attribute_slug}.{$j}" : "{$attribute_slug}.{$j}";
                    $product_val_array = [];
                    if(!isset($state[$attribute_value["id"]])) {
                        $state[$attribute_value["id"]] = true;
                        $product_val_array["value"] = $attribute_value["id"];
                        $product_val_array["label"] = $attribute_value["label"];
                        $product_val_array["code"] = $attribute_value["code"];
                        if($attribute_value["attribute_slug"] == "color") {
                            $visibility_item = collect($attribute_values)->where("visibility", 8)->where("id", $attribute_value["id"])->first();
                            $product_val_array["url_key"] = isset($visibility_item["url_key"]) ? $visibility_item["url_key"] : $attribute_value["url_key"];
                            $product_val_array["product_id"] = isset($visibility_item["product_id"]) ? $visibility_item["product_id"] : $attribute_value["product_id"];                          
                            $product_val_array["parent_id"] = isset($visibility_item["parent_id"]) ? $visibility_item["parent_id"] : $attribute_value["parent_id"];
                            $product_val_array["image"] = isset($visibility_item["image"]) ? $visibility_item["image"] : $attribute_value["image"];
                        }
                        if(count($attribute_slugs) == 1) {
                            $fake_array = array_merge($this->nested_product, [$attribute_value["attribute_slug"] => $attribute_value["id"]]);
                            $dot_product = collect($this->config_products)->where("attribute_combination", $fake_array)->first();
                            $product_val_array["product_id"] = isset($dot_product["product_id"]) ? $dot_product["product_id"] : 0;
                            $product_val_array["parent_id"] = isset($dot_product["parent_id"]) ? $dot_product["parent_id"] : 0;
                            $product_val_array["sku"] = isset($dot_product["product_sku"]) ? $dot_product["product_sku"] : 0;
                            $product_val_array["stock_status"] = isset($dot_product["stock_status"]) ? $dot_product["stock_status"] : 0;
                            //if($count == ($j+1)) $this->nested_product = [];
                        }
                        setDotToArray($append_key, $this->final_product_val,  $product_val_array);
                        $j = $j + 1;
                        if(count($attribute_slugs) > 1) {
                            $this->nested_product[ $attribute_value["attribute_slug"] ] = $attribute_value["id"];
                            $this->getVariations($variations, "{$append_key}.variations");
                        }
                    }
                }
            }
        }
        catch(Exception $exception)
        {
            throw $exception;
        }
    }

    public function getBaseImage($product): ?array
    {
        try
        {
            $image = $product->images()->wherehas("types", function($query) {
                $query->whereSlug("base_image");
            })->first();
            if(!$image) $image = $product->images()->first();
            $path = $image ? Storage::url($image->path) : $image;
        }
        catch (Exception $exception)
        {
            throw $exception;
        }

        return [
            "url" => $path,
            "background_color" => $image?->background_color
        ];
    }

    public function getImages($product, $image_name): ?array
    {
        try
        {
            $image = $product->images()->wherehas("types", function($query) use($image_name) {
                $query->whereSlug($image_name);
            })->first();
            $path = $image ? Storage::url($image->path) : $image;
        }
        catch (Exception $exception)
        {
            throw $exception;
        }

        return [
            "url" => $path,
            "background_color" => $image?->background_color
        ];
    }

    public function getCategory(array $scope, string $category_slug): object
    {
        try
        {
            $category_value = CategoryValue::whereAttribute("slug")->whereValue($category_slug)->firstOrFail();
            $category = $category_value->category;

            if(!$this->categoryRepository->checkStatus($category, $scope)) throw new CategoryNotFoundException();
        }
        catch(Exception $exception)
        {
            throw $exception;
        }

        return $category;
    }

    public function getOptions(object $request, array $category_slugs): ?array
    {
        try
        {
            $category_ids = [];
            $coreCache = $this->getCoreCache($request);
            $scope = [
                "scope" => "store",
                "scope_id" => $coreCache->store->id
            ];

            $data = $this->categoryRepository->getNestedcategory($coreCache, $scope, $category_slugs, "productFilter");
            $category = $data["category"];

            $layout_type = $category->value($scope, "layout_type");
            if($layout_type && $layout_type == "multiple") {
                $all_categories = $category->value($scope, "categories");
                foreach($all_categories as $single_category) {
                    $category_data = Category::find($single_category);
                    if(!$category_data) continue;
                    $category_ids[] = $single_category;
                }
            }

            $category_ids[] = $category->id;


            $fetched = $this->search_repository->getFilterOptions($category_ids, $coreCache->store);
        }
        catch(Exception $exception)
        {
            throw $exception;
        }

        return $fetched;
    }

    public function categoryWiseProduct(object $request, array $category_slugs): ?array
    {
        try
        {
            $fetched = [];

            $coreCache = $this->getCoreCache($request);
            $scope = [
                "scope" => "store",
                "scope_id" => $coreCache->store->id
            ];

            $data = $this->categoryRepository->getNestedcategory($coreCache, $scope, $category_slugs, "productFetch");
            $category = $data["category"];

            $layout_type = $category->value($scope, "layout_type");
            if($layout_type && $layout_type == "multiple") {
                $all_categories = $category->value($scope, "categories");
                foreach($all_categories as $single_category) {
                    $category_data = Category::find($single_category);
                    if(!$category_data) continue;

                    $category_val = [
                        "id" => $category_data->id,
                        "slug" => $category_data->value($scope, "slug"),
                        "name" => $category_data->value($scope, "name")
                    ];
                    
                    $limit = $category->value($scope, "no_of_items");
                    $is_paginated = $category->value($scope, "pagination");

                    $elastic_products = $this->search_repository->getFilterProducts($request, $category_data->id, $coreCache->store, $limit, $is_paginated); 
                    $category_val = array_merge($category_val, $elastic_products);
                    $fetched["categories"][] = $category_val;
                }
            }
            else $fetched = $this->search_repository->getFilterProducts($request, $category->id, $coreCache->store);
        }
        catch (Exception $exception)
        {
            throw $exception;
        }

        return $fetched;
    }

    public function searchWiseProduct(object $request): ?array
    {
        try
        {
            $fetched = [];

            $coreCache = $this->getCoreCache($request);
            $scope = [
                "scope" => "store",
                "scope_id" => $coreCache->store->id
            ];

            $fetched = $this->search_repository->getSearchProducts($request, $coreCache->store);
        }
        catch (Exception $exception)
        {
            throw $exception;
        }

        return $fetched;
    }

}
