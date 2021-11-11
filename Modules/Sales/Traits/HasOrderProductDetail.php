<?php

namespace Modules\Sales\Traits;

use Exception;
use Illuminate\Support\Facades\Storage;
use Modules\Coupon\Entities\Coupon;
use Modules\Customer\Entities\Customer;
use Modules\Product\Entities\Product;
use Modules\Tax\Facades\TaxPrice;

trait HasOrderProductDetail
{
    protected array $product_attribute_slug = [
        "name",
        "tax_class_id",
        "cost",
        "price",
        "weight"
    ];

	public function getProductDetail(object $request, array $order, ?callable $callback = null): ?object
    {
        try
        {
            $coreCache = $this->getCoreCache($request);
            $with = [
                "product_attributes",
                "catalog_inventories",
                "attribute_options_child_products.attribute_option.attribute",
                "images.types"
            ];
            $product = Product::whereId($order["product_id"])->with($with)->first();
            foreach ( $this->product_attribute_slug as $slug )
            {
                $data[$slug] = $product->value([
                    "scope" => "store",
                    "scope_id" => $coreCache->store->id,
                    "attribute_slug" => $slug
                ]);
            }
            if ($callback) $data = array_merge($data, $callback($product));
            $data = array_merge($data, ["sku" => $product->sku, "type" => $product->type]);
        }
        catch (Exception $exception)
        {
            throw $exception;
        }
        return (object) $data;
    }

    public function getProductOptions(object $coreCache, object $product): ?array
    {
        try
        {
            $image_path = $product->images?->filter(fn ($product_image) => in_array("base_image", $product_image->types->pluck("slug")->toArray()) )->first()?->path;
            $url = ($image_path) ? Storage::url($image_path) : null;

            if ( $product->parent_id ) {
                $product_options = [
                    "product_options" => [
                        "attributes" => $product->attribute_options_child_products
                        ->filter(fn ($child_product) => ($child_product->product_id == $product->id))
                        ->map(function ($child_product) {
                            return [
                                "attribute_id" => $child_product->attribute_option?->attribute_id,
                                "label" => $child_product->attribute_option?->attribute->name,
                                "name" => $child_product->attribute_option?->attribute->name,
                                "value" => $child_product->attribute_option?->name,
                            ];
                        })->toArray(),
                        "image_url" => $url
                    ]
                ];
            }
            else {
                $product_options = [
                    "product_options" => [
                        "attributes" => [
                            [
                                "attribute_id" => $this->getAttributeValue($coreCache, $product, "size")?->attribute_id,
                                "label" => "size",
                                "name" => "Size",
                                "value" => $this->getAttributeValue($coreCache, $product, "size")?->name
                            ],
                            [
                                "attribute_id" => $this->getAttributeValue($coreCache, $product, "color")?->attribute_id,
                                "label" => "color",
                                "name" => "Color",
                                "value" => $this->getAttributeValue($coreCache, $product, "color")?->name 
                            ],
                        ],
                        "image_url" => $url
                    ]
                ];
            }
        }
        catch ( Exception $exception )
        {
            throw $exception;
        }

        return $product_options;
    }

    public function getAttributeValue(mixed $coreCache, object $product, string $slug): ?object
    {
        return $product->value([
            "scope" => "store",
            "scope_id" => $coreCache->store->id,
            "attribute_slug" => $slug
        ]);
    }

    public function calculateTax(object $request, array $order): ?object
    {
        try
        {
            $get_zip_code = collect($request->get("address"))->where("address_type", "shipping")->first();
            $zip_code = isset($get_zip_code['postal_code']) ? $get_zip_code['postal_code'] : null;

            $product_data = $this->getProductDetail($request, $order);
            if ( auth("customer")->id() ) {
                $customer = Customer::whereId(auth("customer")->id())->with(["group.tax_group"])->first();
                $customer_tax_group_id = $customer?->group?->tax_group?->id;
                $customer_tax = TaxPrice::calculate($request, $product_data->price, customer_tax_group_id:$customer_tax_group_id, zip_code:$zip_code);
            }
            else $customer_tax = TaxPrice::calculate($request, $product_data->price, $product_data->tax_class_id?->id, zip_code:$zip_code);
        }
        catch (Exception $exception)
        {
            throw $exception;
        }

        return $customer_tax;
    }

    public function calculateDiscount(object $request): ?object
    {
        if ($request->coupon_code) {
            $coupon = Coupon::whereCode($request->coupon_code)->publiclyAvailable()->first();
            if (!$coupon) throw new Exception("Coupon Expired");  
            $this->discount_percent = $coupon->discount_percent;          
        }

        return $coupon; 
    }


}
