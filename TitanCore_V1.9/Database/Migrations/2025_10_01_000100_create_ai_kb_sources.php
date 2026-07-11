<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
  public function up(){
    if (!Schema::hasTable('ai_kb_sources')) {
    Schema::create('ai_kb_sources', function(Blueprint $t){
      $t->bigIncrements('id');
      $t->unsignedBigInteger('tenant_id')->nullable();
      $t->enum('source_type',['upload','url','crawler','api']);
      $t->string('display_name');
      $t->json('meta')->nullable();
      $t->timestamps();
    });
    }
  }
  public function down(){ Schema::dropIfExists('ai_kb_sources'); }
};
