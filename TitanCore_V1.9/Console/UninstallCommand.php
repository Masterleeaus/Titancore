<?php
namespace Modules\TitanCore\Console;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
class UninstallCommand extends Command {
  protected $signature = 'titancore:uninstall {--force}';
  protected $description = 'Remove TitanCore tables and seeded rows (DANGEROUS)';
  public function handle(){
    if (!$this->option('force')){
      $this->warn('This will DROP TitanCore tables and delete seeded rows. Re-run with --force to proceed.');
      return 1;
    }
    $drop = ['ai_usage_ledger','ai_kb_collection_docs','ai_kb_collections','ai_kb_chunks','ai_kb_documents','ai_kb_sources','ai_prompts'];
    foreach ($drop as $tbl){ if (Schema::hasTable($tbl)) { Schema::drop($tbl); $this->info("Dropped {$tbl}"); } }
    if (Schema::hasTable('permissions')) {
      DB::table('permissions')->whereIn('name', [
        'manage_ai','manage_ai_prompts','publish_ai_prompts','manage_ai_kb','ingest_ai_kb','use_ai_features','view_ai_usage'
      ])->delete();
      $this->info('Permissions cleaned');
    }
    if (Schema::hasTable('menu_items')) {
      DB::table('menu_items')->whereIn('key', ['titan_core','ai_usage'])->delete();
      $this->info('Menu cleaned');
    }
    $this->info('TitanCore uninstall complete.');
    return 0;
  }
}