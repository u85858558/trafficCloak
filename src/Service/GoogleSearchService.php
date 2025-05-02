<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\Panther\Client;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverElement;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Symfony\Component\Panther\Browser\RemoteBrowser;

class GoogleSearchService
{
    private LoggerInterface $logger;
    private string $dataDir;

    public function __construct(LoggerInterface $logger, string $dataDir)
    {
        $this->logger = $logger;
        $this->dataDir = $dataDir;
    }

    private function fill()
    {
        // This method seems unused.  Leaving it here in case it's used elsewhere.
    }

    public function search(): void
    {
        $keywords = $this->getKeywords();
        $this->logSearch($keywords);

        try {
            $driver = $this->getBrowser();
            $this->performSearch($driver, $keywords);
        } catch (\Exception $e) {
            $this->logger->error('Google search failed: ' . $e->getMessage());
        }
    }

    private function logSearch(array $keywords): void
    {
        $this->logger->info('Searching Google for: ' . implode(', ', $keywords));
    }

    private function performSearch($driver, array $keywords): void
    {
        $driver->manage()->window()->maximize(); // Maximize the window
        $driver->get('https://www.google.com');

        // Wait for the search input field to be visible and interactable
        $searchField = (new \Facebook\WebDriver\Remote\WebDriverWait($driver, 10))
            ->until(
                WebDriverExpectedCondition::visibilityOfElementLocated(WebDriverBy::name('q'))
            );

        // Type the search query
        $searchField->sendKeys(implode(' ', $keywords));

        // Wait for the search button to be clickable
        $searchButton = (new \Facebook\WebDriver\Remote\WebDriverWait($driver, 10))
            ->until(
                WebDriverExpectedCondition::elementToBeClickable(WebDriverBy::name('btnK'))
            );

        // Click the search button
        $searchButton->click();

        // Wait for search results to load (e.g., check for a specific element)
        (new \Facebook\WebDriver\Remote\WebDriverWait($driver, 10))
            ->until(
                WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::id('search'))
            );

        $this->clickRandomLink($driver);
        $this->processClickDepth($driver, 2); // Example click depth
    }

    private function shouldClickThrough(): bool
    {
        // Implement your logic to determine if a click-through is needed
        return true;
    }

    private function clickRandomLink($driver): void
    {
        try {
            // Wait for the search results to load
            (new \Facebook\WebDriver\Remote\WebDriverWait($driver, 10))
                ->until(
                    WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::cssSelector('div.g'))
                );

            // Find all search result links
            $links = $driver->findElements(WebDriverBy::cssSelector('div.g a'));

            if (empty($links)) {
                $this->logger->warning('No search result links found.');
                return;
            }

            // Select a random link
            $randomIndex = array_rand($links);
            $randomLink = $links[$randomIndex];

            // Wait for the link to be clickable
            (new \Facebook\WebDriver\Remote\WebDriverWait($driver, 10))
                ->until(
                    WebDriverExpectedCondition::elementToBeClickable($randomLink)
                );

            $this->logger->info('Clicking on a random link.');
            $randomLink->click();

        } catch (\Exception $e) {
            $this->logger->error('Error clicking random link: ' . $e->getMessage());
        }
    }

    private function getKeywords(): array
    {
        // Implement your logic to get search keywords
        return ['example', 'search', 'term']; // Replace with your actual keywords
    }

    private function urlIsAbsolute(string $url): bool
    {
        return strpos($url, '//') !== false;
    }

    private function getBrowser(): RemoteWebDriver
    {
        $host = 'http://localhost:4444'; // Default Selenium server address
        $capabilities = \Facebook\WebDriver\Remote\DesiredCapabilities::chrome();
        return RemoteWebDriver::create($host, $capabilities);
    }

    private function processClickDepth(RemoteWebDriver $driver, int $depth): void
    {
        if ($depth <= 0) {
            return;
        }

        try {
            // Wait for links on the current page
            (new \Facebook\WebDriver\Remote\WebDriverWait($driver, 10))
                ->until(
                    WebDriverExpectedCondition::presenceOfAllElementsLocatedBy(WebDriverBy::cssSelector('a'))
                );

            $links = $driver->findElements(WebDriverBy::cssSelector('a'));

            if (empty($links)) {
                $this->logger->info('No links found on the page.');
                return;
            }

            $randomIndex = array_rand($links);
            $randomLink = $links[$randomIndex];

            // Wait for the link to be clickable
            (new \Facebook\WebDriver\Remote\WebDriverWait($driver, 10))
                ->until(
                    WebDriverExpectedCondition::elementToBeClickable($randomLink)
                );

            $this->logger->info('Clicking on a link for click depth.');
            $randomLink->click();

            // Recursive call for the next level
            $this->processClickDepth($driver, $depth - 1);

        } catch (\Exception $e) {
            $this->logger->error('Error processing click depth: ' . $e->getMessage());
        }
    }
}
