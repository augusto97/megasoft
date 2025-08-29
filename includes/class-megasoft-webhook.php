<?php
/**
 * MegaSoft Webhook Class
 * Maneja las notificaciones automáticas de Mega Soft
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MegaSoft_Webhook {
    
    private $logger;
    
    public function __construct() {
        $this->logger = new MegaSoft_Logger( true, 'info' );
        
        // Hook para manejar webhooks
        add_action( 'woocommerce_api_megasoft_webhook', array( $this, 'handle_webhook_request' ) );
        
        // Verificar webhooks pendientes cada 5 minutos
        add_action( 'init', array( $this, 'schedule_webhook_processor' ) );
        add_action( 'megasoft_process_pending_webhooks', array( $this, 'process_pending_webhooks' ) );
    }
    
    /**
     * Programar procesamiento de webhooks
     */
    public function schedule_webhook_processor() {
        if ( ! wp_next_scheduled( 'megasoft_process_pending_webhooks' ) ) {
            wp_schedule_event( time(), 'megasoft_5min', 'megasoft_process_pending_webhooks' );
        }
    }
    
    /**
     * Manejar petición de webhook entrante
     */
    public function handle_webhook_request() {
        $this->logger->info( "Webhook recibido", array(
            'method' => $_SERVER['REQUEST_METHOD'],
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'ip' => $this->get_client_ip()
        ) );
        
        // Verificar método HTTP
        if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
            $this->logger->warn( "Webhook con método incorrecto: " . $_SERVER['REQUEST_METHOD'] );
            $this->send_webhook_response( 405, 'Method not allowed' );
            return;
        }
        
        // Obtener datos del webhook
        $raw_data = file_get_contents( 'php://input' );
        
        if ( empty( $raw_data ) ) {
            $this->logger->warn( "Webhook sin datos" );
            $this->send_webhook_response( 400, 'No data received' );
            return;
        }
        
        // Procesar webhook
        $result = $this->process_webhook_data( $raw_data );
        
        if ( $result['success'] ) {
            $this->send_webhook_response( 200, 'OK' );
        } else {
            $this->send_webhook_response( 400, $result['message'] );
        }
    }
    
    /**
     * Procesar datos del webhook
     */
    private function process_webhook_data( $raw_data ) {
        try {
            // Intentar parsear como JSON primero
            $data = json_decode( $raw_data, true );
            
            if ( json_last_error() !== JSON_ERROR_NONE ) {
                // Si no es JSON, intentar como XML
                $data = $this->parse_xml_webhook( $raw_data );
            }
            
            if ( ! $data ) {
                throw new Exception( 'Formato de datos no válido' );
            }
            
            $this->logger->debug( "Datos del webhook parseados", array(
                'data_keys' => array_keys( $data ),
                'control_number' => $data['control'] ?? 'N/A'
            ) );
            
            // Validar datos requeridos
            $validation = $this->validate_webhook_data( $data );
            if ( ! $validation['valid'] ) {
                throw new Exception( $validation['message'] );
            }
            
            // Buscar la orden correspondiente
            $order = $this->find_order_by_control( $data['control'] );
            
            if ( ! $order ) {
                throw new Exception( 'Orden no encontrada para control: ' . $data['control'] );
            }
            
            // Procesar la actualización
            $this->update_order_from_webhook( $order, $data );
            
            return array( 'success' => true, 'message' => 'Procesado correctamente' );
            
        } catch ( Exception $e ) {
            $this->logger->error( "Error procesando webhook: " . $e->getMessage(), array(
                'raw_data' => substr( $raw_data, 0, 500 )
            ) );
            
            // Guardar webhook fallido para reintento posterior
            $this->save_failed_webhook( $raw_data, $e->getMessage() );
            
            return array( 'success' => false, 'message' => $e->getMessage() );
        }
    }
    
    /**
     * Parsear webhook XML
     */
    private function parse_xml_webhook( $xml_data ) {
        libxml_use_internal_errors( true );
        $xml = simplexml_load_string( $xml_data );
        
        if ( $xml === false ) {
            return false;
        }
        
        // Convertir XML a array
        return json_decode( json_encode( $xml ), true );
    }
    
    /**
     * Validar datos del webhook
     */
    private function validate_webhook_data( $data ) {
        $required_fields = array( 'control', 'codigo', 'descripcion' );
        
        foreach ( $required_fields as $field ) {
            if ( ! isset( $data[$field] ) ) {
                return array(
                    'valid' => false,
                    'message' => "Campo requerido faltante: {$field}"
                );
            }
        }
        
        // Validar formato del número de control
        if ( ! is_numeric( $data['control'] ) || strlen( $data['control'] ) < 10 ) {
            return array(
                'valid' => false,
                'message' => 'Formato de número de control inválido'
            );
        }
        
        return array( 'valid' => true, 'message' => 'OK' );
    }
    
    /**
     * Buscar orden por número de control
     */
    private function find_order_by_control( $control_number ) {
        // Buscar en meta_data de las órdenes
        $orders = wc_get_orders( array(
            'meta_key'   => '_megasoft_control_number',
            'meta_value' => $control_number,
            'limit'      => 1
        ) );
        
        if ( ! empty( $orders ) ) {
            return $orders[0];
        }
        
        // Buscar en base de datos personalizada
        global $wpdb;
        $table_name = $wpdb->prefix . 'megasoft_transactions';
        
        $transaction = $wpdb->get_row( $wpdb->prepare(
            "SELECT order_id FROM {$table_name} WHERE control_number = %s",
            $control_number
        ) );
        
        if ( $transaction ) {
            return wc_get_order( $transaction->order_id );
        }
        
        return false;
    }
    
    /**
     * Actualizar orden desde webhook
     */
    private function update_order_from_webhook( $order, $data ) {
        $order_id = $order->get_id();
        $control_number = $data['control'];
        $codigo = $data['codigo'];
        $approved = $codigo === '00';
        
        $this->logger->info( "Actualizando orden desde webhook", array(
            'order_id' => $order_id,
            'control_number' => $control_number,
            'codigo' => $codigo,
            'approved' => $approved
        ) );
        
        // Evitar procesar webhooks duplicados
        if ( $this->is_webhook_already_processed( $order, $control_number ) ) {
            $this->logger->info( "Webhook ya procesado anteriormente", array(
                'order_id' => $order_id,
                'control_number' => $control_number
            ) );
            return;
        }
        
        // Actualizar estado de la orden
        if ( $approved ) {
            if ( $order->get_status() === 'pending' ) {
                $auth_id = $data['authid'] ?? $control_number;
                $order->payment_complete( $auth_id );
                $order->add_order_note( 
                    sprintf( 
                        __( 'Pago confirmado via webhook. Auth ID: %s', 'woocommerce-megasoft-gateway-universal' ),
                        $auth_id
                    )
                );
            }
        } else {
            if ( in_array( $order->get_status(), array( 'pending', 'on-hold' ) ) ) {
                $order->update_status( 'failed', $data['descripcion'] );
            }
        }
        
        // Guardar datos adicionales
        $metadata_fields = array(
            'authid', 'referencia', 'medio', 'monto', 'terminal', 'lote', 'seqnum'
        );
        
        foreach ( $metadata_fields as $field ) {
            if ( isset( $data[$field] ) ) {
                $order->update_meta_data( "_megasoft_webhook_{$field}", $data[$field] );
            }
        }
        
        // Marcar webhook como procesado
        $order->update_meta_data( '_megasoft_webhook_processed', array(
            'control_number' => $control_number,
            'processed_at' => current_time( 'mysql' ),
            'webhook_data' => $data
        ) );
        
        $order->save();
        
        // Actualizar base de datos de transacciones
        $this->update_transaction_from_webhook( $order_id, $data );
        
        // Hook para permitir acciones adicionales
        do_action( 'megasoft_webhook_processed', $order, $data );
    }
    
    /**
     * Verificar si webhook ya fue procesado
     */
    private function is_webhook_already_processed( $order, $control_number ) {
        $processed_data = $order->get_meta( '_megasoft_webhook_processed' );
        
        return ! empty( $processed_data ) && 
               isset( $processed_data['control_number'] ) && 
               $processed_data['control_number'] === $control_number;
    }
    
    /**
     * Actualizar transacción desde webhook
     */
    private function update_transaction_from_webhook( $order_id, $data ) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'megasoft_transactions';
        $approved = $data['codigo'] === '00';
        
        $wpdb->update(
            $table_name,
            array(
                'status'         => $approved ? 'approved' : 'failed',
                'auth_id'        => $data['authid'] ?? '',
                'reference'      => $data['referencia'] ?? '',
                'payment_method' => $data['medio'] ?? '',
                'response_data'  => json_encode( $data ),
                'updated_at'     => current_time( 'mysql' )
            ),
            array( 'order_id' => $order_id ),
            array( '%s', '%s', '%s', '%s', '%s', '%s' ),
            array( '%d' )
        );
    }
    
    /**
     * Guardar webhook fallido para reintento
     */
    private function save_failed_webhook( $raw_data, $error_message ) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'megasoft_failed_webhooks';
        
        // Crear tabla si no existe
        $this->create_failed_webhooks_table();
        
        $wpdb->insert(
            $table_name,
            array(
                'raw_data'       => $raw_data,
                'error_message'  => $error_message,
                'retry_count'    => 0,
                'next_retry'     => date( 'Y-m-d H:i:s', time() + 300 ), // Reintentar en 5 minutos
                'created_at'     => current_time( 'mysql' )
            ),
            array( '%s', '%s', '%d', '%s', '%s' )
        );
    }
    
    /**
     * Crear tabla para webhooks fallidos
     */
    private function create_failed_webhooks_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'megasoft_failed_webhooks';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            raw_data longtext NOT NULL,
            error_message text NOT NULL,
            retry_count int(11) DEFAULT 0,
            next_retry datetime DEFAULT NULL,
            processed tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY next_retry (next_retry),
            KEY processed (processed)
        ) $charset_collate;";
        
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }
    
    /**
     * Procesar webhooks pendientes de reintento
     */
    public function process_pending_webhooks() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'megasoft_failed_webhooks';
        
        // Buscar webhooks para reintentar
        $pending_webhooks = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table_name} 
             WHERE processed = 0 
             AND retry_count < 5 
             AND next_retry <= %s 
             ORDER BY created_at ASC 
             LIMIT 10",
            current_time( 'mysql' )
        ) );
        
        if ( empty( $pending_webhooks ) ) {
            return;
        }
        
        $this->logger->info( "Procesando " . count( $pending_webhooks ) . " webhooks pendientes" );
        
        foreach ( $pending_webhooks as $webhook ) {
            $result = $this->process_webhook_data( $webhook->raw_data );
            
            if ( $result['success'] ) {
                // Marcar como procesado
                $wpdb->update(
                    $table_name,
                    array( 'processed' => 1, 'updated_at' => current_time( 'mysql' ) ),
                    array( 'id' => $webhook->id ),
                    array( '%d', '%s' ),
                    array( '%d' )
                );
                
                $this->logger->info( "Webhook reintentado exitosamente", array( 'webhook_id' => $webhook->id ) );
            } else {
                // Incrementar contador de reintentos
                $retry_count = $webhook->retry_count + 1;
                $next_retry = date( 'Y-m-d H:i:s', time() + ( $retry_count * 300 ) ); // Backoff exponencial
                
                $wpdb->update(
                    $table_name,
                    array( 
                        'retry_count' => $retry_count,
                        'next_retry' => $next_retry,
                        'error_message' => $result['message'],
                        'updated_at' => current_time( 'mysql' )
                    ),
                    array( 'id' => $webhook->id ),
                    array( '%d', '%s', '%s', '%s' ),
                    array( '%d' )
                );
                
                $this->logger->warn( "Webhook reintentado sin éxito", array(
                    'webhook_id' => $webhook->id,
                    'retry_count' => $retry_count,
                    'error' => $result['message']
                ) );
            }
        }
    }
    
    /**
     * Enviar respuesta del webhook
     */
    private function send_webhook_response( $status_code, $message ) {
        status_header( $status_code );
        header( 'Content-Type: application/json' );
        
        echo json_encode( array(
            'status' => $status_code,
            'message' => $message,
            'timestamp' => current_time( 'mysql' )
        ) );
        
        exit;
    }
    
    /**
     * Obtener IP del cliente
     */
    private function get_client_ip() {
        $ip_fields = array(
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP', 
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR'
        );
        
        foreach ( $ip_fields as $field ) {
            if ( ! empty( $_SERVER[$field] ) ) {
                $ip = $_SERVER[$field];
                // Tomar la primera IP si hay múltiples
                if ( strpos( $ip, ',' ) !== false ) {
                    $ip = explode( ',', $ip )[0];
                }
                return trim( $ip );
            }
        }
        
        return 'unknown';
    }
    
    /**
     * Limpiar webhooks antiguos
     */
    public function cleanup_old_webhooks( $days = 30 ) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'megasoft_failed_webhooks';
        
        $deleted = $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$table_name} 
             WHERE (processed = 1 OR retry_count >= 5) 
             AND created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ) );
        
        $this->logger->info( "Limpieza de webhooks: {$deleted} registros eliminados." );
        
        return $deleted;
    }
}

// Agregar intervalo personalizado para cron
add_filter( 'cron_schedules', function( $schedules ) {
    $schedules['megasoft_5min'] = array(
        'interval' => 300,
        'display'  => __( 'Cada 5 minutos' )
    );
    return $schedules;
} );