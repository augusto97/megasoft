<?php
/**
 * Plugin Name:         Pasarela de Pago Mega Soft para WooCommerce (Modalidad Universal) - PRODUCCIÓN
 * Plugin URI:          https://github.com/
 * Description:         Pasarela de pago venezolana Mega Soft completamente funcional para producción con dashboard, webhooks, validaciones avanzadas y soporte completo.
 * Author:              Tu Nombre
 * Author URI:          https://tu-sitio-web.com
 * Version:             3.0.0
 * Requires at least:   5.8
 * Requires PHP:        7.4
 * WC requires at least: 6.0
 * WC tested up to:     8.5
 * Text Domain:         woocommerce-megasoft-gateway-universal
 * Domain Path:         /languages
 * Network:             false
 * License:             GPL v2 or later
 * License URI:         https://www.gnu.org/licenses/gpl-2.0.html
 */

// Prevenir acceso directo
if ( ! defined( 'ABSPATH' ) ) {
    exit; 
}

// Definir constantes del plugin
define( 'MEGASOFT_PLUGIN_VERSION', '3.0.0' );
define( 'MEGASOFT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'MEGASOFT_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'MEGASOFT_PLUGIN_FILE', __FILE__ );

/**
 * Verificar si WooCommerce está activo
 */
function megasoft_check_woocommerce() {
    if ( ! class_exists( 'WooCommerce' ) ) {
        return false;
    }
    return true;
}

/**
 * Mostrar notice si WooCommerce no está activo
 */
function megasoft_woocommerce_missing_notice() {
    ?>
    <div class="error">
        <p>
            <strong><?php _e( 'Mega Soft Gateway', 'woocommerce-megasoft-gateway-universal' ); ?></strong> 
            <?php _e( 'requiere que WooCommerce esté instalado y activo.', 'woocommerce-megasoft-gateway-universal' ); ?>
            <a href="<?php echo admin_url( 'plugin-install.php?s=woocommerce&tab=search&type=term' ); ?>">
                <?php _e( 'Instalar WooCommerce', 'woocommerce-megasoft-gateway-universal' ); ?>
            </a>
        </p>
    </div>
    <?php
}

/**
 * Verificar dependencias antes de cargar el plugin
 */
function megasoft_init_plugin() {
    // Verificar si WooCommerce existe
    if ( ! megasoft_check_woocommerce() ) {
        add_action( 'admin_notices', 'megasoft_woocommerce_missing_notice' );
        return;
    }
    
    // Cargar el plugin principal
    megasoft_load_plugin();
}

/**
 * Cargar el plugin principal
 */
function megasoft_load_plugin() {
    
    // Cargar clases base primero
    if ( ! class_exists( 'MegaSoft_Logger' ) ) {
        require_once MEGASOFT_PLUGIN_PATH . 'includes/class-megasoft-logger.php';
    }
    
    if ( ! class_exists( 'MegaSoft_API' ) ) {
        require_once MEGASOFT_PLUGIN_PATH . 'includes/class-megasoft-api.php';
    }
    
    if ( ! class_exists( 'MegaSoft_Webhook' ) ) {
        require_once MEGASOFT_PLUGIN_PATH . 'includes/class-megasoft-webhook.php';
    }
    
    // Cargar admin solo en backend
    if ( is_admin() && ! class_exists( 'MegaSoft_Admin' ) ) {
        require_once MEGASOFT_PLUGIN_PATH . 'includes/class-megasoft-admin.php';
    }
    
    // Inicializar webhook
    new MegaSoft_Webhook();
    
    // Inicializar admin si estamos en el backend
    if ( is_admin() ) {
        new MegaSoft_Admin();
    }
    
    // Agregar gateway a WooCommerce
    add_filter( 'woocommerce_payment_gateways', 'megasoft_add_gateway_class' );
}

/**
 * Agregar la clase del gateway a WooCommerce
 */
function megasoft_add_gateway_class( $gateways ) {
    $gateways[] = 'WC_Gateway_MegaSoft_Universal';
    return $gateways;
}

/**
 * Hook de inicialización - ESPERAMOS A QUE WOOCOMMERCE ESTÉ CARGADO
 */
add_action( 'plugins_loaded', 'megasoft_init_plugin', 11 );

// Hooks de activación y desactivación
register_activation_hook( MEGASOFT_PLUGIN_FILE, 'megasoft_plugin_activate' );
register_deactivation_hook( MEGASOFT_PLUGIN_FILE, 'megasoft_plugin_deactivate' );

