<?php
/**
 * Plugin Name:         Pasarela de Pago Mega Soft para WooCommerce (Modalidad Universal)
 * Plugin URI:          https://github.com/
 * Description:         Integra la pasarela de pago venezolana de Mega Soft con WooCommerce usando la Modalidad Universal.
 * Author:              Tu Nombre
 * Author URI:          https://tu-sitio-web.com
 * Version:             2.3.0
 * Requires at least:   5.8
 * Requires PHP:        7.4
 * WC requires at least: 6.0
 * WC tested up to:     8.5
 * Text Domain:         woocommerce-megasoft-gateway-universal
 * Domain Path:         /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Salir si se accede directamente.
}

/**
 * Comprobar si WooCommerce está activo.
 */
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
    return;
}

/**
 * Registra la clase de la pasarela de pago en WooCommerce.
 */
function add_megasoft_universal_gateway_class( $gateways ) {
    $gateways[] = 'WC_Gateway_MegaSoft_Universal';
    return $gateways;
}
add_filter( 'woocommerce_payment_gateways', 'add_megasoft_universal_gateway_class' );

/**
 * Inicializa la clase de la pasarela de pago.
 */
function init_megasoft_universal_gateway_class() {

    class WC_Gateway_MegaSoft_Universal extends WC_Payment_Gateway {

        public $debug_logger;

        /**
         * Constructor de la pasarela de pago.
         */
        public function __construct() {
            $this->id                 = 'megasoft_gateway_universal';
            $this->icon               = '';
            $this->has_fields         = false;
            $this->method_title       = __( 'Pasarela de Pago Mega Soft (Universal)', 'woocommerce-megasoft-gateway-universal' );
            $this->method_description = __( 'Acepta pagos a través de la plataforma de Mega Soft mediante redirección.', 'woocommerce-megasoft-gateway-universal' );

            $this->init_form_fields();
            $this->init_settings();

            $this->title        = $this->get_option( 'title' );
            $this->description  = $this->get_option( 'description' );
            $this->enabled      = $this->get_option( 'enabled' );
            $this->testmode     = 'yes' === $this->get_option( 'testmode' );
            $this->debug        = 'yes' === $this->get_option( 'debug' );

            $this->api_base_url = $this->testmode ? 'https://paytest.megasoft.com.ve/action/' : 'https://URL_DE_PRODUCCION/action/';

            if ( $this->debug ) {
                $this->debug_logger = wc_get_logger();
            }

            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
            add_action( 'woocommerce_api_wc_gateway_megasoft_universal', array( $this, 'handle_return' ) );
            // Hook para mostrar el voucher en la página de agradecimiento.
            add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'display_voucher_details' ) );
        }

        /**
         * Define los campos de configuración para el área de administración.
         */
        public function init_form_fields() {
            $this->form_fields = array(
                'enabled' => array(
                    'title'   => __( 'Activar/Desactivar', 'woocommerce-megasoft-gateway-universal' ),
                    'type'    => 'checkbox',
                    'label'   => __( 'Activar Pasarela Mega Soft (Universal)', 'woocommerce-megasoft-gateway-universal' ),
                    'default' => 'no'
                ),
                'testmode' => array(
                    'title'   => __( 'Modo de Prueba', 'woocommerce-megasoft-gateway-universal' ),
                    'type'    => 'checkbox',
                    'label'   => __( 'Activar Modo de Prueba', 'woocommerce-megasoft-gateway-universal' ),
                    'default' => 'yes',
                    'description' => __( 'Usa el entorno de pruebas de paytest.megasoft.com.ve. Desactívalo para ir a producción.', 'woocommerce-megasoft-gateway-universal' ),
                ),
                 'debug' => array(
                    'title'   => __( 'Modo de Depuración', 'woocommerce-megasoft-gateway-universal' ),
                    'type'    => 'checkbox',
                    'label'   => __( 'Activar logs de depuración', 'woocommerce-megasoft-gateway-universal' ),
                    'default' => 'no',
                    'description' => __( 'Si está activado, se guardarán logs detallados en WooCommerce > Estado > Registros.', 'woocommerce-megasoft-gateway-universal' ),
                ),
                'title' => array(
                    'title'       => __( 'Título', 'woocommerce-megasoft-gateway-universal' ),
                    'type'        => 'text',
                    'default'     => __( 'Pagar con Tarjeta o Pago Móvil', 'woocommerce-megasoft-gateway-universal' ),
                    'desc_tip'    => true,
                ),
                'description' => array(
                    'title'       => __( 'Descripción', 'woocommerce-megasoft-gateway-universal' ),
                    'type'        => 'textarea',
                    'default'     => __( 'Serás redirigido a una página segura para completar tu pago.', 'woocommerce-megasoft-gateway-universal' ),
                ),
                'api_details' => array(
                    'title'       => __( 'Credenciales de la API (Modalidad Universal)', 'woocommerce-megasoft-gateway-universal' ),
                    'type'        => 'title',
                ),
                'cod_afiliacion' => array(
                    'title'       => __( 'Código de Afiliación', 'woocommerce-megasoft-gateway-universal' ),
                    'type'        => 'text',
                    'default'     => '20250508',
                ),
                'api_user' => array(
                    'title'       => __( 'Usuario API', 'woocommerce-megasoft-gateway-universal' ),
                    'type'        => 'text',
                    'default'     => 'multimuniv',
                ),
                'api_password' => array(
                    'title'       => __( 'Contraseña API', 'woocommerce-megasoft-gateway-universal' ),
                    'type'        => 'password',
                    'default'     => 'Caracas123.1',
                ),
                'return_url_info' => array(
                    'title' => __( 'URL de Retorno (¡Importante!)', 'woocommerce-megasoft-gateway-universal' ),
                    'type'  => 'title',
                    'description' => __( 'Debes proporcionar la siguiente URL a tu contacto en Mega Soft. Esta es la dirección a la que los clientes regresarán después de pagar. Pide que usen este formato exacto.', 'woocommerce-megasoft-gateway-universal' ) .
                        '<br><br><strong>' . __('Formato Requerido:', 'woocommerce-megasoft-gateway-universal') . '</strong><br><code>' .
                        WC()->api_request_url( 'WC_Gateway_MegaSoft_Universal' ) . '?control=@control@&factura=@facturatrx@' . '</code>',
                ),
            );
        }

        /**
         * Procesa el pago.
         */
        public function process_payment( $order_id ) {
            $order = wc_get_order( $order_id );
            
            $cod_afiliacion = $this->get_option('cod_afiliacion');
            $api_user       = $this->get_option('api_user');
            $api_password   = $this->get_option('api_password');

            $prereg_url = add_query_arg( array(
                'cod_afiliacion' => $cod_afiliacion,
                'factura'        => $order->get_id(),
                'monto'          => $order->get_total(),
            ), $this->api_base_url . 'paymentgatewayuniversal-prereg' );

            if ( $this->debug ) $this->debug_logger->debug( "Intento de Pre-Registro para Pedido #{$order_id}. URL: {$prereg_url}", array( 'source' => $this->id ) );

            $auth_credentials = base64_encode( $api_user . ':' . $api_password );
            $headers = array('Authorization' => 'Basic ' . $auth_credentials);

            $response = wp_remote_get( $prereg_url, array(
                'headers'   => $headers, 'timeout'   => 30, 'sslverify' => false,
            ) );

            if ( is_wp_error( $response ) ) {
                $error_message = $response->get_error_message();
                wc_add_notice( __( 'Error de conexión con la pasarela de pago.', 'woocommerce-megasoft-gateway-universal' ), 'error' );
                $order->add_order_note( 'Error de conexión en Pre-Registro: ' . $error_message );
                if ( $this->debug ) $this->debug_logger->error( "Error de WP_Error en Pre-Registro para Pedido #{$order_id}: {$error_message}", array( 'source' => $this->id ) );
                return;
            }

            $control_number = wp_remote_retrieve_body( $response );

            if ( ! is_numeric( $control_number ) || strlen($control_number) < 5 ) {
                 $error_details = 'Respuesta recibida: ' . esc_html($control_number);
                 wc_add_notice( __( 'La pasarela de pago devolvió una respuesta inesperada.', 'woocommerce-megasoft-gateway-universal' ), 'error' );
                 $order->add_order_note( 'Error en pre-registro. ' . $error_details );
                 if ( $this->debug ) $this->debug_logger->error( "Error de respuesta en Pre-Registro para Pedido #{$order_id}. {$error_details}", array( 'source' => $this->id ) );
                 return;
            }
            
            $order->update_meta_data( '_megasoft_control_number', $control_number );
            $order->add_order_note( 'Número de control de Mega Soft generado: ' . $control_number );
            $order->save();

            $redirect_url = add_query_arg( 'control', $control_number, $this->api_base_url . 'paymentgatewayuniversal-data' );

            if ( $this->debug ) $this->debug_logger->debug( "Pre-Registro exitoso para Pedido #{$order_id}. Redirigiendo a: {$redirect_url}", array( 'source' => $this->id ) );

            return array( 'result'   => 'success', 'redirect' => $redirect_url );
        }

        /**
         * Maneja el retorno del cliente desde Mega Soft.
         */
        public function handle_return() {
            $control_number = isset($_GET['control']) ? sanitize_text_field($_GET['control']) : '';
            $order_id       = isset($_GET['factura']) ? absint($_GET['factura']) : 0;

            if ( $this->debug ) $this->debug_logger->debug( "Retorno recibido. Control: {$control_number}, Factura (Order ID): {$order_id}", array( 'source' => $this->id ) );

            if ( ! $control_number || ! $order_id ) {
                wc_add_notice( __( 'Faltan parámetros en la URL de retorno.', 'woocommerce-megasoft-gateway-universal' ), 'error' );
                wp_redirect( wc_get_checkout_url() );
                exit;
            }

            $order = wc_get_order( $order_id );

            if ( ! $order ) {
                if ( $this->debug ) $this->debug_logger->error( "Error en retorno: Pedido no encontrado para ID #{$order_id}", array( 'source' => $this->id ) );
                wc_add_notice( __( 'Error de validación del pedido.', 'woocommerce-megasoft-gateway-universal' ), 'error' );
                wp_redirect( wc_get_checkout_url() );
                exit;
            }
            
            $saved_control = $order->get_meta('_megasoft_control_number');
            if ( $saved_control !== $control_number ) {
                 $order->add_order_note('ALERTA DE SEGURIDAD: El número de control de retorno no coincide con el guardado.');
                 if ( $this->debug ) $this->debug_logger->error( "Discrepancia de número de control para Pedido #{$order_id}. Guardado: {$saved_control}, Recibido: {$control_number}", array( 'source' => $this->id ) );
                 wp_redirect( wc_get_checkout_url() );
                 exit;
            }
            
            $querystatus_url = add_query_arg( 'control', $control_number, $this->api_base_url . 'paymentgatewayuniversal-querystatus' );
            
            $response = wp_remote_get( $querystatus_url, array( 'timeout' => 30, 'sslverify' => false ) );

            if ( is_wp_error( $response ) ) {
                $error_message = $response->get_error_message();
                wc_add_notice( __( 'No se pudo verificar el estado de la transacción.', 'woocommerce-megasoft-gateway-universal' ), 'error' );
                wp_redirect( $order->get_checkout_payment_url() );
                exit;
            }

            $xml_string = wp_remote_retrieve_body( $response );
            if ( $this->debug ) $this->debug_logger->debug( "Respuesta de QueryStatus para Pedido #{$order_id}: " . $xml_string, array( 'source' => $this->id ) );

            libxml_use_internal_errors(true);
            $xml = simplexml_load_string( $xml_string );
            libxml_clear_errors();

            if ( $xml === false || !isset($xml->codigo) ) {
                wc_add_notice( __( 'Respuesta de verificación inválida.', 'woocommerce-megasoft-gateway-universal' ), 'error' );
                wp_redirect( $order->get_checkout_payment_url() );
                exit;
            }

            // Formatear y guardar el voucher en el pedido.
            $voucher_html = $this->format_voucher_from_xml($xml);
            $order->add_order_note( $voucher_html, true ); // true para que sea visible al cliente.
            $order->update_meta_data('_megasoft_voucher', $voucher_html);
            $order->save();

            if ( (string) $xml->codigo === '00' ) {
                $order->payment_complete( (string) $xml->authid );
                wp_redirect( $this->get_return_url( $order ) );
                exit;
            } else {
                $error_message = isset($xml->descripcion) ? (string) $xml->descripcion : __( 'Transacción rechazada.', 'woocommerce-megasoft-gateway-universal' );
                $full_error = '<strong>' . $error_message . '</strong><br/><br/>' . $voucher_html;
                wc_add_notice( $full_error, 'error' );
                $order->update_status( 'failed', __( 'Pago con Mega Soft fallido.', 'woocommerce-megasoft-gateway-universal' ) );
                wp_redirect( $order->get_checkout_payment_url() );
                exit;
            }
        }

        /**
         * Muestra los detalles del voucher en la página de agradecimiento.
         */
        public function display_voucher_details( $order_id ) {
            $order = wc_get_order( $order_id );
            $voucher_html = $order->get_meta('_megasoft_voucher');
            if ( ! empty( $voucher_html ) ) {
                echo '<h2>' . __( 'Detalles de la Transacción', 'woocommerce-megasoft-gateway-universal' ) . '</h2>';
                echo $voucher_html;
            }
        }

        /**
         * Formatea el voucher desde el objeto XML a HTML.
         */
        private function format_voucher_from_xml( $xml ) {
            if ( !isset($xml->voucher) || !isset($xml->voucher->linea) ) {
                return '<p>' . __( 'No se encontraron detalles del voucher en la respuesta.', 'woocommerce-megasoft-gateway-universal' ) . '</p>';
            }

            $voucher_text = '';
            foreach ($xml->voucher->linea as $line) {
                // Reemplazar guiones bajos por espacios y limpiar etiquetas HTML.
                $clean_line = str_replace('_', ' ', (string)$line);
                $voucher_text .= esc_html($clean_line) . "\n";
            }
            
            // Usamos <pre> para mantener el formato de texto preformateado del recibo.
            return '<pre style="background-color: #f7f7f7; border: 1px solid #ccc; padding: 15px; border-radius: 5px; white-space: pre-wrap; word-wrap: break-word; font-family: monospace;">' . trim($voucher_text) . '</pre>';
        }
    }
}
add_action( 'plugins_loaded', 'init_megasoft_universal_gateway_class' );