<?php
require_once __DIR__.'/../classes/NwOnlineTvPlayer.php';
$good=NwOnlineTvPlayer::render(['title'=>'<script>alert(1)</script>','stream_url'=>'https://video.example.org/live.m3u8']);
if(strpos($good,'<script>')!==false||strpos($good,'&lt;script&gt;')===false||strpos($good,'<video')===false)throw new RuntimeException('Player output encoding');
$bad=NwOnlineTvPlayer::render(['title'=>'Bad','stream_url'=>'http://local.example.org/a.m3u8']);
if(strpos($bad,'<video')!==false)throw new RuntimeException('Invalid player source accepted');
echo "PASS player\n";
