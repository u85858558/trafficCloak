<?php

declare(strict_types=1);

namespace App\Helper;

use Exception;

class SentenceGenerator
{
    private array $templates = [];
    private array $wordPools = [];

    public function addTemplate($template)
    {
        $this->templates[] = $template;
    }

    /**
     * @throws Exception
     */
    public function loadTemplatesFromFile(string $filePath): void
    {
        if (!file_exists($filePath)) {
            throw new Exception("File not found: " . $filePath);
        }

        $lines = file($filePath, FILE_IGNORE_NEW_LINES);

        foreach ($lines as $line) {
            $this->addTemplate($line);
        }
    }

    /**
     * @throws Exception
     */
    public function addWordPoolFromFile($placeholder, $filePath) {
        if (!file_exists($filePath)) {
            throw new Exception("File not found: " . $filePath);
        }

        // Read the file and split its contents into an array of words
        $words = file($filePath, FILE_IGNORE_NEW_LINES);
        $this->wordPools[$placeholder] = $words;
    }

    /**
     * @throws Exception
     */
    public function generateSentence()
    {
        if (empty($this->templates)) {
            throw new Exception('No available.');
        }

        $template = $this->templates[array_rand($this->templates)];

        foreach ($this->wordPools as $placeholder => $words) {
            $randomWord = $words[array_rand($words)];
            $template = str_replace($placeholder, $randomWord, $template);
        }

        return $template;
    }

    /**
     * @throws Exception
     */
    public function generateMultipleSentences($count = 1): array
    {
        $sentence = [];
        for ($i = 0; $i < $count; $i++) {
            $sentence[] = $this->generateSentence();
        }
        return $sentence;
    }
}