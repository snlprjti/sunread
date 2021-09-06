<?php

namespace Modules\Customer\Http\Controllers;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Modules\Core\Http\Controllers\BaseController;
use Modules\Customer\Entities\Customer;
use Modules\Customer\Entities\CustomerAddress;
use Modules\Customer\Exceptions\ActionUnauthorizedException;
use Modules\Customer\Exceptions\AddressAlreadyCreatedException;
use Modules\Customer\Repositories\CustomerAddressRepository;
use Modules\Customer\Transformers\CustomerAddressResource;

class CustomerAddressAccountController extends BaseController
{
    protected $repository, $customer;

    public function __construct(CustomerAddressRepository $customerAddressRepository, CustomerAddress $customerAddress)
    {
        $this->repository = $customerAddressRepository;
        $this->model = $customerAddress;
        $this->model_name = "Customer Address";
        $this->customer = auth()->guard("customer")->user();
        $exception_statuses = [
            ActionUnauthorizedException::class => 403
        ];
        parent::__construct($this->model, $this->model_name, $exception_statuses);
    }

    public function collection(object $data): ResourceCollection
    {
        return CustomerAddressResource::collection($data);
    }

    public function resource(object $data): JsonResource
    {
        return new CustomerAddressResource($data);
    }

    public function show(string $type): JsonResponse
    {
        try
        {
            $customer_id = $this->customer->id;
            if($type == "shipping") {
                $fetched  = $this->repository->checkShippingAddress($customer_id)->firstOrFail();
            }
            else {
                $fetched = $this->repository->checkBillingAddress($customer_id)->firstOrFail();
            }
        }
        catch (Exception $exception)
        {
            return $this->handleException($exception);
        }

        return $this->successResponse($this->resource($fetched), $this->lang("fetch-success"));
    }

    public function create(Request $request): JsonResponse
    {
        try
        {
            if($request->default_shipping_address == 0 && $request->default_billing_address == 0) throw new Exception("Choose Atleast One.");
            $customer_id = $this->customer->id;

            $created = $this->repository->insert($request, $customer_id);
        }
        catch (Exception $exception)
        {
            return $this->handleException($exception);
        }

        return $this->successResponse($this->resource($created), $this->lang("create-success"));
    }

    public function update(Request $request, string $type): JsonResponse
    {
        try
        {
            $customer_id = $this->customer->id;
            if($type == "shipping") {
                $address  = $this->repository->checkShippingAddress($customer_id)->firstOrFail();
            }
            else {
                $address = $this->repository->checkBillingAddress($customer_id)->firstOrFail();
            }

            $data = $this->repository->validateData($request);
            $updated = $this->repository->update($data, $address->id);
        }
        catch (Exception $exception)
        {
            return $this->handleException($exception);
        }

        return $this->successResponse($this->resource($updated), $this->lang("update-success"));
    }

    public function delete(string $type): JsonResponse
    {
        try
        {
            $customer_id = $this->customer->id;
            if($type == "shipping") {
                $address  = $this->repository->checkShippingAddress($customer_id)->firstOrFail();
            }
            else {
                $address = $this->repository->checkBillingAddress($customer_id)->firstOrFail();
            }

            $this->repository->delete($address->id);
        }
        catch (Exception $exception)
        {
            return $this->handleException($exception);
        }

        return $this->successResponseWithMessage($this->lang("delete-success"));
    }

}
