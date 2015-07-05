<?php 

return [
    'modelLocation' => 'Models',
    //This indicates the url(s) where you'd like to broadcast every cache
    //removal message for sync cache removal accross multiple domains
    //This should be your root url. All broadcasts will be queued and will not
    //be sent without a queue enabled.
    //This is useful if your application is spread accross multiple servers 
    'removal_broadcast_url' => ['http://mysite.dev'],
];