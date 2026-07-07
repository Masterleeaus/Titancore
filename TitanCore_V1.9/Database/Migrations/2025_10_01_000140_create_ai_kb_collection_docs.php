<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
  public function up(){
    if (!Schema::hasTable('ai_kb_collection_docs')) {
    Schema::create('ai_kb_collection_docs', function(Blueprint $t){
      $t->unsignedBigInteger('collection_id');
      $t->unsignedBigInteger('document_id');
      $t->primary(['collection_id','document_id']);
    });
    }
  }
  public function down(){ Schema::dropIfExists('ai_kb_collection_docs'); }
};
