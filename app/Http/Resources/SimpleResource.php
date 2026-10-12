<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Pass-through resource for flat models (subscribers, contact submissions, redirects, menu items). */
class SimpleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = $this->resource->toArray();
        foreach (['created_at', 'updated_at', 'read_at', 'confirmed_at', 'unsubscribed_at', 'last_hit_at'] as $k) {
            if (isset($this->resource->{$k}) && $this->resource->{$k} instanceof \DateTimeInterface) {
                $data[$k] = $this->resource->{$k}->format(DATE_ATOM);
            }
        }
        unset($data['deleted_at']);

        return $data;
    }
}
