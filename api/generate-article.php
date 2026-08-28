<?php
define('ROOT_PATH',dirname(__DIR__));require_once ROOT_PATH.'/config/constants.php';require_once ROOT_PATH.'/config/session.php';require_once ROOT_PATH.'/config/database.php';require_once ROOT_PATH.'/includes/functions.php';require_once ROOT_PATH.'/includes/auth.php';requireAdmin();
if($_SERVER['REQUEST_METHOD']!=='POST')jsonResponse(['error'=>'Metode tidak diizinkan.'],405);validateCsrf();
$provider=sanitizeString($_POST['provider']??'');$topic=sanitizeString($_POST['topic']??'');$tone=sanitizeString($_POST['tone']??'informatif');
// Support custom endpoint dari form$customUrl=trim($_POST['custom_url']??'');$customKey=trim($_POST['custom_key']??'');$customModel=trim($_POST['custom_model']??'');
$validProviders=['openai','gemini','deepseek','openrouter','custom'];
if($topic===''||!in_array($provider,$validProviders,true))jsonResponse(['error'=>'Provider dan topik wajib diisi.'],422);
if($provider==='custom'&&($customUrl===''||$customKey===''))jsonResponse(['error'=>'Custom endpoint wajib diisi URL dan API key.'],422);
// Konfigurasi per provider$configs=[
 'openai'=>['key'=>$_ENV['OPENAI_API_KEY']??'','model'=>$_ENV['OPENAI_MODEL']??'gpt-4.1-mini','url'=>'https://api.openai.com/v1/chat/completions'],
 'deepseek'=>['key'=>$_ENV['DEEPSEEK_API_KEY']??'','model'=>$_ENV['DEEPSEEK_MODEL']??'deepseek-chat','url'=>'https://api.deepseek.com/chat/completions'],
 'openrouter'=>['key'=>$_ENV['OPENROUTER_API_KEY']??'','model'=>$_ENV['OPENROUTER_MODEL']??'nvidia/nemotron-3-super-120b-a12b:free','url'=>'https://openrouter.ai/api/v1/chat/completions'],
 'gemini'=>['key'=>$_ENV['GEMINI_API_KEY']??'','model'=>$_ENV['GEMINI_MODEL']??'gemini-2.5-flash','url'=>'https://generativelanguage.googleapis.com/v1beta/models/'],
 'custom'=>['key'=>$customKey,'model'=>$customModel?:'gpt-4.1-mini','url'=>$customUrl],
];$cfg=$configs[$provider]??null;if(!$cfg||$cfg['key']==='')jsonResponse(['error'=>'API key '.ucfirst($provider).' belum diatur.'],422);
$prompt="Tulis artikel berbahasa Indonesia untuk website pondok pesantren tentang: {$topic}. Nada: {$tone}. Keluarkan HANYA JSON valid dengan properti judul, ringkasan, dan isi. Isi memakai HTML sederhana (p, h2, h3, ul, li, strong), faktual, ramah, 700-1000 kata, tanpa markdown fence.";
// Gemini pakai format berbeda (Google API, bukan OpenAI-compatible)
if($provider==='gemini'){$url=$cfg['url'].rawurlencode($cfg['model']).':generateContent';$payload=['contents'=>[['parts'=>[['text'=>$prompt]]]],'generationConfig'=>['responseMimeType'=>'application/json']];$headers=['Content-Type: application/json','x-goog-api-key: '.$cfg['key']];}
// Semua lainnya (OpenAI, DeepSeek, OpenRouter, Custom) = OpenAI-compatible format
else{$url=$cfg['url'];$payload=['model'=>$cfg['model'],'messages'=>[['role'=>'system','content'=>'Anda adalah editor artikel pesantren yang teliti.'],['role'=>'user','content'=>$prompt]],'temperature'=>0.7,'max_tokens'=>4000];$headers=['Content-Type: application/json','Authorization: Bearer '.$cfg['key']];if($provider==='openrouter'){$headers[]='HTTP-Referer: '.BASE_URL;$headers[]='X-Title: '.APP_NAME;}}
// Kirim request ke AI
$ch=curl_init($url);curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>$headers,CURLOPT_POSTFIELDS=>json_encode($payload),CURLOPT_TIMEOUT=>120]);$raw=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$curlError=curl_error($ch);curl_close($ch);if($raw===false||$status<200||$status>=300)jsonResponse(['error'=>'Permintaan AI gagal'.($curlError?': '.$curlError:'. Periksa API key/model (HTTP '.$status.').')],502);
// Parse response — Gemini beda format
$response=json_decode($raw,true);$text=$provider==='gemini'?($response['candidates'][0]['content']['parts'][0]['text']??''):($response['choices'][0]['message']['content']??'');$text=preg_replace('/^```(?:json)?\s*|\s*```$/i','',$text);$article=json_decode($text,true);if(!is_array($article)||empty($article['isi']))jsonResponse(['error'=>'Format jawaban AI tidak valid. Coba lagi.'],502);jsonResponse(['article'=>['judul'=>sanitizeString($article['judul']??$topic),'ringkasan'=>sanitizeString($article['ringkasan']??''),'isi'=>strip_tags($article['isi'],'<p><br><strong><em><h2><h3><h4><ul><ol><li><blockquote>')]]);
