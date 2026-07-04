<?php
/**
 * DeepSeekSolver.php
 * Class សម្រាប់ហៅទៅកាន់ DeepSeek API ដើម្បីបំពេញចម្លើយ
 */

require_once __DIR__ . '/../config/ai_config.php';

class DeepSeekSolver
{
    private string $apiKey;
    private string $apiUrl;
    private string $model;

    public function __construct()
    {
        $this->apiKey = DEEPSEEK_API_KEY;
        $this->apiUrl = DEEPSEEK_API_URL;
        $this->model  = DEEPSEEK_MODEL;
    }

    // -------------------------------------------------------
    // Public Methods
    // -------------------------------------------------------

    /**
     * ដោះស្រាយសំណួរទាំងអស់ពី Google Form
     *
     * @param  array  $questions  Array of questions from FormScraper::parseQuestions()
     * @param  string $context    Optional — ព័ត៌មានបន្ថែម (ប្រធានបទ, ភាសា ...)
     * @return array              $questions ដែលបន្ថែម 'answer' field
     */
    public function solveAll(array $questions, string $context = ''): array
    {
        foreach ($questions as &$q) {
            $q['answer'] = $this->solveOne($q, $context);
        }
        unset($q);

        return $questions;
    }

    /**
     * ដោះស្រាយសំណួរតែមួយ
     *
     * @param  array  $question  Single question array
     * @param  string $context
     * @return string            ចម្លើយ
     */
    public function solveOne(array $question, string $context = ''): string
    {
        $prompt = $this->buildPrompt($question, $context);

        try {
            $response = $this->callApi($prompt);
            return $this->parseResponse($response, $question);
        } catch (Exception $e) {
            $msg = $e->getMessage();
            // Always show error prefix so user knows it's an API issue
            if (stripos($msg, '401') !== false || stripos($msg, 'unauthorized') !== false || stripos($msg, 'authentication') !== false) {
                return '⚠️ API Key មិនត្រឹមត្រូវ — សូមពិនិត្យ config/ai_config.php';
            }
            if (stripos($msg, 'timeout') !== false || stripos($msg, 'timed out') !== false) {
                return '⚠️ API Timeout — សូមសាកល្បងម្តងទៀត';
            }
            return DEBUG_MODE ? '❌ ' . $msg : '⚠️ API Error — សូមបើក DEBUG_MODE ដើម្បីមើលព័ត៌មានលម្អិត';
        }
    }

    // -------------------------------------------------------
    // Private Helpers
    // -------------------------------------------------------

    /**
     * Build prompt សម្រាប់ DeepSeek API
     */
    private function buildPrompt(array $question, string $context): string
    {
        $type    = $question['type']     ?? 'text';
        $qText   = $question['question'] ?? '';
        $choices = $question['choices']  ?? [];

        $choiceStr = '';
        if (!empty($choices)) {
            $choiceStr = "\nជម្រើស (Choices):\n";
            foreach ($choices as $i => $c) {
                $choiceStr .= "  " . ($i + 1) . ". $c\n";
            }
        }

        $contextStr = $context ? "\nបរិបទ (Context): $context" : '';

        $instruction = match ($type) {
            'multiple_choice' => 'ជ្រើសរើស ចម្លើយ មួយ ដែលត្រឹមត្រូវបំផុតពីជម្រើសខាងក្រោម។ ឆ្លើយតែជម្រើសនោះប៉ុណ្ណោះ។',
            'checkbox'        => 'ជ្រើសរើស ចម្លើយ ដែលត្រឹមត្រូវ (អាចជ្រើសច្រើន) ពីជម្រើសខាងក្រោម។ ដោយឡែកនីមួយៗដោយក្បៀស។',
            'dropdown'        => 'ជ្រើស ចម្លើយ ត្រឹមត្រូវ ពី dropdown ខាងក្រោម។',
            'linear_scale'    => 'ផ្តល់ ចម្លើយ ជាលេខ (scale) ដែលសមស្រប។',
            'date'            => 'ផ្តល់ ចម្លើយ ជាទម្រង់ YYYY-MM-DD ។',
            'time'            => 'ផ្តល់ ចម្លើយ ជាទម្រង់ HH:MM ។',
            default           => 'ផ្តល់ ចម្លើយ ខ្លីៗ និងច្បាស់លាស់។',
        };

        return <<<PROMPT
អ្នកជា AI Assistant ដែលឆ្លាតវាងវៃ។ ចូរឆ្លើយសំណួរ Google Form ខាងក្រោម។
{$contextStr}

សំណួរ: {$qText}
{$choiceStr}
សូម{$instruction}
ឆ្លើយជាភាសា English ឬ Khmer ដូចជំនួសដើម។ ឆ្លើយសង្ខេប ច្បាស់លាស់ — មិនត្រូវបន្ថែម explanation ឬ formatting ។
PROMPT;
    }

    /**
     * ហៅ DeepSeek Chat Completions API
     */
    private function callApi(string $prompt): array
    {
        $payload = json_encode([
            'model'       => $this->model,
            'messages'    => [
                [
                    'role'    => 'system',
                    'content' => 'You are a helpful assistant that answers Google Form questions accurately and concisely.',
                ],
                [
                    'role'    => 'user',
                    'content' => $prompt,
                ],
            ],
            'max_tokens'  => DEEPSEEK_MAX_TOKENS,
            'temperature' => DEEPSEEK_TEMPERATURE,
        ]);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $this->apiUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_TIMEOUT        => DEEPSEEK_TIMEOUT,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ],
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $result = curl_exec($ch);
        $error  = curl_error($ch);
        $code   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($error) {
            throw new RuntimeException("API cURL Error: $error");
        }

        $decoded = json_decode($result, true);

        if ($code !== 200) {
            $msg = $decoded['error']['message'] ?? "HTTP $code";
            throw new RuntimeException("DeepSeek API Error: $msg");
        }

        return $decoded;
    }

    /**
     * Parse ចម្លើយ ចេញពី API response
     */
    private function parseResponse(array $response, array $question): string
    {
        $content = $response['choices'][0]['message']['content'] ?? '';
        $content = trim($content);

        // For multiple choice: match against known choices
        if (!empty($question['choices']) && in_array($question['type'], ['multiple_choice', 'dropdown'])) {
            foreach ($question['choices'] as $choice) {
                if (stripos($content, $choice) !== false) {
                    return $choice;
                }
            }
        }

        return $content ?: 'N/A';
    }
}
