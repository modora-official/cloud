<?php
date_default_timezone_set('Asia/Jakarta');
set_time_limit(600); 

$folder_upload = __DIR__ . '/uploads/';
if (!file_exists($folder_upload)) {
    @mkdir($folder_upload, 0777, true);
}

$protokol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http");
$base_url = $protokol . "://" . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['PHP_SELF']), '/\\') . "/";

// ==========================================
// 0. BLOK DOWNLOADER (LITESPEED / RUMAHWEB FIX)
// ==========================================
if (isset($_GET['download'])) {
    $file_name = basename($_GET['download']);
    $file_path = $folder_upload . $file_name;
    
    if (file_exists($file_path)) {
        while (ob_get_level()) { ob_end_clean(); }
        
        @ini_set('zlib.output_compression', 'Off');
        if (function_exists('apache_setenv')) {
            apache_setenv('no-gzip', '1');
        }
        
        $file_size = filesize($file_path);
        
        header('HTTP/1.1 200 OK');
        header('Content-Type: application/vnd.android.package-archive');
        header('Content-Disposition: attachment; filename="' . $file_name . '"');
        header('Content-Length: ' . $file_size);
        header('Accept-Ranges: bytes');
        
        header('Content-Encoding: none'); 
        header('X-LiteSpeed-Cache-Control: no-cache');
        
        $uri_path = rtrim(dirname($_SERVER['PHP_SELF']), '/\\') . '/uploads/' . $file_name;
        header("X-LiteSpeed-Location: " . $uri_path);
        
        readfile($file_path);
        exit;
    } else {
        die("File tidak ditemukan di server.");
    }
}

// Fungsi Format Byte ke MB/KB/GB
function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

// Fungsi untuk Format Nama File Otomatis Modora
function generateModoraName($custom_name, $ekstensi, $folder_upload) {
    $custom_name = preg_replace('/[\/\\\:\*\?\"\<\>\|]/', '', $custom_name);
    $custom_name = ucwords(strtolower(trim($custom_name)));
    if(empty($custom_name)) $custom_name = "File";

    $ekstensi = preg_replace('/[^a-zA-Z0-9]/', '', $ekstensi);
    $ekstensi = substr($ekstensi, 0, 6);
    if(empty($ekstensi)) $ekstensi = "apk";

    $base_name = $custom_name . " (MOD APK - MODORA Official)";
    $final_name = $base_name . "." . $ekstensi;
    
    $counter = 1;
    while (file_exists($folder_upload . $final_name)) {
        $final_name = $base_name . " (" . $counter . ")." . $ekstensi;
        $counter++;
    }
    
    return $final_name;
}

// ==========================================
// 1. BLOK TRACKER KECEPATAN (REAL-TIME)
// ==========================================
if (isset($_GET['check_progress']) && isset($_GET['id'])) {
    header('Content-Type: application/json');
    $prog_file = $folder_upload . 'prog_' . preg_replace('/[^0-9]/', '', $_GET['id']) . '.json';
    if (file_exists($prog_file)) {
        echo file_get_contents($prog_file);
    } else {
        echo json_encode(['speed' => 0, 'downloaded' => 0, 'total' => 0, 'percent' => 0]);
    }
    exit;
}

