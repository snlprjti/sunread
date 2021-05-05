<?php

namespace Modules\Review\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Customer\Transformers\CustomerResource;

class ReviewVoteResource extends JsonResource
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
            "customer" => new CustomerResource($this->customer),
            "vote_type" => $this->vote_type,
            "created_at" => $this->created_at->format("M d, Y H:i A")
        ];
    }
}
