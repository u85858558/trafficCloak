<?php

namespace App\Helper;

use Symfony\Component\Filesystem\Filesystem;

class TrafficCloakHelper
{
    public function __construct(private readonly string $dataDir)
    {
    }

    public function generateWordlist(string $wordFile, int $minLength = 5, int $maxLength = 20): array
    {
        $filePath = $this->dataDir . '/' . $wordFile;
        if (! file_exists($filePath)) {
            throw new \InvalidArgumentException("Wordlist file not found: {$filePath}");
        }

        $words = [];
        $pattern = '/^.{' . $minLength . ',' . $maxLength . '}$/';

        foreach (file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $word = trim($line);
            if (preg_match($pattern, $word)) {
                $words[] = $word;
            }
        }

        return $words;
    }

    public function getRandomWord(array $words): string
    {
        return $words[array_rand($words)];
    }

    public function getKeywords(int $num = 3): string
    {
        $adjectives = $this->generateWordlist('adjectives.txt');
        $nouns = $this->generateWordlist('nouns.txt');
        $verbs = $this->generateWordlist('verb.txt');

        $allWords = [$adjectives, $nouns, $verbs];
        $keywords = [];

        for ($i = 0; $i < $num; $i++) {
            $keywords[] = $this->getRandomWord($allWords[array_rand($allWords)]);
        }

        return implode(' ', $keywords);
    }

    public function getRandomLine(string $file): string
    {
        if (! file_exists($file)) {
            throw new \InvalidArgumentException("File not found: {$file}");
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        return $lines[array_rand($lines)];
    }

    public function urlIsAbsolute(string $url): bool
    {
        return (bool) parse_url($url, PHP_URL_SCHEME);
    }

    public function processClickDepth($browser, $clickDepth = null): void
    {
        $clickCount = is_numeric($clickDepth) ? (int) $clickDepth : random_int(...explode('..', (string) $clickDepth));

        for ($i = 0; $i < $clickCount; $i++) {
            $links = $browser->findElements(WebDriverBy::tagName('a'));
            if (empty($links)) {
                break;
            }

            $link = $links[array_rand($links)]->getAttribute('href');
            if (! $this->urlIsAbsolute($link)) {
                $link = $browser->getCurrentURL() . $link;
            }

            try {
                $browser->get($link);
                if ($i + 1 < $clickCount) {
                    usleep(random_int(500000, 2000000)); // Sleep between 0.5 and 2 seconds
                }
            } catch (\Exception) {
            }
        }
    }

    public function getBrowser(): \Symfony\Component\Panther\Browser
    {
        $filesystem = new Filesystem();
        $chromeDriverPath = $this->dataDir . '/chromedriver';

        if (! $filesystem->exists($chromeDriverPath)) {
            throw new \RuntimeException("Chromedriver not found: {$chromeDriverPath}");
        }

        $browser = PantherTestCase::createPantherClient([
            'browser' => PantherTestCase::CHROME,
            'chromeDriverBinary' => $chromeDriverPath,
        ]);

        $userAgent = $this->getRandomLine($this->dataDir . '/user-agents.txt');
        $browser->getWebDriver()->manage()->addCookie([
            'name' => 'user-agent',
            'value' => $userAgent,
        ]);

        return $browser;
    }
}