function megasoft_plugin_activate() {
    // Verificar WooCommerce durante activación
    if ( ! megasoft_check_woocommerce() ) {
        deactivate_plugins( plugin_basename( MEGASOFT_PLUGIN_FILE ) );
        wp_die( 
            __( 'MegaSoft Gateway requiere WooCommerce. Por favor, instala y activa WooCommerce primero.', 'woocommerce-megasoft-gateway-universal' ),
            'Plugin dependency check',
            array( 'back_link' => true )
        );
    }
    
    // Crear tablas de base de datos
    megasoft_create_tables();
    
    // Agregar capacidades
    $role = get_role( 'administrator' );
    if ( $role ) {
        $role->add_cap( 'manage_megasoft_transactions' );
    }
    
    // Programar tareas cron
    if ( ! wp_next_scheduled( 'megasoft_sync_transactions' ) ) {
        wp_schedule_event( time(), 'hourly', 'megasoft_sync_transactions' );
    }
    
    flush_rewrite_rules();
}

function megasoft_plugin_deactivate() {
    wp_clear_scheduled_hook( 'megasoft_sync_transactions' );
    flush_rewrite_rules();
}

/**
 * Crear tablas de base de datos
 */
function megasoft_create_tables() {
    global $wpdb;
    
    $charset_collate = $wpdb->get_charset_collate();
    
    // Tabla de transacciones
    $table_name = $wpdb->prefix . 'megasoft_transactions';
    
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        order_id bigint(20) UNSIGNED NOT NULL,
        control_number varchar(50) DEFAULT NULL,
        status varchar(20) DEFAULT 'pending',
        amount decimal(10,2) NOT NULL,
        currency varchar(3) DEFAULT 'VES',
        auth_id varchar(50) DEFAULT NULL,
        reference varchar(100) DEFAULT NULL,
        payment_method varchar(50) DEFAULT NULL,
        document_type varchar(2) DEFAULT NULL,
        document_number varchar(20) DEFAULT NULL,
        client_name varchar(100) DEFAULT NULL,
        response_data longtext DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        UNIQUE KEY control_number (control_number),
        KEY order_id (order_id),
        KEY status (status),
        KEY created_at (created_at)
    ) $charset_collate;";
    
    // Tabla de logs
    $table_logs = $wpdb->prefix . 'megasoft_logs';
    
    $sql_logs = "CREATE TABLE IF NOT EXISTS $table_logs (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        order_id bigint(20) UNSIGNED DEFAULT NULL,
        control_number varchar(50) DEFAULT NULL,
        level varchar(10) NOT NULL,
        message text NOT NULL,
        context longtext DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY order_id (order_id),
        KEY level (level),
        KEY created_at (created_at)
    ) $charset_collate;";
    
    // Tabla para webhooks fallidos
    $table_webhooks = $wpdb->prefix . 'megasoft_failed_webhooks';
    
    $sql_webhooks = "CREATE TABLE IF NOT EXISTS $table_webhooks (
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
    dbDelta( $sql_logs );
    dbDelta( $sql_webhooks );
}

// SOLO DEFINIR LA CLASE CUANDO WOOCOMMERCE ESTÉ DISPONIBLE
add_action( 'woocommerce_loaded', 'megasoft_define_gateway_class' );

