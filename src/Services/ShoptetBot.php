<?php

namespace ShopCode\Services;

use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Facebook\WebDriver\WebDriverWait;

/**
 * Selenium robot pro automatický import CSV fotek do Shoptetu.
 *
 * Požadavky na serveru:
 *   composer require facebook/webdriver
 *   apt install chromium-browser chromium-chromedriver
 *   chromedriver --port=9515 &   (nebo spouštěno automaticky)
 *
 * Konfigurace v config.php:
 *   SHOPTET_URL, SHOPTET_EMAIL, SHOPTET_PASSWORD
 *   CHROMEDRIVER_URL (default: http://localhost:9515)
 */
class ShoptetBot
{
    private const WAIT_TIMEOUT   = 30;  // sekund čekání na element
    private const TOTAL_TIMEOUT  = 300; // 5 minut max běh

    private RemoteWebDriver $driver;
    private array $log = [];

    public function __construct()
    {
        $options = new ChromeOptions();
        $options->addArguments([
            '--headless',
            '--no-sandbox',
            '--disable-dev-shm-usage',
            '--disable-gpu',
            '--window-size=1280,900',
            '--disable-extensions',
        ]);

        $capabilities = DesiredCapabilities::chrome();
        $capabilities->setCapability(ChromeOptions::CAPABILITY_W3C, $options);

        $chromedriverUrl = defined('CHROMEDRIVER_URL') ? CHROMEDRIVER_URL : 'http://localhost:9515';

        $this->driver = RemoteWebDriver::create($chromedriverUrl, $capabilities, 30000, 30000);
        $this->driver->manage()->timeouts()->pageLoadTimeout(60);
    }

    /**
     * Hlavní metoda — přihlášení + import CSV + odhlášení
     * @param  string $csvPath Cesta k CSV souboru
     * @return array  ['success' => bool, 'message' => string, 'log' => array]
     */
    public function importCsv(string $csvPath): array
    {
        try {
            set_time_limit(self::TOTAL_TIMEOUT);

            $this->log('Spouštím Selenium robot...');
            $this->login();
            $this->log('Přihlášení úspěšné.');

            $this->navigateToImport();
            $this->log('Navigace na stránku importu.');

            $this->uploadCsv($csvPath);
            $this->log('CSV soubor nahrán, čekám na potvrzení importu...');

            $this->confirmImport();
            $this->log('Import potvrzen.');

            $result = $this->waitForImportResult();
            $this->log('Import dokončen: ' . $result);

            return ['success' => true, 'message' => $result, 'log' => $this->log];

        } catch (\Throwable $e) {
            $this->log('CHYBA: ' . $e->getMessage());

            // Screenshot pro debug
            try {
                $screenshotPath = ROOT . '/tmp/selenium_error_' . date('YmdHis') . '.png';
                $this->driver->takeScreenshot($screenshotPath);
                $this->log('Screenshot uložen: ' . basename($screenshotPath));
            } catch (\Throwable $ignored) {}

            return ['success' => false, 'message' => $e->getMessage(), 'log' => $this->log];

        } finally {
            try { $this->driver->quit(); } catch (\Throwable $ignored) {}
        }
    }

    // ── Kroky importu ─────────────────────────────────────────

    private function login(): void
    {
        $shoptetUrl = defined('SHOPTET_URL') ? SHOPTET_URL : 'https://admin.shoptet.cz';
        $this->driver->get($shoptetUrl . '/admin/login/');

        $wait = new WebDriverWait($this->driver, self::WAIT_TIMEOUT);

        // Vyplnění přihlašovacího formuláře
        $emailField = $wait->until(
            WebDriverExpectedCondition::presenceOfElementLocated(
                WebDriverBy::name('username')
            )
        );
        $emailField->clear()->sendKeys(defined('SHOPTET_EMAIL') ? SHOPTET_EMAIL : '');

        $this->driver->findElement(WebDriverBy::name('password'))
                     ->clear()
                     ->sendKeys(defined('SHOPTET_PASSWORD') ? SHOPTET_PASSWORD : '');

        $this->driver->findElement(WebDriverBy::cssSelector('button[type=submit], input[type=submit]'))
                     ->click();

        // Počkáme na dashboard — URL se změní
        $wait->until(
            WebDriverExpectedCondition::urlContains('/admin/')
        );

        // Ověření — pokud jsme stále na login stránce, přihlášení selhalo
        if (str_contains($this->driver->getCurrentURL(), 'login')) {
            throw new \RuntimeException('Přihlášení do Shoptetu selhalo — zkontrolujte přihlašovací údaje.');
        }
    }

    private function navigateToImport(): void
    {
        // Shoptet import fotek: Katalog → Import a export → Import fotek
        $shoptetUrl = defined('SHOPTET_URL') ? SHOPTET_URL : 'https://admin.shoptet.cz';
        $this->driver->get($shoptetUrl . '/admin/products/import-photos/');

        $wait = new WebDriverWait($this->driver, self::WAIT_TIMEOUT);
        $wait->until(
            WebDriverExpectedCondition::presenceOfElementLocated(
                WebDriverBy::cssSelector('input[type=file]')
            )
        );
    }

    private function uploadCsv(string $csvPath): void
    {
        if (!file_exists($csvPath)) {
            throw new \RuntimeException("CSV soubor neexistuje: {$csvPath}");
        }

        $fileInput = $this->driver->findElement(WebDriverBy::cssSelector('input[type=file]'));
        $fileInput->sendKeys($csvPath); // Selenium zadá absolutní cestu

        usleep(500_000); // 0.5s pauza
    }

    private function confirmImport(): void
    {
        $wait = new WebDriverWait($this->driver, self::WAIT_TIMEOUT);

        // Klikneme na tlačítko pro spuštění importu
        $submitBtn = $wait->until(
            WebDriverExpectedCondition::elementToBeClickable(
                WebDriverBy::cssSelector('button[type=submit], input[type=submit], .btn-primary')
            )
        );
        $submitBtn->click();
    }

    private function waitForImportResult(): string
    {
        $wait = new WebDriverWait($this->driver, self::WAIT_TIMEOUT * 2); // 60s pro import

        // Čekáme na success nebo error zprávu
        try {
            $element = $wait->until(
                WebDriverExpectedCondition::presenceOfElementLocated(
                    WebDriverBy::cssSelector('.alert-success, .alert-danger, .flash-message, [class*="success"], [class*="error"]')
                )
            );
            return $element->getText();
        } catch (\Throwable $e) {
            // Zkusíme načíst text stránky jako fallback
            return 'Import dokončen (výsledek nelze přesně určit)';
        }
    }

    private function log(string $message): void
    {
        $this->log[] = '[' . date('H:i:s') . '] ' . $message;
    }

    public function getLogs(): array
    {
        return $this->log;
    }
}
