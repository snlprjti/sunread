<?php

namespace Modules\Product\Repositories;

use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Modules\Core\Entities\Store;
use Modules\Core\Rules\ScopeRule;
use Modules\Core\Entities\Channel;
use Intervention\Image\Facades\Image;
use Modules\Product\Entities\Product;
use Illuminate\Support\Facades\Storage;
use Modules\Product\Entities\ImageType;
use Illuminate\Support\Facades\Validator;
use Modules\Attribute\Entities\Attribute;
use Modules\Product\Entities\ProductImage;
use Modules\Attribute\Entities\AttributeSet;
use Modules\Core\Repositories\BaseRepository;
use Illuminate\Validation\ValidationException;
use Modules\Product\Entities\ProductAttribute;
use Modules\Attribute\Entities\AttributeOption;
use Modules\Product\Rules\WebsiteWiseScopeRule;
use Modules\Inventory\Entities\CatalogInventory;
use Modules\Inventory\Jobs\LogCatalogInventoryItem;
use Modules\Attribute\Repositories\AttributeRepository;
use Modules\Product\Transformers\VariantProductResource;
use Modules\Attribute\Repositories\AttributeSetRepository;
use Modules\Core\Facades\SiteConfig;
use Modules\Product\Entities\AttributeConfigurableProduct;
use Modules\Product\Entities\Feature;
use Modules\Product\Transformers\ProductGalleryRescouce;
use Modules\Tax\Entities\ProductTaxGroup;

class ProductRepository extends BaseRepository
{
    protected $attribute, $attribute_set_repository, $channel_model, $store_model, $image_repository, $productBuilderRepository, $pageAttributeRepository;

    public function __construct(Product $product, ProductBuilderRepository $productBuilderRepository, AttributeSetRepository $attribute_set_repository, AttributeRepository $attribute_repository, ProductImageRepository $image_repository,Channel $channel_model, Store $store_model)
    {
        $this->model = $product;
        $this->model_key = "catalog.products";
        $this->rules = [
            "parent_id" => "sometimes|nullable|exists:products,id",
            "brand_id" => "sometimes|nullable|exists:brands,id",
            "attributes" => "required|array",
            "scope" => "sometimes|in:website,channel,store"
        ];

        $this->attribute_set_repository = $attribute_set_repository;
        $this->attribute_repository = $attribute_repository;
        $this->channel_model = $channel_model;
        $this->store_model = $store_model;
        $this->image_repository = $image_repository;
        $this->productBuilderRepository = $productBuilderRepository;

    }

    public function validataInventoryData(array $data): array
    {
        try
        {
            $config_rules = (isset($data["manage_stock"]) && $data["manage_stock"] == 1) ? 0 : 1;
            $no_config_rules = (isset($data["use_config_manage_stock"]) && $data["use_config_manage_stock"] == 1) ? 0 : 1;

            $validator = Validator::make($data, [
                "quantity" => "required|numeric",
                "use_config_manage_stock" => "required|boolean|in:$config_rules",
                "manage_stock" => "required|boolean|in:$no_config_rules"
            ]);
            if ( $validator->fails() ) throw ValidationException::withMessages($validator->errors()->toArray());
        }
        catch ( Exception $exception )
        {
            throw $exception;
        }

        return $validator->validated();
    }

    public function catalogInventory(object $product, object $request, string $method, array $value): bool
    {
        try
        {
            if (isset($value["value"]) && isset($value["catalog_inventory"]))
            {
                $data = $this->validataInventoryData($value["catalog_inventory"]);
                $data["product_id"] = $product->id;
                $data["website_id"] = $product->website_id;
                $data["is_in_stock"] = (bool) $value["value"];
                $match = [
                    "product_id" => $product->id,
                    "website_id" => $product->website_id
                ];

                unset($data["quantity"]);
                $catalog_inventory = CatalogInventory::updateOrCreate($match, $data);

                $original_quantity = (float) $catalog_inventory->quantity;
                $adjustment_type = (($value["catalog_inventory"]["quantity"] - $original_quantity) > 0) ? "addition" : "deduction";
                LogCatalogInventoryItem::dispatchSync([
                    "product_id" => $catalog_inventory->product_id,
                    "website_id" => $catalog_inventory->website_id,
                    "event" => ($method == "store") ? "{$this->model_key}.store" : "{$this->model_key}.{$adjustment_type}",
                    "adjustment_type" => ($method == "store") ? "addition" : $adjustment_type,
                    "adjusted_by" => auth()->guard("admin")->id(),
                    "quantity" => ($method == "store") ? $value["catalog_inventory"]["quantity"] : (float) abs($original_quantity - $value["catalog_inventory"]["quantity"])
                ]);
            }
        }
        catch ( Exception $exception )
        {
            throw $exception;
        }

        return true;
    }