function megasoft_define_gateway_class() {
    
    if ( class_exists( 'WC_Payment_Gateway' ) && ! class_exists( 'WC_Gateway_MegaSoft_Universal' ) ) {
        
        class WC_Gateway_MegaSoft_Universal extends WC_Payment_Gateway {
            
            private $logger;
            private $api;
            private $test_mode;
            
            public function __construct() {
                $this->id                 = 'megasoft_gateway_universal';
                $this->icon               = MEGASOFT_PLUGIN_URL . 'assets/images/megasoft-icon.png';
                $this->has_fields         = true;
                $this->method_title       = __( 'Mega Soft (Universal)', 'woocommerce-megasoft-gateway-universal' );
                $this->method_description = __( 'Pasarela de pago venezolana con soporte completo para TDC Nacional/Internacional y Pago Móvil.', 'woocommerce-megasoft-gateway-universal' );
                
                $this->supports = array(
                    'products',
                    'refunds'
                );
                
                $this->init_form_fields();
                $this->init_settings();
                
                // Propiedades
                $this->title             = $this->get_option( 'title' );
                $this->description       = $this->get_option( 'description' );
                $this->enabled           = $this->get_option( 'enabled' );
                $this->test_mode         = 'yes' === $this->get_option( 'testmode' );
                $this->debug             = 'yes' === $this->get_option( 'debug' );
                $this->require_document  = 'yes' === $this->get_option( 'require_document' );
                $this->enable_installments = 'yes' === $this->get_option( 'enable_installments' );
                
                // Inicializar servicios
                $this->logger = new MegaSoft_Logger( $this->debug );
                $this->api    = new MegaSoft_API( $this->get_api_config(), $this->logger );
                
                // Hooks
                add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
                add_action( 'woocommerce_api_wc_gateway_megasoft_universal', array( $this, 'handle_return' ) );
                add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'display_receipt' ) );
                add_action( 'woocommerce_checkout_process', array( $this, 'validate_checkout_fields' ) );
                
                // Scripts y estilos
                add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
            }
            
            private function get_api_config() {
                return array(
                    'base_url'       => $this->test_mode ? 'https://paytest.megasoft.com.ve/action/' : 'https://pay.megasoft.com.ve/action/',
                    'cod_afiliacion' => $this->get_option( 'cod_afiliacion' ),
                    'api_user'       => $this->get_option( 'api_user' ),
                    'api_password'   => $this->get_option( 'api_password' ),
                    'test_mode'      => $this->test_mode
                );
            }
            
            public function init_form_fields() {
                $this->form_fields = array(
                    'enabled' => array(
                        'title'   => __( 'Activar/Desactivar', 'woocommerce-megasoft-gateway-universal' ),
                        'type'    => 'checkbox',
                        'label'   => __( 'Activar Mega Soft Gateway', 'woocommerce-megasoft-gateway-universal' ),
                        'default' => 'no'
                    ),
                    'title' => array(
                        'title'       => __( 'Título', 'woocommerce-megasoft-gateway-universal' ),
                        'type'        => 'text',
                        'description' => __( 'Título que verán los usuarios en el checkout.', 'woocommerce-megasoft-gateway-universal' ),
                        'default'     => __( 'Tarjeta de Crédito/Débito y Pago Móvil', 'woocommerce-megasoft-gateway-universal' ),
                        'desc_tip'    => true,
                    ),
                    'description' => array(
                        'title'       => __( 'Descripción', 'woocommerce-megasoft-gateway-universal' ),
                        'type'        => 'textarea',
                        'description' => __( 'Descripción que verán los usuarios en el checkout.', 'woocommerce-megasoft-gateway-universal' ),
                        'default'     => __( 'Paga de forma segura con tu tarjeta de crédito, débito o pago móvil. Serás redirigido a una página segura para completar tu pago.', 'woocommerce-megasoft-gateway-universal' ),
                        'desc_tip'    => true,
                    ),
                    
                    // Configuración de API
                    'api_settings' => array(
                        'title'       => __( 'Configuración de API', 'woocommerce-megasoft-gateway-universal' ),
                        'type'        => 'title',
                        'description' => __( 'Configura las credenciales proporcionadas por Mega Soft.', 'woocommerce-megasoft-gateway-universal' ),
                    ),
                    'testmode' => array(
                        'title'       => __( 'Modo de Prueba', 'woocommerce-megasoft-gateway-universal' ),
                        'type'        => 'checkbox',
                        'label'       => __( 'Activar modo de prueba', 'woocommerce-megasoft-gateway-universal' ),
                        'default'     => 'yes',
                        'description' => __( 'Utiliza el ambiente de pruebas de Mega Soft. Desactiva para producción.', 'woocommerce-megasoft-gateway-universal' ),
                    ),
                    'cod_afiliacion' => array(
                        'title'       => __( 'Código de Afiliación', 'woocommerce-megasoft-gateway-universal' ),
                        'type'        => 'text',
                        'description' => __( 'Código de afiliación proporcionado por Mega Soft.', 'woocommerce-megasoft-gateway-universal' ),
                        'default'     => '20250508',
                        'desc_tip'    => true,
                    ),
                    'api_user' => array(
                        'title'       => __( 'Usuario API', 'woocommerce-megasoft-gateway-universal' ),
                        'type'        => 'text',
                        'description' => __( 'Usuario para la autenticación con la API.', 'woocommerce-megasoft-gateway-universal' ),
                        'default'     => 'multimuniv',
                        'desc_tip'    => true,
                    ),
                    'api_password' => array(
                        'title'       => __( 'Contraseña API', 'woocommerce-megasoft-gateway-universal' ),
                        'type'        => 'password',
                        'description' => __( 'Contraseña para la autenticación con la API.', 'woocommerce-megasoft-gateway-universal' ),
                        'default'     => 'Caracas123.1',
                        'desc_tip'    => true,
                    ),
                    
                    // Configuración de documentos
                    'document_settings' => array(
                        'title'       => __( 'Configuración de Documentos', 'woocommerce-megasoft-gateway-universal' ),
                        'type'        => 'title',
                        'description' => __( 'Configura la captura de datos de identificación del cliente.', 'woocommerce-megasoft-gateway-universal' ),
                    ),
                    'require_document' => array(
                        'title'   => __( 'Requerir Documento', 'woocommerce-megasoft-gateway-universal' ),
                        'type'    => 'checkbox',
                        'label'   => __( 'Requerir tipo y número de documento', 'woocommerce-megasoft-gateway-universal' ),
                        'default' => 'yes',
                        'description' => __( 'Obligatorio para cumplir regulaciones venezolanas.', 'woocommerce-megasoft-gateway-universal' ),
                    ),
                    'save_documents' => array(
                        'title'   => __( 'Guardar Documentos', 'woocommerce-megasoft-gateway-universal' ),
                        'type'    => 'checkbox',
                        'label'   => __( 'Guardar documentos para futuras compras', 'woocommerce-megasoft-gateway-universal' ),
                        'default' => 'yes',
                        'description' => __( 'Permite a clientes registrados reutilizar sus datos de documento.', 'woocommerce-megasoft-gateway-universal' ),
                    ),
                    
                    // Configuraciones avanzadas
                    'advanced_settings' => array(
                        'title'       => __( 'Configuraciones Avanzadas', 'woocommerce-megasoft-gateway-universal' ),
                        'type'        => 'title',
                    ),
                    'enable_installments' => array(
                        'title'   => __( 'Habilitar Cuotas', 'woocommerce-megasoft-gateway-universal' ),
                        'type'    => 'checkbox',
                        'label'   => __( 'Permitir pagos en cuotas', 'woocommerce-megasoft-gateway-universal' ),
                        'default' => 'yes',
                        'description' => __( 'Permite a los clientes pagar en cuotas con tarjetas de crédito.', 'woocommerce-megasoft-gateway-universal' ),
                    ),
                    'max_installments' => array(
                        'title'       => __( 'Máximo de Cuotas', 'woocommerce-megasoft-gateway-universal' ),
                        'type'        => 'select',
                        'default'     => '12',
                        'options'     => array(
                            '3'  => '3 cuotas',
                            '6'  => '6 cuotas', 
                            '12' => '12 cuotas',
                            '18' => '18 cuotas',
                            '24' => '24 cuotas'
                        ),
                        'description' => __( 'Número máximo de cuotas permitidas.', 'woocommerce-megasoft-gateway-universal' ),
                    ),
                    'min_amount_installments' => array(
                        'title'       => __( 'Monto Mínimo para Cuotas', 'woocommerce-megasoft-gateway-universal' ),
                        'type'        => 'text',
                        'default'     => '50',
                        'description' => __( 'Monto mínimo para habilitar pagos en cuotas (en moneda base).', 'woocommerce-megasoft-gateway-universal' ),
                    ),
                    
                    // Configuración de logs y debugging
                    'debug_settings' => array(
                        'title'       => __( 'Depuración y Logs', 'woocommerce-megasoft-gateway-universal' ),
                        'type'        => 'title',
                    ),
                    'debug' => array(
                        'title'   => __( 'Modo Debug', 'woocommerce-megasoft-gateway-universal' ),
                        'type'    => 'checkbox',
                        'label'   => __( 'Activar logs detallados', 'woocommerce-megasoft-gateway-universal' ),
                        'default' => 'no',
                        'description' => __( 'Guarda logs detallados para depuración. Solo activar si es necesario.', 'woocommerce-megasoft-gateway-universal' ),
                    ),
                    
                    // URLs importantes
                    'urls_info' => array(
                        'title' => __( 'URLs del Sistema', 'woocommerce-megasoft-gateway-universal' ),
                        'type'  => 'title',
                        'description' => $this->get_urls_info(),
                    ),
                );
            }
            
            private function get_urls_info() {
                $return_url = WC()->api_request_url( 'WC_Gateway_MegaSoft_Universal' );
                $webhook_url = WC()->api_request_url( 'megasoft_webhook' );
                
                return sprintf(
                    __( 'Proporciona las siguientes URLs a Mega Soft:<br/><br/><strong>URL de Retorno:</strong><br/><code>%s?control=@control@&factura=@facturatrx@</code><br/><br/><strong>URL de Webhook (Opcional):</strong><br/><code>%s</code>', 'woocommerce-megasoft-gateway-universal' ),
                    $return_url,
                    $webhook_url
                );
            }
            
            public function enqueue_scripts() {
                if ( is_checkout() ) {
                    wp_enqueue_style( 
                        'megasoft-checkout', 
                        MEGASOFT_PLUGIN_URL . 'assets/css/checkout.css', 
                        array(), 
                        MEGASOFT_PLUGIN_VERSION 
                    );
                    
                    wp_enqueue_script( 
                        'megasoft-checkout', 
                        MEGASOFT_PLUGIN_URL . 'assets/js/checkout.js', 
                        array( 'jquery' ), 
                        MEGASOFT_PLUGIN_VERSION, 
                        true 
                    );
                    
                    wp_localize_script( 'megasoft-checkout', 'megasoft_params', array(
                        'ajax_url'       => admin_url( 'admin-ajax.php' ),
                        'save_documents' => $this->get_option( 'save_documents' ),
                        'nonce'          => wp_create_nonce( 'megasoft_checkout' ),
                        'messages'       => array(
                            'processing' => __( 'Procesando pago...', 'woocommerce-megasoft-gateway-universal' ),
                            'redirecting' => __( 'Redirigiendo a la pasarela de pago...', 'woocommerce-megasoft-gateway-universal' ),
                        )
                    ) );
                }
            }
            
            public function payment_fields() {
                if ( $this->description ) {
                    echo '<p>' . wp_kses_post( $this->description ) . '</p>';
                }
                
                $this->render_document_fields();
                $this->render_installments_fields();
            }
            
            private function render_document_fields() {
                if ( ! $this->require_document ) {
                    return;
                }
                
                $saved_data = $this->get_saved_customer_data();
                
                ?>
                <fieldset class="megasoft-document-fields">
                    <legend><?php _e( 'Datos de Identificación', 'woocommerce-megasoft-gateway-universal' ); ?></legend>
                    
                    <div class="megasoft-field-row">
                        <p class="form-row form-row-first">
                            <label for="megasoft_document_type">
                                <?php _e( 'Tipo de Documento', 'woocommerce-megasoft-gateway-universal' ); ?>
                                <span class="required">*</span>
                            </label>
                            <select id="megasoft_document_type" name="megasoft_document_type" required>
                                <option value=""><?php _e( 'Seleccione...', 'woocommerce-megasoft-gateway-universal' ); ?></option>
                                <option value="V" <?php selected( $saved_data['type'] ?? '', 'V' ); ?>><?php _e( 'V - Cédula Venezolana', 'woocommerce-megasoft-gateway-universal' ); ?></option>
                                <option value="E" <?php selected( $saved_data['type'] ?? '', 'E' ); ?>><?php _e( 'E - Cédula Extranjera', 'woocommerce-megasoft-gateway-universal' ); ?></option>
                                <option value="J" <?php selected( $saved_data['type'] ?? '', 'J' ); ?>><?php _e( 'J - RIF Jurídico', 'woocommerce-megasoft-gateway-universal' ); ?></option>
                                <option value="G" <?php selected( $saved_data['type'] ?? '', 'G' ); ?>><?php _e( 'G - RIF Gubernamental', 'woocommerce-megasoft-gateway-universal' ); ?></option>
                                <option value="P" <?php selected( $saved_data['type'] ?? '', 'P' ); ?>><?php _e( 'P - Pasaporte', 'woocommerce-megasoft-gateway-universal' ); ?></option>
                                <option value="C" <?php selected( $saved_data['type'] ?? '', 'C' ); ?>><?php _e( 'C - Cédula (Nuevo)', 'woocommerce-megasoft-gateway-universal' ); ?></option>
                            </select>
                        </p>
                        
                        <p class="form-row form-row-last">
                            <label for="megasoft_document_number">
                                <?php _e( 'Número de Documento', 'woocommerce-megasoft-gateway-universal' ); ?>
                                <span class="required">*</span>
                            </label>
                            <input 
                                type="text" 
                                id="megasoft_document_number" 
                                name="megasoft_document_number" 
                                value="<?php echo esc_attr( $saved_data['number'] ?? '' ); ?>"
                                placeholder="<?php _e( 'Ej: 12345678', 'woocommerce-megasoft-gateway-universal' ); ?>" 
                                pattern="[0-9A-Za-z]+"
                                title="<?php _e( 'Solo números y letras', 'woocommerce-megasoft-gateway-universal' ); ?>"
                                required 
                            />
                        </p>
                    </div>
                    
                    <?php if ( 'yes' === $this->get_option( 'save_documents' ) && is_user_logged_in() ) : ?>
                    <p class="form-row">
                        <label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox">
                            <input type="checkbox" id="megasoft_save_document" name="megasoft_save_document" value="1" />
                            <span class="woocommerce-form__label-text">
                                <?php _e( 'Guardar estos datos para futuras compras', 'woocommerce-megasoft-gateway-universal' ); ?>
                            </span>
                        </label>
                    </p>
                    <?php endif; ?>
                    
                    <div class="clear"></div>
                </fieldset>
                
                <style>
                .megasoft-document-fields {
                    background: #f8f9fa;
                    border: 1px solid #e9ecef;
                    padding: 15px;
                    margin: 15px 0;
                    border-radius: 5px;
                }
                .megasoft-document-fields .form-row {
                    margin: 0 0 10px 0;
                }
                .megasoft-document-fields label {
                    font-weight: bold;
                    color: #333;
                }
                .megasoft-document-fields .required {
                    color: #e74c3c;
                }
                </style>
                <?php
            }
            
            private function render_installments_fields() {
                if ( ! $this->enable_installments ) {
                    return;
                }
                
                $cart_total = WC()->cart ? WC()->cart->get_total( 'raw' ) : 0;
                $min_amount = floatval( $this->get_option( 'min_amount_installments', 50 ) );
                
                if ( $cart_total < $min_amount ) {
                    return;
                }
                
                $max_installments = intval( $this->get_option( 'max_installments', 12 ) );
                
                ?>
                <fieldset class="megasoft-installments-fields">
                    <legend><?php _e( 'Opciones de Pago', 'woocommerce-megasoft-gateway-universal' ); ?></legend>
                    
                    <p class="form-row">
                        <label for="megasoft_installments">
                            <?php _e( 'Número de Cuotas', 'woocommerce-megasoft-gateway-universal' ); ?>
                        </label>
                        <select id="megasoft_installments" name="megasoft_installments">
                            <option value="1"><?php _e( 'Pago único', 'woocommerce-megasoft-gateway-universal' ); ?></option>
                            <?php for ( $i = 3; $i <= $max_installments; $i += 3 ) : ?>
                                <?php 
                                $installment_amount = $cart_total / $i;
                                ?>
                                <option value="<?php echo $i; ?>">
                                    <?php printf( 
                                        __( '%d cuotas de %s', 'woocommerce-megasoft-gateway-universal' ), 
                                        $i, 
                                        wc_price( $installment_amount ) 
                                    ); ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </p>
                </fieldset>
                <?php
            }
            
            private function get_saved_customer_data() {
                if ( ! is_user_logged_in() ) {
                    return array();
                }
                
                $user_id = get_current_user_id();
                return array(
                    'type'   => get_user_meta( $user_id, '_megasoft_document_type', true ),
                    'number' => get_user_meta( $user_id, '_megasoft_document_number', true ),
                );
            }
            
            public function validate_checkout_fields() {
                if ( isset( $_POST['payment_method'] ) && $_POST['payment_method'] === $this->id ) {
                    
                    // Validar documento si es requerido
                    if ( $this->require_document ) {
                        $document_type = sanitize_text_field( $_POST['megasoft_document_type'] ?? '' );
                        $document_number = sanitize_text_field( $_POST['megasoft_document_number'] ?? '' );
                        
                        if ( empty( $document_type ) ) {
                            wc_add_notice( __( 'Por favor, seleccione el tipo de documento.', 'woocommerce-megasoft-gateway-universal' ), 'error' );
                        }
                        
                        if ( empty( $document_number ) ) {
                            wc_add_notice( __( 'Por favor, ingrese el número de documento.', 'woocommerce-megasoft-gateway-universal' ), 'error' );
                        } else {
                            // Validar formato del documento
                            if ( ! $this->validate_document_format( $document_type, $document_number ) ) {
                                wc_add_notice( __( 'Formato de documento inválido.', 'woocommerce-megasoft-gateway-universal' ), 'error' );
                            }
                        }
                    }
                    
                    // Validar cuotas
                    if ( $this->enable_installments && ! empty( $_POST['megasoft_installments'] ) ) {
                        $installments = intval( $_POST['megasoft_installments'] );
                        $max_installments = intval( $this->get_option( 'max_installments', 12 ) );
                        
                        if ( $installments < 1 || $installments > $max_installments ) {
                            wc_add_notice( 
                                sprintf( 
                                    __( 'Número de cuotas inválido. Máximo permitido: %d', 'woocommerce-megasoft-gateway-universal' ), 
                                    $max_installments 
                                ), 
                                'error' 
                            );
                        }
                    }
                }
            }
            
            private function validate_document_format( $type, $number ) {
                switch ( $type ) {
                    case 'V':
                    case 'E':
                    case 'C':
                        return preg_match( '/^[0-9]{6,10}$/', $number );
                    case 'J':
                    case 'G':
                        return preg_match( '/^[0-9]{8,10}$/', $number );
                    case 'P':
                        return preg_match( '/^[A-Z0-9]{6,15}$/i', $number );
                    default:
                        return false;
                }
            }
            
            public function process_payment( $order_id ) {
                $order = wc_get_order( $order_id );
                
                if ( ! $order ) {
                    throw new Exception( __( 'Orden no encontrada.', 'woocommerce-megasoft-gateway-universal' ) );
                }
                
                try {
                    $this->logger->info( "Iniciando proceso de pago para orden #{$order_id}" );
                    
                    // Recopilar datos del pago
                    $payment_data = $this->prepare_payment_data( $order );
                    
                    // Guardar datos en la base de datos
                    $this->save_transaction_data( $order, $payment_data );
                    
                    // Hacer pre-registro con Mega Soft
                    $control_number = $this->api->create_preregistration( $payment_data );
                    
                    if ( ! $control_number ) {
                        throw new Exception( __( 'Error al generar el pre-registro con Mega Soft.', 'woocommerce-megasoft-gateway-universal' ) );
                    }
                    
                    // Actualizar orden con número de control
                    $order->update_meta_data( '_megasoft_control_number', $control_number );
                    $order->update_status( 'pending', __( 'Redirigiendo a Mega Soft...', 'woocommerce-megasoft-gateway-universal' ) );
                    $order->save();
                    
                    // Actualizar transacción en BD
                    $this->update_transaction_control( $order_id, $control_number );
                    
                    // URL de redirección
                    $redirect_url = $this->api->get_payment_url( $control_number );
                    
                    $this->logger->info( "Pre-registro exitoso. Control: {$control_number}, Orden: #{$order_id}" );
                    
                    return array(
                        'result'   => 'success',
                        'redirect' => $redirect_url
                    );
                    
                } catch ( Exception $e ) {
                    $this->logger->error( "Error en process_payment para orden #{$order_id}: " . $e->getMessage() );
                    wc_add_notice( $e->getMessage(), 'error' );
                    return array( 'result' => 'failure' );
                }
            }
            
            private function prepare_payment_data( $order ) {
                $data = array(
                    'order_id'    => $order->get_id(),
                    'amount'      => $order->get_total(),
                    'currency'    => $order->get_currency(),
                    'client_name' => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
                );
                
                // Datos del documento
                if ( $this->require_document ) {
                    $data['document_type']   = sanitize_text_field( $_POST['megasoft_document_type'] ?? '' );
                    $data['document_number'] = sanitize_text_field( $_POST['megasoft_document_number'] ?? '' );
                    
                    // Guardar documentos si el usuario lo solicita
                    if ( isset( $_POST['megasoft_save_document'] ) && is_user_logged_in() ) {
                        $user_id = get_current_user_id();
                        update_user_meta( $user_id, '_megasoft_document_type', $data['document_type'] );
                        update_user_meta( $user_id, '_megasoft_document_number', $data['document_number'] );
                    }
                }
                
                // Datos de cuotas
                if ( $this->enable_installments && ! empty( $_POST['megasoft_installments'] ) ) {
                    $data['installments'] = intval( $_POST['megasoft_installments'] );
                }
                
                return $data;
            }
            
            private function save_transaction_data( $order, $payment_data ) {
                global $wpdb;
                
                $table_name = $wpdb->prefix . 'megasoft_transactions';
                
                $wpdb->insert(
                    $table_name,
                    array(
                        'order_id'        => $order->get_id(),
                        'amount'          => $payment_data['amount'],
                        'currency'        => $payment_data['currency'],
                        'document_type'   => $payment_data['document_type'] ?? '',
                        'document_number' => $payment_data['document_number'] ?? '',
                        'client_name'     => $payment_data['client_name'],
                        'status'          => 'pending',
                        'created_at'      => current_time( 'mysql' )
                    ),
                    array( '%d', '%f', '%s', '%s', '%s', '%s', '%s', '%s' )
                );
            }
            
            private function update_transaction_control( $order_id, $control_number ) {
                global $wpdb;
                
                $table_name = $wpdb->prefix . 'megasoft_transactions';
                
                $wpdb->update(
                    $table_name,
                    array( 'control_number' => $control_number ),
                    array( 'order_id' => $order_id ),
                    array( '%s' ),
                    array( '%d' )
                );
            }
            
            public function handle_return() {
                try {
                    $control_number = sanitize_text_field( $_GET['control'] ?? '' );
                    $order_id       = absint( $_GET['factura'] ?? 0 );
                    
                    $this->logger->info( "Procesando retorno. Control: {$control_number}, Orden: #{$order_id}" );
                    
                    if ( ! $control_number || ! $order_id ) {
                        throw new Exception( __( 'Parámetros de retorno inválidos.', 'woocommerce-megasoft-gateway-universal' ) );
                    }
                    
                    $order = wc_get_order( $order_id );
                    
                    if ( ! $order ) {
                        throw new Exception( __( 'Orden no encontrada.', 'woocommerce-megasoft-gateway-universal' ) );
                    }
                    
                    // Verificar seguridad del número de control
                    $saved_control = $order->get_meta( '_megasoft_control_number' );
                    if ( $saved_control !== $control_number ) {
                        $this->logger->error( "Control number mismatch. Saved: {$saved_control}, Received: {$control_number}" );
                        throw new Exception( __( 'Error de validación de seguridad.', 'woocommerce-megasoft-gateway-universal' ) );
                    }
                    
                    // Consultar estado de la transacción
                    $transaction_result = $this->api->query_transaction_status( $control_number );
                    
                    if ( ! $transaction_result ) {
                        throw new Exception( __( 'Error al consultar el estado de la transacción.', 'woocommerce-megasoft-gateway-universal' ) );
                    }
                    
                    // Procesar resultado
                    $this->process_transaction_result( $order, $transaction_result );
                    
                    // Redireccionar según el resultado
                    if ( $transaction_result['approved'] ) {
                        wp_redirect( $this->get_return_url( $order ) );
                    } else {
                        wc_add_notice( $transaction_result['message'], 'error' );
                        wp_redirect( $order->get_checkout_payment_url() );
                    }
                    
                } catch ( Exception $e ) {
                    $this->logger->error( "Error en handle_return: " . $e->getMessage() );
                    wc_add_notice( $e->getMessage(), 'error' );
                    wp_redirect( wc_get_checkout_url() );
                }
                
                exit;
            }
            
            private function process_transaction_result( $order, $result ) {
                global $wpdb;
                
                // Actualizar orden
                if ( $result['approved'] ) {
                    $order->payment_complete( $result['auth_id'] );
                    $order->add_order_note( 
                        sprintf( 
                            __( 'Pago aprobado via Mega Soft. Auth ID: %s, Referencia: %s', 'woocommerce-megasoft-gateway-universal' ),
                            $result['auth_id'],
                            $result['reference']
                        )
                    );
                } else {
                    $order->update_status( 'failed', $result['message'] );
                }
                
                // Guardar datos de la transacción
                foreach ( $result['metadata'] as $key => $value ) {
                    $order->update_meta_data( "_megasoft_{$key}", $value );
                }
                
                $order->save();
                
                // Actualizar base de datos
                $table_name = $wpdb->prefix . 'megasoft_transactions';
                
                $wpdb->update(
                    $table_name,
                    array(
                        'status'         => $result['approved'] ? 'approved' : 'failed',
                        'auth_id'        => $result['auth_id'],
                        'reference'      => $result['reference'],
                        'payment_method' => $result['payment_method'],
                        'response_data'  => json_encode( $result['raw_data'] ),
                        'updated_at'     => current_time( 'mysql' )
                    ),
                    array( 'order_id' => $order->get_id() ),
                    array( '%s', '%s', '%s', '%s', '%s', '%s' ),
                    array( '%d' )
                );
                
                $this->logger->info( 
                    sprintf( 
                        "Transacción procesada. Orden: #%d, Estado: %s, Auth: %s",
                        $order->get_id(),
                        $result['approved'] ? 'approved' : 'failed',
                        $result['auth_id']
                    )
                );
            }
            
            public function display_receipt( $order_id ) {
                $order = wc_get_order( $order_id );
                $voucher = $order->get_meta( '_megasoft_voucher' );
                
                if ( empty( $voucher ) ) {
                    return;
                }
                
                ?>
                <section class="megasoft-receipt">
                    <h2><?php _e( 'Comprobante de Pago', 'woocommerce-megasoft-gateway-universal' ); ?></h2>
                    <?php echo $voucher; ?>
                    
                    <div class="receipt-actions">
                        <button onclick="window.print()" class="button">
                            <?php _e( 'Imprimir Comprobante', 'woocommerce-megasoft-gateway-universal' ); ?>
                        </button>
                    </div>
                </section>
                
                <style>
                @media print {
                    .megasoft-receipt { 
                        page-break-inside: avoid; 
                    }
                    .receipt-actions { 
                        display: none !important; 
                    }
                }
                </style>
                <?php
            }
            
            public function admin_options() {
                ?>
                <h2><?php _e( 'Mega Soft Gateway', 'woocommerce-megasoft-gateway-universal' ); ?></h2>
                <p><?php _e( 'Acepta pagos usando la pasarela venezolana Mega Soft con soporte completo para producción.', 'woocommerce-megasoft-gateway-universal' ); ?></p>
                
                <div class="megasoft-admin-notices">
                    <?php $this->display_admin_notices(); ?>
                </div>
                
                <table class="form-table">
                    <?php $this->generate_settings_html(); ?>
                </table>
                <?php
            }
            
            private function display_admin_notices() {
                // Verificar configuración
                if ( empty( $this->get_option( 'cod_afiliacion' ) ) ) {
                    echo '<div class="notice notice-error"><p>' . __( 'Debes configurar el código de afiliación.', 'woocommerce-megasoft-gateway-universal' ) . '</p></div>';
                }
                
                if ( empty( $this->get_option( 'api_user' ) ) || empty( $this->get_option( 'api_password' ) ) ) {
                    echo '<div class="notice notice-error"><p>' . __( 'Debes configurar las credenciales de la API.', 'woocommerce-megasoft-gateway-universal' ) . '</p></div>';
                }
                
                if ( $this->test_mode ) {
                    echo '<div class="notice notice-warning"><p>' . __( 'El gateway está en modo de prueba. Desactívalo para usar en producción.', 'woocommerce-megasoft-gateway-universal' ) . '</p></div>';
                }
                
                // Verificar SSL en producción
                if ( ! $this->test_mode && ! is_ssl() ) {
                    echo '<div class="notice notice-error"><p>' . __( 'Se requiere SSL (HTTPS) para usar el gateway en producción.', 'woocommerce-megasoft-gateway-universal' ) . '</p></div>';
                }
            }
        }
    }
}

// Hooks adicionales
add_action( 'megasoft_sync_transactions', 'megasoft_sync_pending_transactions' );

function megasoft_sync_pending_transactions() {
    // Sincronizar transacciones pendientes cada hora
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'megasoft_transactions';
    $pending_transactions = $wpdb->get_results(
        "SELECT * FROM {$table_name} 
         WHERE status = 'pending' 
         AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
         LIMIT 50"
    );
    
    if ( ! empty( $pending_transactions ) && class_exists( 'WC_Gateway_MegaSoft_Universal' ) ) {
        foreach ( $pending_transactions as $transaction ) {
            if ( $transaction->control_number ) {
                $gateway = new WC_Gateway_MegaSoft_Universal();
                $result = $gateway->api->query_transaction_status( $transaction->control_number );
                
                if ( $result && isset( $result['approved'] ) ) {
                    $order = wc_get_order( $transaction->order_id );
                    if ( $order && $order->get_status() === 'pending' ) {
                        $gateway->process_transaction_result( $order, $result );
                    }
                }
            }
        }
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