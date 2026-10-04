<?php

declare(strict_types=1);
namespace Database\Seeders;
use App\Models\Deal;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
class PipelineSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $pipeline=Pipeline::updateOrCreate(['slug'=>'sales-pipeline'],['name'=>'Sales Pipeline','description'=>'Default sales opportunity pipeline.','is_default'=>true,'is_active'=>true]);
            $stages=[
                ['name'=>'New','slug'=>'new','position'=>1,'color'=>'secondary','probability'=>10],
                ['name'=>'Qualified','slug'=>'qualified','position'=>2,'color'=>'info','probability'=>30],
                ['name'=>'Proposal','slug'=>'proposal','position'=>3,'color'=>'primary','probability'=>50],
                ['name'=>'Negotiation','slug'=>'negotiation','position'=>4,'color'=>'warning','probability'=>70],
                ['name'=>'Won','slug'=>'won','position'=>5,'color'=>'success','probability'=>100,'is_won'=>true],
                ['name'=>'Lost','slug'=>'lost','position'=>6,'color'=>'danger','probability'=>0,'is_lost'=>true],
            ];
            foreach($stages as $stage){ PipelineStage::updateOrCreate(['pipeline_id'=>$pipeline->id,'slug'=>$stage['slug']],$stage+['pipeline_id'=>$pipeline->id]); }
            $ids=PipelineStage::where('pipeline_id',$pipeline->id)->pluck('id','slug');
            Deal::query()->whereNull('pipeline_stage_id')->chunkById(100,function($deals) use($pipeline,$ids){ foreach($deals as $deal){ $slug=(string)$deal->stage; $slug=$ids->has($slug)?$slug:'new'; $stage=PipelineStage::find($ids[$slug]); $deal->forceFill(['pipeline_id'=>$pipeline->id,'pipeline_stage_id'=>$stage->id,'stage'=>$stage->slug,'closed_at'=>($stage->is_won||$stage->is_lost)?($deal->closed_at??now()):null])->save(); } });
        });
    }
}
