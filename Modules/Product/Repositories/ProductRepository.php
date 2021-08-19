<?php

namespace Modules\Product\Repositories;

use Exception;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Modules\Core\Entities\Store;
use Modules\Core\Rules\ScopeRule;
use Illuminate\Support\Facades\DB;
use Modules\Core\Entities\Channel;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Intervention\Image\Facades\Image;
use Modules\Product\Entities\Product;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Modules\Attribute\Entities\Attribute;
use Modules\Product\Entities\ProductImage;
use Modules\Attribute\Entities\AttributeSet;
use Modules\Core\Repositories\BaseRepository;
use Illuminate\Validation\ValidationException;
use Modules\Product\Entities\ProductAttribute;
use Modules\Attribute\Entities\AttributeOption;
use Modules\Inventory\Entities\CatalogInventory;
use Modules\Inventory\Jobs\LogCatalogInventoryItem;
use Modules\Attribute\Repositories\AttributeRepository;
use Modules\Attribute\Repositories\AttributeSetRepository;
use Modules\Product\Entities\AttributeConfigurableProduct;
use Modules\Product\Jobs\MapProductImageTypeValueJob;
use Modules\Product\Rules\WebsiteWiseScopeRule;

class ProductRepository extends BaseRepository
{
    protected $attribute, $attribute_set_repository, $channel_model, $store_model, $image_repository;
    
    public function __construct(Product $product, AttributeSetRepository $attribute_set_repository, AttributeRepository $attribute_repository, ProductImageRepository $image_repository,Channel $channel_model, Store $store_model)
    {
        $this->model = $product;
        $this->model_key = "catalog.products";
        $this->rules = [
            "parent_id" => "sometimes|nullable|exists:products,id",
            "brand_id" => "sometimes|nullable|exists:brands,id",
            "attributes" => "required|array",
            "scope" => "sometimes|in:website,channel,store",
            "website_id" => "required|exists:websites,id"
        ];

        $this->attribute_set_repository = $attribute_set_repository;
        $this->attribute_repository = $attribute_repository;
        $this->channel_model = $channel_model;
        $this->store_model = $store_model;
        $this->image_repository = $image_repository;
    }