    public function sku(object $product, object $request, string $method, array $value): bool
    {
        try
        {
            if (isset($value["value"]))
            {
                // $id = ($method == "update") ? $product->id : "";
                $validator = Validator::make(["sku" => $value["value"] ], [
                    "sku" => "required|unique:products,sku,".$product->id
                ]);

                if ( $validator->fails() ) throw ValidationException::withMessages($validator->errors()->toArray());
                $product->update($validator->validated());
            }
        }
        catch ( Exception $exception )
        {
            throw $exception;
        }

        return true;
    }

    public function status(object $product, object $request, string $method, array $value): bool
    {
        try
        {
            if (isset($value["value"]))
            {
                $attributeOption = AttributeOption::find($value["value"]);
                if($attributeOption) $product->update(["status" => $attributeOption?->code]);
            }
        }
        catch ( Exception $exception )
        {
            throw $exception;
        }
        return true;
    }

    public function categories(object $product, object $request, string $method, array $value): bool
    {
        try
        {
            if (isset($value["value"]))
            {
                $validator = Validator::make(["categories" => $value["value"]], [
                    "categories" => "required|array",
                    "categories.*" => "required|exists:categories,id"
                ]);
                if ( $validator->fails() ) throw ValidationException::withMessages($validator->errors()->toArray());

                $product->categories()->sync($validator->validated()["categories"]);
            }
        }
        catch ( Exception $exception )
        {
            throw $exception;
        }

        return true;
    }

    public function gallery(object $product, object $request, string $method, array $value): bool
    {
        try
        {
            $request_images = $value["value"];
            if ($method == "update" && isset($request_images["existing"])) {
                $this->updateImageType($request_images, $product);
            }

            unset($request_images["existing"]);

            if ( !empty($request_images) ) {
                $validator = Validator::make($request_images, [
                    "*.type" => "required|array",
                    "*.type.*" => "exists:image_types,slug",
                    "*.file" => "required|mimes:bmp,jpeg,jpg,png",
                    "*.background_color" => "sometimes|nullable",
                    "*.position" => "sometimes|nullable|numeric",
                ],[
                    "*.type.*.in" => "Product Image type must be in base_image,thumbnail_image,section_background_image,small_image,gallery",
                ]);
                if ( $validator->fails() ) throw ValidationException::withMessages($validator->errors()->toArray());
                foreach ( $request_images as $image_values )
                {
                    $this->storeImages($product, $image_values);
                }
            }
        }
        catch ( Exception $exception )
        {
            throw $exception;
        }

        return true;
    }

    private function updateImageType(mixed $data, object $product): bool
    {
        try
        {
            if (isset($data["existing"])) {
                $validate_data = $this->validateUpdateImage($data["existing"], $product);

                foreach ( $validate_data as $item )
                {
                    if ($item["delete"]) {
                        $this->image_repository->delete($item["id"], function ($deleted) {
                            if ($deleted->path) Storage::delete($deleted->path);
                        });
                        continue;
                    }
                    $product_image = ProductImage::whereId($item["id"])->first();
                    $product_image->update(["position" => $item["position"], "background_color" => $item["background_color"]]);
                    $product_image->types()->detach($product_image);
                    $image_type_ids = ImageType::whereIn("slug", $item["type"])->pluck("id")->toArray();
                    $product_image->types()->sync($image_type_ids);
                }
            }
        }
        catch (Exception $exception)
        {
            throw $exception;
        }
        return true;
    }

