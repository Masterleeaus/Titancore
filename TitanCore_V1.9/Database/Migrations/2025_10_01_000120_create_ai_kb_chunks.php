<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
  public function up(){
    if (!Schema::hasTable('ai_kb_chunks')) {
    Schema::create('ai_kb_chunks', function(Blueprint $t){
      $t->bigIncrements('id');
      $t->unsignedBigInteger('document_id');
      $t->integer('chunk_index');
      $t->mediumText('content');
      $t->text('embedding')->nullable();
      $t->integer('tokens')->nullable();
      $t->json('meta')->nullable();
      $t->timestamps();
      $t->index(['document_id']);
    });
    }
  }
  public function down(){ Schema::dropIfExists('ai_kb_chunks'); }
};
