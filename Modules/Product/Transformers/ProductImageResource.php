<?php

namespace Modules\Product\Transformers;

use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductImageResource extends JsonResource
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
            "position" => $this->position,
            "path" => $this->path,
            "main_image" => $this->main_image,
            "small_image" => $this->small_image,
            "thumbnail" => $this->thumbnail,
            "created_at" => Carbon::parse($this->created_at)->format('M j\\,Y H:i A')
        ];
    }
}