    private function validateUpdateImage(array $data, object $product)
    {
        try
        {
            $validator = Validator::make($data, [
                "*.type" => "required|array",
                "*.type.*" => "exists:image_types,slug",
                "*.delete" => "required|boolean",
                "*.id" => "required|exists:product_images,id",
                "*.id" => Rule::in($product->images()->pluck("id")->toArray()),
                "*.background_color" => "sometimes|nullable",
                "*.position" => "sometimes|nullable|numeric",
            ], [
                "*.id.required" => "Product Image id is required",
                "*.id.in" => "Product Image id does not belongs to current product.",
                "*.type.*.in" => "Product Image type must be in base_image,thumbnail_image,section_background_image,small_image,gallery"
            ]);

            if ( $validator->fails() ) throw ValidationException::withMessages($validator->errors()->toArray());
        }
        catch (Exception $exception)
        {
            throw $exception;
        }

        return $validator->validate();
    }

    public function storeImages(object $product, array $values): bool
    {
        try
        {

            $image = $values["file"];
            $image_types = array_unique($values["type"]);
            if ( isset($image) ) {
                $key = Str::random(6);
                $data = [];
                $file_name = $this->generateFileName($image);
                $data["path"] = $image->storeAs("images/products/{$key}", $file_name);

                foreach ( $image_types as $image_type ) {
                    $image_dimensions = config("product_image.image_dimensions.product_{$image_type}");
                    foreach ( $image_dimensions as $dimension )
                    {
                        $width = $dimension["width"];
                        $height = $dimension["height"];
                        $path = "images/products/{$key}/{$image_type}";
                        if(!Storage::has($path)) Storage::makeDirectory($path, 0777, true, true);

                        $image = Image::make($image)
                            ->fit($width, $height, function($constraint) {
                                $constraint->upsize();
                            })->encode('jpg', 80);
                    }
                }
                $data["product_id"] = $product->id;
                $data["background_color"] = isset($values["background_color"]) ? $values["background_color"] : null;
                $data["position"] = isset($values["position"]) ? $values["position"] : 0;
                $product_image = ProductImage::create($data);

                $image_type_ids = ImageType::whereIn("slug", $image_types)->pluck("id")->toArray();
                $product_image->types()->sync($image_type_ids);
            }
        }
        catch ( Exception $exception )
        {
            throw $exception;
        }

        return true;
    }

    public function scopeFilter(string $scope, string $element_scope): bool
    {
        if($scope == "channel" && in_array($element_scope, ["website"])) return true;
        if($scope == "store" && in_array($element_scope, ["website", "channel"])) return true;
        return false;
    }

    public function product_attribute_data(int $id, object $request): array
    {
        try
        {
            $product = $this->model::with(["variants.attribute_options_child_products"])->findOrFail($id);

            $request->validate([
                "scope" => "sometimes|in:website,channel,store",
                "scope_id" => [ "sometimes", "integer", "min:1", new ScopeRule($request->scope), new WebsiteWiseScopeRule($request->scope ?? "website", $product->website_id)]
            ]);

            $scope = [
                "scope" => $request->scope ?? "website",
                "scope_id" => $request->scope_id ??  $product->website_id,
            ];

            $fetched = [];
            $fetched = [
                "parent_id" => $product->id,
                "website_id" => $product->website_id
            ];
            $fetched["attributes"] = $this->getData($id, $scope);
            $fetched["product_builders"] = $this->productBuilderRepository->getProductBuilder($id, $scope);

            if ($product->type == "configurable") $fetched = array_merge($fetched, $this->getConfigurableData($product));
        }
        catch ( Exception $exception )
        {
            throw $exception;
        }

        return $fetched;
    }

    public function getVariants(object $request, int $id): mixed
    {
        try
        {
            $product = Product::whereId($id)->firstOrFail();
            $variants = $this->filterVariants($product, $request);
        }
        catch ( Exception $exception )
        {
            throw $exception;
        }

        return $variants;

    }

