<?php

namespace App\Services;

use LLPhant\Chat\OpenAIChat;
use LLPhant\OpenAIConfig;
use LLPhant\Chat\Enums\OpenAIChatModel;
use LLPhant\Embeddings\DataReader\FileDataReader;
use LLPhant\Embeddings\DocumentSplitter\DocumentSplitter;
use LLPhant\Embeddings\EmbeddingFormatter\EmbeddingFormatter;
use LLPhant\Embeddings\EmbeddingGenerator\EmbeddingGeneratorInterface;
use LLPhant\Embeddings\EmbeddingGenerator\OpenAI\OpenAI3SmallEmbeddingGenerator;
use LLPhant\Embeddings\VectorStores\Memory\MemoryVectorStore;
use LLPhant\Query\SemanticSearch\QuestionAnswering;
use Psr\Http\Message\StreamInterface;
use Illuminate\Support\Facades\Log;

class LLMService
{
    private $chat;
    private $qa;

    public function __construct(public bool $includeOwnData = true, public ?string $model = 'openai')
    {
        switch($model) {
            case 'openai':
            default:
                $this->initOpenai($includeOwnData);
                break;
        }
    }

    public function getResponse($text): string
    {
        if ($this->qa instanceof QuestionAnswering) {
            return $this->qa->answerQuestion($text);
        }

        return $this->chat->generateText($text);
    }

    public function getStreamResponse($text): StreamInterface
    {
        if ($this->qa instanceof QuestionAnswering) {
            return $this->qa->answerQuestionStream($text);
        }

        return $this->chat->generateStreamOfText($text);
    }

    private function initOpenai(bool $includeOwnData)
    {
        $config = new OpenAIConfig();
        $config->apiKey = config('llm.openai.apikey');
        $config->model = OpenAIChatModel::Gpt35Turbo->getModelName();
        $this->chat = new OpenAIChat($config);
        if ($includeOwnData) {
            $this->generateEmbeddings(new OpenAI3SmallEmbeddingGenerator());
        }
    }

    private function generateEmbeddings(EmbeddingGeneratorInterface $embeddingGenerator)
    {
        try {
            $filePath = storage_path('app/interview_questions_answers.txt');
            $reader = new FileDataReader($filePath);
            $documents = $reader->getDocuments();
            $splittedDocuments = DocumentSplitter::splitDocuments($documents, 500);
            $formattedDocuments = EmbeddingFormatter::formatEmbeddings($splittedDocuments);
            $embeddedDocuments = $embeddingGenerator->embedDocuments($formattedDocuments);
            $memoryVectorStore = new MemoryVectorStore();
            $memoryVectorStore->addDocuments($embeddedDocuments);
            $this->qa = new QuestionAnswering(
                $memoryVectorStore,
                $embeddingGenerator,
                $this->chat
            );
        } catch (\Exception $e) {
            Log::error($e->getMessage(), $e->getTrace());
            $this->chat->setSystemMessage('Whatever we ask you, you MUST answer "Sorry, I did not catch that. Can you repeat your question"');
        }
        
        return $this;
    }
}