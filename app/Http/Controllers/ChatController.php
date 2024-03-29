<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use App\Jobs\HandleStreamResponseJob;
use App\Services\ChatImagesService;

class ChatController extends Controller
{
    public function index()
    {
        return Inertia::render('Chat', [
            'session_id' => session()->getId(),
            'chat_images' => (new ChatImagesService())->fetch(),
        ]);
    }

    public function ask(Request $request)
    {
        $text = $request->input('question');
        try {
            HandleStreamResponseJob::dispatch(session()->getId(), $text)->onQueue('database');
        } catch (\Exception $e) {
            Log::error($e->getMessage(), $e->getTrace());
        }
        
        return to_route('chat.index');
    }
}
