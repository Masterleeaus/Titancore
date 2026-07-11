<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
  public function up(){
    if (!Schema::hasTable('ai_usage_ledger')) {
    Schema::create('ai_usage_ledger', function(Blueprint $t){
      $t->bigIncrements('id');
      $t->unsignedBigInteger('tenant_id')->nullable();
      $t->string('module',64)->nullable();
      $t->string('operation',64)->nullable();
      $t->integer('tokens_in')->default(0);
      $t->integer('tokens_out')->default(0);
      $t->decimal('cost',10,4)->default(0);
      $t->string('status',32)->default('ok');
      $t->string('correlation_id',191)->nullable();
      $t->json('meta')->nullable();
      $t->timestamps();
      $t->index(['tenant_id','module','operation']);
      $t->unique(['correlation_id']);
    });
    }
  }
  public function down(){ Schema::dropIfExists('ai_usage_ledger'); }
};
