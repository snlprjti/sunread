<?php

namespace Modules\Customer\Repositories;

use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Modules\Customer\Entities\Customer;
use Modules\Core\Repositories\BaseRepository;
use Modules\Customer\Entities\CustomerAddress;

class CustomerAddressRepository extends BaseRepository
{
    public function __construct(CustomerAddress $customer_address)
    {
        $this->model = $customer_address;
        $this->model_key = "customers.addresses";

        $this->rules = [
            "first_name" => "required|min:2|max:200",
            "middle_name" => "sometimes|nullable|min:2|max:200",
            "last_name" => "required|min:2|max:200",

            "address1" => "required|min:2|max:500",
            "address2" => "sometimes",
            "address3" => "sometimes",

            "country_id" => "required|exists:countries,id",
            "region_id" => "sometimes|nullable|exists:regions,id",
            "city_id" => "sometimes|nullable|exists:cities,id",
            "postcode" => "required",
            "phone" => "required",
            "vat_number" => "sometimes",
            "default_billing_address" => "sometimes|boolean",
            "default_shipping_address" => "sometimes|boolean"
        ];
    }

    public function regionAndCityRules(object $request): array
    {
        return [
            "region_id" => "sometimes|nullable|exists:regions,id,country_id,{$request->country_id}",
            "city_id" => "sometimes|nullable|exists:cities,id,region_id,{$request->region_id}",
        ];
    }

    public function unsetDefaultAddresses(array $data, int $customer_id, int $address_id): void
    {
        DB::beginTransaction();

        try
        {
            $customer = Customer::findOrFail($customer_id);

            foreach (["default_billing_address", "default_shipping_address"] as $address_type) {
                if ( !isset($data[$address_type]) ) continue;
                if ( $data[$address_type] != 1 ) continue;

                $customer->addresses()->where("id", "<>", $address_id)->update([
                    $address_type => 0
                ]);
            }
        }
        catch(Exception $exception)
        {
            DB::rollBack();
            throw $exception;
        }

        DB::commit();
    }

    public function updateDefaultAddress(array $data, int $customer_id, int $address_id, ?callable $callback = null): object
    {
        DB::beginTransaction();
        Event::dispatch("{$this->model_key}.updated-default.before");

        try
        {
            $updated = $this->model::whereCustomerId($customer_id)->whereId($address_id)->firstOrFail();
            $updated->fill($data);
            $updated->save();

            if ($callback) $callback($updated);
        }
        catch(Exception $exception)
        {
            DB::rollBack();
            throw $exception;
        }

        Event::dispatch("{$this->model_key}.updated-default.after", $updated);
        DB::commit();

        return $updated;
    }


    public function checkShippingAddress(int $customer_id): object
    {
        try
        {
            $address = $this->model->whereCustomerId($customer_id)->whereDefaultShippingAddress(1);
        }
        catch (Exception $exception)
        {
            throw $exception;
        }

        return $address;
    }

    public function checkBillingAddress(int $customer_id): object
    {
        try
        {
            $address = $this->model->whereCustomerId($customer_id)->whereDefaultBillingAddress(1);
        }
        catch (Exception $exception)
        {
            throw $exception;
        }

        return $address;
    }

    public function insert(object $request, int $customer_id): array
    {
        try
        {
            $data = $this->validateData($request, array_merge($this->regionAndCityRules($request), [
                "default_shipping_address" => "boolean|required_without:default_billing_address",
                "default_billing_address" => "boolean|required_without:default_shipping_address"
            ]), function () use ($customer_id) {
                return [
                    "customer_id" => Customer::findOrFail($customer_id)->id,
                ];
            });

            if ($request->default_shipping_address == 1) {
                $shipping = $this->checkShippingAddress($customer_id)->first();
                if ($shipping) {
                    $created["shipping"] = $this->update($data, $shipping->id);
                }
                else {
                    $data["default_billing_address"] = 0;
                    $created["shipping"] = $this->create($data);
                }
            }

            if ($request->default_billing_address == 1) {
                $billing = $this->checkBillingAddress($customer_id)->first();
                if ($billing) {
                    $created["billing"] = $this->update($data, $billing->id);
                }
                else {
                    $data["default_shipping_address"] = 0;
                    $data["default_billing_address"] = 1;
                    $created["billing"] = $this->create($data);
                }
            }
        }
        catch (Exception $exception)
        {
            throw $exception;
        }

        return $created;
    }
}
