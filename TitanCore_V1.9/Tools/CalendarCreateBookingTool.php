<?php
namespace Modules\TitanCore\Tools;
class CalendarCreateBookingTool {
  public function __invoke(array $params): array {
    return ['status'=>'accepted','tool'=>'calendar.create_booking','params'=>$params];
  }
}