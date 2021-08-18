<?php

namespace Modules\Page\Repositories;

use Attribute;
use Exception;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Entities\Store;
use Modules\Core\Repositories\BaseRepository;
use Modules\Page\Entities\Page;
use Illuminate\Validation\ValidationException;
use Modules\Page\Entities\PageScope;

class PageRepository extends BaseRepository
{
    protected $pageAttributeRepository, $parent = [], $store_model, $pageScope;

    public function __construct(Page $page, PageAttributeRepository $pageAttributeRepository, Store $store, PageScope $pageScope)
    {
        $this->model = $page;
        $this->model_key = "page";
        $this->rules = [
            "title" => "required",
            "position" => "sometimes|numeric",
            "status" => "sometimes|boolean",
            "meta_title" => "sometimes|nullable",
            "meta_description" => "sometimes|nullable",
            "meta_keywords" => "sometimes|nullable",
            "website_id" => "required|exists:websites,id",
            "stores" => "required|array",
            "components" => "required|array"
        ];
        $this->pageAttributeRepository = $pageAttributeRepository;
        $this->store_model = $store;
        $this->pageScope = $pageScope;
    }

    public function validateSlug(array $data, ?int $id=null): void
    {
        $model = ($id) ? $this->model->where('id', '!=', $id) : $this->model;
        array_map(function($scope) use ($data, $model) {
            $exist_slug = $model->whereSlug($data["slug"])->whereHas("page_scopes", function ($query) use ($scope) {
                $query->whereScope("store")->whereScopeId($scope);
            })->first();
            if ($exist_slug) throw ValidationException::withMessages(["slug" => "Slug has already taken."]);
        }, $data["stores"]);
    }

    public function show(int $id): array
    {
        try
        {
            $attributes = [];
            $data = $this->fetch($id, [ "page_scopes", "page_attributes", "website" ]);
            $components = $data->page_attributes()->get();
            $stores = $data->page_scopes()->pluck("scope_id")->toArray();

            foreach($components as $component)
            {
                $attributes[] = $this->getParent($component);
            }

            $item = $data->toArray();
            unset($item["page_attributes"], $item["page_scopes"]);
            $item["stores"] = $stores;
            $item["components"] = $attributes;
        }
        catch( Exception $exception )
        {
            throw $exception;
        }

        return $item;
    }

    public function getParent(object $component): array
    {
        try
        {
            $this->parent = [];

            $data = collect($this->pageAttributeRepository->config_fields)->where("slug", $component->attribute)->first();
            $this->getChildren($data["groups"], values:$component->value);
        }
        catch( Exception $exception )
        {
            throw $exception;
        }

        return [
            "id" => $component->id,
            "title" => $data["title"],
            "slug" => $data["slug"],
            "groups" => $this->parent
        ];
    }

    private function getChildren(array $elements, ?string $key = null, array $values, ?string $slug_key = null): void
    {
        try
        {
            foreach($elements as $i => &$element)
            {
                $append_key = isset($key) ? "$key.$i" : $i;
                $append_slug_key = isset($slug_key) ? "$slug_key.{$element["slug"]}" : $element["slug"];

                if ($element["hasChildren"] == 0) {
                    if ( $element["provider"] !== "" ) $element["options"] = $this->pageAttributeRepository->cacheQuery((object) $element, $element["pluck"]);
                    unset($element["pluck"], $element["provider"], $element["rules"]);

                    if (count($values) > 0) {
                        $default = getDotToArray($append_slug_key, $values);
                        if($default) $element["default"] = ($element["type"] == "file") ? Storage::url($default) : $default;
                    }

                    setDotToArray($append_key, $this->parent, $element);
                    continue;
                }

                if (isset($element["type"])) {
                    unset($element["pluck"], $element["provider"], $element["rules"]);

                    if ($element["type"] == "repeater") {
                        $count = isset($values[$element["slug"]]) ? count($values[$element["slug"]]) : 0;
                        $fake_element = $element;
                        $fake_element["attributes"] = [];
                        for($j=0; $j<$count; $j++)
                        {
                            if ($j==0) setDotToArray($append_key, $this->parent, $fake_element);
                            $this->getChildren($element["attributes"][0], "$append_key.attributes.$j", $values, "$append_slug_key.$j");
                        }
                        continue;
                    }
                    if ($element["type"] == "normal") {
                        setDotToArray($append_key, $this->parent,  $element);
                        $this->getChildren($element["attributes"], "$append_key.attributes", $values, $append_slug_key);
                        continue;
                    }
                }

                setDotToArray($append_key, $this->parent,  $element);
                $this->getChildren($element["attributes"], "$append_key.attributes", $values);
            }
        }
        catch( Exception $exception )
        {
            throw $exception;
        }
    }

    public function findPage(object $request, string $slug): object
    {
        try
        {
            $store_code = ($request->hasHeader("store")) ? $request->header("store") : null;
            $store = $this->store_model->whereCode($store_code)->firstOrFail();
            $page = $this->model->with("page_attributes")->whereSlug($slug)->firstOrFail();
            $page_scope = $page->page_scopes()->whereScope("store");
            $all_scope = (clone $page_scope)->whereScopeId(0)->first();
            if(!$all_scope) $page = $page_scope->whereScopeId($store->id)->firstOrFail();
        }
        catch( Exception $exception )
        {
            throw $exception;
        }

        return $page;
    }
}