    public function filterVariants(object $product, object $request): mixed
    {
        try
        {
            $request->validate([
                "scope" => "sometimes|in:website,channel,store",
                "scope_id" => [ "sometimes", "integer", "min:1", new ScopeRule($request->scope)],
                "website_id" => "required|exists:websites,id",
                "product_name" => "sometimes|string",
                "sku" => "sometimes|string",
                "status" => "sometimes|boolean",
                "visibility" => "sometimes",
            ]);

            $this->validateListFiltering($request);

            $variant = Product::whereParentId($product->id)->with(["categories", "product_attributes", "catalog_inventories", "attribute_options_child_products"]);

            if (isset($request->sku)) $variant->whereLike("sku", $request->sku);

            if (isset($request->status)) $variant->where("status",$request->status);

            if (isset($request->product_name))  {
                $product_attributes = ProductAttribute::whereAttributeId(1)
                    ->whereScope($request->scope ?? "website")
                    ->whereScopeId($request->scope_id ?? $request->website_id)
                    ->get();

                $product_ids = [];
                foreach ( $product_attributes as $product_attribute )
                {
                    $value = $product_attribute->value()->query();
                    $matched = $value->whereLike("value", $request->product_name)->get();
                    if(count($matched) > 0) $product_ids[] = $product_attribute->product()->pluck("id");
                }
                $variant->whereIn("id", Arr::flatten($product_ids));
            }
        }
        catch ( Exception $exception )
        {
            throw $exception;
        }

        return $variant;
    }

    public function getConfigurableData(object $product): array
    {
        try
        {
            $fetched = [];

            $variant_attribute_options = $product->variants->map(function($variant) {
                return $variant->attribute_options_child_products->pluck("attribute_option_id")->toArray();
            })->flatten(1)->unique();

            $items = [];
            $variant_attribute_options->map(function($variant_attribute_option) use(&$items) {
                $attribute_option = AttributeOption::find($variant_attribute_option);
                $attribute = $attribute_option->attribute;

                $items[$attribute->id]["attribute_id"] = $attribute->id;
                $items[$attribute->id]["attribute_option_id"][] = $attribute_option->id;
            })->toArray();
            $fetched["configurable"]["attributes"] = array_values($items);

            $group_attribute = AttributeConfigurableProduct::whereProductId($product->id)->whereUsedInGrouping(1)->first();
            if($group_attribute) $fetched["configurable"]["group_attribute"] = $group_attribute->attribute?->slug;
        }
        catch ( Exception $exception )
        {
            throw $exception;
        }

        return $fetched;
    }

    public function getParentScope(array $scope): array
    {
        try
        {
            switch($scope["scope"])
            {
                case "store":
                    $data["scope"] = "channel";
                    $data["scope_id"] = $this->store_model->find($scope["scope_id"])->channel->id;
                    break;

                case "channel":
                    $data["scope"] = "website";
                    $data["scope_id"] = $this->channel_model->find($scope["scope_id"])->website->id;
                    break;
            }
        }
        catch (Exception $exception)
        {
            throw $exception;
        }

        return $data;
    }

    public function getData(int $id, array $scope): array
    {
        try
        {
            $configurable_attribute_ids = [];
            $product = $this->model->with([ "parent", "brand", "website" ])->findOrFail($id);

            if($product->parent_id) $configurable_attribute_ids = $product->parent->attribute_configurable_products->pluck("attribute_id")->toArray();

            $attribute_set = AttributeSet::findOrFail($product->attribute_set_id);

            $groups = $attribute_set->attribute_groups->sortBy("position")->map(function ($attribute_group) use ($product, $scope, $configurable_attribute_ids)  {
                return [
                    "id" => $attribute_group->id,
                    "name" => $attribute_group->name,
                    "position" => $attribute_group->position,
                    "attributes" => $attribute_group->attributes->sortBy("pivot.position")->map(function ($attribute) use ($product, $scope, $configurable_attribute_ids) {
                        $match = [
                            "attribute_id" => $attribute->id,
                            "scope" => $scope["scope"],
                            "scope_id" => $scope["scope_id"]
                        ];

                        $existAttributeData = $product->product_attributes()->where($match)->first();
                        $mapper = $attribute->checkMapper() && !$attribute->checkOption();

                        $attributesData = [
                            "id" => $attribute->id,
                            "name" => $attribute->name,
                            "slug" => $attribute->slug,
                            "type" => $attribute->type,
                            "scope" => $attribute->scope,
                            "position" => $attribute->position,
                            "is_required" => $attribute->is_required,
                            "is_user_defined" => (bool) $attribute->is_user_defined,
                            "is_synchronized" => (bool) $attribute->is_synchronized,
                            "is_configurable_attribute" => in_array($attribute->id, $configurable_attribute_ids) ? 1 : 0
                        ];
                        if($match["scope"] != "website") {
                            $scopeFilter = $this->scopeFilter($scope["scope"], $attribute->scope);
                            $attributesData["use_default_value"] = $scopeFilter ? 0 : ($mapper ? 0 : ($existAttributeData ? 0 : 1));
                        }
                        $attributesData["value"] = $mapper ? $this->getMapperValue($attribute, $product, $match) : ($existAttributeData ? $existAttributeData->value?->value : $this->getDefaultValues($product, $match));

                        if(in_array($attribute->type, $this->attribute_repository->non_filterable_fields))
                        {
                            if ($attribute->slug == "quantity_and_stock_status") {
                                $attributesData["options"] = [["value" => 1, "label" => "In Stock"],["value" => 0, "label" => "Out of Stock"]];
                            }
                            else $attributesData["options"] = $this->attribute_set_repository->getAttributeOption($attribute);
                            if(isset($attributesData["value"]) && !is_array($attributesData["value"])) {

                                if($attribute->slug == "tax_class_id") $attribute_option_check = ProductTaxGroup::find($attributesData["value"]);
                                elseif($attribute->slug == "features") $attribute_option_check = Feature::find($attributesData["value"]);
                                else $attribute_option_check = AttributeOption::whereId($attributesData["value"])->whereAttributeId($attribute->id)->first();
                                $attributesData["value"] = $attribute_option_check ? json_decode($attributesData["value"]) : null;
                            }
                        }

                        if(($attribute->type == "image" || $attribute->type == "file") && isset($attributesData["value"])) $attributesData["value"] = Storage::url($attributesData["value"]);
                        if($attribute->slug == "quantity_and_stock_status") $attributesData["children"] = $this->attribute_set_repository->getInventoryChildren($product->id);

                        return $attributesData;
                    })->values()->toArray()
                ];
            })->values()->toArray();
        }
        catch( Exception $exception )
        {
            throw $exception;
        }
        return $groups;
    }

