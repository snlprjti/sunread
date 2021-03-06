<?php

namespace Modules\Product\Repositories;

use Carbon\Carbon;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Modules\Attribute\Entities\Attribute;
use Modules\Attribute\Entities\AttributeOption;
use Modules\Core\Entities\Store;
use Modules\Core\Entities\Website;
use Modules\Core\Facades\PriceFormat;
use Modules\Core\Facades\SiteConfig;
use Modules\Product\Entities\Product;
use Modules\Product\Jobs\BulkIndexing;
use Modules\Product\Jobs\SingleIndexing;
use Modules\Product\Repositories\StoreFront\ProductFormatRepository;
use Modules\Product\Traits\ElasticSearch\HasIndexing;
use Modules\Tax\Facades\TaxPrice;

class ProductSearchRepository extends ElasticSearchRepository
{
    use HasIndexing;

    protected $model, $mainFilterKeys, $attributeFilterKeys, $searchKeys, $staticFilterKeys, $sortByKeys, $listSource, $product_format_repo;

    public function __construct(Product $product, ProductFormatRepository $product_format_repo)
    {
        $this->model = $product;
        $this->product_format_repo = $product_format_repo;
        $this->mainFilterKeys = [ "brand_id", "attribute_set_id", "type", "website_id" ];

        $this->attributeFilterKeys = Attribute::where('use_in_layered_navigation', 1)->pluck('slug')->toArray();
        $this->searchKeys = Attribute::where('is_searchable', 1)->pluck('slug')->toArray();

        $this->sortByKeys = [ "sort_by_id", "sort_by_name", "sort_by_price" ];

        $this->staticFilterKeys = ["color", "size", "collection", "configurable_size", "configurable_color"];

        $this->listSource = [ "id", "parent_id", "website_id", "name", "sku", "type", "is_in_stock", "stock_status_value", "url_key", "quantity", "visibility", "visibility_value", "price", "special_price", "special_from_date", "special_to_date", "new_from_date", "new_to_date", "base_image", "thumbnail_image", "rollover_image", "color", "color_value", "tax_class_id", "configurable_attributes"];
    }

    public function search(object $request): array
    {
        try
        {
            $search = [];

            if(isset($request->q)) {
                //$search[] = $this->queryString($this->searchKeys, $request->q);
                foreach($this->searchKeys as $key) $search[] = $this->match_phrase_prefix($key, $request->q);
            }
            $query = $this->orwhereQuery($search);
        }
        catch (Exception $exception)
        {
            throw $exception;
        }
        
        return [
            "query" => $query,
            "sort" => []
        ];
    }

    public function filterAndSort(?object $request = null, ?int $category_id = null): array
    {
        try
        {
            $filter = [];
            $sort = [];
    
            if($category_id) $filter[]= $this->term("categories.id", $category_id);
    
            if ($request && count($request->all()) > 0) {
                if ($request->sort_by) $sort = $this->sort($request->sort_by, $request->sort_order ?? "asc");
                
                foreach($request->all() as $key => $value) 
                {
                    if(in_array($key, $this->attributeFilterKeys) && $value) {
                        if($key == "size" || $key == "color") {
                            $size = [$this->terms("configurable_{$key}", $value), $this->terms($key, $value)];
                            $filter[] = $this->OrwhereQuery($size); 
                        }
                        else $filter[] = $this->terms($key, $value);
                    } 
                }
            }
    
            $query = $this->whereQuery($filter);   
        }
        catch (Exception $exception)
        {
            throw $exception;
        }

        return [
            "query" => $query,
            "sort" => $sort
        ];
    }

    public function categoryFilter(array $category_id): array
    {
        try
        {
            $filter = [];
            $sort = [];
    
            if($category_id) $filter[]= $this->terms("categories.id", $category_id);
    
            $query = $this->whereQuery($filter);   
        }
        catch (Exception $exception)
        {
            throw $exception;
        }

        return [
            "query" => $query,
            "sort" => $sort
        ];
    }

    public function getStore(object $request): object
    {
        $website = Website::whereHostname($request->header("hc-host"))->firstOrFail();
        $store = Store::whereCode($request->header("hc-store"))->firstOrFail();
        
        if($store->channel->website->id != $website->id) throw ValidationException::withMessages(["hc-store" => "Store does not belong to this website"]);
        return $store;
    }

    public function getFilterProducts(object $request, int $category_id, object $store, ?int $limit = null, ?int $is_paginated = 1): ?array
    {
        $filter = $this->filterAndSort($request, $category_id);
        if(!$limit) $limit = SiteConfig::fetch("pagination_limit", "global", 0) ?? 10;
        return $this->getProductWithPagination($filter, $request, $store, $limit, $is_paginated);
    }

    public function getSearchProducts(object $request, object $store, ?int $is_paginated = 1): ?array
    {
        $filter = $this->search($request);
        $limit = SiteConfig::fetch("pagination_limit", "global", 0) ?? 10;
        return $this->getProductWithPagination($filter, $request, $store, $limit, $is_paginated);
    }

    public function getProductWithPagination(array $filter, object $request, object $store, int $limit, ?int $is_paginated = 1): ?array
    {
        try
        {
            $data = [];
            $data = $this->finalQuery($filter, $request, $store, $limit, $is_paginated);
            $total = isset($data["products"]["hits"]["total"]["value"]) ? $data["products"]["hits"]["total"]["value"] : 0;
            $products = isset($data["products"]["hits"]["hits"]) ? collect($data["products"]["hits"]["hits"])->pluck("_source")->toArray() : [];
            $data["products"] = $this->productWithFormat($request, $products, $store);

            if($is_paginated == 1) {
                $data["last_page"] = (int) ceil($total/$data["limit"]);
                $data["total"] = $total;   
            } 
        }
        catch (Exception $exception)
        {
            throw $exception;
        }

        return $data; 
    }

