<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
  public function up(){
    if (!Schema::hasTable('ai_kb_collections')) {
    Schema::create('ai_kb_collections', function(Blueprint $t){
      $t->bigIncrements('id');
      $t->unsignedBigInteger('tenant_id')->nullable();
      $t->string('key_slug',128)->unique();
      $t->string('title');
      $t->json('meta')->nullable();
      $t->timestamps();
    });
    }
  }
  public function down(){ Schema::dropIfExists('ai_kb_collections'); }
};
