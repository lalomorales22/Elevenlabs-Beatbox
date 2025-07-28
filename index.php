<?php
// Initialize SQLite Database
$db_file = 'beatbox.db';
$pdo = new PDO("sqlite:$db_file");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Create tables if they don't exist
$pdo->exec("
    CREATE TABLE IF NOT EXISTS patterns (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        data TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )
");

$pdo->exec("
    CREATE TABLE IF NOT EXISTS sounds (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        category TEXT NOT NULL,
        subcategory TEXT,
        file_path TEXT,
        sound_data TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )
");

$pdo->exec("
    CREATE TABLE IF NOT EXISTS tracks (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        pattern_id INTEGER,
        name TEXT NOT NULL,
        volume REAL DEFAULT 0.8,
        muted INTEGER DEFAULT 0,
        color TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )
");

$pdo->exec("
    CREATE TABLE IF NOT EXISTS settings (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        setting_key TEXT UNIQUE NOT NULL,
        setting_value TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )
");

$pdo->exec("
    CREATE TABLE IF NOT EXISTS beats (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        file_path TEXT NOT NULL,
        duration REAL,
        tempo INTEGER,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )
");

// Handle audio serving for GET requests
if (isset($_GET['action']) && $_GET['action'] === 'serve_audio') {
    $filePath = $_GET['file'] ?? '';
    if ($filePath && file_exists($filePath)) {
        $mimeType = 'audio/webm';
        if (strpos($filePath, '.mp4') !== false) $mimeType = 'audio/mp4';
        elseif (strpos($filePath, '.ogg') !== false) $mimeType = 'audio/ogg';
        elseif (strpos($filePath, '.wav') !== false) $mimeType = 'audio/wav';
        
        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . filesize($filePath));
        header('Accept-Ranges: bytes');
        readfile($filePath);
        exit;
    } else {
        http_response_code(404);
        echo 'File not found';
        exit;
    }
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    header('Content-Type: application/json');
    
    switch ($action) {
        case 'save_pattern':
            $name = $_POST['name'];
            $data = $_POST['data'];
            $stmt = $pdo->prepare("INSERT INTO patterns (name, data) VALUES (?, ?)");
            $stmt->execute([$name, $data]);
            echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
            exit;
            
        case 'load_patterns':
            $stmt = $pdo->query("SELECT * FROM patterns ORDER BY created_at DESC");
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            exit;
            
        case 'save_sound':
            $name = $_POST['name'];
            $category = $_POST['category'];
            $subcategory = $_POST['subcategory'] ?? null;
            $sound_data = $_POST['sound_data'];
            $stmt = $pdo->prepare("INSERT INTO sounds (name, category, subcategory, sound_data) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $category, $subcategory, $sound_data]);
            echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
            exit;
            
        case 'load_sounds':
            $stmt = $pdo->query("SELECT * FROM sounds ORDER BY category, subcategory, name");
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            exit;
            
        case 'delete_sound':
            $id = $_POST['id'];
            $stmt = $pdo->prepare("DELETE FROM sounds WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true]);
            exit;
            
        case 'rename_sound':
            $id = $_POST['id'];
            $newName = $_POST['new_name'];
            $stmt = $pdo->prepare("UPDATE sounds SET name = ? WHERE id = ?");
            $stmt->execute([$newName, $id]);
            echo json_encode(['success' => true]);
            exit;
            
        case 'reset_database':
            // Delete all sounds
            $pdo->exec("DELETE FROM sounds");
            // Delete all patterns
            $pdo->exec("DELETE FROM patterns");
            // Delete all beats
            $pdo->exec("DELETE FROM beats");
            // Delete uploaded files
            if (is_dir('uploads')) {
                $files = glob('uploads/*');
                foreach($files as $file) {
                    if(is_file($file)) {
                        unlink($file);
                    }
                }
            }
            echo json_encode(['success' => true]);
            exit;
            
        case 'save_beat':
            $name = $_POST['name'];
            $filePath = $_POST['file_path'];
            $duration = $_POST['duration'] ?? null;
            $tempo = $_POST['tempo'] ?? null;
            $stmt = $pdo->prepare("INSERT INTO beats (name, file_path, duration, tempo) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $filePath, $duration, $tempo]);
            echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
            exit;
            
        case 'load_beats':
            $stmt = $pdo->query("SELECT * FROM beats ORDER BY created_at DESC");
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            exit;
            
        case 'delete_beat':
            $id = $_POST['id'];
            // Get file path before deleting
            $stmt = $pdo->prepare("SELECT file_path FROM beats WHERE id = ?");
            $stmt->execute([$id]);
            $beat = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Delete from database
            $stmt = $pdo->prepare("DELETE FROM beats WHERE id = ?");
            $stmt->execute([$id]);
            
            // Delete file if it exists
            if ($beat && file_exists($beat['file_path'])) {
                unlink($beat['file_path']);
            }
            
            echo json_encode(['success' => true]);
            exit;
            
        case 'serve_audio':
            $filePath = $_GET['file'] ?? $_POST['file_path'] ?? '';
            if ($filePath && file_exists($filePath)) {
                $mimeType = 'audio/webm';
                if (strpos($filePath, '.mp4') !== false) $mimeType = 'audio/mp4';
                elseif (strpos($filePath, '.ogg') !== false) $mimeType = 'audio/ogg';
                elseif (strpos($filePath, '.wav') !== false) $mimeType = 'audio/wav';
                
                header('Content-Type: ' . $mimeType);
                header('Content-Length: ' . filesize($filePath));
                header('Accept-Ranges: bytes');
                readfile($filePath);
                exit;
            } else {
                http_response_code(404);
                echo 'File not found';
                exit;
            }
            
        case 'upload_audio':
            if (isset($_FILES['audio'])) {
                $file = $_FILES['audio'];
                $category = $_POST['category'] ?? 'custom';
                $name = pathinfo($file['name'], PATHINFO_FILENAME);
                
                // Create uploads directory if it doesn't exist
                if (!is_dir('uploads')) {
                    mkdir('uploads', 0777, true);
                }
                
                $file_path = 'uploads/' . uniqid() . '_' . $file['name'];
                
                if (move_uploaded_file($file['tmp_name'], $file_path)) {
                    $stmt = $pdo->prepare("INSERT INTO sounds (name, category, file_path) VALUES (?, ?, ?)");
                    $stmt->execute([$name, $category, $file_path]);
                    echo json_encode(['success' => true, 'id' => $pdo->lastInsertId(), 'file_path' => $file_path]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'File upload failed']);
                }
            }
            exit;
            
        case 'save_setting':
            $key = $_POST['key'];
            $value = $_POST['value'];
            $stmt = $pdo->prepare("INSERT OR REPLACE INTO settings (setting_key, setting_value) VALUES (?, ?)");
            $stmt->execute([$key, $value]);
            echo json_encode(['success' => true]);
            exit;
            
        case 'get_setting':
            $key = $_POST['key'];
            $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
            $stmt->execute([$key]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode(['value' => $result ? $result['setting_value'] : null]);
            exit;
            
        case 'generate_sound_effect':
            $text = $_POST['text'];
            $category = $_POST['category'] ?? 'generated';
            $duration = $_POST['duration'] ?? null;
            $prompt_influence = $_POST['prompt_influence'] ?? 0.3;
            
            // Get API key
            $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'elevenlabs_api_key'");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$result || !$result['setting_value']) {
                echo json_encode(['success' => false, 'error' => 'ElevenLabs API key not configured']);
                exit;
            }
            
            $api_key = $result['setting_value'];
            
            // Prepare API request
            $url = 'https://api.elevenlabs.io/v1/sound-generation';
            $headers = [
                'xi-api-key: ' . $api_key,
                'Content-Type: application/json'
            ];
            
            $data = [
                'text' => $text,
                'prompt_influence' => floatval($prompt_influence)
            ];
            
            if ($duration) {
                $data['duration_seconds'] = floatval($duration);
            }
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($http_code === 200 && $response) {
                // Create uploads directory if it doesn't exist
                if (!is_dir('uploads')) {
                    mkdir('uploads', 0777, true);
                }
                
                // Save audio file
                $filename = 'generated_' . uniqid() . '.mp3';
                $file_path = 'uploads/' . $filename;
                
                if (file_put_contents($file_path, $response)) {
                    // Save to database
                    $sound_name = substr($text, 0, 50) . (strlen($text) > 50 ? '...' : '');
                    $stmt = $pdo->prepare("INSERT INTO sounds (name, category, subcategory, file_path) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$sound_name, $category, null, $file_path]);
                    
                    echo json_encode([
                        'success' => true, 
                        'id' => $pdo->lastInsertId(),
                        'name' => $sound_name,
                        'file_path' => $file_path
                    ]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Failed to save generated audio']);
                }
            } else {
                $error_msg = 'API request failed';
                if ($response) {
                    $error_data = json_decode($response, true);
                    if (isset($error_data['detail']['message'])) {
                        $error_msg = $error_data['detail']['message'];
                    }
                }
                echo json_encode(['success' => false, 'error' => $error_msg]);
            }
            exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Professional Beatbox Machine</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', sans-serif;
            background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
            min-height: 100vh;
            padding: 20px;
            color: #ffffff;
        }

        .beatbox-container {
            max-width: 1400px;
            margin: 0 auto;
            background: linear-gradient(145deg, #1e1e1e, #0a0a0a);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 
                0 20px 40px rgba(0, 0, 0, 0.5),
                inset 0 1px 0 rgba(255, 255, 255, 0.1),
                inset 0 -1px 0 rgba(0, 0, 0, 0.5);
            border: 2px solid #333;
            position: relative;
        }

        .beatbox-container::before {
            content: '';
            position: absolute;
            top: 10px;
            left: 10px;
            right: 10px;
            bottom: 10px;
            border-radius: 15px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            pointer-events: none;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 2px solid #333;
        }

        .logo {
            font-size: 2.5rem;
            font-weight: bold;
            background: linear-gradient(45deg, #ff6b6b, #4ecdc4, #45b7d1);
            background-clip: text;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-shadow: 0 0 30px rgba(255, 107, 107, 0.5);
        }

        .main-container {
            display: flex;
            gap: 20px;
            height: calc(100vh - 200px);
        }

        .sequencer-section {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .pattern-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .pattern-tab {
            background: linear-gradient(145deg, #2a2a2a, #1a1a1a);
            border: 1px solid #444;
            color: #fff;
            padding: 8px 15px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }

        .pattern-tab:hover {
            background: linear-gradient(145deg, #3a3a3a, #2a2a2a);
            box-shadow: 0 4px 15px rgba(255, 107, 107, 0.3);
        }

        .pattern-tab.active {
            background: linear-gradient(145deg, #ff6b6b, #e55656);
            box-shadow: 0 4px 15px rgba(255, 107, 107, 0.5);
        }

        .selected-sound {
            background: linear-gradient(145deg, #4ecdc4, #3bb3ac);
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 15px rgba(78, 205, 196, 0.3);
        }

        .sequencer {
            flex: 1;
            overflow-y: auto;
            background: linear-gradient(145deg, #1a1a1a, #0f0f0f);
            border-radius: 15px;
            padding: 20px;
            border: 2px solid #333;
            margin-bottom: 20px;
        }

        .step-header {
            display: flex;
            gap: 4px;
            margin-bottom: 15px;
        }

        .step-header .track-info {
            width: 250px;
            flex-shrink: 0;
        }

        .step-numbers {
            display: flex;
            gap: 4px;
            flex: 1;
        }

        .step-number {
            height: 30px;
            background: linear-gradient(145deg, #2a2a2a, #1a1a1a);
            border: 1px solid #444;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            font-weight: bold;
            flex: 1;
            transition: all 0.3s ease;
        }

        .step-number.active {
            background: linear-gradient(145deg, #ff6b6b, #e55656);
            box-shadow: 0 0 20px rgba(255, 107, 107, 0.6);
        }

        .track-row {
            display: flex;
            gap: 4px;
            margin-bottom: 8px;
            align-items: center;
        }

        .track-controls {
            width: 250px;
            flex-shrink: 0;
            background: linear-gradient(145deg, #2a2a2a, #1a1a1a);
            border: 1px solid #444;
            border-radius: 10px;
            padding: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .track-color {
            width: 16px;
            height: 16px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .track-name {
            font-size: 0.9rem;
            font-weight: 500;
            min-width: 60px;
            color: #fff;
        }

        .track-button {
            background: linear-gradient(145deg, #333, #222);
            border: 1px solid #555;
            color: #fff;
            padding: 4px 8px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.8rem;
            transition: all 0.3s ease;
        }

        .track-button:hover {
            background: linear-gradient(145deg, #444, #333);
        }

        .track-button.muted {
            background: linear-gradient(145deg, #ff6b6b, #e55656);
        }

        .volume-slider {
            width: 50px;
        }

        .step-buttons {
            display: flex;
            gap: 4px;
            flex: 1;
        }

        .step-button {
            height: 50px;
            background: linear-gradient(145deg, #2a2a2a, #1a1a1a);
            border: 2px solid #333;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 0.7rem;
            position: relative;
        }

        .step-button:hover {
            background: linear-gradient(145deg, #3a3a3a, #2a2a2a);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3);
        }

        .step-button.active {
            background: linear-gradient(145deg, #4ecdc4, #3bb3ac);
            box-shadow: 0 6px 20px rgba(78, 205, 196, 0.4);
        }

        .step-button.current {
            border-color: #ffeb3b;
            box-shadow: 0 0 15px rgba(255, 235, 59, 0.6);
        }

        .add-track {
            width: 250px;
            background: linear-gradient(145deg, #4ecdc4, #3bb3ac);
            border: none;
            color: #fff;
            padding: 15px;
            border-radius: 10px;
            cursor: pointer;
            font-weight: bold;
            margin-top: 10px;
            transition: all 0.3s ease;
        }

        .add-track:hover {
            background: linear-gradient(145deg, #5dd3ca, #4ecdc4);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(78, 205, 196, 0.4);
        }

        .controls {
            background: linear-gradient(145deg, #1a1a1a, #0f0f0f);
            border-radius: 15px;
            padding: 20px;
            border: 2px solid #333;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        .control-group {
            background: linear-gradient(145deg, #2a2a2a, #1a1a1a);
            padding: 15px;
            border-radius: 10px;
            border: 1px solid #444;
        }

        .control-group h3 {
            color: #4ecdc4;
            margin-bottom: 15px;
            font-size: 1rem;
        }

        .control-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .control-btn {
            background: linear-gradient(145deg, #333, #222);
            border: 1px solid #555;
            color: #fff;
            padding: 10px 15px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }

        .control-btn:hover {
            background: linear-gradient(145deg, #444, #333);
            transform: translateY(-2px);
        }

        .control-btn.playing {
            background: linear-gradient(145deg, #ff6b6b, #e55656);
            box-shadow: 0 4px 15px rgba(255, 107, 107, 0.4);
        }

        .control-btn.recording {
            background: linear-gradient(145deg, #ff6b6b, #e55656);
            animation: pulse 1s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }

        .slider-control {
            margin-bottom: 15px;
        }

        .slider-control label {
            display: block;
            margin-bottom: 5px;
            font-size: 0.9rem;
            color: #ccc;
        }

        .slider {
            width: 100%;
            height: 6px;
            background: #333;
            border-radius: 3px;
            outline: none;
            -webkit-appearance: none;
        }

        .slider::-webkit-slider-thumb {
            -webkit-appearance: none;
            width: 20px;
            height: 20px;
            background: linear-gradient(145deg, #4ecdc4, #3bb3ac);
            border-radius: 50%;
            cursor: pointer;
            box-shadow: 0 2px 10px rgba(78, 205, 196, 0.3);
        }

        .sidebar {
            width: 320px;
            background: linear-gradient(145deg, #1a1a1a, #0f0f0f);
            border-radius: 15px;
            padding: 20px;
            border: 2px solid #333;
            overflow-y: auto;
            transition: all 0.3s ease;
        }

        .sidebar.hidden {
            display: none;
        }

        .sidebar h2 {
            color: #4ecdc4;
            margin-bottom: 20px;
            font-size: 1.3rem;
        }

        .upload-controls {
            margin-bottom: 20px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .file-input {
            display: none;
        }

        .upload-btn {
            background: linear-gradient(145deg, #4ecdc4, #3bb3ac);
            border: none;
            color: #fff;
            padding: 12px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.3s ease;
        }

        .upload-btn:hover {
            background: linear-gradient(145deg, #5dd3ca, #4ecdc4);
            transform: translateY(-2px);
        }

        .sound-category {
            margin-bottom: 20px;
            background: linear-gradient(145deg, #2a2a2a, #1a1a1a);
            border-radius: 10px;
            padding: 15px;
            border: 1px solid #444;
        }

        .category-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .category-title {
            font-weight: bold;
            color: #4ecdc4;
            text-transform: capitalize;
        }

        .sound-item {
            background: linear-gradient(145deg, #333, #222);
            border: 1px solid #555;
            border-radius: 6px;
            padding: 8px 12px;
            margin-bottom: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.9rem;
        }

        .sound-item:hover {
            background: linear-gradient(145deg, #444, #333);
            transform: translateX(5px);
        }

        .sound-item.selected {
            background: linear-gradient(145deg, #4ecdc4, #3bb3ac);
            box-shadow: 0 4px 15px rgba(78, 205, 196, 0.3);
        }

        .delete-sound {
            background: #ff6b6b;
            border: none;
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.8rem;
        }

        .delete-sound:hover {
            background: #e55656;
        }

        .toggle-sidebar {
            background: linear-gradient(145deg, #4ecdc4, #3bb3ac);
            border: none;
            color: #fff;
            padding: 12px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.3s ease;
        }

        .toggle-sidebar:hover {
            background: linear-gradient(145deg, #5dd3ca, #4ecdc4);
            transform: translateY(-2px);
        }

        .new-category {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
        }

        .new-category input {
            flex: 1;
            background: #333;
            border: 1px solid #555;
            color: #fff;
            padding: 8px;
            border-radius: 4px;
        }

        /* Scrollbar styling */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #1a1a1a;
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb {
            background: linear-gradient(145deg, #4ecdc4, #3bb3ac);
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(145deg, #5dd3ca, #4ecdc4);
        }

        /* Settings Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(5px);
        }

        .modal-content {
            background: linear-gradient(145deg, #1e1e1e, #0a0a0a);
            margin: 5% auto;
            padding: 30px;
            border-radius: 20px;
            width: 90%;
            max-width: 500px;
            border: 2px solid #333;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.5);
            position: relative;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #333;
        }

        .modal-title {
            font-size: 1.5rem;
            font-weight: bold;
            color: #4ecdc4;
        }

        .close-modal {
            background: none;
            border: none;
            color: #fff;
            font-size: 2rem;
            cursor: pointer;
            padding: 0;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .close-modal:hover {
            color: #ff6b6b;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #ccc;
            font-weight: 500;
        }

        .form-group input[type="text"],
        .form-group input[type="password"],
        .form-group textarea {
            width: 100%;
            padding: 12px;
            background: linear-gradient(145deg, #2a2a2a, #1a1a1a);
            border: 2px solid #333;
            border-radius: 8px;
            color: #fff;
            font-size: 1rem;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #4ecdc4;
            box-shadow: 0 0 10px rgba(78, 205, 196, 0.3);
        }

        .form-group textarea {
            min-height: 80px;
            resize: vertical;
        }

        .save-btn {
            background: linear-gradient(145deg, #4ecdc4, #3bb3ac);
            border: none;
            color: #fff;
            padding: 12px 24px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
            font-size: 1rem;
            transition: all 0.3s ease;
            width: 100%;
        }

        .save-btn:hover {
            background: linear-gradient(145deg, #5dd3ca, #4ecdc4);
            transform: translateY(-2px);
        }

        .save-btn:disabled {
            background: #555;
            cursor: not-allowed;
            transform: none;
        }

        /* Sound Generation */
        .sound-generator {
            background: linear-gradient(145deg, #2a2a2a, #1a1a1a);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            border: 2px solid #444;
        }

        .generator-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 15px;
        }

        .generator-title {
            font-weight: bold;
            color: #4ecdc4;
            font-size: 1.1rem;
        }

        .generate-form {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .generate-btn {
            background: linear-gradient(145deg, #ff6b6b, #e55656);
            border: none;
            color: #fff;
            padding: 12px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.3s ease;
        }

        .generate-btn:hover {
            background: linear-gradient(145deg, #ff7a7a, #ff6b6b);
            transform: translateY(-2px);
        }

        .generate-btn:disabled {
            background: #555;
            cursor: not-allowed;
            transform: none;
        }

        .generate-btn.loading {
            background: linear-gradient(145deg, #ffa726, #ff9800);
            animation: pulse 1s infinite;
        }

        .advanced-options {
            background: linear-gradient(145deg, #1a1a1a, #0f0f0f);
            border-radius: 10px;
            padding: 15px;
            border: 1px solid #333;
        }

        .options-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
            margin-bottom: 10px;
        }

        .options-content {
            display: none;
        }

        .options-content.expanded {
            display: block;
        }

        .gear-icon {
            background: linear-gradient(145deg, #333, #222);
            border: 1px solid #555;
            color: #fff;
            padding: 8px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .gear-icon:hover {
            background: linear-gradient(145deg, #444, #333);
            transform: rotate(90deg);
        }

        .playback-btn {
            padding: 15px 20px !important;
            font-size: 1.2rem !important;
            min-width: 60px;
        }
    </style>
</head>
<body>
    <div class="beatbox-container">
        <div class="header">
            <div class="logo">🎵 BEATBOX PRO</div>
            <div style="display: flex; gap: 15px; align-items: center;">
                <!-- API Key Settings -->
                <div style="display: flex; align-items: center; gap: 10px; background: linear-gradient(145deg, #2a2a2a, #1a1a1a); padding: 10px 15px; border-radius: 10px; border: 1px solid #444;">
                    <button class="gear-icon" onclick="toggleApiKeyArea()" title="ElevenLabs Settings" id="api-toggle-btn">
                        ⚙️
                    </button>
                    <div id="api-key-area" style="display: none; align-items: center; gap: 10px;">
                        <input type="password" id="api-key-input" placeholder="ElevenLabs API Key" 
                               style="background: #333; border: 1px solid #555; color: #fff; padding: 8px; border-radius: 6px; width: 200px; font-size: 0.9rem;">
                        <button onclick="saveApiKey()" 
                                style="background: linear-gradient(145deg, #4ecdc4, #3bb3ac); border: none; color: #fff; padding: 8px 12px; border-radius: 6px; cursor: pointer; font-size: 0.9rem;">
                            💾
                        </button>
                        <small style="color: #888; font-size: 0.8rem;">
                            <a href="https://elevenlabs.io/app/settings/api-keys" target="_blank" style="color: #4ecdc4;">Get API Key</a>
                        </small>
                    </div>
                </div>
                
                <button class="toggle-sidebar" onclick="toggleSidebar()">
                    <span id="sidebar-toggle-text">🎵 Sound Library</span>
                </button>
                <button class="toggle-sidebar" onclick="toggleBeatsLibrary()">
                    <span id="beats-toggle-text">🎼 Beats Library</span>
                </button>
            </div>
        </div>

        <div class="main-container">
            <div class="sequencer-section">
                <!-- Pattern Management -->
                <div class="pattern-tabs" id="pattern-tabs">
                    <div class="pattern-tab active" onclick="switchPattern(0)">Pattern 1</div>
                    <button class="pattern-tab" onclick="addPattern()">+ Add Pattern</button>
                </div>

                <!-- Selected Sound Display -->
                <div class="selected-sound" id="selected-sound" style="display: none;">
                    <span>Selected Sound: <span id="selected-sound-name"></span></span>
                    <button onclick="clearSelectedSound()">✕</button>
                </div>

                <!-- Sequencer Grid -->
                <div class="sequencer">
                    <div class="step-header">
                        <div class="track-info"></div>
                        <div class="step-numbers" id="step-numbers">
                            <!-- Step numbers will be generated by JS -->
                        </div>
                    </div>
                    <div id="tracks-container">
                        <!-- Tracks will be generated by JS -->
                    </div>
                    <button class="add-track" onclick="addTrack()">+ Add Track</button>
                </div>

                <!-- Controls -->
                <div class="controls">
                    <div class="control-group">
                        <h3>🎮 Playback</h3>
                        <div class="control-buttons">
                            <button class="control-btn playback-btn" onclick="rewind()">⏮</button>
                            <button class="control-btn playback-btn" id="play-btn" onclick="togglePlay()">▶️</button>
                            <button class="control-btn playback-btn" onclick="stop()">⏹</button>
                        </div>
                    </div>

                    <div class="control-group">
                        <h3>🔊 Audio</h3>
                        <div class="slider-control">
                            <label>Tempo: <span id="tempo-value">120</span> BPM</label>
                            <input type="range" class="slider" id="tempo-slider" min="8" max="200" value="120" oninput="updateTempo(this.value)">
                        </div>
                        <div class="slider-control">
                            <label>Volume: <span id="volume-value">70</span>%</label>
                            <input type="range" class="slider" id="volume-slider" min="0" max="100" value="70" oninput="updateVolume(this.value)">
                        </div>
                        <div class="slider-control">
                            <label>Swing: <span id="swing-value">0</span>%</label>
                            <input type="range" class="slider" id="swing-slider" min="0" max="50" step="5" value="0" oninput="updateSwing(this.value)">
                        </div>
                    </div>

                    <div class="control-group">
                        <h3>🎙️ Recording</h3>
                        <div class="control-buttons">
                            <button class="control-btn" id="record-btn" onclick="toggleRecording()">🎙️ Record</button>
                            <button class="control-btn" onclick="savePattern()">💾 Save</button>
                            <button class="control-btn" onclick="exportPattern()">📥 Export</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sound Library Sidebar -->
            <div class="sidebar" id="sound-library">
                <h2>🎵 Sound Library</h2>
                
                <!-- Sound Effect Generator -->
                <div class="sound-generator">
                    <div class="generator-header">
                        <div class="generator-title">🤖 AI Sound Effects</div>
                    </div>
                    <div class="generate-form">
                        <div class="form-group">
                            <label>Describe your sound effect:</label>
                            <textarea id="sound-description" placeholder="Epic drum hit with reverb&#10;Futuristic laser beam&#10;Cat meowing&#10;Screaming guitar solo&#10;Deep bass drop"></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label>Category:</label>
                            <select id="generation-category" style="width: 100%; padding: 10px; background: #333; border: 1px solid #555; color: #fff; border-radius: 6px; font-size: 0.9rem;">
                                <option value="drums">🥁 Drums</option>
                                <option value="guitar">🎸 Guitar</option>
                                <option value="bass">🎸 Bass</option>
                                <option value="horns">🎺 Horns</option>
                                <option value="voices">🎤 Voices</option>
                                <option value="screams">😱 Screams</option>
                                <option value="animals">🐾 Animals</option>
                                <option value="sounds">🔊 Sounds</option>
                                <option value="weird">🤪 Weird</option>
                                <option value="custom">📁 Custom</option>
                            </select>
                        </div>
                        
                        <div class="advanced-options">
                            <div class="options-header" onclick="toggleAdvancedOptions()">
                                <span>⚙️ Advanced Options</span>
                                <span id="options-toggle">▼</span>
                            </div>
                            <div class="options-content" id="advanced-options-content">
                                <div class="form-group">
                                    <label>Duration: <span id="duration-display">Auto</span></label>
                                    <input type="range" class="slider" id="duration-slider" min="0.5" max="22" step="0.5" value="0" 
                                           oninput="updateDurationDisplay(this.value)">
                                    <small style="color: #888; font-size: 0.8rem;">Leave at Auto for best results</small>
                                </div>
                                <div class="form-group">
                                    <label>Prompt Influence: <span id="influence-display">0.3</span></label>
                                    <input type="range" class="slider" id="influence-slider" min="0" max="1" step="0.1" value="0.3" 
                                           oninput="updateInfluenceDisplay(this.value)">
                                    <small style="color: #888; font-size: 0.8rem;">Higher = more creative, Lower = more literal</small>
                                </div>
                            </div>
                        </div>
                        
                        <button class="generate-btn" id="generate-btn" onclick="generateSoundEffect()">
                            🎵 Generate Sound Effect
                        </button>
                    </div>
                </div>
                
                <div class="upload-controls">
                    <button class="upload-btn" id="mic-record-btn" onclick="toggleMicRecording()">
                        🎤 Record Audio
                    </button>
                    
                    <input type="file" class="file-input" id="audio-upload" accept="audio/*" onchange="handleFileUpload(event)">
                    <button class="upload-btn" onclick="document.getElementById('audio-upload').click()">
                        📁 Upload Audio
                    </button>
                    
                    <div class="new-category" id="new-category" style="display: none;">
                        <input type="text" id="new-category-name" placeholder="Category name">
                        <button class="control-btn" onclick="addNewCategory()">Add</button>
                        <button class="control-btn" onclick="cancelNewCategory()">✕</button>
                    </div>
                    <button class="upload-btn" onclick="showNewCategory()">+ New Category</button>
                </div>

                <div id="sound-categories">
                    <!-- Sound categories will be loaded here -->
                </div>
            </div>
            
            <!-- Beats Library Sidebar -->
            <div class="sidebar hidden" id="beats-library">
                <h2>🎼 Beats Library</h2>
                
                <div class="upload-controls">
                    <p style="color: #888; font-size: 0.9rem; margin-bottom: 15px;">
                        Record beats using the Record button below and save them here.
                    </p>
                </div>

                <div id="beats-gallery">
                    <!-- Beats will be loaded here -->
                </div>
            </div>
        </div>

        <!-- Settings Modal -->
        <div id="settings-modal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <div class="modal-title">⚙️ Settings</div>
                    <button class="close-modal" onclick="closeSettings()">×</button>
                </div>
                
                <div class="form-group">
                    <label for="elevenlabs-api-key">ElevenLabs API Key:</label>
                    <input type="password" id="elevenlabs-api-key" placeholder="Enter your ElevenLabs API key">
                    <small style="color: #888; display: block; margin-top: 5px;">
                        Get your API key from <a href="https://elevenlabs.io/app/settings/api-keys" target="_blank" style="color: #4ecdc4;">ElevenLabs Dashboard</a>
                    </small>
                </div>
                
                <button class="save-btn" onclick="saveSettings()">💾 Save Settings</button>
            </div>
        </div>
    </div>

    <script>
        // Global state
        let isPlaying = false;
        let isRecording = false;
        let isMicRecording = false;
        let currentStep = 0;
        let selectedSound = null;
        let selectedSoundPath = null;
        let currentPattern = 0;
        let tempo = 120;
        let masterVolume = 0.7;
        let swing = 0;
        let playInterval = null;
        let audioContext = null;
        let audioDestination = null;
        let audioRecorder = null;
        let mediaRecorder = null;
        let audioChunks = [];
        let micAudioChunks = [];
        let micMediaRecorder = null;

        // Initialize
        let patterns = [Array(8).fill().map(() => Array(16).fill(null))];
        let tracks = Array(8).fill().map((_, i) => ({
            id: i,
            name: `Track ${i + 1}`,
            volume: 0.8,
            muted: false,
            color: `hsl(${i * 45}, 70%, 50%)`
        }));

        // Sample presets
        const samplePresets = {
            '808 Kick': { type: 'sine', frequency: 60, duration: 0.3, filterFreq: 200 },
            'Acoustic Kick': { type: 'triangle', frequency: 80, duration: 0.2, filterFreq: 300 },
            'Sub Kick': { type: 'sine', frequency: 40, duration: 0.4, filterFreq: 150 },
            'Punchy Kick': { type: 'square', frequency: 70, duration: 0.15, filterFreq: 400 },
            'Acoustic Snare': { type: 'square', frequency: 200, duration: 0.1, filterFreq: 2000 },
            '808 Snare': { type: 'square', frequency: 150, duration: 0.08, filterFreq: 1500 },
            'Clap': { type: 'square', frequency: 300, duration: 0.05, filterFreq: 3000 },
            'Rim Shot': { type: 'triangle', frequency: 250, duration: 0.03, filterFreq: 2500 },
            'Closed Hat': { type: 'square', frequency: 8000, duration: 0.02, filterFreq: 10000 },
            'Open Hat': { type: 'square', frequency: 6000, duration: 0.15, filterFreq: 8000 },
            'Sub Bass': { type: 'sine', frequency: 80, duration: 0.5, filterFreq: 300 },
            'Lead Synth': { type: 'sawtooth', frequency: 440, duration: 0.3, filterFreq: 2000 }
        };

        // Default sound library
        let soundLibrary = {
            drums: [],
            guitar: [],
            bass: [],
            horns: [],
            voices: [],
            screams: [],
            animals: [],
            sounds: [],
            weird: [],
            custom: []
        };

        // Initialize audio context
        function initAudio() {
            if (!audioContext) {
                audioContext = new (window.AudioContext || window.webkitAudioContext)();
                
                // Create a destination for recording
                audioDestination = audioContext.createMediaStreamDestination();
                
                // Create master gain for overall volume control
                const masterGain = audioContext.createGain();
                masterGain.gain.value = 1.0;
                
                // Create a splitter to send audio to both speakers and recording
                const splitter = audioContext.createGain();
                
                // Connect: sounds -> splitter -> both destination and recording
                splitter.connect(masterGain);
                masterGain.connect(audioContext.destination); // To speakers
                splitter.connect(audioDestination); // To recording
                
                // Store references
                audioContext.masterGain = masterGain;
                audioContext.splitter = splitter;
                
                console.log('Audio context initialized with recording capability');
            }
        }

        // Create advanced sound
        function createAdvancedSound(type, params = {}) {
            if (!audioContext) return null;

            const {
                frequency = 440,
                duration = 0.1,
                volume = 0.7,
                attack = 0.01,
                decay = 0.1,
                sustain = 0.5,
                release = 0.2,
                filterFreq = 1000
            } = params;

            const oscillator = audioContext.createOscillator();
            const gainNode = audioContext.createGain();
            const filter = audioContext.createBiquadFilter();
            
            oscillator.type = type;
            oscillator.frequency.setValueAtTime(frequency, audioContext.currentTime);
            
            filter.type = 'lowpass';
            filter.frequency.setValueAtTime(filterFreq, audioContext.currentTime);
            
            gainNode.gain.setValueAtTime(0, audioContext.currentTime);
            gainNode.gain.linearRampToValueAtTime(volume, audioContext.currentTime + attack);
            gainNode.gain.linearRampToValueAtTime(volume * sustain, audioContext.currentTime + attack + decay);
            gainNode.gain.setValueAtTime(volume * sustain, audioContext.currentTime + duration - release);
            gainNode.gain.linearRampToValueAtTime(0, audioContext.currentTime + duration);
            
            oscillator.connect(filter);
            filter.connect(gainNode);
            
            // Connect to the splitter which routes to both speakers and recording
            if (audioContext.splitter) {
                gainNode.connect(audioContext.splitter);
            } else {
                gainNode.connect(audioContext.destination);
            }
            
            return { oscillator, gainNode, filter };
        }

        // Play sound
        function playSound(soundName, trackIndex = 0) {
            if (!soundName || !audioContext) return;
            
            const trackSettings = tracks[trackIndex];
            if (trackSettings?.muted) return;
            
            const soundParams = samplePresets[soundName] || samplePresets['808 Kick'];
            
            const finalParams = {
                ...soundParams,
                volume: (soundParams.volume || 0.7) * (trackSettings?.volume || 0.8) * masterVolume
            };
            
            const sound = createAdvancedSound(soundParams.type, finalParams);
            if (sound) {
                sound.oscillator.start();
                setTimeout(() => {
                    sound.oscillator.stop();
                }, finalParams.duration * 1000);
            }
        }

        // UI Functions
        function initUI() {
            renderStepNumbers();
            renderTracks();
            loadSounds();
            initAudio();
        }

        function renderStepNumbers() {
            const container = document.getElementById('step-numbers');
            container.innerHTML = '';
            for (let i = 0; i < 16; i++) {
                const stepDiv = document.createElement('div');
                stepDiv.className = 'step-number';
                stepDiv.textContent = i + 1;
                stepDiv.id = `step-${i}`;
                container.appendChild(stepDiv);
            }
        }

        function renderTracks() {
            const container = document.getElementById('tracks-container');
            container.innerHTML = '';
            
            tracks.forEach((track, trackIndex) => {
                const trackRow = document.createElement('div');
                trackRow.className = 'track-row';
                
                const trackControls = document.createElement('div');
                trackControls.className = 'track-controls';
                
                trackControls.innerHTML = `
                    <div class="track-color" style="background-color: ${track.color}"></div>
                    <div class="track-name">${track.name}</div>
                    <button class="track-button ${track.muted ? 'muted' : ''}" onclick="toggleMute(${trackIndex})">M</button>
                    <input type="range" class="volume-slider" min="0" max="100" value="${track.volume * 100}" 
                           onchange="updateTrackVolume(${trackIndex}, this.value)">
                    <button class="track-button" onclick="clearTrack(${trackIndex})">Clear</button>
                    <button class="track-button" onclick="removeTrack(${trackIndex})" style="color: #ff6b6b;">Del</button>
                `;
                
                const stepButtons = document.createElement('div');
                stepButtons.className = 'step-buttons';
                
                for (let i = 0; i < 16; i++) {
                    const stepButton = document.createElement('button');
                    stepButton.className = 'step-button';
                    stepButton.onclick = () => toggleStep(trackIndex, i);
                    
                    const currentPatternData = patterns[currentPattern] || patterns[0];
                    if (currentPatternData[trackIndex] && currentPatternData[trackIndex][i]) {
                        stepButton.classList.add('active');
                        const soundData = currentPatternData[trackIndex][i];
                        const displayName = typeof soundData === 'string' ? soundData : soundData.name;
                        stepButton.textContent = displayName.slice(0, 3);
                    }
                    
                    stepButtons.appendChild(stepButton);
                }
                
                trackRow.appendChild(trackControls);
                trackRow.appendChild(stepButtons);
                container.appendChild(trackRow);
            });
        }

        function toggleStep(trackIndex, stepIndex) {
            if (!patterns[currentPattern]) patterns[currentPattern] = Array(tracks.length).fill().map(() => Array(16).fill(null));
            
            if (selectedSound) {
                const currentValue = patterns[currentPattern][trackIndex][stepIndex];
                const soundData = {
                    name: selectedSound,
                    filePath: selectedSoundPath || null
                };
                
                patterns[currentPattern][trackIndex][stepIndex] = currentValue && currentValue.name === selectedSound ? null : soundData;
                
                if (patterns[currentPattern][trackIndex][stepIndex]) {
                    if (soundData.filePath) {
                        playCustomSound(soundData.filePath);
                    } else {
                        playSound(selectedSound, trackIndex);
                    }
                }
            } else {
                patterns[currentPattern][trackIndex][stepIndex] = null;
            }
            
            renderTracks();
        }

        function addTrack() {
            const newTrackIndex = tracks.length;
            const newTrack = {
                id: newTrackIndex,
                name: `Track ${newTrackIndex + 1}`,
                volume: 0.8,
                muted: false,
                color: `hsl(${newTrackIndex * 45}, 70%, 50%)`
            };
            
            tracks.push(newTrack);
            
            // Update all patterns
            patterns.forEach(pattern => {
                pattern.push(Array(16).fill(null));
            });
            
            renderTracks();
        }

        function removeTrack(index) {
            if (tracks.length > 1) {
                tracks.splice(index, 1);
                patterns.forEach(pattern => {
                    pattern.splice(index, 1);
                });
                renderTracks();
            }
        }

        function clearTrack(index) {
            if (patterns[currentPattern]) {
                patterns[currentPattern][index] = Array(16).fill(null);
                renderTracks();
            }
        }

        function toggleMute(index) {
            tracks[index].muted = !tracks[index].muted;
            renderTracks();
        }

        function updateTrackVolume(index, value) {
            tracks[index].volume = value / 100;
        }

        // Transport controls
        function togglePlay() {
            isPlaying = !isPlaying;
            const btn = document.getElementById('play-btn');
            
            if (isPlaying) {
                btn.textContent = '⏸️';
                btn.classList.add('playing');
                startPlayback();
            } else {
                btn.textContent = '▶️';
                btn.classList.remove('playing');
                stopPlayback();
            }
        }

        function stop() {
            isPlaying = false;
            currentStep = 0;
            const btn = document.getElementById('play-btn');
            btn.textContent = '▶️';
            btn.classList.remove('playing');
            stopPlayback();
            updateStepIndicator();
        }

        function rewind() {
            currentStep = 0;
            updateStepIndicator();
        }

        function startPlayback() {
            playInterval = setInterval(() => {
                if (!isPlaying) return;
                
                // Play current step
                if (patterns[currentPattern]) {
                    patterns[currentPattern].forEach((row, trackIndex) => {
                        if (row[currentStep]) {
                            const soundData = row[currentStep];
                            if (typeof soundData === 'string') {
                                playSound(soundData, trackIndex);
                            } else if (soundData.filePath) {
                                playCustomSound(soundData.filePath);
                            } else {
                                playSound(soundData.name, trackIndex);
                            }
                        }
                    });
                }
                
                currentStep = (currentStep + 1) % 16;
                updateStepIndicator();
            }, (60 / tempo) * 1000 / 4);
        }

        function stopPlayback() {
            if (playInterval) {
                clearInterval(playInterval);
                playInterval = null;
            }
        }

        function updateStepIndicator() {
            document.querySelectorAll('.step-number').forEach((el, i) => {
                el.classList.toggle('active', i === currentStep);
            });
            
            document.querySelectorAll('.step-button').forEach((el, i) => {
                const stepIndex = i % 16;
                el.classList.toggle('current', stepIndex === currentStep);
            });
        }

        // Control functions
        function updateTempo(value) {
            tempo = parseInt(value);
            document.getElementById('tempo-value').textContent = tempo;
            
            if (isPlaying) {
                stopPlayback();
                startPlayback();
            }
        }

        function updateVolume(value) {
            masterVolume = value / 100;
            document.getElementById('volume-value').textContent = value;
        }

        function updateSwing(value) {
            swing = parseInt(value);
            document.getElementById('swing-value').textContent = swing;
        }


        function clearSelectedSound() {
            selectedSound = null;
            selectedSoundPath = null;
            document.getElementById('selected-sound').style.display = 'none';
            document.querySelectorAll('.sound-item').forEach(el => {
                el.classList.remove('selected');
            });
        }

        function toggleSidebar() {
            const sidebar = document.getElementById('sound-library');
            const isHidden = sidebar.classList.contains('hidden');
            
            if (isHidden) {
                sidebar.classList.remove('hidden');
                document.getElementById('sidebar-toggle-text').textContent = '🎵 Hide Library';
            } else {
                sidebar.classList.add('hidden');
                document.getElementById('sidebar-toggle-text').textContent = '🎵 Sound Library';
            }
        }

        // Load sounds from database
        function loadSounds() {
            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=load_sounds'
            })
            .then(response => response.json())
            .then(sounds => {
                renderSoundLibrary(sounds);
            })
            .catch(error => console.error('Error loading sounds:', error));
        }

        function renderSoundLibrary(customSounds = []) {
            const container = document.getElementById('sound-categories');
            container.innerHTML = '';
            
            // Merge default sounds with custom sounds from database
            const allSounds = { ...soundLibrary };
            
            customSounds.forEach(sound => {
                // Skip recorded beats - they belong in the beats library
                if (sound.category === 'recorded_beats') {
                    return;
                }
                
                if (!allSounds[sound.category]) {
                    allSounds[sound.category] = [];
                }
                // Check if sound already exists to prevent duplicates
                const existsAlready = allSounds[sound.category].some(existingSound => 
                    (typeof existingSound === 'object' && existingSound.id === sound.id) ||
                    (typeof existingSound === 'string' && existingSound === sound.name)
                );
                if (!existsAlready) {
                    allSounds[sound.category].push(sound);
                }
            });
            
            Object.entries(allSounds).forEach(([category, sounds]) => {
                // Skip empty categories except custom
                if (sounds.length === 0 && category !== 'custom') return;
                
                const categoryDiv = document.createElement('div');
                categoryDiv.className = 'sound-category';
                
                const headerDiv = document.createElement('div');
                headerDiv.className = 'category-header';
                headerDiv.innerHTML = `
                    <div class="category-title">📁 ${category}</div>
                    <input type="file" class="file-input" id="upload-${category}" accept="audio/*" 
                           onchange="handleCategoryUpload(event, '${category}')">
                    <button class="upload-btn" style="padding: 4px 8px; font-size: 0.8rem;" 
                            onclick="document.getElementById('upload-${category}').click()">📁</button>
                `;
                
                const soundsDiv = document.createElement('div');
                
                sounds.forEach(sound => {
                    const soundItem = document.createElement('div');
                    soundItem.className = 'sound-item';
                    soundItem.dataset.sound = typeof sound === 'string' ? sound : sound.name;
                    
                    const soundName = typeof sound === 'string' ? sound : sound.name;
                    const isCustomSound = typeof sound === 'object' && sound.file_path;
                    
                    soundItem.innerHTML = `
                        <span onclick="selectSound('${soundName}', ${isCustomSound ? `'${sound.file_path}'` : 'null'})" style="cursor: pointer; flex: 1;">${soundName}</span>
                        <div style="display: flex; gap: 4px;">
                            <button class="delete-sound" style="padding: 2px 6px;" onclick="event.stopPropagation(); ${isCustomSound ? `playCustomSound('${sound.file_path}')` : `playSound('${soundName}')`}" title="Play Sound">▶️</button>
                            ${typeof sound === 'object' ? `<button class="delete-sound" style="padding: 2px 6px;" onclick="event.stopPropagation(); renameSound(${sound.id}, '${soundName}')">✏️</button>` : ''}
                            ${typeof sound === 'object' ? `<button class="delete-sound" onclick="event.stopPropagation(); deleteSound(${sound.id})">🗑️</button>` : ''}
                        </div>
                    `;
                    soundsDiv.appendChild(soundItem);
                });
                
                categoryDiv.appendChild(headerDiv);
                categoryDiv.appendChild(soundsDiv);
                container.appendChild(categoryDiv);
            });
        }

        function handleFileUpload(event) {
            const file = event.target.files[0];
            if (file && file.type.startsWith('audio/')) {
                const formData = new FormData();
                formData.append('action', 'upload_audio');
                formData.append('audio', file);
                formData.append('category', 'custom');
                
                fetch('', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        loadSounds();
                    } else {
                        alert('Upload failed: ' + result.error);
                    }
                })
                .catch(error => console.error('Error uploading file:', error));
            }
        }

        function handleCategoryUpload(event, category) {
            const file = event.target.files[0];
            if (file && file.type.startsWith('audio/')) {
                const formData = new FormData();
                formData.append('action', 'upload_audio');
                formData.append('audio', file);
                formData.append('category', category);
                
                fetch('', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        loadSounds();
                    } else {
                        alert('Upload failed: ' + result.error);
                    }
                })
                .catch(error => console.error('Error uploading file:', error));
            }
        }

        function deleteSound(soundId) {
            if (confirm('Are you sure you want to delete this sound?')) {
                fetch('', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=delete_sound&id=${soundId}`
                })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        loadSounds();
                    }
                })
                .catch(error => console.error('Error deleting sound:', error));
            }
        }

        function renameSound(soundId, currentName) {
            const newName = prompt('Enter new name for this sound:', currentName);
            if (newName && newName.trim() !== '' && newName !== currentName) {
                fetch('', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=rename_sound&id=${soundId}&new_name=${encodeURIComponent(newName.trim())}`
                })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        loadSounds();
                        // Update selected sound name if this was the selected sound
                        if (selectedSound === currentName) {
                            selectedSound = newName.trim();
                            document.getElementById('selected-sound-name').textContent = newName.trim();
                        }
                    } else {
                        alert('Failed to rename sound');
                    }
                })
                .catch(error => {
                    console.error('Error renaming sound:', error);
                    alert('Error renaming sound');
                });
            }
        }

        function showNewCategory() {
            document.getElementById('new-category').style.display = 'flex';
        }

        function cancelNewCategory() {
            document.getElementById('new-category').style.display = 'none';
            document.getElementById('new-category-name').value = '';
        }

        function addNewCategory() {
            const name = document.getElementById('new-category-name').value.trim();
            if (name) {
                soundLibrary[name] = [];
                cancelNewCategory();
                loadSounds(); // Reload from database to get fresh data
            }
        }

        // Pattern management
        function addPattern() {
            patterns.push(Array(tracks.length).fill().map(() => Array(16).fill(null)));
            currentPattern = patterns.length - 1;
            renderPatternTabs();
            renderTracks();
        }

        function switchPattern(index) {
            currentPattern = index;
            renderPatternTabs();
            renderTracks();
        }

        function renderPatternTabs() {
            const container = document.getElementById('pattern-tabs');
            container.innerHTML = '';
            
            patterns.forEach((_, index) => {
                const tab = document.createElement('div');
                tab.className = `pattern-tab ${index === currentPattern ? 'active' : ''}`;
                tab.textContent = `Pattern ${index + 1}`;
                tab.onclick = () => switchPattern(index);
                container.appendChild(tab);
            });
            
            const addBtn = document.createElement('button');
            addBtn.className = 'pattern-tab';
            addBtn.textContent = '+ Add Pattern';
            addBtn.onclick = addPattern;
            container.appendChild(addBtn);
        }

        // Save/Export functions
        function savePattern() {
            const patternName = prompt('Enter pattern name:') || `Pattern ${Date.now()}`;
            const patternData = JSON.stringify({
                patterns,
                tracks,
                tempo,
                swing,
                masterVolume
            });
            
            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=save_pattern&name=${encodeURIComponent(patternName)}&data=${encodeURIComponent(patternData)}`
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    alert('Pattern saved successfully!');
                } else {
                    alert('Failed to save pattern');
                }
            })
            .catch(error => console.error('Error saving pattern:', error));
        }

        function exportPattern() {
            const patternData = {
                patterns,
                tracks,
                tempo,
                swing,
                masterVolume,
                timestamp: new Date().toISOString()
            };
            
            const dataStr = JSON.stringify(patternData, null, 2);
            const dataBlob = new Blob([dataStr], { type: 'application/json' });
            const url = URL.createObjectURL(dataBlob);
            
            const link = document.createElement('a');
            link.href = url;
            link.download = `beatbox-pattern-${Date.now()}.json`;
            link.click();
            
            URL.revokeObjectURL(url);
        }

        function toggleRecording() {
            if (!isRecording) {
                startRecording();
            } else {
                stopRecording();
            }
        }

        function startRecording() {
            // Ensure audio context is initialized
            if (!audioContext) {
                initAudio();
            }
            
            if (!audioDestination || !audioDestination.stream) {
                alert('Audio recording not available. Please try refreshing the page.');
                return;
            }
            
            console.log('Audio destination stream active:', audioDestination.stream.active);
            console.log('Audio destination stream tracks:', audioDestination.stream.getTracks().length);
            
            console.log('Starting app audio recording...');
            
            // Try different formats in order of preference
            let options = {};
            if (MediaRecorder.isTypeSupported('audio/webm;codecs=opus')) {
                options.mimeType = 'audio/webm;codecs=opus';
            } else if (MediaRecorder.isTypeSupported('audio/webm')) {
                options.mimeType = 'audio/webm';
            } else if (MediaRecorder.isTypeSupported('audio/mp4')) {
                options.mimeType = 'audio/mp4';
            } else if (MediaRecorder.isTypeSupported('audio/ogg;codecs=opus')) {
                options.mimeType = 'audio/ogg;codecs=opus';
            }
            
            console.log('Using MIME type for app recording:', options.mimeType);
            
            // Create MediaRecorder from the audio destination stream
            mediaRecorder = new MediaRecorder(audioDestination.stream, options);
            audioChunks = [];
            
            mediaRecorder.ondataavailable = event => {
                if (event.data.size > 0) {
                    console.log('App audio data received, size:', event.data.size);
                    audioChunks.push(event.data);
                }
            };
            
            mediaRecorder.onstop = () => {
                console.log('App recording stopped, chunks:', audioChunks.length);
                if (audioChunks.length > 0) {
                    const mimeType = mediaRecorder.mimeType || 'audio/webm';
                    const audioBlob = new Blob(audioChunks, { type: mimeType });
                    console.log('Created app audio blob, size:', audioBlob.size, 'type:', audioBlob.type);
                    saveBeatRecording(audioBlob, mimeType);
                } else {
                    alert('No app audio was recorded. Make sure beats are playing while recording.');
                }
            };
            
            mediaRecorder.start();
            isRecording = true;
            document.getElementById('record-btn').classList.add('recording');
            document.getElementById('record-btn').textContent = '⏹️ Stop Recording';
            
            console.log('App audio recording started. Play some beats!');
        }

        function stopRecording() {
            if (mediaRecorder && mediaRecorder.state !== 'inactive') {
                mediaRecorder.stop();
            }
            isRecording = false;
            document.getElementById('record-btn').classList.remove('recording');
            document.getElementById('record-btn').textContent = '🎙️ Record';
            
            console.log('App audio recording stopped');
        }

        // Initialize the application
        document.addEventListener('DOMContentLoaded', function() {
            initUI();
            renderPatternTabs();
            loadSettings();
            
            // Initialize audio context early (user interaction required)
            document.addEventListener('click', function initAudioOnFirstClick() {
                initAudio();
                document.removeEventListener('click', initAudioOnFirstClick);
            }, { once: true });
            
            // Start with sidebar visible
            document.getElementById('sound-library').classList.remove('hidden');
        });

        // Settings Management
        function toggleApiKeyArea() {
            const apiArea = document.getElementById('api-key-area');
            const toggleBtn = document.getElementById('api-toggle-btn');
            
            if (apiArea.style.display === 'none' || apiArea.style.display === '') {
                apiArea.style.display = 'flex';
                toggleBtn.style.transform = 'rotate(90deg)';
                loadApiKey();
            } else {
                apiArea.style.display = 'none';
                toggleBtn.style.transform = 'rotate(0deg)';
            }
        }

        function loadApiKey() {
            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=get_setting&key=elevenlabs_api_key'
            })
            .then(response => response.json())
            .then(result => {
                if (result.value) {
                    document.getElementById('api-key-input').value = result.value;
                }
            })
            .catch(error => console.error('Error loading API key:', error));
        }

        function saveApiKey() {
            const apiKey = document.getElementById('api-key-input').value.trim();
            
            if (!apiKey) {
                alert('Please enter your ElevenLabs API key');
                return;
            }
            
            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=save_setting&key=elevenlabs_api_key&value=${encodeURIComponent(apiKey)}`
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    alert('API key saved successfully!');
                    // Visual feedback
                    const saveBtn = event.target;
                    saveBtn.textContent = '✅';
                    setTimeout(() => {
                        saveBtn.textContent = '💾';
                    }, 2000);
                } else {
                    alert('Failed to save API key');
                }
            })
            .catch(error => {
                console.error('Error saving API key:', error);
                alert('Error saving API key');
            });
        }

        // Sound Effect Generation
        function toggleAdvancedOptions() {
            const content = document.getElementById('advanced-options-content');
            const toggle = document.getElementById('options-toggle');
            
            if (content.classList.contains('expanded')) {
                content.classList.remove('expanded');
                toggle.textContent = '▼';
            } else {
                content.classList.add('expanded');
                toggle.textContent = '▲';
            }
        }

        function updateDurationDisplay(value) {
            const display = document.getElementById('duration-display');
            display.textContent = value == 0 ? 'Auto' : value + 's';
        }

        function updateInfluenceDisplay(value) {
            document.getElementById('influence-display').textContent = value;
        }

        function generateSoundEffect() {
            const description = document.getElementById('sound-description').value.trim();
            const duration = document.getElementById('duration-slider').value;
            const influence = document.getElementById('influence-slider').value;
            const category = document.getElementById('generation-category').value;
            
            if (!description) {
                alert('Please describe the sound effect you want to generate');
                return;
            }
            
            const generateBtn = document.getElementById('generate-btn');
            generateBtn.disabled = true;
            generateBtn.classList.add('loading');
            generateBtn.textContent = '🔄 Generating...';
            
            const formData = new FormData();
            formData.append('action', 'generate_sound_effect');
            formData.append('text', description);
            formData.append('category', category);
            formData.append('prompt_influence', influence);
            
            if (duration > 0) {
                formData.append('duration', duration);
            }
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    document.getElementById('sound-description').value = '';
                    loadSounds(); // Refresh the sound library
                    
                    // Auto-select the generated sound
                    if (result.name) {
                        setTimeout(() => {
                            selectSound(result.name, result.file_path);
                        }, 500);
                    }
                } else {
                    alert('Generation failed: ' + (result.error || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error generating sound effect:', error);
                alert('Error generating sound effect: ' + error.message);
            })
            .finally(() => {
                generateBtn.disabled = false;
                generateBtn.classList.remove('loading');
                generateBtn.textContent = '🎵 Generate Sound Effect';
            });
        }


        // Enhanced selectSound function to handle custom audio files
        function selectSound(soundName, filePath = null) {
            selectedSound = soundName;
            selectedSoundPath = filePath;
            document.getElementById('selected-sound').style.display = 'flex';
            document.getElementById('selected-sound-name').textContent = soundName;
            
            // Update UI
            document.querySelectorAll('.sound-item').forEach(el => {
                el.classList.toggle('selected', el.dataset.sound === soundName);
            });
            
            // Play sound
            if (filePath) {
                playCustomSound(filePath);
            } else {
                playSound(soundName);
            }
        }

        // Play custom uploaded audio files
        function playCustomSound(filePath) {
            if (!audioContext) {
                initAudio();
            }
            
            // Create URL with proper audio serving endpoint
            const audioUrl = `?action=serve_audio&file=${encodeURIComponent(filePath)}`;
            console.log('Playing custom sound from:', audioUrl);
            
            const audio = new Audio(audioUrl);
            audio.volume = masterVolume;
            
            // Add error handling
            audio.addEventListener('error', (e) => {
                console.error('Audio playback error:', e);
                console.error('Failed to load audio from:', audioUrl);
                alert('Unable to play this sound. File may be missing or corrupted.');
            });
            
            audio.addEventListener('loadstart', () => {
                console.log('Audio loading started for:', filePath);
            });
            
            // Always route through Web Audio API for consistent recording
            if (audioContext && audioContext.splitter) {
                try {
                    const audioElement = audioContext.createMediaElementSource(audio);
                    audioElement.connect(audioContext.splitter);
                } catch (error) {
                    console.log('Could not route custom audio through Web Audio API:', error);
                    // Fall back to direct playback
                }
            }
            
            audio.play().catch(error => {
                console.error('Error playing custom sound:', error);
            });
        }

        // Beats Library Functions
        function toggleBeatsLibrary() {
            const beatsLibrary = document.getElementById('beats-library');
            const soundLibrary = document.getElementById('sound-library');
            const beatsToggleText = document.getElementById('beats-toggle-text');
            
            if (beatsLibrary.classList.contains('hidden')) {
                // Show beats library, hide sound library
                beatsLibrary.classList.remove('hidden');
                soundLibrary.classList.add('hidden');
                beatsToggleText.textContent = '🎼 Hide Beats';
                document.getElementById('sidebar-toggle-text').textContent = '🎵 Sound Library';
                loadBeats();
            } else {
                // Hide beats library
                beatsLibrary.classList.add('hidden');
                beatsToggleText.textContent = '🎼 Beats Library';
            }
        }

        function saveBeatRecording(audioBlob, mimeType) {
            const beatName = prompt('Enter a name for this beat:') || `Beat ${Date.now()}`;
            
            // Determine file extension based on mime type
            let extension = '.webm';
            if (mimeType.includes('mp4')) extension = '.mp4';
            else if (mimeType.includes('ogg')) extension = '.ogg';
            else if (mimeType.includes('wav')) extension = '.wav';
            
            const formData = new FormData();
            formData.append('action', 'upload_audio');
            formData.append('audio', audioBlob, `${beatName}${extension}`);
            formData.append('category', 'recorded_beats');
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    // Get the actual file path from the upload result
                    const actualFilePath = result.file_path || `uploads/${beatName}${extension}`;
                    console.log('Beat file uploaded successfully to:', actualFilePath);
                    
                    // Save beat metadata
                    const beatFormData = new FormData();
                    beatFormData.append('action', 'save_beat');
                    beatFormData.append('name', beatName);
                    beatFormData.append('file_path', actualFilePath);
                    beatFormData.append('tempo', tempo);
                    
                    return fetch('', {
                        method: 'POST',
                        body: beatFormData
                    });
                } else {
                    throw new Error('Failed to upload beat recording');
                }
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    console.log('Beat saved successfully to database');
                    if (!document.getElementById('beats-library').classList.contains('hidden')) {
                        loadBeats();
                    }
                } else {
                    console.error('Failed to save beat metadata:', result);
                    alert('Failed to save beat metadata');
                }
            })
            .catch(error => {
                console.error('Error saving beat:', error);
                alert('Error saving beat: ' + error.message);
            });
        }

        function loadBeats() {
            fetch('', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=load_beats'
            })
            .then(response => response.json())
            .then(beats => {
                renderBeatsLibrary(beats);
            })
            .catch(error => console.error('Error loading beats:', error));
        }

        function renderBeatsLibrary(beats) {
            const container = document.getElementById('beats-gallery');
            container.innerHTML = '';
            
            if (beats.length === 0) {
                container.innerHTML = '<p style="color: #888; text-align: center; margin: 20px 0;">No beats recorded yet. Use the Record button to create your first beat!</p>';
                return;
            }
            
            beats.forEach(beat => {
                const beatItem = document.createElement('div');
                beatItem.className = 'sound-item';
                beatItem.style.marginBottom = '10px';
                
                beatItem.innerHTML = `
                    <div style="display: flex; flex-direction: column; gap: 5px; flex: 1;">
                        <span style="font-weight: bold;">${beat.name}</span>
                        <small style="color: #888;">
                            ${beat.tempo ? `${beat.tempo} BPM • ` : ''}
                            ${new Date(beat.created_at).toLocaleDateString()}
                        </small>
                    </div>
                    <div style="display: flex; gap: 4px;">
                        <button class="delete-sound" style="padding: 4px 8px;" onclick="playBeat('${beat.file_path}', ${beat.id})">▶️</button>
                        <button class="delete-sound" onclick="deleteBeat(${beat.id})">🗑️</button>
                    </div>
                `;
                container.appendChild(beatItem);
            });
        }

        function playBeat(filePath, beatId = null) {
            console.log('Attempting to play beat with file path:', filePath);
            
            // Create a URL to serve the audio file directly
            const audioUrl = `?action=serve_audio&file=${encodeURIComponent(filePath)}`;
            console.log('Audio URL:', audioUrl);
            
            const audio = new Audio(audioUrl);
            audio.volume = masterVolume;
            
            audio.addEventListener('loadstart', () => {
                console.log('Audio loading started');
            });
            
            audio.addEventListener('canplay', () => {
                console.log('Audio can play');
            });
            
            audio.addEventListener('error', (e) => {
                console.error('Audio error:', e);
                console.error('Audio error details:', audio.error);
                alert('Unable to play this beat. Error: ' + (audio.error ? audio.error.message : 'Unknown error'));
            });
            
            audio.play().catch(error => {
                console.error('Play failed:', error);
                alert('Playback failed: ' + error.message);
            });
        }

        function deleteBeat(beatId) {
            if (confirm('Are you sure you want to delete this beat?')) {
                fetch('', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `action=delete_beat&id=${beatId}`
                })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        loadBeats();
                    } else {
                        alert('Failed to delete beat');
                    }
                })
                .catch(error => {
                    console.error('Error deleting beat:', error);
                    alert('Error deleting beat');
                });
            }
        }

        // Microphone Recording Functions for Sound Library
        function toggleMicRecording() {
            if (!isMicRecording) {
                startMicRecording();
            } else {
                stopMicRecording();
            }
        }

        function startMicRecording() {
            navigator.mediaDevices.getUserMedia({ audio: true })
                .then(stream => {
                    console.log('Microphone access granted for sound recording...');
                    
                    let options = {};
                    if (MediaRecorder.isTypeSupported('audio/webm;codecs=opus')) {
                        options.mimeType = 'audio/webm;codecs=opus';
                    } else if (MediaRecorder.isTypeSupported('audio/webm')) {
                        options.mimeType = 'audio/webm';
                    } else if (MediaRecorder.isTypeSupported('audio/mp4')) {
                        options.mimeType = 'audio/mp4';
                    }
                    
                    micMediaRecorder = new MediaRecorder(stream, options);
                    micAudioChunks = [];
                    
                    micMediaRecorder.ondataavailable = event => {
                        if (event.data.size > 0) {
                            micAudioChunks.push(event.data);
                        }
                    };
                    
                    micMediaRecorder.onstop = () => {
                        if (micAudioChunks.length > 0) {
                            const mimeType = micMediaRecorder.mimeType || 'audio/webm';
                            const audioBlob = new Blob(micAudioChunks, { type: mimeType });
                            saveMicRecording(audioBlob, mimeType);
                        }
                        // Stop all tracks
                        stream.getTracks().forEach(track => track.stop());
                    };
                    
                    micMediaRecorder.start();
                    isMicRecording = true;
                    document.getElementById('mic-record-btn').classList.add('recording');
                    document.getElementById('mic-record-btn').textContent = '⏹️ Stop Recording';
                })
                .catch(error => {
                    console.error('Error accessing microphone:', error);
                    alert('Could not access microphone');
                });
        }

        function stopMicRecording() {
            if (micMediaRecorder && micMediaRecorder.state !== 'inactive') {
                micMediaRecorder.stop();
            }
            isMicRecording = false;
            document.getElementById('mic-record-btn').classList.remove('recording');
            document.getElementById('mic-record-btn').textContent = '🎤 Record Audio';
        }

        function saveMicRecording(audioBlob, mimeType) {
            const soundName = prompt('Enter a name for this sound:') || `Mic Recording ${Date.now()}`;
            const category = prompt('Which category should this go in?', 'custom') || 'custom';
            
            let extension = '.webm';
            if (mimeType.includes('mp4')) extension = '.mp4';
            else if (mimeType.includes('ogg')) extension = '.ogg';
            
            const formData = new FormData();
            formData.append('action', 'upload_audio');
            formData.append('audio', audioBlob, `${soundName}${extension}`);
            formData.append('category', category);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    console.log('Microphone recording saved successfully to sound library');
                    loadSounds(); // Refresh sound library
                    
                    // Auto-select the new recording
                    setTimeout(() => {
                        selectSound(soundName, result.file_path);
                    }, 500);
                } else {
                    alert('Failed to save recording: ' + (result.error || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error saving mic recording:', error);
                alert('Error saving recording: ' + error.message);
            });
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('settings-modal');
            if (event.target === modal) {
                closeSettings();
            }
        }
    </script>
</body>
</html>