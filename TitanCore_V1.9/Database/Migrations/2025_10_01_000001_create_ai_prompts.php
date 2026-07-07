<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
  public function up(){
    if (!Schema::hasTable('ai_prompts')) {
    Schema::create('ai_prompts', function(Blueprint $t){
      $t->bigIncrements('id');
      $t->string('namespace',128);
      $t->string('slug',128);
      $t->unsignedInteger('version');
      $t->string('locale',16)->default('en');
      $t->mediumText('content');
      $t->json('metadata')->nullable();
      $t->enum('source',['core','module','agent','tenant'])->default('core');
      $t->timestamps();
      $t->unique(['namespace','slug','version','locale']);
    });
    }
  }
  public function down(){ Schema::dropIfExists('ai_prompts'); }
};
