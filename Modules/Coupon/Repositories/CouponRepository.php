<?php


namespace Modules\Coupon\Repositories;


use Illuminate\Support\Str;
use Modules\Core\Repositories\BaseRepository;
use Modules\Coupon\Entities\Coupon;

class CouponRepository extends BaseRepository
{
    public function __construct(Coupon $coupon)
    {
        $this->model = $coupon;
        $this->model_key = "coupons";
        $this->rules = [
            "code" => "nullable|unique:coupons,code",
            "name" => "required",
            "description" => "sometimes|nullable",
            "valid_from" => "required",
            "valid_to" => "required",
            "flat_discount_amount" => "sometimes|nullable",
            "min_discount_amount" => "sometimes|nullable",
            "max_discount_amount" => "sometimes|nullable",
            "min_purchase_amount" => "sometimes|nullable",
            "discount_percent" => "sometimes|nullable",
            "max_uses" => "required",
            "single_user_uses" => "sometimes|nullable",
            "only_new_user" => "sometimes|boolean",
            "scope_public" => "sometimes|boolean",
            "status" => "sometimes|boolean"
        ];
    }

    public function createCouponCode($name): string
    {
        $replace = str_replace(' ','-',$name);
        $name = substr(strtoupper($replace),0,10);
        $code = $name.'-'.strtoupper(Str::random(4));
        return $code;
    }

}
