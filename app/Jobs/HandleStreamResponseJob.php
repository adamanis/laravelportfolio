<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Events\StreamTextChunk;
use App\Services\LLMService;
use App\Services\ChatHistoryService;
use Illuminate\Support\Facades\Log;

class HandleStreamResponseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private string $sessionID;
    private string $prompt;

    public const DELIMITER = '/\r\n|\r|\n/';

    /**
     * Create a new job instance.
     */
    public function __construct(string $sessionID, string $prompt)
    {
        $this->sessionID = $sessionID;
        $this->prompt = $prompt;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $responseText = "";
        try {
            $responseText = $this->streamChatResponse();
        } catch (\Exception $e) {
            $responseText = LLMService::getFallbackResponse();
            event(new StreamTextChunk($this->sessionID, $responseText));
            Log::error($e, $e->getTrace());
            throw $e;
        } finally {
            (new ChatHistoryService)->store($this->sessionID, $this->prompt, $responseText);
        }
    }

    private function streamChatResponse(): string
    {
        $service = new LLMService();
        $chatHistoryFilePath = (new ChatHistoryService())->getFilePath($this->sessionID);
        if ($chatHistoryFilePath) {
            $service->addFilepath($chatHistoryFilePath);
        }
        $textStream = $service->getStreamResponse($this->prompt);
        $previousText = '';
        $allText = '';
        while (!$textStream->eof()) {
            $chunk = $textStream->read(1024); // Read chunk of 1024 bytes
            $allText .= $chunk;
            $sentences = preg_split(self::DELIMITER, $chunk);
            $sentences[0] = $previousText . $sentences[0];
            for ($i = 0; $i < count($sentences); $i++) {
                if (empty($sentences[$i]) || 
                    (
                        $i == count($sentences) - 1 && 
                        !$textStream->eof()
                    )
                ) {
                    continue;
                }
                event(new StreamTextChunk($this->sessionID, $sentences[$i]));
                usleep(1000); // Sleep for 10 milliseconds (adjust as needed)
            }

            $previousText = isset($sentences[count($sentences) - 1]) ? $sentences[count($sentences) - 1] : '';
        }

        return $allText;
    }
}
