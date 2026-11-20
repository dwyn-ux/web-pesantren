<?php
/**
 * API: Generate Artikel dengan AI
 * Mendukung OpenAI, DeepSeek, OpenRouter, Gemini, dan Custom Endpoint
 */
define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/config/constants.php';
require_once ROOT_PATH . '/config/session.php';
require_once ROOT_PATH . '/config/database.php';
require_once ROOT_PATH . '/includes/functions.php';
require_once ROOT_PATH . '/includes/auth.php';

// ── Autentikasi & validasi request ──────────────────────────
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Metode tidak diizinkan.'], 405);
}
validateCsrf();

// ── Ambil input dari form ───────────────────────────────────
$provider = sanitizeString($_POST['provider'] ?? '');
$topic    = sanitizeString($_POST['topic'] ?? '');
$tone     = sanitizeString($_POST['tone'] ?? 'informatif');

// Custom endpoint dari form
$customUrl   = trim($_POST['custom_url'] ?? '');
$customKey   = trim($_POST['custom_key'] ?? '');
$customModel = trim($_POST['custom_model'] ?? '');

$validProviders = ['openai', 'gemini', 'deepseek', 'openrouter', 'custom'];

if ($topic === '' || !in_array($provider, $validProviders, true)) {
    jsonResponse(['error' => 'Provider dan topik wajib diisi.'], 422);
}
if ($provider === 'custom' && ($customUrl === '' || $customKey === '')) {
    jsonResponse(['error' => 'Custom endpoint wajib diisi URL dan API key.'], 422);
}

// ── Validasi SSRF untuk custom endpoint (admin-only tapi tetap kunci) ──
if ($provider === 'custom') {
    if (!filter_var($customUrl, FILTER_VALIDATE_URL) || !str_starts_with(strtolower($customUrl), 'https://')) {
        jsonResponse(['error' => 'Custom URL harus HTTPS yang valid.'], 422);
    }
    $parts = parse_url($customUrl);
    $host = strtolower($parts['host'] ?? '');
    if ($host === '' || $host === 'localhost' || $host === '127.0.0.1' || $host === '[::1]') {
        jsonResponse(['error' => 'Custom URL tidak diizinkan (host lokal).'], 422);
    }
    // Blokir IP privat / link-local / metadata cloud
    $resolved = gethostbyname($host);
    if (filter_var($resolved, FILTER_VALIDATE_IP)) {
        $isPrivate = !filter_var($resolved, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
        if ($isPrivate || str_starts_with($resolved, '169.254.')) {
            jsonResponse(['error' => 'Custom URL menunjuk ke jaringan internal.'], 422);
        }
    }
    if (strlen($customUrl) > 500 || strlen($customModel) > 100) {
        jsonResponse(['error' => 'Custom endpoint terlalu panjang.'], 422);
    }
    $customUrl = substr($customUrl, 0, 500);
}

// ── Konfigurasi per provider ────────────────────────────────
$openrouterModel = $_ENV['OPENROUTER_MODEL'] ?? 'particle-ai/particle-1:free';
if ($openrouterModel === 'minimax/minimax-m3:free' || $openrouterModel === '') {
    $openrouterModel = 'particle-ai/particle-1:free';
}

$configs = [
    'openai'     => [
        'key'   => $_ENV['OPENAI_API_KEY'] ?? '',
        'model' => $_ENV['OPENAI_MODEL'] ?? 'gpt-4.1-mini',
        'url'   => 'https://api.openai.com/v1/chat/completions',
    ],
    'deepseek'   => [
        'key'   => $_ENV['DEEPSEEK_API_KEY'] ?? '',
        'model' => $_ENV['DEEPSEEK_MODEL'] ?? 'deepseek-chat',
        'url'   => 'https://api.deepseek.com/chat/completions',
    ],
    'openrouter' => [
        'key'   => $_ENV['OPENROUTER_API_KEY'] ?? '',
        'model' => $openrouterModel,
        'url'   => 'https://openrouter.ai/api/v1/chat/completions',
    ],
    'gemini'     => [
        'key'   => $_ENV['GEMINI_API_KEY'] ?? '',
        'model' => $_ENV['GEMINI_MODEL'] ?? 'gemini-2.5-flash',
        'url'   => 'https://generativelanguage.googleapis.com/v1beta/models/',
    ],
    'custom'     => [
        'key'   => $customKey,
        'model' => $customModel ?: 'gpt-4.1-mini',
        'url'   => $customUrl,
    ],
];

$cfg = $configs[$provider] ?? null;
if (!$cfg || $cfg['key'] === '') {
    jsonResponse(['error' => 'API key ' . ucfirst($provider) . ' belum diatur.'], 422);
}

// ── Prompt untuk AI ─────────────────────────────────────────
$prompt = "Tulis artikel berbahasa Indonesia untuk website pondok pesantren tentang: {$topic}. "
        . "Nada: {$tone}. Keluarkan HANYA JSON valid dengan properti judul, ringkasan, dan isi. "
        . "Isi memakai HTML sederhana (p, h2, h3, ul, li, strong), faktual, ramah, 700-1000 kata, "
        . "tanpa markdown fence. JANGAN sertakan penjelasan, analisis, atau teks apapun di luar JSON.";

// ── Susun payload & header sesuai provider ──────────────────
if ($provider === 'gemini') {
    // Gemini pakai Google API format (bukan OpenAI-compatible)
    $url = $cfg['url'] . rawurlencode($cfg['model']) . ':generateContent';
    $payload = [
        'contents'          => [['parts' => [['text' => $prompt]]]],
        'generationConfig'  => ['responseMimeType' => 'application/json'],
    ];
    $headers = [
        'Content-Type: application/json',
        'x-goog-api-key: ' . $cfg['key'],
    ];
} else {
    // OpenAI, DeepSeek, OpenRouter, Custom — format OpenAI-compatible
    $url = $cfg['url'];
    $payload = [
        'model'       => $cfg['model'],
        'messages'    => [
            ['role' => 'system', 'content' => 'Anda adalah editor artikel pesantren yang teliti. Jawab HANYA dalam format JSON valid. Jangan sertakan teks penjelasan, analisis, atau reasoning apapun. Langsung mulai dengan karakter { dan tutup dengan }.'],
            ['role' => 'user',   'content' => $prompt],
        ],
        'temperature' => 0.7,
        'max_tokens'  => 4000,
    ];
    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $cfg['key'],
    ];
    // Header khusus OpenRouter + otomatis fallback model jika model pertama sibuk
    if ($provider === 'openrouter') {
        $headers[] = 'HTTP-Referer: ' . BASE_URL;
        $headers[] = 'X-Title: ' . APP_NAME;
        $primaryModel = $cfg['model'];
        $fallbackList = array_values(array_unique(array_filter([
            $primaryModel,
            'particle-ai/particle-1:free',
            'nvidia/nemotron-3.5-lightning:free',
            'google/gemma-4-31b-it:free',
            'google/gemma-4-26b-a4b-it:free',
        ])));
        $payload['models'] = $fallbackList;
        unset($payload['model']);
    }
}

