<?php
namespace Modules\TitanCore\Tools;
class CrmCreateLeadTool {
  public function __invoke(array $params): array {
    return ['status'=>'accepted','tool'=>'crm.create_lead','params'=>$params];
  }
}