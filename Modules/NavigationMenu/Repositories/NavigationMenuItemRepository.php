<?php

namespace Modules\NavigationMenu\Repositories;

use Exception;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Modules\Page\Entities\Page;
use Modules\Core\Facades\CoreCache;
use Illuminate\Support\Facades\Storage;
use Modules\Category\Entities\Category;
use Modules\NavigationMenu\Traits\HasScope;
use Modules\Core\Repositories\BaseRepository;
use Modules\Core\Services\RedisHelper;
use Modules\NavigationMenu\Entities\NavigationMenuItem;
use Modules\NavigationMenu\Entities\NavigationMenuItemValue;
use Modules\NavigationMenu\Exceptions\NavigationMenuItemNotFoundException;
use Modules\NavigationMenu\Transformers\StoreFront\NavigationMenuItemResource;

class NavigationMenuItemRepository extends BaseRepository
{
    use HasScope;

    // Properties for NavigationMenuItemRepostiory
    protected $navigation_menu_repository, $repository, $config_fields, $location_fields, $redis_helper;
    protected bool $without_pagination = true;

    /**
     * NavigationMenuItemRepostiory Class Constructor
     */
    public function __construct(NavigationMenuItem $navigationMenuItem, NavigationMenuRepository $navigation_menu_repository, NavigationMenuItemValue $navigationMenuItemValue, RedisHelper $redis_helper)
    {
        $this->model = $navigationMenuItem;
        $this->value_model = $navigationMenuItemValue;
        $this->navigation_menu_repository = $navigation_menu_repository;
        $this->model_key = "navigation_menu";
        $this->redis_helper = $redis_helper;

        $this->rules = [
            "position" => "sometimes|nullable|numeric",
            "parent_id" => "nullable|numeric|exists:navigation_menu_items,id",
        ];

        $this->config_fields = config("navigation_menu.attributes");
        $this->location_fields = config("locations.locations");

        $this->createModel();
    }

    /**
     * Get Attributes value from Config Data
     */
    public function getConfigData(array $data): array
    {
        $fetched = $this->config_fields;

        foreach($fetched as $key => $children){
            if(!isset($children["elements"])) continue;

            $children_data["title"] = $children["title"];
            $children_data["elements"] = [];

            foreach($children["elements"] as &$element){
                if($this->scopeFilter($data["scope"], $element["scope"])) continue;

                if(isset($data["navigation_menu_item_id"])){
                    $data["attribute"] = $element["slug"];

                    $existData = $this->has($data);

                    if($data["scope"] != "website") $element["use_default_value"] = $existData ? 0 : 1;
                    $elementValue = $existData ? $this->getValues($data) : $this->getDefaultValues($data);
                    $element["value"] = $elementValue?->value ?? null;
                    if ($element["type"] == "file" && $element["value"]) $element["value"] = Storage::url($element["value"]);
                }
                unset($element["rules"]);

                $children_data["elements"][] = $element;
            }
            $attributes[$key] = $children_data;
        }
        return $attributes;
    }

    public function updateItemStatus(Request $request, int $navigation_menu_id, int $id)
    {

        $navigation_menu = $this->navigation_menu_repository->fetch($navigation_menu_id);
        $updated = $this->updateStatus($request, $id);

        $website = $this->website_repository->fetch($navigation_menu->website_id);
        $this->redis_helper->deleteCache("store_front_nav_menu_website_{$website->hostname}_*");
        return $updated;
    }

    public function deleteItem(int $navigation_menu_id, int $id)
    {
        $navigation_menu = $this->navigation_menu_repository->fetch($navigation_menu_id);

        $fetched = $this->model->findOrFail($id);

        if($fetched->navigation_menu_id !== $navigation_menu_id) throw new NavigationMenuItemNotFoundException();

        $this->delete($id);

        // Delete Cache on Delete Items
        $website = $this->website_repository->fetch($navigation_menu->website_id);
        $this->redis_helper->deleteCache("store_front_nav_menu_website_{$website->hostname}_*");
    }

    /**
     * Get Attributes value from Config Data
     */
    public function getLocationData(): array
    {
        $attributes = $this->location_fields;
        return $attributes;
    }

    /**
     * Get NavigationMenuItem with it's Attributes and Values
     */
    public function fetchWithAttributes(object $request, int $navigation_menu_item_id)
    {
        $navigation_menu_item = $this->model->findOrFail($navigation_menu_item_id);

        $data = [
            "scope" => $request->scope ?? "website",
            "scope_id" => $request->scope_id ?? $navigation_menu_item->navigationMenu->website_id,
            "navigation_menu_item_id" => $navigation_menu_item->id,
        ];

        // Accessing NavigationMenuItem title through values
        $title_data = array_merge($data, ["attribute" => "title"]);
        $navigation_menu_item->createModel();
        $value = $navigation_menu_item->has($title_data) ? $navigation_menu_item->getValues($title_data) : $navigation_menu_item->getDefaultValues($title_data);

        $fetched = [
            "id" => $navigation_menu_item->id,
            "title" => $value?->value,
            "navigation_menu_id" => $navigation_menu_item->navigation_menu_id,
        ];
        $fetched["attributes"] = $this->getConfigData($data, $navigation_menu_item);
        return $fetched;
    }

    /**
     * Creates a Unique Slug for NavigationMenuItem
     */
    public function createUniqueSlug(array $data, ?object $navigation_menu_item = null)
    {
        $slug = is_null($navigation_menu_item) ? Str::slug($data["items"]["title"]["value"]) : (isset($data["items"]["title"]["value"]) ? Str::slug($data["items"]["title"]["value"]) : $navigation_menu_item->value([ "scope" => $data["scope"], "scope_id" => $data["scope_id"] ], "slug"));
        $original_slug = $slug;

        $count = 1;

        while ($this->checkSlug($data, $slug, $navigation_menu_item)) {
            $slug = "{$original_slug}-{$count}";
            $count++;
        }
        return $slug;
    }

