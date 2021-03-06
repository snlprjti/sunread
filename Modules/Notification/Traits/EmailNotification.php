<?php

namespace Modules\Notification\Traits;

use Illuminate\Support\Facades\Blade;
use Modules\Core\Entities\Store;
use Modules\Core\Facades\SiteConfig;
use Modules\Customer\Entities\Customer;
use Modules\Customer\Entities\CustomerAddress;
use Exception;

trait EmailNotification
{
    /**
     *  get email Content and subject data from email template
     */
    public function getData( int $entity_id, string $event, string $append_data = ""): object
    {
        try
        {
            /** get all email variables data by specific events */
            $variable_data = $this->getVariableData($event, $entity_id, $append_data);
            /**
             * get template from configurations according to scope, scope id and event code
             */
            $email_template = SiteConfig::fetch($event, "store", $variable_data->store_id);

            if( !$email_template ) throw new Exception(__("core::app.response.not-found", [ "name" => "Email Template"]));

            /**
             * Set store_id as store (global variable) to get content from configuration
             */
            config(['store' => $variable_data->store_id]);

            $data = new \stdClass();
            $data->content = htmlspecialchars_decode($this->render($email_template->content, $variable_data));
            $data->subject = htmlspecialchars_decode($this->render($email_template->subject, $variable_data));
            $data->template_id = $email_template->id;
            $data->to_email = $variable_data->customer_email_address;
            $data->style = $email_template->style;
            $data->sender_name = SiteConfig::fetch("email_sender_name", "store", $variable_data->store_id) ?? config("mail.from.name");
            $data->sender_email = SiteConfig::fetch("email_sender_email", "store", $variable_data->store_id) ?? config("mail.from.address");
        }
        catch (Exception $exception)
        {
            throw $exception;
        }

        return $data;
    }

    /**
     * compile php variable and content to render in blade template
     */
    public function render(string $content, object $data = null): string
    {
        try
        {
            $php = Blade::compileString($content);
            ob_start();

            /** Extract template variables */
            extract((array)$data, EXTR_SKIP);

            /** replace template variables with defined variable data  */
            eval("?>" . $php);

            $content = ob_get_contents();
            ob_end_clean();
        }
        catch (Exception $exception)
        {
            throw $exception;
        }

        return $content;
    }

    /**
     * get all template variable data according to email template code
     */
    public function getVariableData(string $event_code, int $entity_id, string $append_data): object
    {
        try
        {
            switch ($event_code) {
                case "forgot_password" :
                    $data = $this->forgotPassword($entity_id, $append_data);
                    break;

                case "new_account" :
                    $data = $this->newAccount($entity_id, $append_data);
                    break;

                case "contact_form" :
                    $data = [];
                    break;

                case "welcome_email":
                case "confirm_email":
                case "reset_password":
                    $data = $this->getCustomerData($entity_id);
                    break;

                case "new_order" :
                case "order_update" :
                case "new_guest_order" :
                case "order_update_guest" :
                    $data = $this->orderData($entity_id);
                    break;
            }
            $general = $this->getGeneralVariableData($data->store_id);
        }
        catch (Exception $exception)
        {
            throw $exception;
        }

        $result = array_merge((array)$general, (array)$data);
        return (object) $result;
    }

    /**
     * get customer data by customer id
    */
    private function getCustomerData(int $customer_id): object
    {
        try
        {
            $customer = Customer::findOrFail($customer_id);
            /** get store data by its id */
            if(empty($customer->store_id)) {
                $channel = SiteConfig::fetch("website_default_channel", "website", $customer->website_id);
                $store = Store::findOrFail($channel->default_store_id);
            }
            else {
                $store = Store::findOrFail($customer->store_id);
            }
            /** get channel by store */
            $channel = $store->channel;

            /** get store url from configuration */
            $store_front_baseurl = SiteConfig::fetch("storefront_base_urL", "store", $store->id);

            $storefront_url = "{$store_front_baseurl}/{$channel->code}/{$store->code}";

            $customer_dashboard_url = url("{$storefront_url}/account");

            $customer_data = [
                "customer_id" => $customer->id,
                "customer_name" => "{$customer->first_name} {$customer->middle_name} {$customer->last_name}",
                "customer_email_address" => $customer->email,
                "customer_dashboard_url" => $customer_dashboard_url,
                "store_id" => $customer->store_id,
                "channel_code" => $channel->code,
                "store_code" => $store->code
            ];
        }
        catch (Exception $exception)
        {
            throw $exception;
        }

        return (object)$customer_data;
    }

