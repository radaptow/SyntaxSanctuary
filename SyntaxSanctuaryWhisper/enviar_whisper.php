<?php
session_start();

// Validar que el usuario esté autenticado
if (!isset($_SESSION['logeado']) || $_SESSION['logeado'] !== true) {
    http_response_code(403);
    echo json_encode(['error' => 'Acceso denegado. Inicia sesión primero.']);
    exit;
}

header('Content-Type: application/json');

if (!isset($_FILES['file'])) {
    http_response_code(400);
    echo json_encode(['error' => 'No se recibió ningún archivo de audio.']);
    exit;
}

// La IP de tu máquina Windows en la red local
$url = 'http://xxx.xxx.xxx.xxx:8000/v1/audio/transcriptions';

// Preparamos el archivo para enviarlo a Whisper
$cfile = new CURLFile(
    $_FILES['file']['tmp_name'], 
    $_FILES['file']['type'], 
    $_FILES['file']['name']
);

$post_fields = [
    'file' => $cfile,
    'model' => 'medium',
    'language' => 'es'
];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

// Eliminar límite de tiempo en la transferencia y en la conexión
curl_setopt($ch, CURLOPT_TIMEOUT, 0);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if(curl_errno($ch)){
    http_response_code(500);
    echo json_encode(['error' => 'Error de conexión con Windows: ' . curl_error($ch)]);
} else {
    http_response_code($http_code);
    echo $response;
}

curl_close($ch);
?>