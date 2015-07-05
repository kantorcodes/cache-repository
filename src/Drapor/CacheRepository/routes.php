<?php

Route::any('cache/broadcast',[
	'as'   => 'cache.broadcast', 
	'uses' => 'Drapor\\CacheRepository\\Controllers\\BroadcastController@postBroadcast'
]);



?>