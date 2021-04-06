<?php

namespace Modules\Attribute\Transformers;

use Illuminate\Http\Resources\Json\Resource;

class AttributeFamilyResource extends Resource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request
     * @return array
     */
    public function toArray($request)
    {
        return [
            "id" => $this->id,
            "slug" => $this->slug,
            "name" => $this->name,
            "status" => $this->status,
            "is_user_defined" => $this->is_user_defined
        ];
    }
}