// ==========================================
// 2. BLOK UPLOAD (PROSES MANUAL FILE)
// ==========================================
if (isset($_GET['ajax_manual']) && $_SERVER['REQUEST_METHOD'] == 'POST') {
    header('Content-Type: application/json');
    
    if (!isset($_FILES['manual_file'])) {
        echo json_encode(['status' => 'error', 'message' => 'Tidak ada file yang dikirim.']);
        exit;
    }

    if ($_FILES['manual_file']['error'] !== UPLOAD_ERR_OK) {
        $err_msg = 'Gagal upload: ';
        switch ($_FILES['manual_file']['error']) {
            case UPLOAD_ERR_INI_SIZE: $err_msg .= 'Ukuran file melewati batas upload_max_filesize di php.ini.'; break;
            case UPLOAD_ERR_FORM_SIZE: $err_msg .= 'Ukuran file melewati batas form HTML.'; break;
            case UPLOAD_ERR_PARTIAL: $err_msg .= 'File hanya terupload sebagian. Coba lagi.'; break;
            case UPLOAD_ERR_NO_FILE: $err_msg .= 'Tidak ada file yang dipilih.'; break;
            case UPLOAD_ERR_NO_TMP_DIR: $err_msg .= 'Folder /tmp server penuh atau hilang.'; break;
            case UPLOAD_ERR_CANT_WRITE: $err_msg .= 'Gagal menulis ke disk (Penyimpanan penuh).'; break;
            default: $err_msg .= 'Error tidak diketahui (Kode: ' . $_FILES['manual_file']['error'] . ')'; break;
        }
        echo json_encode(['status' => 'error', 'message' => $err_msg]);
        exit;
    }

    if (!is_writable($folder_upload)) {
        echo json_encode(['status' => 'error', 'message' => 'Folder uploads/ tidak memiliki izin menulis (CHMOD 755/777).']);
        exit;
    }

    $custom_name = trim($_POST['custom_name_manual'] ?? '');
    $original_name = $_FILES['manual_file']['name'];
    $ekstensi = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
    if (empty($ekstensi)) $ekstensi = 'apk';

    $nama_file = generateModoraName($custom_name, $ekstensi, $folder_upload);
    $target_file = $folder_upload . $nama_file;

    if (move_uploaded_file($_FILES['manual_file']['tmp_name'], $target_file)) {
        echo json_encode([
            'status' => 'success', 
            'message' => 'File berhasil diunggah dan diamankan.',
            'link' => $base_url . "?download=" . rawurlencode($nama_file),
            'filename' => $nama_file,
            'filesize' => formatBytes(filesize($target_file))
        ]);
    } else {
        $last_err = error_get_last();
        echo json_encode(['status' => 'error', 'message' => 'Gagal memindahkan file. Detail: ' . ($last_err['message'] ?? '')]);
    }
    exit;
}

