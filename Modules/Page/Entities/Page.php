<?php

namespace Modules\Page\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Entities\Website;
use Modules\Core\Traits\HasFactory;
use Modules\Core\Traits\Sluggable;

class Page extends Model
{
    use HasFactory, Sluggable;

    protected $fillable = [ "slug", "title", "position", "status", "meta_title", "meta_description", "meta_keywords", "website_id" ];

    public function page_attributes(): HasMany
    {
        return $this->hasMany(PageAttribute::class);
    }

    public function page_scopes(): HasMany
    {
        return $this->hasMany(PageScope::class);
    }

    public function website(): BelongsTo
    {
        return $this->belongsTo(Website::class);
    }
}
