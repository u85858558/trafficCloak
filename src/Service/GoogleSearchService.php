<?php

declare(strict_types=1);

namespace App\Service;

use App\Helper\SentenceGenerator;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverBy;
use Psr\Log\LoggerInterface;
use Facebook\WebDriver\Remote\RemoteWebElement;

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
        $query = trim(implode(' ', $keywords));
        $params = [
            'q'  => $query,
            'hl' => 'en',
            // 'num' => 10,  // number of results (optional)
            // 'pws' => '0', // disable personalized search (optional)
            // 'safe' => 'off', // safe search (optional)
        ];

        $url = self::GOOGLE_BASE_URL . '/search?' . http_build_query($params);
        $this->logger->info('Navigating to Google results URL: ' . $url);

        $fromUrl = $driver->getCurrentURL();
        $driver->navigate()->to($url);
        $this->logger->info('After navigation to search', [
            'from' => $fromUrl,
            'to'   => $driver->getCurrentURL(),
            'title'=> $driver->getTitle(),
        ]);

        $consentSelectors = [
            'button#L2AG',
            'form[action*="consent"] button[type="submit"]',
            'button[aria-label="Accept all"]',
            'div[role="dialog"] form [type="submit"]',
        ];
        try {
            $btn = $this->findFirstElementBySelectors($driver, $consentSelectors);
            if ($btn) {
                $btn->click();
                sleep(1);
            }
        } catch (\Throwable) {}

        $searchResultSelectors = [
            '#search',
            '#rso',
            '#result-stats',
        ];

        $ready = $this->waitForAnySelector($driver, $searchResultSelectors, 10_000);
        if (! $ready) {
            sleep(2);
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

        $links = $this->findFirstNonEmptyElementsBySelectors($driver, $linkSelectors);

        $validLinks = array_filter(
            $links,
            fn (RemoteWebElement $link): bool => $this->urlIsAbsolute($link->getAttribute('href'))
        );

        if ($validLinks !== []) {
            $randomLink = $validLinks[array_rand($validLinks)];
            $href = $randomLink->getAttribute('href');
            $this->logger->info('Clicking link: ' . $href);

            try {
                $before = $driver->getCurrentURL();
                $this->logger->info('Navigating to clicked link', ['from' => $before, 'to' => $href]);

                $driver->get($href);

                $final = $this->waitForUrlChange($driver, $before, 15_000) ?? $driver->getCurrentURL();
                $this->logger->info('After click navigation', [
                    'landed' => $final,
                    'title'  => $driver->getTitle(),
                ]);

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

        // for VNC/noVNC debugging: set SELENIUM_HEADLESS=0
        $headless = (($_SERVER['SELENIUM_HEADLESS'] ?? '1') !== '0');
        $args = [
            '--no-sandbox',
            '--disable-dev-shm-usage',
            '--disable-gpu',
            '--window-size=1920,1080',
        ];
        if ($headless) {
            $args[] = '--headless=new';
        }
        $chromeOptions->addArguments($args);

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

    private function waitForUrlChange(RemoteWebDriver $driver, string $fromUrl, int $timeoutMs = 5_000): ?string
    {
        $deadline = microtime(true) + ($timeoutMs / 1000);
        $last = $fromUrl;

        while (microtime(true) < $deadline) {
            usleep(200_000); // 200ms
            try {
                $current = $driver->getCurrentURL();
            } catch (\Throwable) {
                continue;
            }
            if ($current !== $last) {
                return $current;
            }
        }

        return null;
    }

    private function findFirstElementBySelectors(RemoteWebDriver $driver, array $selectors): ?RemoteWebElement
    {
        foreach ($selectors as $selector) {
            try {
                return $driver->findElement(WebDriverBy::cssSelector($selector));
            } catch (\Throwable) {}
        }
        return null;
    }

    private function findFirstNonEmptyElementsBySelectors(RemoteWebDriver $driver, array $selectors): array
    {
        foreach ($selectors as $selector) {
            try {
                $elements = $driver->findElements(WebDriverBy::cssSelector($selector));
                if (!empty($elements)) {
                    return $elements;
                }
            } catch (\Throwable) {}
        }
        return [];
    }

    private function waitForAnySelector(RemoteWebDriver $driver, array $selectors, int $timeoutMs): bool
    {
        $deadline = microtime(true) + ($timeoutMs / 1000);

        while (microtime(true) < $deadline) {
            foreach ($selectors as $selector) {
                try {
                    $el = $driver->findElement(WebDriverBy::cssSelector($selector));
                    if ($el) {
                        return true;
                    }
                } catch (\Throwable) {}
            }
            usleep(250_000); // 250ms
        }

        return false;
    }
}