    public function validataInventoryData(array $data): array
    { 
        try
        {
            $config_rules = (isset($data["manage_stock"]) && $data["manage_stock"] == 1) ? 0 : 1;
            $no_config_rules = (isset($data["use_config_manage_stock"]) && $data["use_config_manage_stock"] == 1) ? 0 : 1;

            $validator = Validator::make($data, [
                "quantity" => "required|decimal",
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
                $id = ($method == "update") ? $product->id : "";
                $validator = Validator::make(["sku" => $value["value"] ], [
                    "sku" => "required|unique:products,sku,".$id
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
            $images = $value["value"]; 
            if ($method == "update")
            {
                $this->updateImageType($images, $product);
                if (isset($value["value"]["new"])) $images = $value["value"]["new"];
            }
            if (isset($images["base_image"])) $this->storeImages($product, $images["base_image"], "base_image", $method);
            if (isset($images["small_image"])) $this->storeImages($product, $images["small_image"], "small_image", $method);
            if (isset($images["thumbnail_image"])) $this->storeImages($product, $images["thumbnail_image"], "thumbnail_image", $method);
            if (isset($images["section_background_image"])) $this->storeImages($product, $images["section_background_image"], "section_background_image", $method);
            if (isset($images["gallery"])) $this->storeImages($product, $images["gallery"], "gallery_image", $method);
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
            if (isset($data["existing"]))
            {
                $validate_data = $this->validateUpdateImage($data["existing"], $product);

                foreach ( $validate_data as $item )
                { 
                    if ($item["delete"])
                    {
                        $this->image_repository->delete($item["id"], function ($deleted) {
                            if ($deleted->path)
                            {
                                Storage::delete($deleted->path);
                                $this->image_repository->deleteThumbnail($deleted->path);
                            }
                        });
                        continue;
                    }
                    $this->storeUpdateImage($item, $product->id);
                }
            }
        }
        catch (Exception $exception) 
        {
            throw $exception;
        }
        return true;
    }

    private function storeUpdateImage(array $data, int $product_id): bool
    {
        try
        {
            $image = $this->image_repository->fetch($data["id"]);
            $get_types = $this->getImageTypes($image);

            $create_new_type_image = [];
            foreach ( $data["type"] as $type )
            {
                if ( in_array($type, $get_types) ) continue;
                // take image and make a copy image
                $get_image = Storage::get($image->path);
                $array_path = explode("/", $image->path);
                $array_path[2] = $array_path[2]."/{$type}";
                $path = implode("/", $array_path);
                Storage::put($path, $get_image);
                
                $create_new_type_image["path"] = $path;
                $create_new_type_image["product_id"] = $product_id;
                $create_new_type_image = array_merge($create_new_type_image, $this->getImageType([$type]));
                ProductImage::updateOrCreate($create_new_type_image);
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
                "*.type.*" => "in:base_image,thumbnail_image,section_background_image,small_image,gallery_image",
                "*.delete" => "required|boolean",
                "*.id" => "required|exists:product_images,id",
                "*.id" => Rule::in($product->images()->pluck("id")->toArray()),
            ]);

            if ( $validator->fails() ) throw ValidationException::withMessages($validator->errors()->toArray());
        }
        catch (Exception $exception)
        {
            throw $exception;
        }

        return $validator->validate();
    }

    private function getImageType(array $types): array
    {
       try
       {
            $all_types = [
                "main_image" => 0,
                "small_image" => 0,
                "thumbnail" => 0,
                "section_background" => 0,
                "gallery" => 0,
            ];

            if (in_array("base_image", $types))
            {
                unset($all_types["main_image"]);
                $all_types["main_image"] = 1;
            }
            if (in_array("small_image", $types))
            {
                unset($all_types["small_image"]);
                $all_types["small_image"] = 1;
            }
            if (in_array("thumbnail_image", $types))
            {
                unset($all_types["thumbnail"]);
                $all_types["thumbnail"] = 1;
            }
            if (in_array("section_background_image", $types))
            {
                unset($all_types["section_background"]);
                $all_types["section_background"] = 1;
            }
            if (in_array("gallery_image", $types))
            {
                unset($all_types["gallery"]);
                $all_types["gallery"] = 1;
            }    
       }
       catch(Exception $exception)
       {
           throw $exception;
       }

       return $all_types;
    }

    public function storeImages(object $product, mixed $images, string $image_type): bool
    {
        try
        {
            $images = is_array($images) ? $images : [$images];

            if ( isset($images) )
            {
                $data = [];
                $image_dimensions = config("product_image.image_dimensions.product_{$image_type}");
                $position = 0;

                $validator = Validator::make(["image" =>  $images], [
                    "image.*" => "required|mimes:bmp,jpeg,jpg,png"
                ]);
                if ( $validator->fails() ) throw ValidationException::withMessages($validator->errors()->toArray());
                
                foreach ( $images as $image )
                {
                    $position += 1;
                    $key = Str::random(6);
                    $file_name = $this->generateFileName($image);
                    $data["path"] = $image->storeAs("images/products/{$key}", $file_name);
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
                    $data["position"] = $position;
                    $data["product_id"] = $product->id;
                    
                    switch ( $image_type )
                    {
                        case "base_image" :
                            $data["main_image"] = 1;
                        break;
    
                        case "small_image" :
                            $data["small_image"] = 1;
                        break;
    
                        case "thumbnail_image" :
                            $data["thumbnail"] = 1;
                        break;

                        case "section_background_image" :
                            $data["section_background"] = 1;
                        break;

                        case "gallery_image" :
                            $data["gallery"] = 1;
                        break;
                    }

                    ProductImage::create($data);
                }
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
            $product = $this->model::findOrFail($id);

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

            if ($product->type == "configurable") {
                $configurable_childs = $this->model->with("attribute_configurable_products")->whereParentId($id)->get();

                foreach($configurable_childs as $configurable_child)
                {
                    $fetched["configurable_attributes"][] = $configurable_child->attribute_configurable_products->map(function ($configurable_attribute) {      
                        return [
                            "product_id" => $configurable_attribute->product_id,
                            "attribute_id" => $configurable_attribute->attribute_id,
                            "attribute_option_id" => $configurable_attribute->attribute_option_id
                        ];
                    })->toArray();
                }
            }
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
            $product = $this->model->with([ "parent", "brand", "website" ])->findOrFail($id);
        
            $attribute_set = AttributeSet::findOrFail($product->attribute_set_id);
    
            $groups = $attribute_set->attribute_groups->sortBy("position")->map(function ($attribute_group) use ($product, $scope)  { 
                return [
                    "id" => $attribute_group->id,
                    "name" => $attribute_group->name,
                    "position" => $attribute_group->position,
                    "attributes" => $attribute_group->attributes->map(function ($attribute) use ($product, $scope) {
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
                            "is_user_defined" => (bool) $attribute->is_user_defined
                        ];
                        if($match["scope"] != "website") $attributesData["use_default_value"] = $mapper ? 0 : ($existAttributeData ? 0 : 1);
                        $attributesData["value"] = $mapper ? $this->getMapperValue($attribute, $product) : ($existAttributeData ? $existAttributeData->value?->value : $this->getDefaultValues($product, $match));

                        if(in_array($attribute->type, $this->attribute_repository->non_filterable_fields))
                        {
                            $attributesData["options"] = $this->attribute_set_repository->getAttributeOption($attribute); 
                            if($attributesData["value"] && !is_array($attributesData["value"])) $attributesData["value"] = json_decode($attributesData["value"]);
                        } 
                        if($attribute->slug == "quantity_and_stock_status") $attributesData["children"] = $this->attribute_set_repository->getInventoryChildren($product->id);
                        
                        return $attributesData;
                    })->toArray()
                ];
            })->toArray();
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
        if(in_array($attribute->type, $this->attribute_repository->non_filterable_fields)) $attributeOptions = AttributeOption::whereAttributeId($attribute->id)->first();
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

    public function getMapperValue($attribute, $product)
    {
        if($attribute->slug == "sku") return $product->sku;
        if($attribute->slug == "status")
        {
            $statusOption = AttributeOption::whereAttributeId($attribute->id)->whereCode($product->status)->first();
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
            $images = [ "existing" => [] ];
            foreach (["base_image", "thumbnail_image", "section_background_image", "small_image" ] as $type) {
                if ( is_null($this->getFullPath($product, $type)) ) continue;
                $images["existing"][] = $this->getFullPath($product, $type);
            }
            foreach ( $product->images()->whereGallery(1)->get() as $gallery ) {
                $images["existing"][] = [ "id" => $gallery->id, "type" => $this->getImageTypes($gallery), "delete" => 0, "url" => Storage::url($gallery->path) ]; 
            }    
        }
        catch( Exception $exception )
        {
            throw $exception;
        }

        return $images;
    }

    private function getFullPath(object $product, string $image_name): ?array
    {
        $image = $product->images()->where($this->getImageTypeMapper($image_name), 1)->latest("updated_at")->first();
        return $image ? [ "id" => $image->id, "type" => $this->getImageTypes($image), "delete" => 0, "url" => Storage::url($image->path) ] : $image;
    }
    
    public function getImageTypes(mixed $image): array
    {
        try
        {
            if ($image->main_image) {
                $types[] = "base_image";
            }
            if ($image->small_image) {
                $types[] = "small_image";
            }
            if ($image->thumbnail) {
                $types[] = "thumbnail_image";
            }
            if ($image->section_background) {
                $types[] = "section_background_image";
            }
            if ($image->gallery) {
                $types[] = "gallery_image";
            }    
        }
        catch ( Exception $exception )
        {
            throw $exception;
        }

        return $types;
    }

    private function getImageTypeMapper(string $type): string
    {
        switch ( $type )
        {
            case "base_image" :
                $image_type = "main_image";
            break;

            case "small_image" :
                $image_type = "small_image";
            break;

            case "thumbnail_image" :
                $image_type = "thumbnail";
            break;

            case "section_background_image" :
                $image_type = "section_background";
            break;

            default:
                $image_type = "gallery";
            break;
        }

        return $image_type;
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
                "id_to" => "sometimes|numeric"
            ]);

            if ( $validator->fails() ) throw ValidationException::withMessages($validator->errors()->toArray());    

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

}
