<?php
// Archivo: includes/whatsapp_sender.php
function enviarWhatsApp($numero, $mensaje) {
    // Tu número de WhatsApp para enviar
    $tu_numero = "964279873";
    
    // Formatear mensaje para URL
    $mensaje_codificado = urlencode($mensaje);
    
    // Crear enlace de WhatsApp Web/API
    $url = "https://api.whatsapp.com/send?phone=" . $numero . "&text=" . $mensaje_codificado;
    
    // Para debugging
    error_log(" Enviando WhatsApp a: " . $numero);
    error_log(" Mensaje: " . $mensaje);
    error_log("URL: " . $url);
    
    // **OPCIÓN 1: Redirigir a WhatsApp Web (más simple)**
    // El usuario debe tener WhatsApp Web abierto
    // header("Location: " . $url);
    // exit;
    
    // **OPCIÓN 2: Usar cURL para enviar automáticamente**
    // Necesitas una API como Twilio o un servicio de WhatsApp Business
    /*
    $data = [
        'number' => $numero,
        'message' => $mensaje,
        'apikey' => 'TU_API_KEY'
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.twilio.com/...');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    */
    
    // **OPCIÓN 3: Mostrar QR/Link (recomendado para desarrollo)**
    // Guardar en archivo para que lo envíes manualmente
    $log_file = 'whatsapp_log.txt';
    $log_entry = date('Y-m-d H:i:s') . " | Para: " . $numero . " | Mensaje: " . $mensaje . "\n";
    file_put_contents($log_file, $log_entry, FILE_APPEND);
    
    return true; // Simulamos éxito
}
?>
