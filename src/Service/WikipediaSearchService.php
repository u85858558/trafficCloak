<?php

declare(strict_types=1);

namespace App\Service;

use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverBy;
use Psr\Log\LoggerInterface;

class WikipediaSearchService
{
    private const RANDOM_ARTICLE_URL = 'https://en.wikipedia.org/wiki/Special:Random';
    private int $maxDepth = 5;
    private int $maxLinksPerPage = 10;

    public function __construct(private readonly LoggerInterface $logger)
    {}

    public function crawl(): void
    {
        $driver = $this->getBrowser();

        try {
            $this->logger->info('Starting Wikipedia random article crawl');

            $this->visitRandomArticle($driver);
            $this->followInternalLinks($driver, $this->maxDepth);
        } catch (\Exception $e) {
            $this->logger->error('Wikipedia crawl failed: ' . $e->getMessage());
        } finally {
            $driver->quit();
        }
    }

    public function setMaxDepth(int $depth): void
    {
        $this->maxDepth = max(1, $depth);
    }

    public function setMaxLinksPerPage(int $maxLinks): void
    {
        $this->maxLinksPerPage = max(1, $maxLinks);
    }

    private function visitRandomArticle(RemoteWebDriver $driver): void
    {
        $driver->get(self::RANDOM_ARTICLE_URL);

        sleep(2);

        $currentUrl = $driver->getCurrentURL();
        $pageTitle = $driver->getTitle();

        $this->logger->info('Visited random article: ' . $pageTitle);
        $this->logger->info('Article URL: ' . $currentUrl);

        $readingTime = random_int(3, 8);
        $this->logger->debug('Simulating reading for ' . $readingTime . ' seconds');
        sleep($readingTime);
    }

    private function followInternalLinks(RemoteWebDriver $driver, int $remainingDepth): void
    {
        if ($remainingDepth <= 0) {
            $this->logger->info('Reached maximum crawl depth');
            return;
        }

        $internalLinks = $this->getInternalLinks($driver);

        if ($internalLinks === []) {
            $this->logger->warning('No internal links found on current page');
            return;
        }

        $randomLink = $internalLinks[array_rand($internalLinks)];
        $linkUrl = $randomLink->getAttribute('href');
        $linkText = trim((string) $randomLink->getText());

        $this->logger->info('Following link: "' . $linkText . '" -> ' . $linkUrl);

        try {
            $driver->get($linkUrl);

            sleep(2);

            $pageTitle = $driver->getTitle();
            $this->logger->info('Arrived at: ' . $pageTitle);

            // Simulate reading time
            $readingTime = random_int(2, 6);
            sleep($readingTime);

            $this->followInternalLinks($driver, $remainingDepth - 1);

        } catch (\Exception $e) {
            $this->logger->warning('Failed to follow link: ' . $e->getMessage());
            $this->followInternalLinks($driver, $remainingDepth - 1);
        }
    }

    private function getInternalLinks(RemoteWebDriver $driver): array
    {
        $linkSelectors = [
            '#mw-content-text a[href^="/wiki/"]:not([href*=":"]):not([href*="#"])', // Main content links
            '.mw-parser-output a[href^="/wiki/"]:not([href*=":"]):not([href*="#"])', // Parser output links
            '#bodyContent a[href^="/wiki/"]:not([href*=":"]):not([href*="#"])', // Body content links
        ];

        $allLinks = [];

        foreach ($linkSelectors as $selector) {
            try {
                $links = $driver->findElements(WebDriverBy::cssSelector($selector));
                $allLinks = array_merge($allLinks, $links);

                if (! empty($links)) {
                    $this->logger->debug('Found ' . count($links) . ' links with selector: ' . $selector);
                    break;
                }
            } catch (\Exception) {
                continue;
            }
        }

        $validLinks = array_filter($allLinks, function (\Facebook\WebDriver\Remote\RemoteWebElement $link): bool {
            $href = $link->getAttribute('href');
            $text = trim($link->getText());

            // Skip empty text links, file links, category links, etc.
            return $text !== '' && $text !== '0' &&
                   ! $this->isUnwantedLink($href) &&
                   strlen($text) > 2 && // Avoid single character links
                   strlen($text) < 100; // Avoid very long link texts
        });

        shuffle($validLinks);
        return array_slice($validLinks, 0, $this->maxLinksPerPage);
    }

    private function isUnwantedLink(string $href): bool
    {
        $unwantedPatterns = [
            '/wiki/File:',
            '/wiki/Category:',
            '/wiki/Template:',
            '/wiki/Help:',
            '/wiki/Wikipedia:',
            '/wiki/Special:',
            '/wiki/Talk:',
            '/wiki/User:',
            '/wiki/MediaWiki:',
        ];

        foreach ($unwantedPatterns as $pattern) {
            if (str_contains($href, $pattern)) {
                return true;
            }
        }

        return false;
    }

    private function getBrowser(): RemoteWebDriver
    {
        $capabilities = DesiredCapabilities::chrome();

        $chromeOptions = new ChromeOptions();
        $chromeOptions->addArguments([
            '--no-sandbox',
            '--disable-dev-shm-usage',
            '--disable-gpu',
            '--headless', // Run in headless mode
            '--window-size=1920,1080',
            '--user-agent=Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        ]);

        $capabilities->setCapability(ChromeOptions::CAPABILITY, $chromeOptions);

        return RemoteWebDriver::create('http://selenium:4444', $capabilities);
    }
}
