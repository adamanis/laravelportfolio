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
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use LLPhant\Chat\ChatInterface;
use LLPhant\Embeddings\VectorStores\VectorStoreBase;

class LLMService
{
    private ChatInterface $chat;
    private QuestionAnswering $qa;
    private EmbeddingGeneratorInterface $embeddingGenerator;
    private VectorStoreBase $vectorStore;
    private array $pendingFilePaths;

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
        $this->includeDataFromFilePaths();

        if ($this->qa instanceof QuestionAnswering) {
            return $this->qa->answerQuestion($text);
        }

        return $this->chat->generateText($text);
    }

    public function getStreamResponse($text): StreamInterface
    {
        $this->includeDataFromFilePaths();

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
        $this->embeddingGenerator = new OpenAI3SmallEmbeddingGenerator();
        $this->vectorStore = new MemoryVectorStore();

        if ($includeOwnData) {
            $this->addFilepath($this->getPersonalDataFilePath());
        }
    }

    public function addFilepath($filePath)
    {
        $this->pendingFilePaths[] = $filePath;
    }

    private function storeEmbeddings($filePath)
    {
        $reader = new FileDataReader($filePath);
        $documents = $reader->getDocuments();
        $splittedDocuments = DocumentSplitter::splitDocuments($documents, 500);
        $formattedDocuments = EmbeddingFormatter::formatEmbeddings($splittedDocuments);
        $embeddedDocuments = $this->embeddingGenerator->embedDocuments($formattedDocuments);
        $this->vectorStore->addDocuments($embeddedDocuments);
    }

    private function includeDataFromFilePaths()
    {
        if (empty($this->pendingFilePaths)) {
            return;
        }
        try {
            foreach ($this->pendingFilePaths as $key => $filePath) {
                $this->storeEmbeddings($filePath);
                unset($this->pendingFilePaths[$key]);
            }
            $this->qa = new QuestionAnswering(
                $this->vectorStore,
                $this->embeddingGenerator,
                $this->chat
            );
        } catch (\Exception $e) {
            Log::warning(sprintf("%s failed - %s", __METHOD__, $e->getMessage()), $e->getTrace());
            $this->chat->setSystemMessage('Whatever we ask you, you MUST answer "Sorry, I had a brainfreeze. Can you ask me again of your question"');
        }
    }

    private function getPersonalDataFilePath()
    {
        return Storage::path('interview_questions_answers.txt');
    }
}