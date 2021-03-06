<?php

namespace Modules\CheckOutMethods\Services;

use Exception;
use Illuminate\Support\Arr;
use Modules\Core\Facades\CoreCache;
use Modules\Core\Facades\SiteConfig;

class CheckOutProcessResolver
{
    protected object $request;
    protected ?object $method_data;
    protected array $custom_checkout_handler;
    
    public function __construct(object $request, ?object $method_data = null)
    {
        $this->request = $request;
        $this->method_data = $method_data;
        
        $this->custom_checkout_handler = [
            "klarna" => [
                "slug" => "klarna",
                "handles" => ["delivery_methods", "payment_methods"],
                "proxy_slug" => "proxy_checkout_method",
                "configuration_path" => "payment_methods_klarna_api_config_allow_custom_shipping_handler",
            ],
            
        ];
    }
    
    public function check(string $value, string $checkout_method): bool
    {
        $condition = false;
        switch ($checkout_method) {
            case "delivery_methods": 
                $request_payment_method = $this->request->payment_method;
                $condition = $this->is_custom_logic_implemented($request_payment_method, $condition, callback:function ($method_data, $condition) use ($value) {
                    return ($condition) ? (in_array("delivery_methods", $method_data["handles"]) && ($value == "proxy_checkout_method")) : $condition;
                });
            break;

            case "payment_methods":
                $request_delivery_method = $this->request->shipping_method;
                $condition = $this->is_custom_logic_implemented($request_delivery_method, $condition, callback:function ($method_data, $condition) use ($value) {
                    return ($condition) ? (in_array("payment_methods", $method_data["handles"]) && ($value == "proxy_checkout_method")) : $condition;
                });
            break;				
        }
        return $condition;
    }

    public function can_initilize(string $checkout_method): bool
    {
        $condition = false;
        switch ($checkout_method) {
            case "delivery_methods": 
                $request_payment_method = $this->request->payment_method;
                $condition = $this->is_custom_logic_implemented($request_payment_method, $condition, callback:function($method_data, $condition) {
                    return ($condition) ? (in_array("delivery_methods", $method_data["handles"])) : $condition;
                });
            break;

            case "payment_methods":
                $request_delivery_method = $this->request->shipping_method;
                $condition = $this->is_custom_logic_implemented($request_delivery_method, $condition, callback:function($method_data, $condition) {
                    return ($condition) ? (in_array("payment_methods", $method_data["handles"])) : $condition;
                });
            break;				
        }
        return $condition;
    }

    private function is_custom_logic_implemented(?string $method = null, bool $condition, mixed $method_data = null, ?callable $callback = null): bool
    {
        $get_method_data = Arr::get($this->custom_checkout_handler, $method);
        if ($method_data) $get_method_data = $method_data;
        if (!is_null($get_method_data)) {
            $condition = $this->is_custom_checkout_enabled($get_method_data["configuration_path"]);
            if ($callback) $condition = $callback($get_method_data, $condition);
        }
        return $condition;
    }

    public function allow_custom_checkout(string $checkout_method): bool
    {
        $condition = false;
        switch ($checkout_method) {
            case "delivery_methods": 
                $method = collect(Arr::get($this->getCheckOutMethods(), "payment_methods"))->where("custom_logic", true)->first();
                if ($method) $condition = $this->is_custom_logic_implemented($method["slug"], $condition);
            break;

            case "payment_methods":
                $method = collect(Arr::get($this->getCheckOutMethods(), "delivery_methods"))->where("custom_logic", true)->first();
                if ($method) $condition = $this->is_custom_logic_implemented($method["slug"], $condition);
            break;				
        }

        return $condition;
    }

    private function is_custom_checkout_enabled(string $configuration_path): bool
    {
        $coreCache = $this->getCoreCache();
        $allow_custom_shipping_handler = SiteConfig::fetch($configuration_path, "channel", $coreCache->channel->id)?->pluck("iso_2_code")->toArray();
        $channel_country = SiteConfig::fetch("default_country", "channel", $coreCache->channel->id)?->iso_2_code;
        $condition = in_array($channel_country, $allow_custom_shipping_handler);
        return $condition;
    }

    public function getCheckOutMethods(?bool $filter_list = true, ?callable $callback = null): mixed
    {
        try
        {
            $coreCache = $this->getCoreCache();
            $method_lists = [];
    
            foreach( ["delivery_methods", "payment_methods"] as $check_out_method )
            {
                $get_method = SiteConfig::get($check_out_method);
                $get_method_list = $get_method->pluck("slug")->unique()->values()->toArray();
                foreach ($get_method_list as $key => $method) {
                    $value = SiteConfig::fetch("{$check_out_method}_{$method}", "channel", $coreCache->channel?->id);
                    if ($filter_list && !$value) continue;
                    $title = SiteConfig::fetch("{$check_out_method}_{$method}_title", "channel", $coreCache->channel?->id);
                    $method_lists[$check_out_method][$key] = [
                        "slug" => $method,
                        "title" => $title,
                        "custom_logic" => in_array($method, array_keys($this->custom_checkout_handler)),
                        "visible" => true
                    ];
                }

                if ($callback) $method_lists[$check_out_method] = $callback($method_lists[$check_out_method], $check_out_method);
            }
        }
        catch ( Exception $exception )
        {
            throw $exception;
        }

        return $method_lists;
    }

    public function resolveCheckOutMethod(mixed $check_out_method): mixed
    {
        if ($this->can_initilize($check_out_method->check_out_method)) {
            switch ($check_out_method->check_out_method) {
                case "delivery_methods":
                    $check_out_method["repository"] = "Modules\CheckOutMethods\Repositories\Proxies\ProxyDeliveryRepository";
                break;
    
                case "payment_methods": 
                    $check_out_method["repository"] = "Modules\CheckOutMethods\Repositories\Proxies\ProxyPaymentRepository";
                break;
            }
        }

        return $check_out_method;
    }

    public function getCoreCache(): object
    {
        try
        {
            $data = [];
            if($this->request->header("hc-host")) $data["website"] = CoreCache::getWebsite($this->request->header("hc-host"));
            if($this->request->header("hc-channel")) $data["channel"] = CoreCache::getChannel($data["website"], $this->request->header("hc-channel"));
            if($this->request->header("hc-store")) $data["store"] = CoreCache::getStore($data["website"], $data["channel"], $this->request->header("hc-store"));
        }
        catch ( Exception $exception )
        {
            throw $exception;
        }

        return (object) $data;
    }


}