    /**
      * get all general variables data
    */
    private function getGeneralVariableData(int $store_id = 0): object
    {
        try
        {
            $data = [
                "store_url" => SiteConfig::fetch("storefront_base_urL", "store", $store_id),
                "store_name" => SiteConfig::fetch("store_name", "store", $store_id)?->name,
                "store_phone_number" => SiteConfig::fetch("store_phone_number", "store", $store_id),
                "store_country" => SiteConfig::fetch("store_country", "store", $store_id)?->name,
                "store_state" => SiteConfig::fetch("store_region", "store", $store_id),
                "store_post_code" => SiteConfig::fetch("store_zip_code", "store", $store_id),
                "store_city" => SiteConfig::fetch("store_city", "store", $store_id),
                "store_address_line_1" => SiteConfig::fetch("store_street_address", "store", $store_id),
                "store_address_line_2" => SiteConfig::fetch("store_address_line2", "store", $store_id),
                "store_vat_number" => SiteConfig::fetch("store_vat_number", "store", $store_id),
                "store_email_address" => SiteConfig::fetch("store_email_address", "store", $store_id),
                "store_email_logo_url" => SiteConfig::fetch("store_email_logo_url", "store", $store_id),
            ];
        }
        catch (Exception $exception)
        {
            throw $exception;
        }

        return (object) $data;
    }

    /**
        get forgot password variables data
    */
    private function forgotPassword(int $customer_id, string $append_data): object
    {
        try
        {
            $data = $this->getCustomerData($customer_id);
            $data->password_reset_url = url("{$data->channel_code}/{$data->store_code}/reset-password/{$append_data }");
        }
        catch (Exception $exception)
        {
            throw $exception;
        }

        return $data;
    }

    /**
        get new account variables data
    */
    private function newAccount(int $customer_id, string $append_data): object
    {
        try
        {
            $data = $this->getCustomerData($customer_id);
            $data->account_confirmation_url = url("{$data->channel_code}/{$data->store_code}/verify-account/{$append_data }");
        }
        catch (Exception $exception)
        {
            throw $exception;
        }

        return $data;
    }

    /**
       * get order variables data
    */
    private function orderData(int $entity_id): array
    {
        try
        {
            /** get order object by its entity id */
            $order = $entity_id;

            /** get customer detail by order id */
            $customer_id = 1;

            $customer_data = $this->getCustomerData($customer_id);
            $billing = $this->getBillingAddress($customer_id);
            $shipping = $this->getShippingAddress($customer_id);
            $data = [
                "order_id" => 1,
                "order_items" => 1,
                "billing_address" => $billing,
                "shipping_address" => $shipping,
                "order" => 1,
                "store_id" => 1
            ];
        }
        catch (Exception $exception)
        {
            throw $exception;
        }

        return array_merge($customer_data, $data);
    }

    /**
    * get customer billing address data
    */
    private function getBillingAddress(int $customer_id): ?object
    {
        try
        {
            $address = CustomerAddress::whereCustomerId($customer_id)->whereDefaultBillingAddress(1)->first();
        }
        catch (Exception $exception)
        {
            throw $exception;
        }

        return $address;
    }

    /**
    * get customer shipping address data
    */
    private function getShippingAddress(int $customer_id): ?object
    {
        try
        {
            $address = CustomerAddress::whereCustomerId($customer_id)->whereDefaultShippingAddress(1)->first();
        }
        catch (Exception $exception)
        {
            throw $exception;
        }

        return $address;
    }
}
