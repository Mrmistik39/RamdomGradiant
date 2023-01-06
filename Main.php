<?php

const TOKEN = 'токен от страницы вк';

function uploadFile($url, $path)
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_POST, true);
    if (class_exists('\CURLFile')) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, ['file1' => new \CURLFile($path)]);
    } else {
        curl_setopt($ch, CURLOPT_POSTFIELDS, ['file1' => "@$path"]);
    }
    $data = curl_exec($ch);
    curl_close($ch);
    return json_decode($data, true);
}

function get($url)
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $data = curl_exec($ch);
    curl_close($ch);
    return json_decode($data, true);
}

function request($method, array $params, $token=TOKEN)
{
    $params['v'] = 5.131;
    $ch = curl_init('https://api.vk.com/method/' . $method . '?access_token=' . $token);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
    $data = curl_exec($ch);
    curl_close($ch);
    $json = json_decode($data, true);
    if (!isset($json['response'])) {
        echo "При загрузке фото произошла неизвестная ошибка: {$json}\n";
        var_dump($json['error']);
    }
    return $json['response'];
}

function getRandomColor(){
    if(rand(0, 1) == 1){
        $color = ['red', 'blue', 'green', 'pink', 'white', 'black', 'orange'];
        $r1 = rand(0, count($color) - 1);
        $r2 = 0;
        for($i = 0; $i != 100000; ++$i){
            $r2 = rand(0, count($color) - 1);
            echo "Совпадение цветов #{$i}...\n";
            if($r1 != $r2 and (($color[$r1] != 'green' or $color[$r2] != 'green') and ($color[$r1] != 'blue' or $color[$r2] != 'blue'))) break;
        }
        return "{$color[$r1]}-{$color[$r2]}";
    }else{
        $x = [rand(0, 255), rand(0, 255), rand(0, 255)];
        $y = [rand(0, 255), rand(0, 255), rand(0, 255)];
        for($i = 0; $i != 2; ++$i) {
            if ($x[$i] > $y[$i]) {
                if ($x[$i] - $y[$i] <= 20 and ($x[$i] + 20) <= 255) {
                    $x[$i] += rand(20, 255 - $x[$i]);
                }
            } else {
                if ($y[$i] - $x[$i] <= 20 and ($y[$i] + 20) <= 255) {
                    $y[$i] += rand(20, 255 - $y[$i]);
                }
            }
        }
        return "rgba(".implode(",", $x).")-rgba(".implode(",", $y).")";
    }
}

$time = 0;
$onlineTime = 0;
$isOnline = false;

while(true){
     $timeUpd = 60 * 60; // время обновления в секундах
     if(time() - $time >= $timeUpd){
        $sTime = microtime(true);                                 /* owner_id => USER ID страницы вк (вместо 1)*/
        $uploadUrl = request('photos.getOwnerPhotoUploadServer', ['owner_id' => 1]);
        $img = "images/gradiant_".(count(scandir('images/')) + 1).".jpg";
        $randColor = getRandomColor();
        $cmd = "convert -size 200x200 'gradient:{$randColor}' {$img}";
        echo "Генерация фотографии {$cmd}...\n";
        exec($cmd);
        if(file_exists($img)) {
            if (isset($uploadUrl['upload_url'])) {
                $request = uploadFile($uploadUrl['upload_url'], $img);
                if(!isset($request['error'])){
                    $uploadUrl = request('photos.saveOwnerPhoto', [
                        'server' => $request['server'],
                        'hash' => $request['hash'],
                        'photo' => $request['photo']
                    ]);
                    echo "Ссылка для загрузки получена.\n";
                    if(!isset($uploadUrl['error'])){      /* owner_id => USER ID страницы вк (вместо 1)*/
                        $wallDelete = request('wall.delete', ['owner_id' => 1, 'post_id' => $uploadUrl['post_id']]);
                        if(!isset($wallDelete['error'])){
                            echo "Пост с обновлением фотографии удален.\n";
                        }else{
                            echo "Ошибка удаления поста с обновлением аватарки: {$wallDelete['error']['error_msg']}\n";
                        }
                    }else{
                        echo "Произошла ошибка photos.saveOwnerPhoto: {$uploadUrl['error']['error_msg']}\n";
                    }
                }else{
                    echo "Произошла ошибка photos.saveOwnerPhoto: {$request['error']['error_msg']}\n";
                }
            } else {
                echo "Произошла ошибка, нету поля upload_url\n";
            }
            echo "Обновление аватарки завершено за " . ((int)(microtime(true) - $sTime)) . "ms.\n\n";
        }else{
            echo "Ошибка генерации фотографии, файл не найден.\n";
        }
        $time = time();
    }
    usleep(200);
}
