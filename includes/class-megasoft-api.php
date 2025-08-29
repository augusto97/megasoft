<?php
/**
 * MegaSoft API Class
 * Maneja todas las comunicaciones con la API de Mega Soft
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MegaSoft_API {
    
    private $config;
    private $logger;
    private $base_url;
    private $auth_header;
    
    public function __construct( $config, $logger ) {
        $this->config = $config;
        $this->logger = $logger;
        $this->base_url = $config['base_url'];
        $this->auth_header = 'Basic ' . base64_encode( $config['api_user'] . ':' . $config['api_password'] );
    }
    
    /**
     * Crear pre-registro de transacción
     */
    public function create_preregistration( $payment_data ) {
        $endpoint = 'paymentgatewayuniversal-prereg';
        
        // Preparar datos XML
        $xml_data = $this->build_preregistration_xml( $payment_data );
        
        $this->logger->debug( "Enviando pre-registro", array(
            'endpoint' => $endpoint,
            'order_id' => $payment_data['order_id'],
            'amount'   => $payment_data['amount']
        ) );
        
        $response = $this->make_request( $endpoint, $xml_data, 'POST' );
        
        if ( ! $response['success'] ) {
            $this->logger->error( "Error en pre-registro: " . $response['message'], array(
                'order_id' => $payment_data['order_id'],
                'response' => $response
            ) );
            return false;
        }
        
        $control_number = trim( $response['body'] );
        
        // Validar formato del número de control
        if ( ! $this->validate_control_number( $control_number ) ) {
            $this->logger->error( "Número de control inválido recibido: " . $control_number, array(
                'order_id' => $payment_data['order_id']
            ) );
            return false;
        }
        
        $this->logger->info( "Pre-registro exitoso: " . $control_number, array(
            'order_id' => $payment_data['order_id'],
            'control_number' => $control_number
        ) );
        
        return $control_number;
    }
    
    /**
     * Construir XML para pre-registro
     */
    private function build_preregistration_xml( $payment_data ) {
        $xml = '<request>';
        $xml .= '<cod_afiliacion>' . esc_html( $this->config['cod_afiliacion'] ) . '</cod_afiliacion>';
        $xml .= '<factura>' . esc_html( $payment_data['order_id'] ) . '</factura>';
        $xml .= '<monto>' . number_format( $payment_data['amount'], 2, '.', '' ) . '</monto>';
        
        if ( ! empty( $payment_data['client_name'] ) ) {
            $xml .= '<nombre>' . esc_html( $payment_data['client_name'] ) . '</nombre>';
        }
        
        if ( ! empty( $payment_data['document_type'] ) && ! empty( $payment_data['document_number'] ) ) {
            $xml .= '<tipo>' . esc_html( $payment_data['document_type'] ) . '</tipo>';
            $xml .= '<cedula_rif>' . esc_html( $payment_data['document_number'] ) . '</cedula_rif>';
        }
        
        if ( ! empty( $payment_data['installments'] ) && $payment_data['installments'] > 1 ) {
            $xml .= '<tipo_transaccion>1</tipo_transaccion>'; // Indica cuotas
            $xml .= '<plan>' . str_pad( $payment_data['installments'], 2, '0', STR_PAD_LEFT ) . '</plan>';
        }
        
        $xml .= '</request>';
        
        return $xml;
    }
    
    /**
     * Obtener URL de pago
     */
    public function get_payment_url( $control_number ) {
        return add_query_arg( 'control', $control_number, $this->base_url . 'paymentgatewayuniversal-data' );
    }
    
    /**
     * Consultar estado de transacción
     */
    public function query_transaction_status( $control_number, $transaction_type = 'CREDITO' ) {
        $endpoint = 'paymentgatewayuniversal-querystatus';
        $url = add_query_arg( array(
            'control' => $control_number,
            'version' => '3',
            'tipo'    => $transaction_type
        ), $this->base_url . $endpoint );
        
        $this->logger->debug( "Consultando estado de transacción", array(
            'control_number' => $control_number,
            'type' => $transaction_type
        ) );
        
        $response = $this->make_request( $url, null, 'GET' );
        
        if ( ! $response['success'] ) {
            $this->logger->error( "Error al consultar estado: " . $response['message'], array(
                'control_number' => $control_number
            ) );
            return false;
        }
        
        return $this->parse_transaction_response( $response['body'], $control_number );
    }
    
    /**
     * Parsear respuesta XML de transacción
     */
    private function parse_transaction_response( $xml_string, $control_number ) {
        libxml_use_internal_errors( true );
        $xml = simplexml_load_string( $xml_string );
        
        if ( $xml === false ) {
            $errors = libxml_get_errors();
            $this->logger->error( "Error parsing XML: " . print_r( $errors, true ), array(
                'control_number' => $control_number,
                'xml_response' => $xml_string
            ) );
            libxml_clear_errors();
            return false;
        }
        
        $codigo = (string) $xml->codigo;
        $approved = $codigo === '00';
        
        $result = array(
            'approved'       => $approved,
            'code'           => $codigo,
            'message'        => (string) $xml->descripcion,
            'auth_id'        => (string) $xml->authid,
            'reference'      => (string) $xml->referencia,
            'payment_method' => (string) $xml->medio,
            'amount'         => (string) $xml->monto,
            'currency'       => $this->parse_currency( $xml ),
            'voucher'        => $this->format_voucher( $xml ),
            'raw_data'       => json_decode( json_encode( $xml ), true ),
            'metadata'       => array(
                'voucher'        => $this->format_voucher( $xml ),
                'auth_id'        => (string) $xml->authid,
                'reference'      => (string) $xml->referencia,
                'payment_method' => (string) $xml->medio,
                'terminal'       => (string) $xml->terminal,
                'lote'           => (string) $xml->lote,
                'sequence'       => (string) $xml->seqnum
            )
        );
        
        $this->logger->info( 
            sprintf( "Respuesta de transacción: %s - %s", $codigo, $result['message'] ),
            array(
                'control_number' => $control_number,
                'approved' => $approved,
                'auth_id' => $result['auth_id']
            )
        );
        
        return $result;
    }
    
    /**
     * Parsear información de moneda
     */
    private function parse_currency( $xml ) {
        if ( isset( $xml->monedaInicio ) && isset( $xml->monedaFin ) ) {
            return array(
                'from' => (string) $xml->monedaInicio,
                'to'   => (string) $xml->monedaFin,
                'amount_divisa' => (string) $xml->monto_divisa ?? null
            );
        }
        
        return array(
            'from' => 'VES',
            'to'   => 'VES',
            'amount_divisa' => null
        );
    }
    
    /**
     * Formatear voucher desde XML
     */
    private function format_voucher( $xml ) {
        if ( ! isset( $xml->voucher ) || ! isset( $xml->voucher->linea ) ) {
            return '';
        }
        
        $voucher_lines = array();
        foreach ( $xml->voucher->linea as $line ) {
            $clean_line = str_replace( '_', ' ', (string) $line );
            $clean_line = html_entity_decode( $clean_line );
            $voucher_lines[] = $clean_line;
        }
        
        $voucher_text = implode( "\n", $voucher_lines );
        
        // Generar HTML del voucher
        $voucher_html = '<div class="megasoft-voucher-container">';
        $voucher_html .= '<pre class="megasoft-voucher">' . esc_html( $voucher_text ) . '</pre>';
        $voucher_html .= '</div>';
        
        return $voucher_html;
    }
    
    /**
     * Procesar reembolso
     */
    public function process_refund( $control_number, $amount, $reason = '' ) {
        $endpoint = 'paymentgateway-anulacion';
        
        $xml_data = '<request>';
        $xml_data .= '<cod_afiliacion>' . esc_html( $this->config['cod_afiliacion'] ) . '</cod_afiliacion>';
        $xml_data .= '<control>' . esc_html( $control_number ) . '</control>';
        $xml_data .= '<monto>' . number_format( $amount, 2, '.', '' ) . '</monto>';
        if ( $reason ) {
            $xml_data .= '<motivo>' . esc_html( $reason ) . '</motivo>';
        }
        $xml_data .= '</request>';
        
        $this->logger->info( "Procesando reembolso", array(
            'control_number' => $control_number,
            'amount' => $amount,
            'reason' => $reason
        ) );
        
        $response = $this->make_request( $endpoint, $xml_data, 'POST' );
        
        if ( ! $response['success'] ) {
            return array(
                'success' => false,
                'message' => $response['message']
            );
        }
        
        // Parsear respuesta de reembolso
        $xml = simplexml_load_string( $response['body'] );
        if ( $xml && isset( $xml->codigo ) ) {
            $success = (string) $xml->codigo === '00';
            $message = (string) $xml->descripcion;
            
            $this->logger->info( "Resultado reembolso: " . $message, array(
                'control_number' => $control_number,
                'success' => $success
            ) );
            
            return array(
                'success' => $success,
                'message' => $message,
                'refund_id' => (string) $xml->referencia ?? ''
            );
        }
        
        return array(
            'success' => false,
            'message' => __( 'Respuesta de reembolso inválida', 'woocommerce-megasoft-gateway-universal' )
        );
    }
    
    /**
     * Probar conexión con la API
     */
    public function test_connection() {
        $test_data = array(
            'order_id'    => 'TEST_' . time(),
            'amount'      => 0.01,
            'client_name' => 'Test Connection',
            'document_type' => 'V',
            'document_number' => '12345678'
        );
        
        $this->logger->info( "Probando conexión con API" );
        
        $control_number = $this->create_preregistration( $test_data );
        
        if ( $control_number ) {
            // Si el pre-registro funciona, probar query status
            $status_result = $this->query_transaction_status( $control_number );
            
            return array(
                'success' => true,
                'message' => __( 'Conexión exitosa', 'woocommerce-megasoft-gateway-universal' ),
                'control_number' => $control_number,
                'status_query' => $status_result !== false
            );
        } else {
            return array(
                'success' => false,
                'message' => __( 'Error de conexión con Mega Soft', 'woocommerce-megasoft-gateway-universal' )
            );
        }
    }
    
    /**
     * Realizar petición HTTP
     */
    private function make_request( $endpoint_or_url, $data = null, $method = 'POST' ) {
        $url = strpos( $endpoint_or_url, 'http' ) === 0 ? $endpoint_or_url : $this->base_url . $endpoint_or_url;
        
        $args = array(
            'method'    => $method,
            'timeout'   => 30,
            'sslverify' => ! $this->config['test_mode'], // Solo verificar SSL en producción
            'headers'   => array(
                'Authorization' => $this->auth_header,
                'User-Agent'    => 'MegaSoft-WooCommerce-Gateway/' . MEGASOFT_PLUGIN_VERSION
            )
        );
        
        if ( $method === 'POST' && $data ) {
            $args['headers']['Content-Type'] = 'text/xml';
            $args['body'] = $data;
        }
        
        $this->logger->debug( "Realizando petición HTTP", array(
            'url' => $url,
            'method' => $method,
            'has_data' => ! empty( $data )
        ) );
        
        $response = wp_remote_request( $url, $args );
        
        if ( is_wp_error( $response ) ) {
            $error_message = $response->get_error_message();
            $this->logger->error( "Error HTTP: " . $error_message, array( 'url' => $url ) );
            
            return array(
                'success' => false,
                'message' => $error_message,
                'code'    => null,
                'body'    => null
            );
        }
        
        $response_code = wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );
        
        $success = $response_code >= 200 && $response_code < 300;
        
        $this->logger->debug( "Respuesta HTTP recibida", array(
            'url' => $url,
            'code' => $response_code,
            'success' => $success,
            'body_length' => strlen( $response_body )
        ) );
        
        if ( ! $success ) {
            $this->logger->error( "HTTP Error {$response_code}", array(
                'url' => $url,
                'response_body' => $response_body
            ) );
        }
        
        return array(
            'success' => $success,
            'message' => $success ? 'OK' : "HTTP Error {$response_code}",
            'code'    => $response_code,
            'body'    => $response_body
        );
    }
    
    /**
     * Validar formato del número de control
     */
    private function validate_control_number( $control_number ) {
        // El número de control debe ser numérico y tener al menos 10 dígitos
        return is_numeric( $control_number ) && strlen( $control_number ) >= 10;
    }
    
    /**
     * Obtener información de medios de pago disponibles
     */
    public function get_available_payment_methods() {
        // Esta información puede venir de la configuración o de una consulta a la API
        return array(
            'credit_card' => array(
                'name' => __( 'Tarjeta de Crédito', 'woocommerce-megasoft-gateway-universal' ),
                'types' => array( 'visa', 'mastercard', 'amex' )
            ),
            'debit_card' => array(
                'name' => __( 'Tarjeta de Débito', 'woocommerce-megasoft-gateway-universal' ),
                'types' => array( 'visa_debit', 'mastercard_debit' )
            ),
            'mobile_payment' => array(
                'name' => __( 'Pago Móvil', 'woocommerce-megasoft-gateway-universal' ),
                'operators' => array( 'digitel', 'movistar', 'movilnet' )
            )
        );
    }
    
    /**
     * Validar configuración de la API
     */
    public function validate_config() {
        $errors = array();
        
        if ( empty( $this->config['cod_afiliacion'] ) ) {
            $errors[] = __( 'Código de afiliación requerido', 'woocommerce-megasoft-gateway-universal' );
        }
        
        if ( empty( $this->config['api_user'] ) ) {
            $errors[] = __( 'Usuario API requerido', 'woocommerce-megasoft-gateway-universal' );
        }
        
        if ( empty( $this->config['api_password'] ) ) {
            $errors[] = __( 'Contraseña API requerida', 'woocommerce-megasoft-gateway-universal' );
        }
        
        if ( ! filter_var( $this->base_url, FILTER_VALIDATE_URL ) ) {
            $errors[] = __( 'URL base inválida', 'woocommerce-megasoft-gateway-universal' );
        }
        
        return empty( $errors ) ? true : $errors;
    }
}