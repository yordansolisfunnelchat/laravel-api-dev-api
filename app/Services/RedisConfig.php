<?php
namespace App\Services;

class RedisConfig {
    // Tiempo de expiración en segundos para la cola y el lock
    const MESSAGE_EXPIRE = 45; // segundos
    const LOCK_EXPIRE    = 30; // segundos

    /**
     * Retorna la clave que se usará para almacenar la cola de mensajes.
     * Ejemplo: "message_queue:573113634658:26"
     */
    public static function getMessageQueueKey($phone, $conversationId) {
        // Si $phone viene vacío, usa un valor de respaldo (o falla)
        if(empty($phone)){
            throw new \Exception("El número de teléfono es vacío al generar la clave de la cola.");
        }
        return "message_queue:{$phone}:{$conversationId}";
    }

    /**
     * Retorna la clave para el lock de procesamiento.
     * Ejemplo: "processing:573113634658:26"
     */
    public static function getProcessingLockKey($phone, $conversationId) {
        if(empty($phone)){
            throw new \Exception("El número de teléfono es vacío al generar la clave de lock.");
        }
        return "processing:{$phone}:{$conversationId}";
    }

    /**
     * Prepara el mensaje en JSON.
     */
    public static function prepareMessage($content, $conversationId, $messageId) {
        return json_encode([
            'content'         => $content,
            'conversation_id' => $conversationId,
            'message_id'      => $messageId,
            'timestamp'       => now()->toIso8601String(),
        ]);
    }
}
