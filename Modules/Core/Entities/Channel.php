<?php

namespace Modules\Core\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;


class Channel extends Model
{
    protected $fillable = [
        'code',
        'name',
        'description',
        'theme',
        'hostname',
        'default_locale_id',
        'base_currency_id',
    ];

    public static $SEARCHABLE = ['code', 'name', 'description', 'theme', 'hostname'];

    /**
     * Get the channel locales.
     */
    public function locales()
    {
        return $this->belongsToMany(Locale::class, 'channel_locales');
    }

    /**
     * Get the default locale
     */
    public function default_locale()
    {
        return $this->belongsTo(Locale::class);
    }

    /**
     * Get the channel locales.
     */
    public function currencies()
    {
        return $this->belongsToMany(Currency::class, 'channel_currencies');
    }


    /**
     * Get the base currency
     */
    public function base_currency()
    {
        return $this->belongsTo(Currency::class);
    }

    /**
     * Get the base currency
     */
    public function root_category()
    {
        return $this->belongsTo(Category::class, 'root_category_id');
    }

    /**
     * Get logo image url.
     */
    public function logo_url()
    {
        if (! $this->logo) {
            return;
        }

        return Storage::url($this->logo);
    }

    /**
     * Get logo image url.
     */
    public function getLogoUrlAttribute()
    {
        return $this->logo_url();
    }

    /**
     * Get favicon image url.
     */
    public function favicon_url()
    {
        if (! $this->favicon) {
            return;
        }

        return Storage::url($this->favicon);
    }

    /**
     * Get favicon image url.
     */
    public function getFaviconUrlAttribute()
    {
        return $this->favicon_url();
    }
}
