<?php

namespace App\Http\Services;


use Illuminate\Support\Facades\File;

class ImageService
{
    public function removeImg($url, $host)
    {
        if (!empty($url)) {
            $path = 'https://' . $host . '/';

            list($pass, $fileName) = explode($path, $url);

            File::delete($fileName);
        }
    }
}
