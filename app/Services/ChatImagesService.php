<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

class ChatImagesService
{
    public function fetch()
    {
        return [
            'large' => $this->fetchLargeImages(),
            'small' => $this->fetchSmallImages(),
        ];
    }

    public function fetchLargeImages()
    {
        return [
            'idle' => Storage::url('stable_diffusion_images/adam.png'),
            'thinking' => Storage::url('stable_diffusion_images/adam_close_eyes.png'),
            'responding' => Storage::url('stable_diffusion_images/adam_open_mouth.png'),
        ];
    }

    public function fetchSmallImages()
    {
        return [
            'idle' => Storage::url('stable_diffusion_images/adam_chibi_closed_mouth.png'),
            'thinking' => Storage::url('stable_diffusion_images/adam_chibi_closed_eyes.png'),
            'responding' => Storage::url('stable_diffusion_images/adam_chibi.png'),
        ];
    }
}