// ── Kirim request ke AI ─────────────────────────────────────
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => $headers,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_TIMEOUT        => 60,
    CURLOPT_CONNECTTIMEOUT => 15,
    CURLOPT_PROTOCOLS      => CURLPROTO_HTTPS,
    CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
    CURLOPT_FOLLOWLOCATION => false,
]);

$raw       = curl_exec($ch);
$status    = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($raw === false || $status < 200 || $status >= 300) {
    $detail = '';
    if (!empty($raw)) {
        $errObj = json_decode($raw, true);
        $detail = $errObj['error']['message'] ?? (is_string($errObj['error'] ?? null) ? $errObj['error'] : '');
    }
    jsonResponse([
        'error' => 'Permintaan AI gagal'
            . ($curlError ? ': ' . $curlError : ($detail ? ': ' . $detail : '. Periksa API key/model (HTTP ' . $status . ').'))
    ], 502);
}

// ── Parse response AI ───────────────────────────────────────
$response = json_decode($raw, true);

// Gemini beda struktur response
if ($provider === 'gemini') {
    $text = $response['candidates'][0]['content']['parts'][0]['text'] ?? '';
} else {
    $msg  = $response['choices'][0]['message'] ?? [];
    $text = $msg['content'] ?? '';
    // Jika content kosong tetapi ada reasoning yang berisi teks/JSON
    if ($text === '' && !empty($msg['reasoning'])) {
        $text = $msg['reasoning'];
    }
}