    public function getDefaultValues(object $product, array $data): mixed
    {
        $attribute = Attribute::findorFail($data["attribute_id"]);
        if(in_array($attribute->type, $this->attribute_repository->non_filterable_fields)) $attributeOptions = AttributeOption::whereAttributeId($attribute->id)->whereIsDefault(1)->first();
        $defaultValue = isset($attributeOptions) ? $attributeOptions->id : $attribute->default_value;

        if($data["scope"] != "website")
        {
            $parent_scope = $this->getParentScope($data);
            $data["scope"] = $parent_scope["scope"];
            $data["scope_id"] = $parent_scope["scope_id"];
            $data["product_id"] = $product->id;
            return ($item = $product->product_attributes()->where($data)->first()) ? $item->value?->value : $this->getDefaultValues($product, $data);
        }
        return ($item = $product->product_attributes()->where($data)->first()) ? $item->value?->value : $defaultValue;
    }

    public function getMapperValue(object $attribute, object $product, array $scope): mixed
    {
        if($attribute->slug == "sku") return $product->sku;
        if($attribute->slug == "status")
        {
            $status_code = $product->status;

            if($scope["scope"] != "website") {
                if($scope["scope"] == "store") $scope["scope_id"] = Store::find($scope["scope_id"])?->channel_id;
                
                $channel_product = $product->channels()->whereChannelId($scope["scope_id"])->first();
                if($channel_product) $status_code = 0;
            }

            $statusOption = AttributeOption::whereAttributeId($attribute->id)->whereCode($status_code)->first();
            return $statusOption?->id;
        }
        if($attribute->slug == "category_ids") return $product->categories()->pluck('category_id')->toArray();
        if($attribute->slug == "gallery") return $this->getImages($product);

        if($attribute->slug == "quantity_and_stock_status") return ($data = $product->catalog_inventories()->first()) ? $data->is_in_stock : null;
    }

    private function getImages(object $product): array
    {
        try
        {
            $images = ["existing" => ProductGalleryRescouce::collection($product->images) ];
        }
        catch( Exception $exception )
        {
            throw $exception;
        }

        return $images;
    }

