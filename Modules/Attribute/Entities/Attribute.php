<?php

namespace Modules\Attribute\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Core\Traits\HasFactory;
use Modules\Core\Traits\HasTranslation;
use Modules\Core\Traits\Sluggable;
use Modules\Product\Entities\ProductAttribute;

class Attribute extends Model
{
    use Sluggable, HasFactory, HasTranslation;

    public static $SEARCHABLE = [ "name", "type" ];
    protected $fillable = [ "slug", "name", "type", "position", "validation", "is_required", "is_unique", "is_filterable", "is_searchable", "is_user_defined", "is_visible_on_front", "used_in_layered_navigation" ];

    protected $touches = [ 'product_attributes' ];
    public $translatedAttributes = ["name"];
    public $translatedModels = [ AttributeTranslation::class, "attribute_id" ];

    public function attribute_group(): BelongsToMany
    {
        return $this->belongsToMany(AttributeGroup::class);
    }

    public function attribute_options(): HasMany
    {
        return $this->hasMany(AttributeOption::class);
    }

    public function translations(): HasMany
    {
        return $this->hasMany(AttributeTranslation::class);
    }

    public function generateValidation(): array
    {
        $validation = $this->is_required ? ["required"] : ["sometimes", "nullable"];
        return array_merge($validation, [config("validation.{$this->type}")]);
    }

    public function getValidationAttribute(): ?string
    {
        return $this->validation ?? implode("|", $this->generateValidation());
    }

    public function product_attributes(): HasMany
    {
        return $this->hasMany(ProductAttribute::class)->with(["value"]);
    }
}
