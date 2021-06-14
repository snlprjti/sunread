<?php

namespace Modules\Attribute\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;

class AttributeGroupResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            "id" => $this->id,
            "name" => $this->name,
            "slug" => $this->slug,
            "position" => $this->position,
            "is_user_defined" => $this->is_user_defined,
            "attribute_set" => new AttributeSetResource($this->whenLoaded("attribute_set")),
            "attributes" => AttributeResource::collection($this->whenLoaded("attributes")),
        ];
    }
}
