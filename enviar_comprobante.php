<?php

session_start();
header('Content-Type: application/json');

if(!isset($_SESSION['usuario_id'])){
    echo json_encode(['ok'=>false,'msg'=>'No autorizado']); exit;
}

require 'includes/db.php'; 

$usuario_id = intval($_SESSION['usuario_id']);


if($_SERVER['REQUEST_METHOD'] !== 'POST'){
    echo json_encode(['ok'=>false,'msg'=>'Método no permitido']); exit;
}


$monto = isset($_POST['monto']) ? floatval($_POST['monto']) : 0;
if($monto <= 0){
    echo json_encode(['ok'=>false,'msg'=>'Monto inválido']); exit;
}


$metodo = isset($_POST['metodo']) ? $mysqli->real_escape_string($_POST['metodo']) : 'otro';
$trans_id = isset($_POST['trans_id']) ? $mysqli->real_escape_string($_POST['trans_id']) : null;


if(empty($_FILES['comprobante']) || $_FILES['comprobante']['error'] !== UPLOAD_ERR_OK){
    echo json_encode(['ok'=>false,'msg'=>'Falta el comprobante o error en la subida']); exit;
}

$file = $_FILES['comprobante'];
$allowed = ['image/jpeg','image/png','application/pdf'];
if(!in_array($file['type'], $allowed)){
    echo json_encode(['ok'=>false,'msg'=>'Tipo de archivo no permitido (solo jpg/png/pdf)']); exit;
}
if($file['size'] > 5 * 1024 * 1024){
    echo json_encode(['ok'=>false,'msg'=>'Archivo demasiado grande (máx 5MB)']); exit;
}

// Preparar carpeta
$uploadDir = __DIR__ . '/uploads/comprobantes';
if(!is_dir($uploadDir)){
    if(!mkdir($uploadDir, 0755, true)){
        echo json_encode(['ok'=>false,'msg'=>'No se pudo crear carpeta de uploads']); exit;
    }
}

// Generar nombre único
$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = 'comp_' . $usuario_id . '_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
$targetPath = $uploadDir . '/' . $filename;

// Mover archivo
if(!move_uploaded_file($file['tmp_name'], $targetPath)){
    echo json_encode(['ok'=>false,'msg'=>'Error guardando el archivo']); exit;
}

// Guardar en DB (tabla recargas: usuario_id,monto,estado,created_at,comprobante,metodo,trans_id)
$stmt = $mysqli->prepare("INSERT INTO recargas (usuario_id,monto,estado,created_at,comprobante,metodo,trans_id) VALUES (?, ?, 'pendiente', NOW(), ?, ?, ?)");
if(!$stmt){
    echo json_encode(['ok'=>false,'msg'=>'Error en BD: prepare fail']); exit;
}
$pathDb = 'uploads/comprobantes/' . $filename;
$stmt->bind_param('idsss', $usuario_id, $monto, $pathDb, $metodo, $trans_id);
$ok = $stmt->execute();
if(!$ok){
    // borrar archivo si falla insert
    @unlink($targetPath);
    echo json_encode(['ok'=>false,'msg'=>'Error al guardar en BD']); exit;
}

echo json_encode(['ok'=>true,'msg'=>'Comprobante subido correctamente']);
exit;