    public function checkStatus(object $navigation_menu_item, $scope): bool
    {
        try
        {
            $data = [
                "scope" => "website",
                "scope_id" => $scope->id,
            ];
            $status_value = $navigation_menu_item->value($data, "status");
        }
        catch (Exception $exception)
        {
            throw $exception;
        }

        return $status_value === 1 ? true : false;
    }

    /**
     * Fetch Navigation Menu with Items
     */
    public function fetchWithItems(object $request, array $with = [], ?callable $callback = null): object
    {
        $data = $this->navigation_menu_repository->fetchAll($request, $with, $callback);
        $store = CoreCache::getStoreWithCode($request->header("hc-store"));
        $channel = CoreCache::getChannelWithCode($request->header("hc-channel"));

        $scope_data = [
            "scope" => "store",
            "scope_id" => $store->id,
        ];

        $data->each(function($item) use($store, $channel, $scope_data) {
            $item->items = $this->fetchNavigationMenuItems($item->navigationMenuItems()->withDepth()->having('depth', '=', 0)->get(), $scope_data, $store, $channel);
            return $item;
        });

        return $data;
    }

    /**
     * Fetch Navigation From Redis
     */
    public function fetchItemsFromCache(object $request): object
    {
        $coreCache = $this->getCoreCache($request);
        $website = $coreCache->website;
        $channel = $coreCache->channel;
        $store = $coreCache->channel;

        $redis_nav_menu_key = "store_front_nav_menu_website_{$website->hostname}_channel_{$channel->code}_store_{$store->code}";

        if($this->redis_helper->checkIfRedisKeyExists($redis_nav_menu_key)) {
            $fetched = collect($this->redis_helper->getRedisData($redis_nav_menu_key));
        } else {
            $fetched = $this->fetchWithItems($request, callback:function() use($website){
                return $this->navigation_menu_repository->model()->where('status', 1)->whereNotNull('location')->where('website_id', $website->id);
            });
            $this->redis_helper->storeCache($redis_nav_menu_key, $fetched);
        }

        return $fetched;
    }


    /**
     * Get Navigation Menu Items
     */
    private function fetchNavigationMenuItems($navigationMenuItems, $scope_data, $store, $channel)
    {
        $navigationMenuItems->transform(function($item) use($scope_data, $channel, $store){

            $item->title = $item->value($scope_data, "title");
            $item->type = $item->value($scope_data, "type");
            $item->status = (int) $item->value($scope_data, "status");
            $item->link = $this->getFinalItemLink($item, $store, $channel);
            $item->background_type = $item->value($scope_data, "background_type");
            $item->background_image = $item->value($scope_data, "background_image");
            $item->background_video_type = $item->value($scope_data, "background_video_type");
            $item->background_video = $item->value($scope_data, "background_video");
            $item->background_overlay_color = $item->value($scope_data, "background_overlay_color");

            unset($item->values);

            return $item;
        });
    }

    public function getFinalItemLink(object $navigation_menu_item, $store, $channel)
    {

        $store_data = [
            "scope" => "store",
            "scope_id" => $store->id,
        ];

        $type = $navigation_menu_item->value($store_data, "type");
        switch ($type) {
            case 'category':
                $type_id = $navigation_menu_item->value($store_data, "category_id");
                $category = Category::find($type_id);
                $slug = $category ? $category->value($store_data, "slug") : "";
                $link = $this->getDynamicLink($slug, $store, $channel, "category/");
                return $link;
                break;

            case 'page':
                $type_id = $navigation_menu_item->value($store_data, "page_id");
                $page = Page::find($type_id);
                $link = $this->getDynamicLink($page ? $page->slug : null, $store, $channel, "page/");
                return $link;
                break;

            case 'custom':
                $custom_link = $navigation_menu_item->value($store_data, "custom_link");
                return $custom_link;
                break;

            case 'dynamic':
                $dynamic_link = $navigation_menu_item->value($store_data, "dynamic_link");
                $link = $this->getDynamicLink($dynamic_link, $store, $channel);
                return $link;
                break;

            default:
                return null;
                break;
        }
    }

    public function getDynamicLink(?string $slug, object $store, $channel, ?string $prepend = null): mixed
    {
        try
        {
            $default_url = "{$channel->code}/{$store->code}/{$prepend}{$slug}";
            $final_url = $default_url;
        }
        catch( Exception $exception )
        {
            throw $exception;
        }

        return $final_url;
    }

    public function getNavigationMenu(object $request): array
    {
        try
        {
            $fetched = [];
            $coreCache = $this->getCoreCache($request);

            $categories = $this->model->withDepth()->having('depth', '=', 0)->whereWebsiteId($coreCache->website->id)->get();
            $scope = [
                "scope" => "store",
                "scope_id" => $coreCache->store->id
            ];

            $fetched = $this->getNavigationMenuItems($categories, $scope);
        }
        catch (Exception $exception)
        {
            throw $exception;
        }

        return $fetched;
    }

    public function getNavigationMenuItems(object $categories, array $scope): array
    {
        try
        {
            $fetched = [];
            foreach($categories as $category)
            {
                if(!$this->checkStatus($category, $scope)) continue;
                $fetched[] = new NavigationMenuItemResource($category);
            }
        }
        catch(Exception $exception)
        {
            throw $exception;
        }

        return $fetched;
    }

}

