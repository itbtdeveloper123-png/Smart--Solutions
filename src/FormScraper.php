<?php
/**
 * FormScraper.php
 * Class សម្រាប់ Scrape និង Parse យកសំណួរពី Google Form
 */

class FormScraper
{
    private string $formUrl;
    private string $rawHtml;
    private string $cookieJarPath;

    /**
     * Constructor - ទទួល Google Form URL
     */
    public function __construct(string $formUrl)
    {
        $this->formUrl = $this->sanitizeUrl($formUrl);
        // Create a unique cookie jar file for this session
        $this->cookieJarPath = sys_get_temp_dir() . '/gf_cookies_' . md5($formUrl . time()) . '.txt';
    }

    public function __destruct()
    {
        // Clean up cookie jar
        if (isset($this->cookieJarPath) && file_exists($this->cookieJarPath)) {
            @unlink($this->cookieJarPath);
        }
    }

    // -------------------------------------------------------
    // Public Methods
    // -------------------------------------------------------

    /**
     * Fetch HTML content ពី Google Form URL
     * @return string  Raw HTML
     * @throws RuntimeException
     */
    public function fetchForm(): string
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $this->formUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) '
                                     . 'AppleWebKit/537.36 (KHTML, like Gecko) '
                                     . 'Chrome/120.0.0.0 Safari/537.36',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_COOKIEJAR      => $this->cookieJarPath,  // Save cookies
            CURLOPT_COOKIEFILE     => $this->cookieJarPath,  // Send cookies (for redirects)
        ]);

        $html  = curl_exec($ch);
        $error = curl_error($ch);
        $code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($error) {
            throw new RuntimeException("cURL Error: $error");
        }
        if ($code !== 200) {
            throw new RuntimeException("HTTP Error: Status code $code returned.");
        }

        $this->rawHtml = $html;
        return $html;
    }

    /**
     * Parse សំណួរទាំងអស់ពី HTML ដែល Fetch មក
     * @return array  Array of question objects
     */
    public function parseQuestions(): array
    {
        if (empty($this->rawHtml)) {
            $this->fetchForm();
        }

        $questions = [];

        // Google Form stores data as JSON inside a <script> tag
        if (preg_match('/var FB_PUBLIC_LOAD_DATA_\s*=\s*(\[.*?\]);\s*<\/script>/s', $this->rawHtml, $match)) {
            $data = json_decode($match[1], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $questions = $this->extractFromJsonData($data);
            }
        }

        // Fallback: parse HTML directly if JSON not found
        if (empty($questions)) {
            $questions = $this->extractFromHtml($this->rawHtml);
        }

        return $questions;
    }

    /**
     * យក Form Title ពី HTML
     */
    public function getFormTitle(): string
    {
        if (preg_match('/<title>(.*?)<\/title>/is', $this->rawHtml, $m)) {
            return html_entity_decode(strip_tags($m[1]), ENT_QUOTES, 'UTF-8');
        }
        return 'Google Form';
    }

    /**
     * យក Form Description ពី HTML
     */
    public function getFormDescription(): string
    {
        if (preg_match('/<meta name="description" content="(.*?)"/is', $this->rawHtml, $m)) {
            return html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
        }
        return '';
    }

    /**
     * Extract FBZX (CSRF token) ដែល Google Form ត្រូវការសម្រាប់ submit
     */
    public function getFbzxToken(): string
    {
        // fbzx is in a hidden input: <input type="hidden" name="fbzx" value="...">
        if (preg_match('/name="fbzx"\s+value="([^"]+)"/i', $this->rawHtml, $m)) {
            return $m[1];
        }
        // Try alternative pattern
        if (preg_match('/"fbzx","([^"]+)"/i', $this->rawHtml, $m)) {
            return $m[1];
        }
        // Extract from FB_PUBLIC_LOAD_DATA_ if present (last element often contains tokens)
        if (preg_match('/var FB_PUBLIC_LOAD_DATA_\s*=\s*(\[.*?\]);\s*<\/script>/s', $this->rawHtml, $match)) {
            $data = json_decode($match[1], true);
            if ($data && isset($data[1][2]) && is_string($data[1][2])) {
                return $data[1][2];
            }
        }
        return '';
    }

    /**
     * Get all hidden form fields needed for submission
     */
    public function getFormHiddenFields(): array
    {
        $fields = [];
        $fbzx = $this->getFbzxToken();
        if ($fbzx) {
            $fields['fbzx'] = $fbzx;
        }
        return $fields;
    }

    /**
     * Get the raw HTML (for proxy/debug purposes)
     */
    public function getRawHtml(): string
    {
        return $this->rawHtml;
    }

    /**
     * Get cookie jar path (for passing to submit endpoint)
     */
    public function getCookieJarPath(): string
    {
        return $this->cookieJarPath;
    }

    /**
     * Get cookies as string (for passing through API)
     */
    public function getCookiesString(): string
    {
        if (!file_exists($this->cookieJarPath)) {
            return '';
        }
        $content = file_get_contents($this->cookieJarPath);
        if (!$content) return '';

        // Parse Netscape cookie format into simple key=value pairs
        $cookies = [];
        foreach (explode("\n", $content) as $line) {
            $line = trim($line);
            if (empty($line) || $line[0] === '#') continue;
            $parts = explode("\t", $line);
            if (count($parts) >= 7) {
                $cookies[] = $parts[5] . '=' . $parts[6];
            }
        }
        return implode('; ', $cookies);
    }

    // -------------------------------------------------------
    // Private Helpers
    // -------------------------------------------------------

    /**
     * Extract questions ពី Google Form JSON data structure
     */
    private function extractFromJsonData(array $data): array
    {
        $questions = [];

        // Google Form JSON: $data[1][1] contains question items
        $items = $data[1][1] ?? [];

        foreach ($items as $item) {
            $questionText = $item[1] ?? '';
            $questionType = $item[3] ?? null;  // 0=short, 1=paragraph, 2=MCQ, 3=checkbox, 4=dropdown

            if (empty($questionText)) {
                continue;
            }

            $q = [
                'id'       => $item[0] ?? uniqid('q_'),
                'entryId'  => $this->extractEntryId($item),
                'question' => $questionText,
                'type'     => $this->mapQuestionType($questionType),
                'required' => isset($item[4]) && $item[4] !== null,
                'choices'  => [],
            ];

            // Extract choices for MCQ / Checkbox / Dropdown
            if (isset($item[3][0]) && is_array($item[3][0])) {
                foreach ($item[3][0] as $choice) {
                    if (isset($choice[0])) {
                        $q['choices'][] = $choice[0];
                    }
                }
            }

            $questions[] = $q;
        }

        return $questions;
    }

    /**
     * Extract Google Form entry ID from item data
     * Entry IDs look like: 1234567890 → maps to entry.1234567890 in form submission
     */
    private function extractEntryId(array $item): ?string
    {
        // Entry ID is typically at $item[4][0][0]
        if (isset($item[4][0][0]) && is_scalar($item[4][0][0])) {
            return (string) $item[4][0][0];
        }
        return null;
    }

    /**
     * Get the form submission URL (formResponse endpoint)
     */
    public function getFormResponseUrl(): string
    {
        // Convert viewform URL to formResponse URL
        // https://docs.google.com/forms/d/e/FORM_ID/viewform
        // → https://docs.google.com/forms/d/e/FORM_ID/formResponse
        if (preg_match('#/d/e/([^/]+)/#', $this->formUrl, $m)) {
            return 'https://docs.google.com/forms/d/e/' . $m[1] . '/formResponse';
        }
        // Fallback: try to extract form ID from other URL patterns
        if (preg_match('#/d/([^/]+)/#', $this->formUrl, $m)) {
            return 'https://docs.google.com/forms/d/' . $m[1] . '/formResponse';
        }
        return '';
    }

    /**
     * Get the form embed URL for iframe
     */
    public function getEmbedUrl(): string
    {
        // Ensure URL is viewform with embedded=true if needed
        $url = $this->formUrl;
        if (!str_contains($url, '?')) {
            $url .= '?embedded=true';
        } elseif (!str_contains($url, 'embedded=true')) {
            $url .= '&embedded=true';
        }
        return $url;
    }

    /**
     * Fallback: Extract ពី HTML DOM ដោយប្រើ Regex
     */
    private function extractFromHtml(string $html): array
    {
        $questions = [];
        $dom = new DOMDocument();
        @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
        $xpath = new DOMXPath($dom);

        // Try common Google Form question selectors
        $nodes = $xpath->query('//*[contains(@class,"freebirdFormviewerViewItemsItemItemTitle")]');
        foreach ($nodes as $i => $node) {
            $questions[] = [
                'id'       => 'q_' . $i,
                'question' => trim($node->textContent),
                'type'     => 'text',
                'required' => false,
                'choices'  => [],
            ];
        }

        return $questions;
    }

    /**
     * Map Google Form type integer → readable string
     */
    private function mapQuestionType(?int $type): string
    {
        return match ($type) {
            0       => 'short_text',
            1       => 'paragraph',
            2       => 'multiple_choice',
            3       => 'checkbox',
            4       => 'dropdown',
            5       => 'linear_scale',
            7       => 'grid',
            9       => 'date',
            10      => 'time',
            default => 'text',
        };
    }

    /**
     * Sanitize និង Validate Google Form URL
     */
    private function sanitizeUrl(string $url): string
    {
        $url = trim($url);

        // Convert viewform URL → proper URL
        if (!preg_match('/^https?:\/\//', $url)) {
            $url = 'https://' . $url;
        }

        // Ensure it's a Google Forms URL
        if (!preg_match('/docs\.google\.com\/forms/', $url)) {
            throw new InvalidArgumentException('Invalid Google Form URL provided.');
        }

        return filter_var($url, FILTER_SANITIZE_URL);
    }
}
