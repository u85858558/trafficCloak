<?php

declare(strict_types=1);

namespace App\Service;

use App\Helper\SentenceGenerator;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverBy;
use Psr\Log\LoggerInterface;

class GoogleSearchService
{
    private const GOOGLE_BASE_URL = 'https://www.google.com';

    private readonly SentenceGenerator $sentenceGenerator;

    public function __construct(private readonly LoggerInterface $logger)
    {
        $this->sentenceGenerator = new SentenceGenerator();
        $this->fill();
    }

    public function search(): void
    {
        $keywords = $this->getKeywords();
        $this->logger->info('Searching Google for: ' . implode(' ', $keywords));

        $driver = $this->getBrowser();
        $this->performSearch($driver, $keywords);

        if ($this->shouldClickThrough()) {
            $this->clickRandomLink($driver);
        }

        $driver->quit();
    }

    private function fill(): void
    {
        $this->sentenceGenerator->loadTemplatesFromFile('data/sentence.txt');
        $this->sentenceGenerator->addWordPoolFromFile('[noun]', 'data/nouns.txt');
        $this->sentenceGenerator->addWordPoolFromFile('[object]', 'data/object.txt');
        $this->sentenceGenerator->addWordPoolFromFile('[verb]', 'data/verb.txt');
        $this->sentenceGenerator->addWordPoolFromFile('[number]', 'data/number.txt');
    }

    private function performSearch(RemoteWebDriver $driver, array $keywords): void
    {
        $driver->get(self::GOOGLE_BASE_URL);
        $searchBox = $driver->findElement(WebDriverBy::name('q'));
        $searchBox->sendKeys(implode(' ', $keywords));
        $searchBox->submit();

        sleep(2);

        $resultSelectors = [
            '#result-stats',
            '#search',
            '#rso',
        ];

        foreach ($resultSelectors as $selector) {
            try {
                $element = $driver->findElement(WebDriverBy::cssSelector($selector));
                if ($element) {
                    break;
                }
            } catch (\Exception) {
                continue;
            }
        }
    }

    /**
     * TODO: This can be modified to include actual logic if needed
     */
    private function shouldClickThrough(): bool
    {
        return true;
    }

    private function clickRandomLink(RemoteWebDriver $driver): void
    {
        $linkSelectors = [
            'a[jsname="UWckNb"]',
            'h3 > a',
            '[data-ved] h3 a',
            'div[data-ved] a[href^="http"]',
            '#search a[href^="http"]:not([href*="google.com"])',
            '#rso a[href^="http"]:not([href*="google.com"])',
            'cite + a',
            'a[ping]',
        ];

        $links = [];
        foreach ($linkSelectors as $selector) {
            try {
                $foundLinks = $driver->findElements(WebDriverBy::cssSelector($selector));
                if (! empty($foundLinks)) {
                    $links = $foundLinks;
                    break;
                }
            } catch (\Exception) {
                continue;
            }
        }

        $validLinks = array_filter($links, fn (\Facebook\WebDriver\Remote\RemoteWebElement $link): bool => $this->urlIsAbsolute($link->getAttribute('href')));

        if ($validLinks !== []) {
            $randomLink = $validLinks[array_rand($validLinks)];
            $href = $randomLink->getAttribute('href');
            $this->logger->info('Clicking link: ' . $href);

            try {
                $driver->get($href);
                $this->processClickDepth($driver, 2);
            } catch (\Exception $e) {
                $this->logger->warning('Failed to click link: ' . $e->getMessage());
            }
        } else {
            $this->logger->warning('No valid links found on search results page');
        }
    }

    private function getKeywords(): array
    {
        $sentence = $this->sentenceGenerator->generateSentence();
        return [$sentence];
    }

    private function urlIsAbsolute(string $url): bool
    {
        return (bool) parse_url($url, PHP_URL_HOST);
    }

    private function getBrowser(): RemoteWebDriver
    {
        $capabilities = DesiredCapabilities::chrome();

        $chromeOptions = new ChromeOptions();
        $chromeOptions->addArguments([
            '--no-sandbox',
            '--disable-dev-shm-usage',
            '--disable-gpu',
            '--headless',
            '--window-size=1920,1080',
        ]);

        $capabilities->setCapability(ChromeOptions::CAPABILITY, $chromeOptions);

        return RemoteWebDriver::create('http://selenium:4444', $capabilities);
    }

    private function processClickDepth(RemoteWebDriver $driver, int $depth): void
    {
        for ($i = 0; $i < $depth; $i++) {
            $links = $driver->findElements(WebDriverBy::cssSelector('a'));
            if (empty($links)) {
                break;
            }

            $randomLink = $links[array_rand($links)];
            $this->logger->info('Following link: ' . $randomLink->getAttribute('href'));
            $driver->get($randomLink->getAttribute('href'));
        }
    }
}
