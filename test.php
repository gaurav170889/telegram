<?php
$url = 'http://localhost/telegram/webhook/8164448894:AAFbloa8DU6DxN8Jjo0nMYnP6bL33DzcBu4';
$data = ['update_id' => 896250643, 'message' => ['message_id'=>3, 'from'=>['id'=>8596579399, 'first_name'=>'Goga'], 'chat'=>['id'=>8596579399, 'first_name'=>'Goga'], 'text'=>'/start']];
$options = [
    'http' => [
        'header'  => "Content-type: application/json\r\n",
        'method'  => 'POST',
        'content' => json_encode($data)
    ]
];
$context  = stream_context_create($options);
$result = file_get_contents($url, false, $context);
echo "Done";
