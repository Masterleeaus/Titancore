<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
  public function up(){
    if (Schema::hasTable('ai_kb_documents')) { return; }
    Schema::create('ai_kb_documents', function(Blueprint $t){
      $t->bigIncrements('id');
      $t->unsignedBigInteger('source_id');
      $t->string('external_id',191)->nullable();
      $t->string('title')->nullable();
      $t->string('mime',64)->nullable();
      $t->string('lang',16)->default('en');
      $t->json('meta')->nullable();
      $t->timestamps();
    });
  }
  public function down(){ Schema::dropIfExists('ai_kb_documents'); }
};
