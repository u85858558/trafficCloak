<?php
declare(strict_types=1);

namespace App\Service;

use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\WebDriverBy;
use Psr\Log\LoggerInterface;

class GoogleSearchService
{
    private const GOOGLE_BASE_URL = 'https://www.google.com';
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function search(): void
    {
        $keywords = $this->getKeywords(3);
        $this->logSearch($keywords);

        $driver = $this->getBrowser();
        $this->performSearch($driver, $keywords);

        if ($this->shouldClickThrough()) {
            $this->clickRandomLink($driver);
        }

        $driver->quit();
    }

    private function logSearch(array $keywords): void
    {
        $this->logger->info('Searching Google for: ' . implode(' ', $keywords));
    }

    private function performSearch($driver, array $keywords): void
    {
        $driver->get(self::GOOGLE_BASE_URL);
        $searchBox = $driver->findElement(WebDriverBy::cssSelector('input[name=q]'));
        $searchBox->sendKeys(implode(' ', $keywords));
        $searchBox->submit();

        $resultsText = $driver->findElement(WebDriverBy::id('result-stats'))->getText();
        $this->logger->debug('Search results: ' . $resultsText);
    }

    /**
     * TODO: This can be modified to include actual logic if needed
     * @return bool
     */
    private function shouldClickThrough(): bool
    {
        return true;
    }

    private function clickRandomLink($driver): void
    {
        $links = $driver->findElements(WebDriverBy::cssSelector('h3 > a'));
        $validLinks = array_filter($links, fn($link) => $this->urlIsAbsolute($link->getAttribute('href')));

        if (!empty($validLinks)) {
            $randomLink = $validLinks[array_rand($validLinks)];
            $this->logger->info('Clicking link: ' . $randomLink->getAttribute('href'));
            $driver->get($randomLink->getAttribute('href'));

            $this->processClickDepth($driver, 2);
        }
    }

    /**
     * Replace with actual logic to retrieve keywords
     * @param int $count
     * @return string[]
     */
    private function getKeywords(int $count): array
    {
        return ['keyword1', 'keyword2', 'keyword3'];
    }

    private function urlIsAbsolute(string $url): bool
    {
        return (bool)parse_url($url, PHP_URL_HOST);
    }

    private function getBrowser(): RemoteWebDriver
    {
        return RemoteWebDriver::create('http://selenium:4444', DesiredCapabilities::chrome());
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