<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Events\StreamTextChunk;
use App\Services\LLMService;
use Illuminate\Support\Facades\Log;

class HandleStreamResponseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private string $prompt;

    public const DELIMITER = '/\r\n|\r|\n/';

    /**
     * Create a new job instance.
     */
    public function __construct(string $prompt)
    {
        $this->prompt = $prompt;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $service = new LLMService();
        $textStream = $service->getStreamResponse($this->prompt);
        $previousText = '';
        while (!$textStream->eof()) {
            $chunk = $textStream->read(1024); // Read chunk of 1024 bytes
            $sentences = preg_split(self::DELIMITER, $chunk);
            $sentences[0] = $previousText . $sentences[0];
            for ($i = 0; $i < count($sentences) - 1; $i++) {
                if (empty($sentences[$i])) {
                    continue; // Skip empty sentences
                }
                event(new StreamTextChunk($sentences[$i]));
                usleep(1000); // Sleep for 10 milliseconds (adjust as needed)
            }

            $previousText = isset($sentences[count($sentences) - 1]) ? $sentences[count($sentences) - 1] : '';
        }
    }
}
