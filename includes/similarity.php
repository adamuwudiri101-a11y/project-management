<?php
function similarity_score(string $a,string $b): float {
 $stop=['the','and','for','with','from','into','that','this','using','based','system','design','implementation','web','of','in','to','a','an','on','by'];
 $tokens=static function(string $text)use($stop):array{$text=mb_strtolower($text,'UTF-8');$text=preg_replace('/[^\p{L}\p{N}]+/u',' ',$text)??'';$out=[];foreach(preg_split('/\s+/u',trim($text))?:[] as $word)if(mb_strlen($word,'UTF-8')>2&&!in_array($word,$stop,true))$out[$word]=true;return $out;};$x=$tokens($a);$y=$tokens($b);if(!$x||!$y)return 0.0;$intersection=count(array_intersect_key($x,$y));$union=count($x)+count($y)-$intersection;return $union?round($intersection/$union*100,2):0.0;
}
