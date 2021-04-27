<?php

namespace Modules\Core\Http\Controllers;

use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Modules\Core\Entities\ExchangeRate;
use Modules\Core\Repositories\ExchangeRateRepository;
use Modules\Core\Transformers\ExchangeRateResource;

class ExchangeRateController extends BaseController
{
    protected $repository;

    public function __construct(ExchangeRate $exchangeRate, ExchangeRateRepository $exchangeRateRepository)
    {
        $this->model = $exchangeRate;
        $this->model_name = "ExchangeRate";
        $this->repository = $exchangeRateRepository;

        parent::__construct($this->model, $this->model_name);
    }

    public function collection(object $data): ResourceCollection
    {
        return ExchangeRateResource::collection($data);
    }

    public function resource(object $data): JsonResource
    {
        return new ExchangeRateResource($data);
    }
    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try
        {
            $this->validateListFiltering($request);
            $fetched = $this->getFilteredList($request);
        }
        catch( Exception $exception )
        {
            return $this->handleException($exception);
        }

        return $this->successResponse($this->collection($fetched), $this->lang('fetch-list-success'));
    }

    public function store(Request $request)
    {
        try
        {
            $data = $this->repository->validateData($request);
            Event::dispatch('core.ExchangeRate.create.before');

            $created = $this->repository->create($data);
            Event::dispatch('core.ExchangeRate.create.after', $created);
        }
        catch( Exception $exception )
        {
            return $this->handleException($exception);
        }

        return $this->successResponse($this->resource($created), $this->lang('create-success'), 201);
    }

    public function show(int $id): JsonResponse
    {
        try
        {
            $fetched = $this->model->findOrFail($id);
        }
        catch( Exception $exception )
        {
            return $this->handleException($exception);
        }

        return $this->successResponse($this->resource($fetched), $this->lang('fetch-success'));
    }

    public function update(Request $request, $id): JsonResponse
    {
        try
        {
            $data = $this->repository->validateData($request);
            Event::dispatch('core.ExchangeRate.update.before', $id);

            $updated = $this->repository->update($data, $id);
            Event::dispatch('core.ExchangeRate.update.after', $updated);
        }
        catch( Exception $exception )
        {
            return $this->handleException($exception);
        }

        return $this->successResponse($this->resource($updated), $this->lang('update-success'));
    }

    public function destroy($id): JsonResponse
    {
        try
        {
            Event::dispatch('core.ExchangeRate.delete.before', $id);

            $this->repository->delete($id);
            Event::dispatch('core.ExchangeRate.delete.after', $id);
        }
        catch( Exception $exception )
        {
            return $this->handleException($exception);
        }

        return $this->successResponseWithMessage($this->lang('delete-success'), 204);
    }
}
