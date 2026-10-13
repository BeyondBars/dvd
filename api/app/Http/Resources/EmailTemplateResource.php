<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Support\EmailTemplates\EmailTemplate
 *
 * Expects `subject` (and optionally `html`) to be passed in as additional data, since they are
 * rendered for the viewing admin.
 */
class EmailTemplateResource extends JsonResource
{
    public function __construct($resource, protected array $rendered = [])
    {
        parent::__construct($resource);
    }

    public function toArray($request): array
    {
        return [
            'key' => $this->key,
            'name' => $this->name,
            'type' => $this->type->value,
            'type_title' => $this->type->title(),
            'triggered_by' => $this->triggeredBy,
            'description' => $this->description,
            ...$this->rendered,
        ];
    }
}