$article = parseAiArticleJson($text, $topic);
if (!is_array($article) || empty($article['isi'])) {
    jsonResponse(['error' => 'Format jawaban AI tidak valid. Coba lagi.'], 502);
}

// ── Kirim hasil ke frontend ─────────────────────────────────
jsonResponse([
    'article' => [
        'judul'     => sanitizeString($article['judul'] ?? $topic),
        'ringkasan' => sanitizeString($article['ringkasan'] ?? ''),
        'isi'       => sanitizeRichHtml($article['isi']),
    ],
]);

/**
 * Ekstrak dan parse artikel yang dihasilkan oleh AI (JSON, Reasoning, Markdown, atau HTML)
 */
function parseAiArticleJson(string $rawText, string $defaultTopic = ''): ?array {
    $text = trim($rawText);
    if ($text === '') {
        return null;
    }

    // 1. Bersihkan tag <think>...</think> dari model reasoning (DeepSeek R1, Nemotron, dll)
    $cleanText = preg_replace('/<think>[\s\S]*?<\/think>/i', '', $text);
    $cleanText = trim($cleanText !== null ? $cleanText : $text);
    if ($cleanText === '') {
        $cleanText = $text;
    }

    // 2. Cari semua blok kode markdown ```json ... ``` (coba dari blok paling akhir backwards)
    if (preg_match_all('/```(?:json)?\s*([\s\S]*?)\s*```/i', $cleanText, $matches)) {
        for ($i = count($matches[1]) - 1; $i >= 0; $i--) {
            $parsed = tryParseJsonString($matches[1][$i]);
            if ($parsed !== null) {
                return $parsed;
            }
        }
    }

    // 3. Coba parse langsung $cleanText
    $parsed = tryParseJsonString($cleanText);
    if ($parsed !== null) {
        return $parsed;
    }

    // 4. Cari blok { ... } dari belakang
    if (preg_match_all('/\{[\s\S]*?\}(?=\s*$|\s*```|\s*\n\n)/', $cleanText, $blocks)) {
        for ($i = count($blocks[0]) - 1; $i >= 0; $i--) {
            $parsed = tryParseJsonString($blocks[0][$i]);
            if ($parsed !== null) {
                return $parsed;
            }
        }
    }

    // Blok kurung kurawal terluar terakhir
    $lastOpen = strrpos($cleanText, '{');
    $lastClose = strrpos($cleanText, '}');
    if ($lastOpen !== false && $lastClose !== false && $lastClose > $lastOpen) {
        $sub = substr($cleanText, $lastOpen, $lastClose - $lastOpen + 1);
        $parsed = tryParseJsonString($sub);
        if ($parsed !== null) {
            return $parsed;
        }
    }

    // 5. Ekstraksi per-field berbasis regex jika struktur JSON sedikit cacat
    $fallback = extractFieldsByRegex($cleanText);
    if ($fallback !== null) {
        return $fallback;
    }

    // 6. Jaminan: Jika AI tidak mengeluarkan format JSON melainkan teks artikel / markdown / HTML biasa
    return parseRawArticleText($cleanText, $defaultTopic);
}

function tryParseJsonString(string $str): ?array {
    $str = trim($str);
    if ($str === '' || !str_starts_with($str, '{')) {
        return null;
    }

    $data = json_decode($str, true);
    if (is_array($data) && !empty($data['isi']) && is_string($data['isi']) && trim($data['isi']) !== '...') {
        return [
            'judul'     => (string) ($data['judul'] ?? ''),
            'ringkasan' => (string) ($data['ringkasan'] ?? ''),
            'isi'       => (string) $data['isi'],
        ];
    }

    // Perbaiki unescaped newlines/tabs di dalam string JSON
    $fixed = preg_replace_callback('/"((?:[^"\\\\]|\\\\.)*)"/s', function ($m) {
        return '"' . str_replace(["\r\n", "\r", "\n", "\t"], ['\n', '\n', '\n', '\t'], $m[1]) . '"';
    }, $str);

    $data = json_decode($fixed, true);
    if (is_array($data) && !empty($data['isi']) && is_string($data['isi']) && trim($data['isi']) !== '...') {
        return [
            'judul'     => (string) ($data['judul'] ?? ''),
            'ringkasan' => (string) ($data['ringkasan'] ?? ''),
            'isi'       => (string) $data['isi'],
        ];
    }

    return null;
}