// ==========================================
// 3. BLOK UPLOAD (PROSES FETCH DARI LINK)
// ==========================================
if (isset($_GET['ajax']) && $_SERVER['REQUEST_METHOD'] == 'POST') {
    header('Content-Type: application/json'); 
    
    if (!is_writable($folder_upload)) {
        echo json_encode(['status' => 'error', 'message' => 'Folder uploads/ terkunci. Set CHMOD ke 755/777.']);
        exit;
    }

    $url = trim($_POST['url_download'] ?? '');
    $custom_name = trim($_POST['custom_name'] ?? '');
    $task_id = $_POST['task_id'] ?? time();
    
    if (empty($url)) {
        echo json_encode(['status' => 'error', 'message' => 'URL tidak boleh kosong.']);
        exit;
    }

    $url = filter_var($url, FILTER_SANITIZE_URL);
    $ekstensi = 'apk'; 
    $parsed_path = parse_url($url, PHP_URL_PATH);
    $ext_from_path = strtolower(pathinfo($parsed_path, PATHINFO_EXTENSION));
    
    if (!empty($ext_from_path)) {
        $ekstensi = explode('?', $ext_from_path)[0];
    } elseif (preg_match('/\.([a-zA-Z0-9]{2,6})(?:\/download|\?|#|$)/i', $url, $matches)) {
        $ekstensi = strtolower($matches[1]);
    }
    
    $ekstensi = preg_replace('/[^a-zA-Z0-9]/', '', $ekstensi);

    // Bypass MediaFire
    if (strpos($url, 'mediafire.com/file/') !== false) {
        $ch_mf = curl_init($url);
        curl_setopt_array($ch_mf, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
            CURLOPT_TIMEOUT => 15
        ]);
        $mf_html = curl_exec($ch_mf);
        curl_close($ch_mf);
        
        if (preg_match('/href=["\']([^"\']+?)["\'][^>]*?id=["\']downloadButton["\']/i', $mf_html, $matches) || 
            preg_match('/id=["\']downloadButton["\'][^>]*?href=["\']([^"\']+?)["\']/i', $mf_html, $matches)) {
            $url = $matches[1];
            $parsed_mf = parse_url($url, PHP_URL_PATH);
            $ext_mf = strtolower(pathinfo($parsed_mf, PATHINFO_EXTENSION));
            if(!empty($ext_mf)) $ekstensi = explode('?', $ext_mf)[0];
        }
    }

    $nama_file = generateModoraName($custom_name, $ekstensi, $folder_upload);
    $target_file = $folder_upload . $nama_file;
    $prog_file = $folder_upload . "prog_{$task_id}.json";
    $cookie_file = $folder_upload . "cookie_{$task_id}.txt";
    
    $fp = @fopen($target_file, 'w+');
    if ($fp) {
        $ch = curl_init($url);
        $start_time = microtime(true);
        $last_write = 0;
        $domain = parse_url($url, PHP_URL_SCHEME) . '://' . parse_url($url, PHP_URL_HOST);
        
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => [
                "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8",
                "Accept-Language: en-US,en;q=0.5",
                "Connection: keep-alive",
                "Upgrade-Insecure-Requests: 1"
            ],
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
            CURLOPT_REFERER => $domain . "/",
            CURLOPT_FILE => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 600,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            CURLOPT_FAILONERROR => true,
            CURLOPT_AUTOREFERER => true,
            CURLOPT_COOKIEJAR => $cookie_file,
            CURLOPT_COOKIEFILE => $cookie_file,
            CURLOPT_ENCODING => "",
            CURLOPT_TCP_KEEPALIVE => 1,
            CURLOPT_BUFFERSIZE => 1048576,
            CURLOPT_NOPROGRESS => false,
            CURLOPT_PROGRESSFUNCTION => function($resource, $download_size, $downloaded, $upload_size, $uploaded) use ($prog_file, &$last_write, $start_time) {
                $now = microtime(true);
                if ($now - $last_write > 0.5 && $download_size > 0) { 
                    $elapsed = $now - $start_time;
                    $speed_kbps = $elapsed > 0 ? ($downloaded / 1024) / $elapsed : 0;
                    $percent = ($download_size > 0) ? round(($downloaded / $download_size) * 100) : 0;
                    
                    @file_put_contents($prog_file, json_encode([
                        'downloaded' => round($downloaded / 1024 / 1024, 2),
                        'total' => round($download_size / 1024 / 1024, 2),
                        'speed' => round($speed_kbps, 2),
                        'percent' => $percent
                    ]));
                    $last_write = $now;
                }
            }
        ]);
        
        curl_exec($ch);
        $error = curl_error($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fp);
        
        @unlink($prog_file); 
        @unlink($cookie_file); 
        
        if ($error || $http_code >= 400) {
            @unlink($target_file); 
            $msg = "Gagal ditarik. (HTTP: $http_code)";
            if ($http_code == 403) $msg .= " (IP ditolak target, gunakan manual upload).";
            if ($http_code == 404) $msg .= " (File terhapus di server sumber).";
            echo json_encode(['status' => 'error', 'message' => $msg . " | Detail: $error"]);
            exit;
        } else {
            clearstatcache();
            if (!file_exists($target_file) || filesize($target_file) < 1024) {
                @unlink($target_file);
                echo json_encode(['status' => 'error', 'message' => 'File gagal ditarik utuh (Ukuran kosong / Terblokir proteksi HTML).']);
                exit;
            }
            
            echo json_encode([
                'status' => 'success', 
                'message' => 'File berhasil difetch dan diamankan.',
                'link' => $base_url . "?download=" . rawurlencode($nama_file),
                'filename' => $nama_file,
                'filesize' => formatBytes(filesize($target_file))
            ]);
            exit;
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Gagal menulis file di server. Pastikan izin folder benar.']);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Modora Server v4.2 | Premium Cloud</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg-color: #000000;
            --card-bg: rgba(28, 28, 30, 0.65);
            --input-bg: rgba(44, 44, 46, 0.7);
            --primary: #0a84ff;
            --primary-hover: #0070e0;
            --success: #30d158;
            --danger: #ff453a;
            --text-main: #ffffff;
            --text-muted: #8e8e93;
            --border-color: rgba(255, 255, 255, 0.08);
            --shadow-premium: 0 20px 40px rgba(0, 0, 0, 0.5), inset 0 1px 0 rgba(255,255,255,0.1);
        }

        * { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        
        body { 
            background-color: var(--bg-color); 
            background-image: radial-gradient(circle at 50% -20%, #1a1a1c 0%, #000000 60%);
            color: var(--text-main); 
            margin: 0; padding: 30px 15px; 
            display: flex; flex-direction: column; align-items: center; 
            min-height: 100vh; overflow-x: hidden; 
            font-family: -apple-system, BlinkMacSystemFont, "SF Pro Text", "Segoe UI", Roboto, Helvetica, Arial, sans-serif; 
        }
        
        .ios-card { 
            width: 100%; max-width: 520px; 
            background: var(--card-bg); 
            backdrop-filter: blur(25px); 
            -webkit-backdrop-filter: blur(25px);
            border: 1px solid var(--border-color);
            border-radius: 20px; 
            padding: 24px; 
            box-shadow: var(--shadow-premium); 
            position: relative;
            overflow: hidden;
        }

        h2 { 
            font-size: 24px; font-weight: 700; margin: 0 0 24px 0; 
            text-align: center; color: var(--text-main); 
            letter-spacing: -0.5px; display: flex; align-items: center; justify-content: center; gap: 10px;
        }
        .version-badge { 
            background: linear-gradient(135deg, #0a84ff, #005bb5); 
            color: #fff; font-size: 11px; padding: 4px 10px; 
            border-radius: 12px; font-weight: 700; letter-spacing: 0.5px; 
            box-shadow: 0 4px 10px rgba(10, 132, 255, 0.3);
        }

        .tab-container { 
            display: flex; background: rgba(0,0,0,0.3); border-radius: 14px; 
            padding: 5px; margin-bottom: 24px; border: 1px solid var(--border-color);
        }
        .tab-btn { 
            flex: 1; text-align: center; padding: 12px; cursor: pointer; 
            border-radius: 10px; transition: all 0.3s cubic-bezier(0.25, 1, 0.5, 1); 
            color: var(--text-muted); font-weight: 600; font-size: 14px; 
            display: flex; align-items: center; justify-content: center; gap: 8px;
        }
        .tab-btn.active { 
            background: var(--input-bg); color: var(--text-main); 
            box-shadow: 0 4px 12px rgba(0,0,0,0.2); 
        }
        
        .tab-content { display: none; animation: slideUp 0.4s cubic-bezier(0.16, 1, 0.3, 1); }
        .tab-content.active { display: block; }
        @keyframes slideUp { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        .section-title { 
            font-size: 13px; font-weight: 600; color: var(--text-muted); 
            margin-bottom: 8px; margin-left: 4px; text-transform: uppercase; letter-spacing: 0.5px;
        }
        
        .input-group { display: flex; gap: 12px; margin-bottom: 20px; }
        .input-group input { margin-bottom: 0 !important; flex: 1; }
        
        .btn-paste { 
            background-color: var(--input-bg); color: var(--primary); 
            border: 1px solid var(--border-color); border-radius: 12px; 
            padding: 0 18px; cursor: pointer; font-size: 18px; 
            transition: all 0.2s; display: flex; align-items: center; justify-content: center; 
        }
        .btn-paste:hover { background-color: rgba(60, 60, 62, 0.9); }
        .btn-paste:active { transform: scale(0.95); }

        input[type="url"], input[type="text"] { 
            background-color: var(--input-bg); color: var(--text-main); 
            border: 1px solid var(--border-color); outline: none; 
            border-radius: 12px; padding: 15px 18px; width: 100%; 
            font-size: 15px; margin-bottom: 20px; transition: all 0.3s; 
        }
        input::placeholder { color: var(--text-muted); }
        input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(10, 132, 255, 0.2); }

        .file-upload-box { 
            background-color: var(--input-bg); color: var(--primary); 
            border: 1px dashed var(--primary); border-radius: 12px; 
            padding: 20px; display: block; text-align: center; cursor: pointer; 
            margin-bottom: 20px; font-weight: 600; transition: all 0.3s; 
        }
        .file-upload-box:hover { background-color: rgba(10, 132, 255, 0.1); }
        
        .preview-box { 
            background: rgba(0,0,0,0.4); border: 1px solid var(--border-color); 
            padding: 14px 16px; border-radius: 12px; font-size: 14px; 
            color: var(--success); margin-bottom: 24px; word-break: break-all; 
            font-family: ui-monospace, SFMono-Regular, Consolas, "Liberation Mono", Menlo, monospace;
        }

        .btn-ios { 
            background: linear-gradient(135deg, var(--primary), var(--primary-hover)); 
            color: #ffffff; border: none; outline: none; border-radius: 14px; 
            padding: 16px; font-size: 16px; font-weight: 700; width: 100%; 
            cursor: pointer; transition: all 0.2s; display: flex; justify-content: center; align-items: center; gap: 10px; 
            box-shadow: 0 4px 15px rgba(10, 132, 255, 0.3);
        }
        .btn-ios:active { transform: scale(0.98); box-shadow: 0 2px 8px rgba(10, 132, 255, 0.3); }
        .btn-ios:disabled { background: var(--input-bg); color: var(--text-muted); cursor: not-allowed; box-shadow: none; }
        
        .btn-copy { background: linear-gradient(135deg, var(--success), #28b84d); box-shadow: 0 4px 15px rgba(48, 209, 88, 0.2); margin-top: 15px; }
        .btn-copy:active { box-shadow: 0 2px 8px rgba(48, 209, 88, 0.2); }

        .progress-area { display: none; margin-top: 24px; text-align: center; background: rgba(0,0,0,0.3); padding: 20px; border-radius: 14px; }
        .spinner { 
            width: 28px; height: 28px; border: 3px solid rgba(255,255,255,0.1); 
            border-top: 3px solid var(--primary); border-radius: 50%; 
            animation: spin 1s cubic-bezier(0.68, -0.55, 0.265, 1.55) infinite; margin: 0 auto 12px auto; 
        }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        .speed-text { font-size: 14px; color: var(--text-muted); margin-top: 8px; font-variant-numeric: tabular-nums; white-space: pre-line; line-height: 1.5; }
        
        .result-area { display: none; margin-top: 24px; padding: 20px; border-radius: 14px; background: rgba(0,0,0,0.3); border: 1px solid var(--border-color); text-align: center; animation: slideUp 0.4s ease; }
        .success-text { color: var(--success); font-weight: 700; margin-bottom: 8px; font-size: 16px; display: flex; align-items: center; justify-content: center; gap: 8px; }
        .error-text { color: var(--danger); font-weight: 700; margin-bottom: 8px; display: flex; align-items: center; justify-content: center; gap: 8px; }
        .result-link { color: var(--primary); text-decoration: none; font-size: 15px; word-break: break-all; display: block; margin-bottom: 16px; padding: 10px; background: rgba(10,132,255,0.1); border-radius: 8px; }
        
        .file-size-badge { display: inline-flex; align-items: center; gap: 6px; background-color: var(--input-bg); color: var(--text-muted); font-size: 13px; padding: 6px 14px; border-radius: 20px; margin-bottom: 10px; font-weight: 600; border: 1px solid var(--border-color); }

        .progress-bar-container { width: 100%; background-color: rgba(255,255,255,0.1); border-radius: 20px; margin: 15px 0; overflow: hidden; display: none; height: 8px; box-shadow: inset 0 1px 3px rgba(0,0,0,0.5); }
        .progress-bar-fill { height: 100%; background: linear-gradient(90deg, #0a84ff, #30d158); width: 0%; transition: width 0.3s ease; border-radius: 20px; }
    </style>
</head>
<body>

    <div class="ios-card">
        <h2><i class="fa-solid fa-server"></i> Modora Cloud <span class="version-badge">v4.2</span></h2>
        
        <div class="tab-container">
            <div class="tab-btn active" onclick="switchTab('linkTab')">
                <i class="fa-solid fa-link"></i> Tarik Link
            </div>
            <div class="tab-btn" onclick="switchTab('manualTab')">
                <i class="fa-solid fa-cloud-arrow-up"></i> File Lokal
            </div>
        </div>

        <div id="linkTab" class="tab-content active">
            <form id="leechForm">
                <div class="section-title"><i class="fa-solid fa-globe"></i> Target URL Server</div>
                <div class="input-group">
                    <input type="url" name="url_download" id="urlInput" placeholder="Masukkan URL File / MediaFire..." required>
                    <button type="button" class="btn-paste" onclick="pasteUrl()" title="Tempel URL">
                        <i class="fa-solid fa-paste"></i>
                    </button>
                </div>
                
                <div class="section-title"><i class="fa-solid fa-pen-to-square"></i> Nama Kustom</div>
                <input type="text" name="custom_name" id="customNameLink" placeholder="Contoh: WhatsApp Plus">
                
                <div class="section-title"><i class="fa-solid fa-eye"></i> Pratinjau Output</div>
                <div class="preview-box" id="namePreviewLink"></div>

                <div class="progress-bar-container" id="linkProgressBarContainer">
                    <div class="progress-bar-fill" id="linkProgressBar"></div>
                </div>
                
                <input type="hidden" name="task_id" id="taskId">
                <button type="submit" id="submitBtnLink" class="btn-ios">
                    <i class="fa-solid fa-bolt"></i> Eksekusi Fetching
                </button>
            </form>
        </div>

        <div id="manualTab" class="tab-content">
            <form id="manualForm">
                <div class="section-title"><i class="fa-solid fa-file-shield"></i> File Target</div>
                <label class="file-upload-box">
                    <span id="fileNameDisplay"><i class="fa-solid fa-folder-open"></i> Ketuk untuk mencari file...</span>
                    <input type="file" name="manual_file" id="fileInput" style="display:none;" required>
                </label>

                <div class="section-title"><i class="fa-solid fa-pen-to-square"></i> Nama Kustom</div>
                <input type="text" name="custom_name_manual" id="customNameManual" placeholder="Contoh: Instagram Pro">
                
                <div class="section-title"><i class="fa-solid fa-eye"></i> Pratinjau Output</div>
                <div class="preview-box" id="namePreviewManual"></div>

                <div class="progress-bar-container" id="manualProgressBarContainer">
                    <div class="progress-bar-fill" id="manualProgressBar"></div>
                </div>
                
                <button type="submit" id="submitBtnManual" class="btn-ios">
                    <i class="fa-solid fa-rocket"></i> Mulai Unggah
                </button>
            </form>
        </div>

        <div id="progressArea" class="progress-area">
            <div class="spinner"></div>
            <div style="font-weight: 600; color: #ffffff; font-size: 15px;">Sinkronisasi Server...</div>
            <div class="speed-text" id="speedText">Menganalisa jaringan...</div>
        </div>

        <div id="resultArea" class="result-area"></div>
    </div>

    <script>
        function switchTab(tabId) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
            
            document.getElementById(tabId).classList.add('active');
            event.target.closest('.tab-btn').classList.add('active');
            
            document.getElementById('resultArea').style.display = 'none';
            document.getElementById('progressArea').style.display = 'none';
            document.querySelectorAll('.progress-bar-container').forEach(el => el.style.display = 'none');
        }

        async function pasteUrl() {
            try {
                const text = await navigator.clipboard.readText();
                if (text) {
                    document.getElementById('urlInput').value = text;
                    updatePreviewLink();
                }
            } catch (err) {
                alert('Akses clipboard diblokir browser. Silakan tempel manual.');
            }
        }

        function toTitleCase(str) {
            return str.replace(/\w\S*/g, function(txt) {
                return txt.charAt(0).toUpperCase() + txt.substr(1).toLowerCase();
            });
        }

        function getExtensionFromUrl(url) {
            if (!url) return 'apk';
            try {
                let urlObj = new URL(url);
                let path = urlObj.pathname;
                let ext = path.split('.').pop().split('?')[0].toLowerCase();
                if(ext && ext.length <= 6 && ext !== path.toLowerCase()) return ext;
            } catch(e) {}
            let match = url.match(/\.([a-zA-Z0-9]{2,6})(?:\/download|\?|#|$)/i);
            return match ? match[1].toLowerCase() : 'apk';
        }

        function updatePreviewLink() {
            let text = document.getElementById('customNameLink').value;
            if(!text || text.trim() === '') text = 'File';
            let url = document.getElementById('urlInput').value;
            let ext = getExtensionFromUrl(url);
            text = toTitleCase(text.trim());
            document.getElementById('namePreviewLink').innerHTML = `<i class="fa-solid fa-file-code"></i> ${text} (MOD APK - MODORA Official).${ext}`;
        }
        document.getElementById('customNameLink').addEventListener('input', updatePreviewLink);
        document.getElementById('urlInput').addEventListener('input', updatePreviewLink);

        function updatePreviewManual() {
            let text = document.getElementById('customNameManual').value;
            if(!text || text.trim() === '') text = 'File';
            let fileInput = document.getElementById('fileInput');
            let ext = 'apk';
            if(fileInput.files.length > 0) {
                let fileName = fileInput.files[0].name;
                ext = fileName.split('.').pop().toLowerCase();
            }
            text = toTitleCase(text.trim());
            document.getElementById('namePreviewManual').innerHTML = `<i class="fa-solid fa-file-code"></i> ${text} (MOD APK - MODORA Official).${ext}`;
        }
        document.getElementById('customNameManual').addEventListener('input', updatePreviewManual);
        document.getElementById('fileInput').addEventListener('change', function(e) {
            let fileName = e.target.files.length > 0 ? `<i class="fa-solid fa-file-circle-check"></i> ${e.target.files[0].name}` : `<i class="fa-solid fa-folder-open"></i> Ketuk untuk mencari file...`;
            document.getElementById('fileNameDisplay').innerHTML = fileName;
            updatePreviewManual();
        });

        updatePreviewLink();
        updatePreviewManual();

        const progressArea = document.getElementById('progressArea');
        const speedText = document.getElementById('speedText');
        const resultArea = document.getElementById('resultArea');
        let progressInterval;

        function copyUrl(url) {
            navigator.clipboard.writeText(url).then(() => {
                const copyBtn = document.getElementById('copyBtn');
                copyBtn.innerHTML = '<i class="fa-solid fa-check-double"></i> Link Tersalin!';
                copyBtn.style.background = 'linear-gradient(135deg, #32d74b, #28b84d)';
                setTimeout(() => {
                    copyBtn.innerHTML = '<i class="fa-solid fa-copy"></i> Salin Link Modora';
                    copyBtn.style.background = 'linear-gradient(135deg, var(--success), #28b84d)';
                }, 2000);
            }).catch(err => { alert('Gagal menyalin link.'); });
        }

        function renderResult(data) {
            if (data.status === 'success') {
                resultArea.innerHTML = `
                    <div class="success-text"><i class="fa-solid fa-circle-check"></i> ${data.message}</div>
                    <a href="${data.link}" target="_blank" class="result-link"><i class="fa-solid fa-link"></i> ${data.filename}</a>
                    <div class="file-size-badge"><i class="fa-solid fa-hard-drive"></i> Ukuran: ${data.filesize}</div>
                    <button id="copyBtn" class="btn-ios btn-copy" onclick="copyUrl('${data.link}')"><i class="fa-solid fa-copy"></i> Salin Link Modora</button>
                `;
            } else {
                resultArea.innerHTML = `<div class="error-text"><i class="fa-solid fa-triangle-exclamation"></i> ${data.message}</div>`;
            }
        }

        document.getElementById('leechForm').addEventListener('submit', function(e) {
            e.preventDefault(); 
            const submitBtn = document.getElementById('submitBtnLink');
            const currentTaskId = Date.now();
            document.getElementById('taskId').value = currentTaskId;
            const barContainer = document.getElementById('linkProgressBarContainer');
            const barFill = document.getElementById('linkProgressBar');

            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fa-solid fa-satellite-dish fa-fade"></i> Membangun Koneksi...';
            resultArea.style.display = 'none';
            progressArea.style.display = 'block';
            barContainer.style.display = 'block';
            barFill.style.width = '0%';
            speedText.innerText = 'Mempersiapkan jalur transfer...';

            progressInterval = setInterval(() => {
                fetch(`?check_progress=1&id=${currentTaskId}`)
                .then(res => res.json())
                .then(data => {
                    if(data.speed !== undefined && data.total > 0) {
                        speedText.innerText = `Kec: ${data.speed} KB/s\nData: ${data.downloaded} MB / ${data.total} MB (${data.percent}%)`;
                        barFill.style.width = data.percent + '%';
                    }
                }).catch(e => console.log('Ping tertunda'));
            }, 1000);

            fetch('?ajax=1', { method: 'POST', body: new FormData(this) })
            .then(async response => {
                const data = await response.json();
                if (!response.ok) throw new Error('Timeout');
                return data;
            })
            .then(data => {
                clearInterval(progressInterval); 
                progressArea.style.display = 'none'; 
                barContainer.style.display = 'none';
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fa-solid fa-bolt"></i> Eksekusi Fetching';
                
                resultArea.style.display = 'block';
                renderResult(data);
                
                if (data.status === 'success') {
                    document.getElementById('urlInput').value = ''; 
                    document.getElementById('customNameLink').value = ''; 
                    updatePreviewLink(); 
                }
            })
            .catch(error => {
                clearInterval(progressInterval);
                progressArea.style.display = 'none';
                barContainer.style.display = 'none';
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fa-solid fa-bolt"></i> Eksekusi Fetching';
                resultArea.style.display = 'block';
                resultArea.innerHTML = `<div class="error-text"><i class="fa-solid fa-circle-xmark"></i> Koneksi server terputus atau respon tidak valid.</div>`;
            });
        });

        document.getElementById('manualForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const submitBtn = document.getElementById('submitBtnManual');
            const fileInput = document.getElementById('fileInput');
            
            if(fileInput.files.length === 0) return;

            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Mentransfer...';
            resultArea.style.display = 'none';
            
            const barContainer = document.getElementById('manualProgressBarContainer');
            const barFill = document.getElementById('manualProgressBar');
            barContainer.style.display = 'block';
            barFill.style.width = '0%';

            speedText.innerText = 'Mengenkripsi dan memindahkan file...';
            progressArea.style.display = 'block';

            const xhr = new XMLHttpRequest();
            xhr.open('POST', '?ajax_manual=1', true);
            
            xhr.upload.onprogress = function(e) {
                if (e.lengthComputable) {
                    const percentComplete = (e.loaded / e.total) * 100;
                    barFill.style.width = percentComplete + '%';
                    const mbLoaded = (e.loaded / 1024 / 1024).toFixed(2);
                    const mbTotal = (e.total / 1024 / 1024).toFixed(2);
                    speedText.innerText = `Proses: ${mbLoaded} MB / ${mbTotal} MB (${Math.round(percentComplete)}%)`;
                }
            };

            xhr.onload = function() {
                progressArea.style.display = 'none';
                barContainer.style.display = 'none';
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fa-solid fa-rocket"></i> Mulai Unggah';
                resultArea.style.display = 'block';

                if (xhr.status === 200) {
                    try {
                        const data = JSON.parse(xhr.responseText);
                        renderResult(data);
                        if (data.status === 'success') {
                            document.getElementById('customNameManual').value = '';
                            document.getElementById('fileInput').value = '';
                            document.getElementById('fileNameDisplay').innerHTML = '<i class="fa-solid fa-folder-open"></i> Ketuk untuk mencari file...';
                            updatePreviewManual();
                        }
                    } catch (e) {
                        resultArea.innerHTML = `<div class="error-text"><i class="fa-solid fa-bug"></i> Terjadi kesalahan internal saat membaca respon server.</div>`;
                    }
                } else {
                    resultArea.innerHTML = `<div class="error-text"><i class="fa-solid fa-circle-xmark"></i> Gagal mentransfer file (HTTP Server: ${xhr.status}).</div>`;
                }
            };

            xhr.onerror = function() {
                progressArea.style.display = 'none';
                barContainer.style.display = 'none';
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fa-solid fa-rocket"></i> Mulai Unggah';
                resultArea.style.display = 'block';
                resultArea.innerHTML = `<div class="error-text"><i class="fa-solid fa-wifi"></i> Jaringan terputus saat mentransfer data.</div>`;
            };

            xhr.send(new FormData(this));
        });
    </script>
</body>
</html>
