<?php
/**
 * GitHub Webhook Deploy Script
 * Plasează acest fișier în root-ul proiectului pe server
 */

// === CONFIGURARE ===
$secret = 'BUWhuOIeznVIdkQSQCDMfAFltxc17LAg+TQgLhlfMLs='; // Generează o cheie sigură
$repository_path = __DIR__; // Path-ul proiectului (root-ul)
$branch = 'main'; // Branch-ul pe care vrei să faci pull
$log_file = $repository_path . '/deploy.log';

// === FUNCȚII ===

function log_message($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] $message\n";
    file_put_contents($log_file, $log_entry, FILE_APPEND);
    echo $log_entry;
}

function verify_github_signature($payload, $signature, $secret) {
    $expected_signature = 'sha256=' . hash_hmac('sha256', $payload, $secret);
    return hash_equals($expected_signature, $signature);
}

// === VALIDĂRI ===

// Verifică dacă e POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    log_message('❌ Eroare: Doar POST requests sunt acceptate');
    die('Method not allowed');
}

// Ia payload-ul raw
$payload = file_get_contents('php://input');

// Ia semnătura din headers
$signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';

// Verifică semnătura GitHub
if (!verify_github_signature($payload, $signature, $secret)) {
    http_response_code(403);
    log_message('❌ Eroare: Semnătura GitHub nu e validă');
    die('Forbidden - Invalid signature');
}

// Decodează JSON-ul
$data = json_decode($payload, true);

if (!$data) {
    http_response_code(400);
    log_message('❌ Eroare: Payload JSON invalid');
    die('Invalid JSON');
}

// Verifică dacă e push event
if (!isset($data['ref'])) {
    http_response_code(400);
    log_message('❌ Eroare: Nu e push event');
    die('Not a push event');
}

// Extrage branch-ul din ref (ex: refs/heads/main -> main)
$pushed_branch = str_replace('refs/heads/', '', $data['ref']);

// Verifică dacă e branch-ul corect
if ($pushed_branch !== $branch) {
    http_response_code(200);
    log_message('⏭️  Ignorat: Push pe branch ' . $pushed_branch . ', dar monitorizez ' . $branch);
    die('Not the monitored branch');
}

// === DEPLOY ===

log_message('🚀 Webhook primit - Inițiez deploy...');
log_message('Branch: ' . $pushed_branch);
log_message('Repository: ' . ($data['repository']['full_name'] ?? 'N/A'));
log_message('Comitter: ' . ($data['pusher']['name'] ?? 'N/A'));
log_message('Commit: ' . ($data['head_commit']['id'] ?? 'N/A'));

// Verifică dacă directorul există
if (!is_dir($repository_path)) {
    http_response_code(500);
    log_message('❌ Eroare: Directorul proiectului nu există: ' . $repository_path);
    die('Repository path does not exist');
}

// Verifică dacă e un repository git
if (!is_dir($repository_path . '/.git')) {
    http_response_code(500);
    log_message('❌ Eroare: Nu e un repository git în: ' . $repository_path);
    die('Not a git repository');
}

// Schimbă în directorul proiectului
chdir($repository_path);

// Verifică dacă git este disponibil
$git_check = shell_exec('which git 2>&1');
if (empty($git_check)) {
    http_response_code(500);
    log_message('❌ Eroare: Git nu este disponibil pe server');
    die('Git not available');
}

// Verifică status-ul git înainte de pull
log_message('📋 Status git înainte de pull:');
$status_before = shell_exec('git status --porcelain 2>&1');
if (!empty($status_before)) {
    log_message('⚠️  Există modificări locale:');
    log_message($status_before);
    // Reset hard pentru a elimina modificările locale și a forța sincronizarea cu remote
    log_message('🔄 Reset hard pentru a sincroniza cu remote...');
    $reset_output = shell_exec('git reset --hard HEAD 2>&1');
    log_message($reset_output);
    $clean_output = shell_exec('git clean -fd 2>&1');
    log_message($clean_output);
}

// Fetch ultimele modificări
log_message('⏳ Execut: git fetch origin');
$fetch_output = shell_exec('git fetch origin 2>&1');
log_message($fetch_output);

// Execută git pull cu rebase pentru a evita merge commits
log_message('⏳ Execut: git pull --rebase origin ' . $branch);
$output = shell_exec('git pull --rebase origin ' . escapeshellarg($branch) . ' 2>&1');

if ($output === null) {
    http_response_code(500);
    log_message('❌ Eroare: Nu se poate executa git pull');
    die('Git pull failed');
}

log_message('📊 Output git pull:');
log_message($output);

// Verifică dacă pull-ul a fost successful
if (strpos(strtolower($output), 'error') !== false || 
    strpos(strtolower($output), 'fatal') !== false ||
    strpos(strtolower($output), 'conflict') !== false) {
    
    // Încearcă reset hard dacă există conflicte
    log_message('⚠️  Detectat eroare/conflict, încerc reset hard...');
    $reset_output = shell_exec('git reset --hard origin/' . escapeshellarg($branch) . ' 2>&1');
    log_message($reset_output);
    
    if (strpos(strtolower($reset_output), 'error') !== false || 
        strpos(strtolower($reset_output), 'fatal') !== false) {
        http_response_code(500);
        log_message('❌ Deploy EȘUAT - Nu s-a putut rezolva');
        die('Deploy failed');
    }
}

// Verifică status-ul final
log_message('📋 Status git după pull:');
$status_after = shell_exec('git status 2>&1');
log_message($status_after);

// Obține commit-ul curent
$current_commit = shell_exec('git rev-parse HEAD 2>&1');
log_message('✅ Commit curent: ' . trim($current_commit));

http_response_code(200);
log_message('✅ Deploy COMPLETAT cu succes!');
echo json_encode([
    'status' => 'success',
    'message' => 'Deploy completed',
    'branch' => $pushed_branch,
    'commit' => trim($current_commit),
    'timestamp' => date('Y-m-d H:i:s')
], JSON_PRETTY_PRINT);
?>