function extractFieldsByRegex(string $str): ?array {
    $judul = '';
    $ringkasan = '';
    $isi = '';

    if (preg_match('/"(?:judul|title)"\s*:\s*"((?:[^"\\\\]|\\\\.)*)"/si', $str, $m)) {
        $judul = stripcslashes($m[1]);
    }
    if (preg_match('/"(?:ringkasan|summary|excerpt)"\s*:\s*"((?:[^"\\\\]|\\\\.)*)"/si', $str, $m)) {
        $ringkasan = stripcslashes($m[1]);
    }
    if (preg_match('/"(?:isi|content|body)"\s*:\s*"(.*)"\s*\}?\s*$/s', $str, $m)) {
        $isi = stripcslashes($m[1]);
    } elseif (preg_match('/"(?:isi|content|body)"\s*:\s*"([\s\S]*?)(?:"\s*,\s*"|"\s*\}|$)/s', $str, $m)) {
        $isi = stripcslashes($m[1]);
    }

    if (trim($isi) !== '' && trim($isi) !== '...') {
        return [
            'judul'     => trim($judul),
            'ringkasan' => trim($ringkasan),
            'isi'       => trim($isi),
        ];
    }

    return null;
}

function parseRawArticleText(string $raw, string $defaultTopic): ?array {
    $clean = trim(str_replace(["\r\n", "\r"], "\n", $raw));
    if (strlen($clean) < 80) {
        return null;
    }

    $clean = preg_replace('/^(?:Tentu|Berikut|Baik|Halo)[\s\S]*?:\s*\n+/iu', '', $clean);

    $judul = '';
    $lines = explode("\n", $clean);
    $firstLine = trim($lines[0] ?? '');
    if (preg_match('/^#+\s*(.+)$/', $firstLine, $jm)) {
        $judul = trim($jm[1]);
        array_shift($lines);
    } elseif (strlen($firstLine) < 120 && !str_ends_with($firstLine, '.') && !str_ends_with($firstLine, ':')) {
        $judul = $firstLine;
        array_shift($lines);
    }

    if ($judul === '') {
        $judul = $defaultTopic ?: 'Artikel Santri Ash-Shiddiq';
    }

    $body = trim(implode("\n", $lines));

    if (str_contains($body, '<p>') || str_contains($body, '<article>') || str_contains($body, '<div>')) {
        preg_match('/<p>(.*?)<\/p>/is', $body, $pm);
        $ringkasan = mb_substr(strip_tags($pm[1] ?? $body), 0, 300);
        return [
            'judul'     => $judul,
            'ringkasan' => $ringkasan,
            'isi'       => $body,
        ];
    }

    $html = '';
    $inList = false;
    $currP = '';

    foreach ($lines as $line) {
        $t = trim($line);
        if ($t === '') {
            if ($currP !== '') {
                $html .= '<p>' . $currP . '</p>';
                $currP = '';
            }
            if ($inList) {
                $html .= '</ul>';
                $inList = false;
            }
            continue;
        }

        if (preg_match('/^(#{1,4})\s*(.+)$/', $t, $hm)) {
            if ($currP !== '') {
                $html .= '<p>' . $currP . '</p>';
                $currP = '';
            }
            if ($inList) {
                $html .= '</ul>';
                $inList = false;
            }
            $level = min(4, max(2, strlen($hm[1]) + 1));
            $html .= "<h{$level}>" . htmlspecialchars($hm[2]) . "</h{$level}>";
        } elseif (preg_match('/^[-*•]\s*(.+)$/', $t, $lm)) {
            if ($currP !== '') {
                $html .= '<p>' . $currP . '</p>';
                $currP = '';
            }
            if (!$inList) {
                $html .= '<ul>';
                $inList = true;
            }
            $html .= '<li>' . htmlspecialchars($lm[1]) . '</li>';
        } else {
            if ($inList) {
                $html .= '</ul>';
                $inList = false;
            }
            $currP = $currP === '' ? htmlspecialchars($t) : $currP . '<br>' . htmlspecialchars($t);
        }
    }
    if ($currP !== '') {
        $html .= '<p>' . $currP . '</p>';
    }
    if ($inList) {
        $html .= '</ul>';
    }

    $plain = strip_tags($html);
    $ringkasan = mb_substr(trim($plain), 0, 300);

    return [
        'judul'     => $judul,
        'ringkasan' => $ringkasan,
        'isi'       => $html,
    ];
}
