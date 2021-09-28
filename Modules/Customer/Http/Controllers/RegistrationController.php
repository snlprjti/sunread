<?php

namespace Modules\Customer\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Event;
use Modules\Customer\Entities\Customer;
use Modules\Core\Http\Controllers\BaseController;
use Modules\Customer\Transformers\CustomerResource;
use Modules\Customer\Repositories\StoreFront\CustomerRepository;
use Exception;
use Illuminate\Support\Facades\Hash;

class RegistrationController extends BaseController
{
    protected $repository;

    public function __construct(CustomerRepository $customerRepository, Customer $customer)
    {
        $this->repository = $customerRepository;
        $this->model = $customer;
        $this->model_name = "Customer Registration";

        parent::__construct($this->model, $this->model_name);
    }

    public function resource(object $data): JsonResource
    {
        return new CustomerResource($data);
    }

    public function register(Request $request): JsonResponse
    {
        try
        {
            $data = $this->repository->registration($request);
            $created = $this->repository->create($data);

        }
        catch (Exception $exception)
        {
            return $this->handleException($exception);
        }

        Event::dispatch( "storefront.customer.registration.success", [ "customer_id" => $created->id ] );

        return $this->successResponse($this->resource($created), $this->lang('create-success'), 201);
    }
}
