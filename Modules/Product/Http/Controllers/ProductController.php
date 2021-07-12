<?php

namespace Modules\Product\Http\Controllers;

use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Modules\Product\Entities\Product;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Core\Http\Controllers\BaseController;
use Modules\Product\Transformers\ProductResource;
use Modules\Product\Repositories\ProductRepository;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Modules\Core\Rules\ScopeRule;
use Modules\Product\Exceptions\ProductAttributeCannotChangeException;

class ProductController extends BaseController
{
    protected $repository;

    public function __construct(Product $product, ProductRepository $productRepository)
    {
        $this->model = $product;
        $this->model_name = "Product";
        $this->repository = $productRepository;
        $exception_statuses = [
            ProductAttributeCannotChangeException::class => 403
        ];

        parent::__construct($this->model, $this->model_name, $exception_statuses);
    }

    public function collection(object $data): ResourceCollection
    {
        return ProductResource::collection($data);
    }

    public function resource(object $data): JsonResource
    {
        return new ProductResource($data);
    }

    public function index(Request $request): JsonResponse
    {
        try
        {
            $this->validateListFiltering($request);
            $fetched = $this->getFilteredList($request, ["product_attributes", "images"]);
        }
        catch( Exception $exception )
        {
            return $this->handleException($exception);
        }

        return $this->successResponse($this->collection($fetched), $this->lang('fetch-list-success'));
    }

    public function store(Request $request): JsonResponse
    {
        try
        {
            $data = $this->repository->validateData($request, [
                "scope_id" => ["sometimes", "integer", "min:0", new ScopeRule($request->scope)]
            ], function ($request) {
                return [
                    "scope" => $request->scope ?? "global",
                    "scope_id" => $request->scope_id ?? 0
                ];
            });
            
            $data["type"] = "simple";
            $scope = [
                "scope" => $data["scope"],
                "scope_id" => $data["scope_id"]
            ];

            $created = $this->repository->create($data, function(&$created) use($request, $scope) {
                $attributes = $this->repository->validateAttributes($created, $request, $scope);

                $this->repository->attributeMapperSync($created, $request);

                $this->repository->syncAttributes($attributes, $created, $scope);
                $created->channels()->sync($request->get("channels"));
            });
        }
        catch( Exception $exception )
        {
            return $this->handleException($exception);
        }

        return $this->successResponse($this->resource($created), $this->lang('create-success'), 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        try
        {
            $request->validate([
                "scope" => "sometimes|in:global,website,channel,store",
                "scope_id" => [ "sometimes", "integer", "min:1", new ScopeRule($request->scope)]
            ]);

            $scope = [
                "scope" => $request->scope ?? "global",
                "scope_id" => $request->scope_id ?? 0

            ];

            $fetched = $this->repository->getData($id, $scope);
        }
        catch( Exception $exception )
        {
            return $this->handleException($exception);
        }

        return $this->successResponse($fetched, $this->lang('fetch-success'));
    }

    public function update(Request $request, int $id): JsonResponse
    {
        try
        {
            // $product = $this->model::findOrFail($id);            
            $data = $this->repository->validateData($request, [
                "scope_id" => ["sometimes", "integer", "min:0", new ScopeRule($request->scope)]
            ], function ($request) {
                return [
                    "scope" => $request->scope ?? "global",
                    "scope_id" => $request->scope_id ?? 0
                ];
            });
            unset($data["attribute_set_id"]);

            $scope = [
                "scope" => $data["scope"],
                "scope_id" => $data["scope_id"]
            ];

            $updated = $this->repository->update($data, $id, function($updated) use($request, $scope) {
                $attributes = $this->repository->validateAttributes($updated, $request, $scope);
                $this->repository->attributeMapperSync($updated, $request, "update");
                $this->repository->syncAttributes($attributes, $updated, $scope);
                $updated->channels()->sync($request->get("channels"));
            });
        }
        catch( Exception $exception )
        {
            return $this->handleException($exception);
        }

        return $this->successResponse($this->resource($updated), $this->lang('update-success'));
    }

    public function destroy(int $id): JsonResponse
    {
        try
        {
            $this->repository->delete($id, function($deleted){
                $deleted->product_attributes()->each(function($product_attribute){
                    $product_attribute->delete();
                });
            });
        }
        catch( Exception $exception )
        {
            return $this->handleException($exception);
        }

        return $this->successResponseWithMessage($this->lang('delete-success'));
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        try
        {
            $updated = $this->repository->updateStatus($request, $id);
        }
        catch (Exception $exception)
        {
            return $this->handleException($exception);
        }

        return $this->successResponse($this->resource($updated), $this->lang("status-updated"));
    }

    public function categoryWiseProducts(Request $request, $category_id): JsonResponse
    {
        try
        {
            $this->validateListFiltering($request);
            $allData = $this->getFilteredList($request);
            foreach($allData as &$data)
            {
                $data->selected = in_array($category_id, $data->categories()->pluck('id')->toArray()) ? 1 : 0;
                $fetched[] = $data;
            }
        }
        catch( Exception $exception )
        {
            return $this->handleException($exception);
        }

        return $this->successResponse($fetched, $this->lang('fetch-list-success'));
    }
}
