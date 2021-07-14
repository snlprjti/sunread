<?php

namespace Modules\Customer\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Traits\HasFactory;
use Modules\Country\Entities\City;
use Modules\Country\Entities\Country;
use Modules\Country\Entities\Region;

class CustomerAddress extends Model
{
    use HasFactory;
    protected $fillable = [ "customer_id", "first_name", "middle_name", "last_name", "address1", "address2", "address3", "country_id", "region_id", "city_id", "postcode", "phone", "vat_number", "default_billing_address", "default_shipping_address" ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }
}
