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

    // Maximum number of links to follow
    private int $maxLinksPerPage = 10;

    public function __construct(private readonly LoggerInterface $logger)
    {
        // Maximum links to consider per page
    }

    /**
     * Start crawling from a random Wikipedia article
     */
    public function crawl(): void
    {
        $driver = $this->getBrowser();

        try {
            $this->logger->info('Starting Wikipedia random article crawl');

            // Start with a random article
            $this->visitRandomArticle($driver);

            // Follow internal links naturally
            $this->followInternalLinks($driver, $this->maxDepth);

        } catch (\Exception $e) {
            $this->logger->error('Wikipedia crawl failed: ' . $e->getMessage());
        } finally {
            $driver->quit();
        }
    }

    /**
     * Set maximum crawl depth
     */
    public function setMaxDepth(int $depth): void
    {
        $this->maxDepth = max(1, $depth);
    }

    /**
     * Set maximum links to consider per page
     */
    public function setMaxLinksPerPage(int $maxLinks): void
    {
        $this->maxLinksPerPage = max(1, $maxLinks);
    }

    private function visitRandomArticle(RemoteWebDriver $driver): void
    {
        $driver->get(self::RANDOM_ARTICLE_URL);

        // Wait for page to load
        sleep(2);

        $currentUrl = $driver->getCurrentURL();
        $pageTitle = $driver->getTitle();

        $this->logger->info('Visited random article: ' . $pageTitle);
        $this->logger->info('Article URL: ' . $currentUrl);

        // Simulate reading time
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

        // Select a random internal link
        $randomLink = $internalLinks[array_rand($internalLinks)];
        $linkUrl = $randomLink->getAttribute('href');
        $linkText = trim((string) $randomLink->getText());

        $this->logger->info('Following link: "' . $linkText . '" -> ' . $linkUrl);

        try {
            $driver->get($linkUrl);

            // Wait for page to load
            sleep(2);

            $pageTitle = $driver->getTitle();
            $this->logger->info('Arrived at: ' . $pageTitle);

            // Simulate reading time
            $readingTime = random_int(2, 6);
            sleep($readingTime);

            // Continue crawling from this page
            $this->followInternalLinks($driver, $remainingDepth - 1);

        } catch (\Exception $e) {
            $this->logger->warning('Failed to follow link: ' . $e->getMessage());
            // Try to continue with remaining depth
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
                    break; // Use the first selector that finds links
                }
            } catch (\Exception) {
                continue;
            }
        }

        // Filter out unwanted links and limit the number
        $validLinks = array_filter($allLinks, function (\Facebook\WebDriver\Remote\RemoteWebElement $link): bool {
            $href = $link->getAttribute('href');
            $text = trim($link->getText());

            // Skip empty text links, file links, category links, etc.
            return $text !== '' && $text !== '0' &&
                   ! $this->isUnwantedLink($href) &&
                   strlen($text) > 2 && // Avoid single character links
                   strlen($text) < 100; // Avoid very long link texts
        });

        // Shuffle and limit the links to make selection more natural
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