    public function productWithFormat(object $request, array $products, object $store): array
    {
        try
        {
            foreach($products as &$product)
            {
                $product = $this->product_format_repo->getProductInFormat($product, $request, $store);
                $product = $this->product_format_repo->changeProductStockStatus($product);
                
                $product["image"] = isset($product["thumbnail_image"]) ? $product["thumbnail_image"] : $product["base_image"];
                $product["quantity"] = isset($product["quantity"]) ? decodeJsonNumeric($product["quantity"]) : 0;
                $product["color"] = isset($product["color"]) ? $product["color"] : null;
                $product["color_value"] = isset($product["color_value"]) ? $product["color_value"] : null;
                unset($product["thumbnail_image"], $product["base_image"]);      
            }
        }
        catch (Exception $exception)
        {
            throw $exception;
        }

        return $products;
    }

    public function getProduct(object $request): ?array
    {
        try
        {
            $data = [];
            $data[] = $this->search($request);
    
            $filter = $this->filterAndSort($request);
            $data[] = $filter["query"];
    
            $query = $this->whereQuery($data);
            $final_query = [];      
        }
        catch (Exception $exception)
        {
            throw $exception;
        }

        return $final_query;
    }

    public function aggregation(): ?array
    {
        try
        {
            $aggregate = [];
            foreach($this->staticFilterKeys as $field) 
            {
                $aggregate[$field] = $this->aggregate($field);
                // $aggregate["{$field}_value"] = $this->aggregate("{$field}_value");
            }         
        }
        catch (Exception $exception)
        {
            throw $exception;
        }

        return $aggregate;   
    }

    public function getFilterOptions(array $category_ids, object $store): ?array
    {
        try
        {
            $filters = [];
            $data = $this->categoryFilter($category_ids);
            $aggregate = $this->aggregation();

            $final_l[] = $data["query"];
            $final_l[] = $this->term("list_status", 1);
            $final_l[] = $this->term("product_status", 1);
            $final_q = $this->whereQuery($final_l);
    
            $query = [
                "size"=> 0,
                "query"=> $final_q,
                "aggs"=> $aggregate
            ];
    
            $fetched = $this->searchIndex($query, $store);

            $fetched["aggregations"]["size"]["buckets"] = array_merge($fetched["aggregations"]["size"]["buckets"], $fetched["aggregations"]["configurable_size"]["buckets"]);
            $fetched["aggregations"]["color"]["buckets"] = array_merge($fetched["aggregations"]["color"]["buckets"], $fetched["aggregations"]["configurable_color"]["buckets"]);
            
            foreach(["color", "size", "collection"] as $field) 
            {
                $state = [];
                $filter["label"] = Attribute::whereSlug($field)->first()?->name;
                $filter["name"] = $field;
                $filter["values"] = collect($fetched["aggregations"][$field]["buckets"])->map(function($bucket) use(&$state) {
                    if(!in_array($bucket["key"], $state)) {
                        $state[] = $bucket["key"];
                        return [
                            "name" => AttributeOption::find($bucket["key"])?->name,
                            "value" =>  $bucket["key"]  
                        ];
                    }
                })->filter()->values();
                $filters[] = $filter;

            } 

            $filters[] = [
                "label" => "Sort By",
                "name" => "sort_by",
                "values" => [
                    [
                        "label" => "Name",
                        "name" => "name",
                        "value" =>  "asc"
                    ],
                    [
                        "label" => "Price",
                        "name" => "price",
                        "value" =>  "asc"
                    ]
                ]
                
            ];
        }
        catch (Exception $exception)
        {
            throw $exception;
        }

        return $filters;
    }

    public function finalQuery(array $filter, object $request, object $store, int $limit, ?int $is_paginated = 1): ?array
    {
        try
        {
            $page = $request->page ?? 1;

            $final_l[] = $filter["query"];
            $final_l[] = $this->term("list_status", 1);
            $final_l[] = $this->term("product_status", 1);
            $final_q = $this->whereQuery($final_l);

            $fetched = [
                "_source" => $this->listSource,
                "from"=> ($page-1) * $limit,
                "size"=> $limit,
                "query"=> $final_q,
                "sort" => (count($filter["sort"]) > 0) ? $filter["sort"] : [],
            ];

            $data =  $this->searchIndex($fetched, $store); 
            $final_data = ($is_paginated == 1) ? [
                "products" => $data,
                "current_page" => (int) $page,
                "limit" => (int) $limit
            ] : [
                "products" => $data
            ];
        }
        catch (Exception $exception)
        {
            throw $exception;
        }

        return $final_data;
    }

    public function reIndex(int $id, ?callable $callback = null): object
    {
        try
        {
            $indexed = $this->model->findOrFail($id);
			if ($callback) $callback($indexed);
            $stores = Website::find($indexed->website_id)->channels->map(function ($channel) {
                return $channel->stores;
            })->flatten(1);
    
            foreach($stores as $store) SingleIndexing::dispatch($indexed, $store);
        }
        catch (Exception $exception)
        {
            throw $exception;
        }

        return $indexed;
    }

    public function bulkReIndex(object $request, ?callable $callback = null): object
    {
        try
        {
            $request->validate([
                'ids' => 'array|required',
                'ids.*' => 'required',
            ]);

            $indexed = $this->model->whereIn('id', $request->ids)->get();
			if ($callback) $callback($indexed);
            // BulkIndexing::dispatch($indexed);
        }
        catch (Exception $exception)
        {
            throw $exception;
        }

        return $indexed;
    }
}
