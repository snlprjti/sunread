<?php

namespace Modules\Tax\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductTaxGroupResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            "name" => $this->name,
            "description" => $this->description,
            "created_at" => $this->created_at->format("M d, Y H:i A")
        ];
    }
}
