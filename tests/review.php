<?php
require_once __DIR__.'/../classes/NwOnlineTvReview.php';
$a=NwOnlineTvReview::nextState([],false);
$b=NwOnlineTvReview::nextState($a,false);
$c=NwOnlineTvReview::nextState($b,true);
if($a['review_required']!==0 || $b['review_required']!==1 || $c['failure_count']!==0 || $c['review_required']!==1)throw new RuntimeException('review state error');
if(NwOnlineTvReview::validateReportReason('not_playing')!=='not_playing')throw new RuntimeException('report validation error');
try{NwOnlineTvReview::validateReportReason('delete_all');throw new RuntimeException('accepted invalid reason');}catch(InvalidArgumentException $expected){}
echo "PASS review\n";
