<?php namespace Drapor\CacheRepository\Controllers;

use Illuminate\Routing\Controller;
use \Request;
use Drapor\CacheRepository\CacheRepository;

class BroadcastController extends Controller{

	public function postBroadcast()
	{
		$data  = Request::input();
		info($data);
		CacheRepository::squash($data['key'],$data['value'],$data['name']);
		return response()->json(['data' => 'success!']);
	}
}