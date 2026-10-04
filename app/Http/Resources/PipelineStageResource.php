<?php

declare(strict_types=1);
namespace App\Http\Resources;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
class PipelineStageResource extends JsonResource
{
    public function toArray(Request $request): array { return ['id'=>$this->id,'pipeline_id'=>$this->pipeline_id,'name'=>$this->name,'slug'=>$this->slug,'position'=>$this->position,'color'=>$this->color,'probability'=>$this->probability,'is_won'=>$this->is_won,'is_lost'=>$this->is_lost,'deals_count'=>isset($this->deals_count)?(int)$this->deals_count:null,'created_at'=>$this->created_at?->toIso8601String(),'updated_at'=>$this->updated_at?->toIso8601String()]; }
}
