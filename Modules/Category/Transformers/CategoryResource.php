<?php

namespace Modules\Category\Transformers;

use Illuminate\Support\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Core\Transformers\ChannelResource;

class CategoryResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            "id" => $this->id,
            "slug" => $this->slug,
            "position" => $this->position,

            "_lft" => $this->_lft,
            "_rgt" => $this->_rgt,
            "parent" => $this->parent ?? null,

            "created_at" => $this->created_at->format('M d, Y H:i A'),
            "values" => CategoryValueResource::collection($this->whenLoaded("values")),
            "channels" => ChannelResource::collection($this->whenLoaded("channels")),
        ];
    }
}
