<?php

namespace Modules\Sales\Repositories;

use Exception;
use Illuminate\Support\Facades\DB;
use Modules\Cart\Entities\CartItem;
use Modules\Sales\Entities\OrderTax;
use Modules\Product\Entities\Product;
use Modules\Sales\Entities\OrderItem;
use Modules\Sales\Traits\HasOrderItem;
use Modules\Sales\Entities\OrderTaxItem;
use Modules\Core\Repositories\BaseRepository;

class OrderItemRepository extends BaseRepository
{
    use HasOrderItem {
        store as traitStore;
    }
    
    protected $product;

    public function __construct(OrderItem $orderItem, Product $product)
    {
        $this->model = $orderItem;
        $this->model_key = "order_items";
        $this->product = $product;
        $this->rules = [
            
        ];
    }

    public function store(object $request, object $order, object $order_item_details): object
    {
        try
        {
            $coreCache = $this->getCoreCache($request);
            $calculation = $this->calculateItems($order_item_details);
            $data = array_merge($calculation, [
                "website_id" => $coreCache?->website->id,
                "store_id" => $coreCache?->store->id,
                "order_id" => $order->id,
                "product_id" => $order_item_details->product_id,
                "name" => $order_item_details->name,
                "sku" => $order_item_details->sku,
                "cost" => $order_item_details->cost,
                "product_type" => $order_item_details->type,
                "product_options" => json_encode($order_item_details->product_options),
           ]);
           
           $order_item = $this->create($data); 
        } 
        catch ( Exception $exception )
        {
            throw $exception;
        }

        return $order_item;
    }

    public function calculateItems(object $order_item_details): array
    {
        try
        {
            $price = $order_item_details->price;
            $qty = $order_item_details->qty;
            $weight = $order_item_details->weight;
    
            $tax_amount = $order_item_details->tax_rate_value;
            $tax_percent = $order_item_details->tax_rate_percent;
    
            $price_incl_tax = $price + $tax_amount;
            $row_total = $price * $qty;
            $row_total_incl_tax = $row_total + $tax_amount;
            $row_weight = $weight * $qty;
    
            $discount_amount_tax = 0.00;
            $discount_amount = 0.00;
            $discount_percent = 0.00;
    
            $data = [
                "price" => $price,
                "qty" => $qty,
                "weight" => $weight,
                "tax_amount" => $tax_amount,
                "tax_percent" => $tax_percent,
                "price_incl_tax" =>$price_incl_tax,
                "row_total" =>$row_total,
                "row_total_incl_tax" =>$row_total_incl_tax,
                "row_weight" =>$row_weight,
                "discount_amount_tax" =>$discount_amount_tax,
                "discount_amount" =>$discount_amount,
                "discount_percent" =>$discount_percent,
            ];
        }
        catch ( Exception $exception )
        {
            throw $exception;
        }
        return $data;
    }

    // public function storeOrderTax(object $order, object $order_item_details, ?callable $callback = null): object
    // {
    //     DB::beginTransaction();
    //     try
    //     {
    //         foreach ($order_item_details->rules as $rule)
    //         {
    //             $percent = array_sum($rule->rates->pluck("tax_rate")->toArray());
    //             $amount = array_sum($rule->rates->pluck("tax_rate_value")->toArray());
            
    //             $data = [
    //                 "order_id" => $order->id,
    //                 "code" => $rule->name,
    //                 "title" => $rule->name,
    //                 "percent" => $percent,
    //                 "amount" => $amount
    //             ];

    //             $order_tax = OrderTax::create($data);
    //             if ($callback) $callback($order_tax, $rule);
    //         }
    //     }
    //     catch ( Exception $exception )
    //     {
    //         DB::rollback();
    //         throw $exception;
    //     }

    //     DB::commit();        
    //     return $order;
    // }

    // public function storeOrderTaxItem(object $order_tax, object $order_item, mixed $rule, ?callable $callback = null): void
    // {
    //     DB::beginTransaction();
    //     try
    //     {
    //         $data = [];
    //         foreach ($rule->rates as $rate) 
    //         {
    //             $data[] = [
    //                 "tax_id" => $order_tax->id,
    //                 "item_id" => $order_item->product_id,
    //                 "tax_percent" => $rate->tax_rate,
    //                 "amount" => $rate->tax_rate_value,
    //                 "tax_item_type" => "product"
    //             ];
    //         }

    //         if ($callback) $data = array_merge($data, $callback());
            
    //         OrderTaxItem::insert($data);  
    //     }
    //     catch ( Exception $exception )
    //     {
    //         DB::rollback();
    //         throw $exception;
    //     }
    //     DB::commit(); 
    // }

}
