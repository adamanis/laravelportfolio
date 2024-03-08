<?php

namespace App\Services;

use App\Models\ChatHistory;
use Illuminate\Support\Facades\Storage;

class ChatHistoryService
{
    private const PROMPT_TEMPLATE = "Question%s: %s\nAnswer%s: %s\n\n";
    private const FILENAME_TEMPLATE = "chat_%s.txt";

    public function store(string $sessionID, string $question, string $answer): ChatHistory
    {
        $newChatHistory = new ChatHistory();
        $newChatHistory->session_id = $sessionID;
        $newChatHistory->question = $question;
        $newChatHistory->answer = $answer;
        $newChatHistory->save();

        $index = ChatHistory::where('session_id', $sessionID)->count();

        $this->storeTextDocument($sessionID, $this->getChatTemplate($index, $question, $answer));

        return $newChatHistory;
    }
    
    public function getFilePath(string $sessionID): string|bool
    {
        $filename = $this->getFilename($sessionID);
        return Storage::has($filename) ? Storage::path($filename) : false;
    }

    private function getChatTemplate($index, $question, $answer)
    {
        return sprintf(self::PROMPT_TEMPLATE, $index, $question, $index, $answer);
    }

    private function getFilename($sessionID)
    {
        return sprintf(self::FILENAME_TEMPLATE, $sessionID);
    }

    private function storeTextDocument(string $sessionID, string $content)
    {
        $filename = "chat_$sessionID.txt";

        if (Storage::exists($filename)) {
           return Storage::append($filename, $content);
        }

        $content = "The conversation so far are as follows:-\n\n" . $content;
        return Storage::put($filename, $content);
    }

}