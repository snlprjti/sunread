<?php

namespace Modules\DeliveryFlatRate\Repositories;

use Exception;
use Modules\Core\Facades\SiteConfig;
use Modules\Sales\Entities\OrderMeta;
use Modules\Sales\Entities\OrderTaxItem;
use Modules\Sales\Repositories\OrderMetaRepository;
use Modules\CheckOutMethods\Contracts\DeliveryMethodInterface;
use Modules\CheckOutMethods\Repositories\BaseDeliveryMethodRepository;

class DeliveryFlatRateRepository extends BaseDeliveryMethodRepository implements DeliveryMethodInterface
{
    protected object $request;
    protected string $method_key;
    protected object $parameter;

    public function __construct(object $request, object $parameter)
    {
        $this->request = $request;
        $this->parameter = $parameter;
        $this->method_key = "flat_rate";
        parent::__construct($this->request, $this->method_key);
    }

    public function get(): mixed
    {
        try
        {
            $coreCache = $this->getCoreCache();
            $order = $this->orderModel->whereId($this->parameter->order->id)->first();
            $channel_id = $coreCache?->channel->id;
            $arr_shipping = [ "shipping_amount" => 0.00, "shipping_tax" => false ];
            $flat_type = SiteConfig::fetch("delivery_methods_{$this->method_key}_flat_type", "channel", $channel_id);
            $flat_price = SiteConfig::fetch("delivery_methods_{$this->method_key}_flat_price", "channel", $channel_id);
            $total_shipping_amount = 0.00;
            if ($flat_type == "per_order") $total_shipping_amount = $flat_price;
            else {
                $order->order_taxes->map( function ($order_tax) use (&$total_shipping_amount) {
                    $order_item_total_amount = $order_tax->order_tax_items
                    ->filter(fn ($order_tax_item) => ($order_tax_item->tax_item_type == "product"))
                    ->map( function ($order_item) use ($order_tax, &$total_shipping_amount) {
                        $amount = (float) (($order_tax->percent/100) * $order_item->amount);
                        $total_shipping_amount += $amount;
                        
                        OrderTaxItem::create([
                            "tax_id" => $order_tax->id,
                            "tax_percent" => (float) $order_tax->percent,
                            "amount" => $amount,
                            "tax_item_type" => "shipping"
                        ]);
                        
                        return ($order_item->amount + $amount);
                    })->toArray();
    
                    $order_tax->update(["amount" => array_sum($order_item_total_amount)]);
                });
                
                $arr_shipping["shipping_tax"] = true;
            }
            $arr_shipping["shipping_amount"] = $total_shipping_amount;
            
            $this->orderRepository->update([
                "shipping_method" => $this->method_key,
                "shipping_method_label" => SiteConfig::fetch("delivery_methods_{$this->method_key}_title", "channel", $channel_id)
            ], $this->parameter->order->id, function ($order) use ($arr_shipping) {
                $this->orderMetaRepository->create([
                    "order_id" => $order->id,
                    "meta_key" => "shipping",
                    "meta_value" => [
                        "shipping_method" => $order->shipping_method,
                        "shipping_method_label" => $order->shipping_method_label,
                        "taxes" => $arr_shipping
                    ]
                ]);
            });
        }
        catch ( Exception $exception )
        {
            throw $exception;
        }

        return $arr_shipping;
    }


    
}