    public function getFilterProducts(object $request): mixed
    {
        try
        {
            $request->validate([
                "scope" => "sometimes|in:website,channel,store",
                "scope_id" => [ "sometimes", "integer", "min:1", new ScopeRule($request->scope)],
                "website_id" => "required|exists:websites,id"
            ]);

            $this->validateListFiltering($request);

            $product = Product::whereWebsiteId($request->website_id);

            $validator = Validator::make( $request->all(), [
                "product_name" => "sometimes|string",
                "sku" => "sometimes|string",
                "attribute_set_id" => "sometimes|exists:attribute_sets,id",
                "status" => "sometimes|boolean",
                "visibility" => "sometimes",
                "type" => "sometimes|in:simple,configurable",
                "price_from" => "sometimes|decimal",
                "price_to" => "sometimes|decimal",
                "id_from" => "sometimes|numeric",
                "id_to" => "sometimes|numeric",
                "show_variants" => "sometimes|boolean"
            ]);

            if ( $validator->fails() ) throw ValidationException::withMessages($validator->errors()->toArray());

            if ( isset($request->show_variants) && (!$request->show_variants) ) $product->where("parent_id", null);

            if (isset($request->product_name))
            {
                $product_attributes = ProductAttribute::whereAttributeId(1)
                    ->whereScope($request->scope ?? "website")
                    ->whereScopeId($request->scope_id ?? $request->website_id)
                    ->get();

                $product_ids = [];
                foreach ( $product_attributes as $product_attribute )
                {
                    $value = $product_attribute->value()->query();
                    $matched = $value->whereLike("value", $request->product_name)->get();
                    if(count($matched) > 0) $product_ids[] = $product_attribute->product()->pluck("id");
                }
                $product->whereIn("id", Arr::flatten($product_ids));
            }

            if (isset($request->visibility))
            {
                $product_attributes = ProductAttribute::whereAttributeId(1)
                    ->whereScope($request->scope ?? "website")
                    ->whereScopeId($request->scope_id ?? $request->website_id)
                    ->get();

                $product_ids = [];
                foreach ( $product_attributes as $product_attribute )
                {
                    $value = $product_attribute->value()->query();
                    $matched = $value->where("value", $request->visibility)->get();
                    if(count($matched) > 0) $product_ids[] = $product_attribute->product()->pluck("id");
                }
                $product->whereIn("id", Arr::flatten($product_ids));
            }

            if (isset($request->price_from) || isset($request->price_to))
            {
                $product_attributes = ProductAttribute::whereAttributeId(3)
                    ->whereScope($request->scope ?? "website")
                    ->whereScopeId($request->scope_id ?? $request->website_id)
                    ->get();

                $product_ids = [];
                foreach ( $product_attributes as $product_attribute )
                {
                    $value = $product_attribute->value()->query();
                    $matched = $value->whereBetween("value", [$request->price_from ?? 0, $request->price_to])->get();
                    if(count($matched) > 0) $product_ids[] = $product_attribute->product()->pluck("id");
                }
                $product->whereIn("id", Arr::flatten($product_ids));
            }

            if (isset($request->id_from) || isset($request->id_to))
            {
                $product->whereBetween("id", [$request->id_from ?? 0, $request->id_to]);
            }

            if (isset($request->type)) $product->where("type", $request->type);

            if (isset($request->sku)) $product->whereLike("sku", $request->sku);

            if (isset($request->attribute_set_id)) $product->where("attribute_set_id", $request->attribute_set_id);

            if (isset($request->status)) $product->where("status",$request->status);

        }
        catch ( Exception $exception )
        {
            throw $exception;
        }

        return $product;
    }

    public function configurations(object $request): array
    {
        try
        {
            $request->validate([
                "scope" => "sometimes|in:global,website,channel,store",
                "scope_id" => [ "sometimes", "integer", "min:1", new ScopeRule($request->scope)]
            ]);

            $scope = [
                "scope" => $request->scope ?? "global",
                "scope_id" => $request->scope_id ?? 0,
            ];

            $fields = [ "channel_currency", "symbol_position", "minus_sign", "minus_sign_position", "group_seperator", "decimal_seperator" ];
            $fetched = $this->configuration_data($fields, $scope);
        }
        catch ( Exception $exception )
        {
            throw $exception;
        }

        return $fetched;
    }

    public function configuration_data(array $fields, array $scope): array
    {
        try
        {
            $fetched = [];
            foreach($fields as $field)
            {
                $value = SiteConfig::fetch($field, $scope["scope"], $scope["scope_id"]);
                if($field == "channel_currency") {
                    $fetched["currency"] = $value;
                    continue;
                }
                $fetched[$field] = $value;
            }
        }
        catch ( Exception $exception )
        {
            throw $exception;
        }

        return $fetched;
    }

}
