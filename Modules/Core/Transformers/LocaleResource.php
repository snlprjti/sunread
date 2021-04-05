<?php

namespace Modules\Core\Transformers;

use Illuminate\Http\Resources\Json\Resource;

class LocaleResource extends Resource
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
            "code" => $this->code,
            "name" => $this->name
        ];
    }
}
