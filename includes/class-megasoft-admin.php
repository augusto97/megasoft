<?php
/**
 * MegaSoft Admin Class
 * Dashboard y herramientas administrativas
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MegaSoft_Admin {
    
    private $logger;
    
    public function __construct() {
        $this->logger = new MegaSoft_Logger( true, 'info' );
        
        add_action( 'admin_menu', array( $this, 'add_admin_menus' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
        add_action( 'wp_ajax_megasoft_admin_action', array( $this, 'handle_admin_ajax' ) );
        
        // Agregar columnas personalizadas en lista de órdenes
        add_filter( 'manage_shop_order_posts_columns', array( $this, 'add_order_columns' ) );
        add_action( 'manage_shop_order_posts_custom_column', array( $this, 'render_order_columns' ), 10, 2 );
        
        // Meta box en edición de órdenes
        add_action( 'add_meta_boxes', array( $this, 'add_order_meta_boxes' ) );
        
        // Notificaciones admin
        add_action( 'admin_notices', array( $this, 'show_admin_notices' ) );
    }
    
    /**
     * Agregar menús de administración
     */
    public function add_admin_menus() {
        // Menú principal
        add_menu_page(
            __( 'Mega Soft Gateway', 'woocommerce-megasoft-gateway-universal' ),
            __( 'Mega Soft', 'woocommerce-megasoft-gateway-universal' ),
            'manage_options',
            'megasoft-dashboard',
            array( $this, 'render_dashboard_page' ),
            'dashicons-credit-card',
            56
        );
        
        // Submenús
        add_submenu_page(
            'megasoft-dashboard',
            __( 'Dashboard', 'woocommerce-megasoft-gateway-universal' ),
            __( 'Dashboard', 'woocommerce-megasoft-gateway-universal' ),
            'manage_options',
            'megasoft-dashboard',
            array( $this, 'render_dashboard_page' )
        );
        
        add_submenu_page(
            'megasoft-dashboard',
            __( 'Transacciones', 'woocommerce-megasoft-gateway-universal' ),
            __( 'Transacciones', 'woocommerce-megasoft-gateway-universal' ),
            'manage_options',
            'megasoft-transactions',
            array( $this, 'render_transactions_page' )
        );
        
        add_submenu_page(
            'megasoft-dashboard',
            __( 'Logs', 'woocommerce-megasoft-gateway-universal' ),
            __( 'Logs', 'woocommerce-megasoft-gateway-universal' ),
            'manage_options',
            'megasoft-logs',
            array( $this, 'render_logs_page' )
        );
        
        add_submenu_page(
            'megasoft-dashboard',
            __( 'Reportes', 'woocommerce-megasoft-gateway-universal' ),
            __( 'Reportes', 'woocommerce-megasoft-gateway-universal' ),
            'manage_options',
            'megasoft-reports',
            array( $this, 'render_reports_page' )
        );
        
        add_submenu_page(
            'megasoft-dashboard',
            __( 'Herramientas', 'woocommerce-megasoft-gateway-universal' ),
            __( 'Herramientas', 'woocommerce-megasoft-gateway-universal' ),
            'manage_options',
            'megasoft-tools',
            array( $this, 'render_tools_page' )
        );
    }
    
    /**
     * Cargar scripts y estilos del admin
     */
    public function enqueue_admin_scripts( $hook ) {
        if ( strpos( $hook, 'megasoft' ) === false && $hook !== 'post.php' ) {
            return;
        }
        
        wp_enqueue_style( 
            'megasoft-admin', 
            MEGASOFT_PLUGIN_URL . 'assets/css/admin.css', 
            array(), 
            MEGASOFT_PLUGIN_VERSION 
        );
        
        wp_enqueue_script( 
            'megasoft-admin', 
            MEGASOFT_PLUGIN_URL . 'assets/js/admin.js', 
            array( 'jquery', 'wp-util' ), 
            MEGASOFT_PLUGIN_VERSION, 
            true 
        );
        
        wp_localize_script( 'megasoft-admin', 'megasoft_admin', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'megasoft_admin' ),
            'strings'  => array(
                'confirm_sync'    => __( '¿Sincronizar transacciones pendientes?', 'woocommerce-megasoft-gateway-universal' ),
                'confirm_cleanup' => __( '¿Eliminar logs antiguos?', 'woocommerce-megasoft-gateway-universal' ),
                'processing'      => __( 'Procesando...', 'woocommerce-megasoft-gateway-universal' ),
                'success'         => __( 'Operación completada', 'woocommerce-megasoft-gateway-universal' ),
                'error'           => __( 'Error en la operación', 'woocommerce-megasoft-gateway-universal' )
            )
        ) );
        
        // Chart.js para reportes
        if ( $hook === 'mega-soft_page_megasoft-reports' ) {
            wp_enqueue_script( 'chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '3.9.1', true );
        }
    }
    
    /**
     * Renderizar página de dashboard
     */
    public function render_dashboard_page() {
        $stats = $this->get_dashboard_stats();
        $recent_transactions = $this->get_recent_transactions( 10 );
        $gateway_status = $this->check_gateway_status();
        
        ?>
        <div class="wrap">
            <h1><?php _e( 'Dashboard Mega Soft Gateway', 'woocommerce-megasoft-gateway-universal' ); ?></h1>
            
            <div class="megasoft-dashboard">
                <!-- Estado del Gateway -->
                <div class="megasoft-status-card <?php echo $gateway_status['status']; ?>">
                    <h2><?php _e( 'Estado del Gateway', 'woocommerce-megasoft-gateway-universal' ); ?></h2>
                    <div class="status-indicator">
                        <span class="status-dot"></span>
                        <?php echo $gateway_status['message']; ?>
                    </div>
                    <?php if ( ! empty( $gateway_status['issues'] ) ) : ?>
                        <ul class="status-issues">
                            <?php foreach ( $gateway_status['issues'] as $issue ) : ?>
                                <li><?php echo esc_html( $issue ); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
                
                <!-- Estadísticas -->
                <div class="megasoft-stats-grid">
                    <div class="stat-card">
                        <h3><?php _e( 'Hoy', 'woocommerce-megasoft-gateway-universal' ); ?></h3>
                        <div class="stat-number"><?php echo $stats['today']['count']; ?></div>
                        <div class="stat-amount"><?php echo wc_price( $stats['today']['amount'] ); ?></div>
                    </div>
                    
                    <div class="stat-card">
                        <h3><?php _e( 'Esta Semana', 'woocommerce-megasoft-gateway-universal' ); ?></h3>
                        <div class="stat-number"><?php echo $stats['week']['count']; ?></div>
                        <div class="stat-amount"><?php echo wc_price( $stats['week']['amount'] ); ?></div>
                    </div>
                    
                    <div class="stat-card">
                        <h3><?php _e( 'Este Mes', 'woocommerce-megasoft-gateway-universal' ); ?></h3>
                        <div class="stat-number"><?php echo $stats['month']['count']; ?></div>
                        <div class="stat-amount"><?php echo wc_price( $stats['month']['amount'] ); ?></div>
                    </div>
                    
                    <div class="stat-card">
                        <h3><?php _e( 'Total', 'woocommerce-megasoft-gateway-universal' ); ?></h3>
                        <div class="stat-number"><?php echo $stats['total']['count']; ?></div>
                        <div class="stat-amount"><?php echo wc_price( $stats['total']['amount'] ); ?></div>
                    </div>
                </div>
                
                <!-- Acciones Rápidas -->
                <div class="megasoft-quick-actions">
                    <h2><?php _e( 'Acciones Rápidas', 'woocommerce-megasoft-gateway-universal' ); ?></h2>
                    <div class="action-buttons">
                        <button class="button button-primary" onclick="megaSoftTestConnection()">
                            <?php _e( 'Probar Conexión', 'woocommerce-megasoft-gateway-universal' ); ?>
                        </button>
                        <button class="button" onclick="megaSoftSyncTransactions()">
                            <?php _e( 'Sincronizar Transacciones', 'woocommerce-megasoft-gateway-universal' ); ?>
                        </button>
                        <button class="button" onclick="megaSoftCleanupLogs()">
                            <?php _e( 'Limpiar Logs Antiguos', 'woocommerce-megasoft-gateway-universal' ); ?>
                        </button>
                        <a href="<?php echo admin_url( 'admin.php?page=wc-settings&tab=checkout&section=megasoft_gateway_universal' ); ?>" class="button">
                            <?php _e( 'Configuración', 'woocommerce-megasoft-gateway-universal' ); ?>
                        </a>
                    </div>
                </div>
                
                <!-- Transacciones Recientes -->
                <div class="megasoft-recent-transactions">
                    <h2><?php _e( 'Transacciones Recientes', 'woocommerce-megasoft-gateway-universal' ); ?></h2>
                    <?php if ( empty( $recent_transactions ) ) : ?>
                        <p><?php _e( 'No hay transacciones recientes.', 'woocommerce-megasoft-gateway-universal' ); ?></p>
                    <?php else : ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php _e( 'Orden', 'woocommerce-megasoft-gateway-universal' ); ?></th>
                                    <th><?php _e( 'Control', 'woocommerce-megasoft-gateway-universal' ); ?></th>
                                    <th><?php _e( 'Estado', 'woocommerce-megasoft-gateway-universal' ); ?></th>
                                    <th><?php _e( 'Monto', 'woocommerce-megasoft-gateway-universal' ); ?></th>
                                    <th><?php _e( 'Fecha', 'woocommerce-megasoft-gateway-universal' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ( $recent_transactions as $transaction ) : ?>
                                    <tr>
                                        <td>
                                            <a href="<?php echo admin_url( 'post.php?post=' . $transaction->order_id . '&action=edit' ); ?>">
                                                #<?php echo $transaction->order_id; ?>
                                            </a>
                                        </td>
                                        <td><?php echo esc_html( $transaction->control_number ); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo esc_attr( $transaction->status ); ?>">
                                                <?php echo esc_html( ucfirst( $transaction->status ) ); ?>
                                            </span>
                                        </td>
                                        <td><?php echo wc_price( $transaction->amount ); ?></td>
                                        <td><?php echo mysql2date( 'Y-m-d H:i', $transaction->created_at ); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <p>
                            <a href="<?php echo admin_url( 'admin.php?page=megasoft-transactions' ); ?>">
                                <?php _e( 'Ver todas las transacciones →', 'woocommerce-megasoft-gateway-universal' ); ?>
                            </a>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Renderizar página de transacciones
     */
    public function render_transactions_page() {
        // Manejar filtros y paginación
        $status_filter = sanitize_text_field( $_GET['status'] ?? '' );
        $search = sanitize_text_field( $_GET['s'] ?? '' );
        $per_page = 20;
        $current_page = max( 1, intval( $_GET['paged'] ?? 1 ) );
        $offset = ( $current_page - 1 ) * $per_page;
        
        $transactions = $this->get_transactions( $per_page, $offset, $status_filter, $search );
        $total_transactions = $this->get_transactions_count( $status_filter, $search );
        $total_pages = ceil( $total_transactions / $per_page );
        
        ?>
        <div class="wrap">
            <h1><?php _e( 'Transacciones Mega Soft', 'woocommerce-megasoft-gateway-universal' ); ?></h1>
            
            <!-- Filtros -->
            <form method="get">
                <input type="hidden" name="page" value="megasoft-transactions">
                <div class="tablenav top">
                    <div class="alignleft actions">
                        <select name="status">
                            <option value=""><?php _e( 'Todos los estados', 'woocommerce-megasoft-gateway-universal' ); ?></option>
                            <option value="pending" <?php selected( $status_filter, 'pending' ); ?>><?php _e( 'Pendiente', 'woocommerce-megasoft-gateway-universal' ); ?></option>
                            <option value="approved" <?php selected( $status_filter, 'approved' ); ?>><?php _e( 'Aprobado', 'woocommerce-megasoft-gateway-universal' ); ?></option>
                            <option value="failed" <?php selected( $status_filter, 'failed' ); ?>><?php _e( 'Fallido', 'woocommerce-megasoft-gateway-universal' ); ?></option>
                        </select>
                        <?php submit_button( __( 'Filtrar', 'woocommerce-megasoft-gateway-universal' ), 'button', false, false, array( 'id' => 'post-query-submit' ) ); ?>
                    </div>
                    
                    <div class="alignright actions">
                        <input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php _e( 'Buscar...', 'woocommerce-megasoft-gateway-universal' ); ?>">
                        <?php submit_button( __( 'Buscar', 'woocommerce-megasoft-gateway-universal' ), 'button', false, false, array( 'id' => 'search-submit' ) ); ?>
                    </div>
                </div>
            </form>
            
            <!-- Tabla de transacciones -->
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e( 'Orden', 'woocommerce-megasoft-gateway-universal' ); ?></th>
                        <th><?php _e( 'Control', 'woocommerce-megasoft-gateway-universal' ); ?></th>
                        <th><?php _e( 'Cliente', 'woocommerce-megasoft-gateway-universal' ); ?></th>
                        <th><?php _e( 'Estado', 'woocommerce-megasoft-gateway-universal' ); ?></th>
                        <th><?php _e( 'Monto', 'woocommerce-megasoft-gateway-universal' ); ?></th>
                        <th><?php _e( 'Método', 'woocommerce-megasoft-gateway-universal' ); ?></th>
                        <th><?php _e( 'Auth ID', 'woocommerce-megasoft-gateway-universal' ); ?></th>
                        <th><?php _e( 'Fecha', 'woocommerce-megasoft-gateway-universal' ); ?></th>
                        <th><?php _e( 'Acciones', 'woocommerce-megasoft-gateway-universal' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $transactions ) ) : ?>
                        <tr>
                            <td colspan="9" style="text-align: center;">
                                <?php _e( 'No se encontraron transacciones.', 'woocommerce-megasoft-gateway-universal' ); ?>
                            </td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ( $transactions as $transaction ) : ?>
                            <tr>
                                <td>
                                    <a href="<?php echo admin_url( 'post.php?post=' . $transaction->order_id . '&action=edit' ); ?>">
                                        #<?php echo $transaction->order_id; ?>
                                    </a>
                                </td>
                                <td>
                                    <code><?php echo esc_html( $transaction->control_number ); ?></code>
                                </td>
                                <td><?php echo esc_html( $transaction->client_name ); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo esc_attr( $transaction->status ); ?>">
                                        <?php echo esc_html( ucfirst( $transaction->status ) ); ?>
                                    </span>
                                </td>
                                <td><?php echo wc_price( $transaction->amount ); ?></td>
                                <td><?php echo esc_html( $transaction->payment_method ); ?></td>
                                <td><?php echo esc_html( $transaction->auth_id ); ?></td>
                                <td><?php echo mysql2date( 'Y-m-d H:i', $transaction->created_at ); ?></td>
                                <td>
                                    <div class="row-actions">
                                        <span class="view">
                                            <a href="#" onclick="megaSoftViewTransaction(<?php echo $transaction->id; ?>)">
                                                <?php _e( 'Ver', 'woocommerce-megasoft-gateway-universal' ); ?>
                                            </a>
                                        </span>
                                        <?php if ( $transaction->status === 'pending' && $transaction->control_number ) : ?>
                                            | <span class="sync">
                                                <a href="#" onclick="megaSoftSyncTransaction('<?php echo $transaction->control_number; ?>')">
                                                    <?php _e( 'Sincronizar', 'woocommerce-megasoft-gateway-universal' ); ?>
                                                </a>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <!-- Paginación -->
            <?php if ( $total_pages > 1 ) : ?>
                <div class="tablenav bottom">
                    <div class="tablenav-pages">
                        <?php
                        echo paginate_links( array(
                            'base'      => add_query_arg( 'paged', '%#%' ),
                            'format'    => '',
                            'current'   => $current_page,
                            'total'     => $total_pages,
                            'prev_text' => '‹',
                            'next_text' => '›'
                        ) );
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Renderizar página de logs
     */
    public function render_logs_page() {
        $level_filter = sanitize_text_field( $_GET['level'] ?? '' );
        $search = sanitize_text_field( $_GET['s'] ?? '' );
        $per_page = 50;
        $current_page = max( 1, intval( $_GET['paged'] ?? 1 ) );
        $offset = ( $current_page - 1 ) * $per_page;
        
        $logs = $this->logger->get_logs( $per_page + $offset, $level_filter );
        $logs = array_slice( $logs, $offset, $per_page );
        
        ?>
        <div class="wrap">
            <h1><?php _e( 'Logs del Sistema', 'woocommerce-megasoft-gateway-universal' ); ?></h1>
            
            <!-- Filtros -->
            <form method="get">
                <input type="hidden" name="page" value="megasoft-logs">
                <div class="tablenav top">
                    <div class="alignleft actions">
                        <select name="level">
                            <option value=""><?php _e( 'Todos los niveles', 'woocommerce-megasoft-gateway-universal' ); ?></option>
                            <option value="error" <?php selected( $level_filter, 'error' ); ?>><?php _e( 'Errores', 'woocommerce-megasoft-gateway-universal' ); ?></option>
                            <option value="warn" <?php selected( $level_filter, 'warn' ); ?>><?php _e( 'Advertencias', 'woocommerce-megasoft-gateway-universal' ); ?></option>
                            <option value="info" <?php selected( $level_filter, 'info' ); ?>><?php _e( 'Información', 'woocommerce-megasoft-gateway-universal' ); ?></option>
                            <option value="debug" <?php selected( $level_filter, 'debug' ); ?>><?php _e( 'Debug', 'woocommerce-megasoft-gateway-universal' ); ?></option>
                        </select>
                        <?php submit_button( __( 'Filtrar', 'woocommerce-megasoft-gateway-universal' ), 'button', false, false ); ?>
                    </div>
                    
                    <div class="alignright actions">
                        <button type="button" class="button" onclick="megaSoftExportLogs()">
                            <?php _e( 'Exportar Logs', 'woocommerce-megasoft-gateway-universal' ); ?>
                        </button>
                        <button type="button" class="button" onclick="megaSoftCleanupLogs()">
                            <?php _e( 'Limpiar Antiguos', 'woocommerce-megasoft-gateway-universal' ); ?>
                        </button>
                    </div>
                </div>
            </form>
            
            <!-- Tabla de logs -->
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 120px;"><?php _e( 'Fecha/Hora', 'woocommerce-megasoft-gateway-universal' ); ?></th>
                        <th style="width: 80px;"><?php _e( 'Nivel', 'woocommerce-megasoft-gateway-universal' ); ?></th>
                        <th style="width: 80px;"><?php _e( 'Orden', 'woocommerce-megasoft-gateway-universal' ); ?></th>
                        <th style="width: 120px;"><?php _e( 'Control', 'woocommerce-megasoft-gateway-universal' ); ?></th>
                        <th><?php _e( 'Mensaje', 'woocommerce-megasoft-gateway-universal' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $logs ) ) : ?>
                        <tr>
                            <td colspan="5" style="text-align: center;">
                                <?php _e( 'No se encontraron logs.', 'woocommerce-megasoft-gateway-universal' ); ?>
                            </td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ( $logs as $log ) : ?>
                            <tr>
                                <td><?php echo mysql2date( 'Y-m-d H:i:s', $log->created_at ); ?></td>
                                <td>
                                    <span class="log-level log-level-<?php echo strtolower( $log->level ); ?>">
                                        <?php echo esc_html( $log->level ); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ( $log->order_id ) : ?>
                                        <a href="<?php echo admin_url( 'post.php?post=' . $log->order_id . '&action=edit' ); ?>">
                                            #<?php echo $log->order_id; ?>
                                        </a>
                                    <?php else : ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ( $log->control_number ) : ?>
                                        <code><?php echo esc_html( $log->control_number ); ?></code>
                                    <?php else : ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo esc_html( $log->message ); ?>
                                    <?php if ( ! empty( $log->context ) ) : ?>
                                        <button type="button" class="button-link" onclick="megaSoftToggleContext(<?php echo $log->id; ?>)">
                                            <?php _e( 'Ver contexto', 'woocommerce-megasoft-gateway-universal' ); ?>
                                        </button>
                                        <div id="context-<?php echo $log->id; ?>" class="log-context" style="display: none;">
                                            <pre><?php echo esc_html( $log->context ); ?></pre>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    /**
     * Renderizar página de reportes
     */
    public function render_reports_page() {
        $period = sanitize_text_field( $_GET['period'] ?? 'last_30_days' );
        $report_data = $this->get_report_data( $period );
        
        ?>
        <div class="wrap">
            <h1><?php _e( 'Reportes y Estadísticas', 'woocommerce-megasoft-gateway-universal' ); ?></h1>
            
            <!-- Selector de período -->
            <form method="get" class="megasoft-period-selector">
                <input type="hidden" name="page" value="megasoft-reports">
                <select name="period" onchange="this.form.submit()">
                    <option value="today" <?php selected( $period, 'today' ); ?>><?php _e( 'Hoy', 'woocommerce-megasoft-gateway-universal' ); ?></option>
                    <option value="yesterday" <?php selected( $period, 'yesterday' ); ?>><?php _e( 'Ayer', 'woocommerce-megasoft-gateway-universal' ); ?></option>
                    <option value="last_7_days" <?php selected( $period, 'last_7_days' ); ?>><?php _e( 'Últimos 7 días', 'woocommerce-megasoft-gateway-universal' ); ?></option>
                    <option value="last_30_days" <?php selected( $period, 'last_30_days' ); ?>><?php _e( 'Últimos 30 días', 'woocommerce-megasoft-gateway-universal' ); ?></option>
                    <option value="this_month" <?php selected( $period, 'this_month' ); ?>><?php _e( 'Este mes', 'woocommerce-megasoft-gateway-universal' ); ?></option>
                    <option value="last_month" <?php selected( $period, 'last_month' ); ?>><?php _e( 'Mes anterior', 'woocommerce-megasoft-gateway-universal' ); ?></option>
                </select>
            </form>
            
            <!-- Resumen de estadísticas -->
            <div class="megasoft-report-summary">
                <div class="summary-card">
                    <h3><?php _e( 'Transacciones Totales', 'woocommerce-megasoft-gateway-universal' ); ?></h3>
                    <div class="summary-number"><?php echo number_format( $report_data['total_transactions'] ); ?></div>
                </div>
                
                <div class="summary-card success">
                    <h3><?php _e( 'Aprobadas', 'woocommerce-megasoft-gateway-universal' ); ?></h3>
                    <div class="summary-number"><?php echo number_format( $report_data['approved_transactions'] ); ?></div>
                    <div class="summary-percentage"><?php echo $report_data['approval_rate']; ?>%</div>
                </div>
                
                <div class="summary-card error">
                    <h3><?php _e( 'Rechazadas', 'woocommerce-megasoft-gateway-universal' ); ?></h3>
                    <div class="summary-number"><?php echo number_format( $report_data['failed_transactions'] ); ?></div>
                    <div class="summary-percentage"><?php echo $report_data['failure_rate']; ?>%</div>
                </div>
                
                <div class="summary-card">
                    <h3><?php _e( 'Volumen Total', 'woocommerce-megasoft-gateway-universal' ); ?></h3>
                    <div class="summary-amount"><?php echo wc_price( $report_data['total_amount'] ); ?></div>
                </div>
            </div>
            
            <!-- Gráficos -->
            <div class="megasoft-charts">
                <div class="chart-container">
                    <h3><?php _e( 'Transacciones por Día', 'woocommerce-megasoft-gateway-universal' ); ?></h3>
                    <canvas id="transactionsChart" width="400" height="200"></canvas>
                </div>
                
                <div class="chart-container">
                    <h3><?php _e( 'Distribución por Método de Pago', 'woocommerce-megasoft-gateway-universal' ); ?></h3>
                    <canvas id="paymentMethodsChart" width="400" height="200"></canvas>
                </div>
            </div>
            
            <!-- Tabla de detalles -->
            <div class="megasoft-report-details">
                <h3><?php _e( 'Top 10 Transacciones', 'woocommerce-megasoft-gateway-universal' ); ?></h3>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e( 'Orden', 'woocommerce-megasoft-gateway-universal' ); ?></th>
                            <th><?php _e( 'Cliente', 'woocommerce-megasoft-gateway-universal' ); ?></th>
                            <th><?php _e( 'Monto', 'woocommerce-megasoft-gateway-universal' ); ?></th>
                            <th><?php _e( 'Estado', 'woocommerce-megasoft-gateway-universal' ); ?></th>
                            <th><?php _e( 'Fecha', 'woocommerce-megasoft-gateway-universal' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ( $report_data['top_transactions'] as $transaction ) : ?>
                            <tr>
                                <td>
                                    <a href="<?php echo admin_url( 'post.php?post=' . $transaction->order_id . '&action=edit' ); ?>">
                                        #<?php echo $transaction->order_id; ?>
                                    </a>
                                </td>
                                <td><?php echo esc_html( $transaction->client_name ); ?></td>
                                <td><?php echo wc_price( $transaction->amount ); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo esc_attr( $transaction->status ); ?>">
                                        <?php echo esc_html( ucfirst( $transaction->status ) ); ?>
                                    </span>
                                </td>
                                <td><?php echo mysql2date( 'Y-m-d H:i', $transaction->created_at ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Gráfico de transacciones por día
            var ctx1 = document.getElementById('transactionsChart').getContext('2d');
            new Chart(ctx1, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode( array_keys( $report_data['daily_transactions'] ) ); ?>,
                    datasets: [{
                        label: '<?php _e( 'Transacciones', 'woocommerce-megasoft-gateway-universal' ); ?>',
                        data: <?php echo json_encode( array_values( $report_data['daily_transactions'] ) ); ?>,
                        borderColor: 'rgb(75, 192, 192)',
                        backgroundColor: 'rgba(75, 192, 192, 0.1)',
                        tension: 0.1
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
            
            // Gráfico de métodos de pago
            var ctx2 = document.getElementById('paymentMethodsChart').getContext('2d');
            new Chart(ctx2, {
                type: 'doughnut',
                data: {
                    labels: <?php echo json_encode( array_keys( $report_data['payment_methods'] ) ); ?>,
                    datasets: [{
                        data: <?php echo json_encode( array_values( $report_data['payment_methods'] ) ); ?>,
                        backgroundColor: [
                            '#FF6384',
                            '#36A2EB', 
                            '#FFCE56',
                            '#4BC0C0',
                            '#9966FF'
                        ]
                    }]
                },
                options: {
                    responsive: true
                }
            });
        });
        </script>
        <?php
    }
    
    /**
     * Renderizar página de herramientas
     */
    public function render_tools_page() {
        ?>
        <div class="wrap">
            <h1><?php _e( 'Herramientas del Sistema', 'woocommerce-megasoft-gateway-universal' ); ?></h1>
            
            <div class="megasoft-tools-grid">
                <!-- Prueba de Conexión -->
                <div class="tool-card">
                    <h3><?php _e( 'Prueba de Conexión', 'woocommerce-megasoft-gateway-universal' ); ?></h3>
                    <p><?php _e( 'Verifica la conectividad con los servidores de Mega Soft.', 'woocommerce-megasoft-gateway-universal' ); ?></p>
                    <button class="button button-primary" onclick="megaSoftTestConnection()">
                        <?php _e( 'Probar Conexión', 'woocommerce-megasoft-gateway-universal' ); ?>
                    </button>
                    <div id="connection-result"></div>
                </div>
                
                <!-- Sincronización de Transacciones -->
                <div class="tool-card">
                    <h3><?php _e( 'Sincronización Manual', 'woocommerce-megasoft-gateway-universal' ); ?></h3>
                    <p><?php _e( 'Sincroniza manualmente el estado de transacciones pendientes.', 'woocommerce-megasoft-gateway-universal' ); ?></p>
                    <button class="button" onclick="megaSoftSyncTransactions()">
                        <?php _e( 'Sincronizar Ahora', 'woocommerce-megasoft-gateway-universal' ); ?>
                    </button>
                    <div id="sync-result"></div>
                </div>
                
                <!-- Limpieza de Datos -->
                <div class="tool-card">
                    <h3><?php _e( 'Limpieza de Datos', 'woocommerce-megasoft-gateway-universal' ); ?></h3>
                    <p><?php _e( 'Limpia logs antiguos y datos innecesarios.', 'woocommerce-megasoft-gateway-universal' ); ?></p>
                    <button class="button" onclick="megaSoftCleanupData()">
                        <?php _e( 'Limpiar Datos Antiguos', 'woocommerce-megasoft-gateway-universal' ); ?>
                    </button>
                    <div id="cleanup-result"></div>
                </div>
                
                <!-- Exportar Configuración -->
                <div class="tool-card">
                    <h3><?php _e( 'Exportar/Importar', 'woocommerce-megasoft-gateway-universal' ); ?></h3>
                    <p><?php _e( 'Exporta o importa la configuración del gateway.', 'woocommerce-megasoft-gateway-universal' ); ?></p>
                    <button class="button" onclick="megaSoftExportConfig()">
                        <?php _e( 'Exportar Configuración', 'woocommerce-megasoft-gateway-universal' ); ?>
                    </button>
                    <button class="button" onclick="megaSoftImportConfig()">
                        <?php _e( 'Importar Configuración', 'woocommerce-megasoft-gateway-universal' ); ?>
                    </button>
                </div>
                
                <!-- Información del Sistema -->
                <div class="tool-card">
                    <h3><?php _e( 'Información del Sistema', 'woocommerce-megasoft-gateway-universal' ); ?></h3>
                    <p><?php _e( 'Muestra información técnica para soporte.', 'woocommerce-megasoft-gateway-universal' ); ?></p>
                    <button class="button" onclick="megaSoftShowSystemInfo()">
                        <?php _e( 'Ver Información', 'woocommerce-megasoft-gateway-universal' ); ?>
                    </button>
                    <div id="system-info"></div>
                </div>
                
                <!-- Generador de Pruebas -->
                <div class="tool-card">
                    <h3><?php _e( 'Generador de Pruebas', 'woocommerce-megasoft-gateway-universal' ); ?></h3>
                    <p><?php _e( 'Genera transacciones de prueba para testing.', 'woocommerce-megasoft-gateway-universal' ); ?></p>
                    <button class="button" onclick="megaSoftGenerateTestData()">
                        <?php _e( 'Generar Datos de Prueba', 'woocommerce-megasoft-gateway-universal' ); ?>
                    </button>
                    <div id="test-data-result"></div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Manejar peticiones AJAX del admin
     */
    public function handle_admin_ajax() {
        check_ajax_referer( 'megasoft_admin', 'nonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Permisos insuficientes', 'woocommerce-megasoft-gateway-universal' ) );
        }
        
        $action = sanitize_text_field( $_POST['admin_action'] ?? '' );
        
        switch ( $action ) {
            case 'test_connection':
                $this->ajax_test_connection();
                break;
                
            case 'sync_transactions':
                $this->ajax_sync_transactions();
                break;
                
            case 'cleanup_logs':
                $this->ajax_cleanup_logs();
                break;
                
            case 'export_logs':
                $this->ajax_export_logs();
                break;
                
            case 'view_transaction':
                $this->ajax_view_transaction();
                break;
                
            case 'system_info':
                $this->ajax_system_info();
                break;
                
            default:
                wp_send_json_error( __( 'Acción no válida', 'woocommerce-megasoft-gateway-universal' ) );
        }
    }
    
    private function ajax_test_connection() {
        $gateway = new WC_Gateway_MegaSoft_Universal();
        $result = $gateway->api->test_connection();
        
        if ( $result['success'] ) {
            wp_send_json_success( array(
                'message' => __( 'Conexión exitosa con Mega Soft', 'woocommerce-megasoft-gateway-universal' ),
                'details' => $result
            ) );
        } else {
            wp_send_json_error( array(
                'message' => __( 'Error de conexión', 'woocommerce-megasoft-gateway-universal' ),
                'details' => $result
            ) );
        }
    }
    
    private function ajax_sync_transactions() {
        $synced = 0;
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'megasoft_transactions';
        $pending_transactions = $wpdb->get_results(
            "SELECT * FROM {$table_name} 
             WHERE status = 'pending' 
             AND control_number IS NOT NULL 
             AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
             LIMIT 20"
        );
        
        $gateway = new WC_Gateway_MegaSoft_Universal();
        
        foreach ( $pending_transactions as $transaction ) {
            $result = $gateway->api->query_transaction_status( $transaction->control_number );
            
            if ( $result && $result['approved'] !== null ) {
                $order = wc_get_order( $transaction->order_id );
                if ( $order ) {
                    $gateway->process_transaction_result( $order, $result );
                    $synced++;
                }
            }
        }
        
        wp_send_json_success( array(
            'message' => sprintf( __( '%d transacciones sincronizadas', 'woocommerce-megasoft-gateway-universal' ), $synced ),
            'synced' => $synced
        ) );
    }
    
    private function ajax_cleanup_logs() {
        $deleted = $this->logger->clear_old_logs( 30 );
        
        wp_send_json_success( array(
            'message' => sprintf( __( '%d logs eliminados', 'woocommerce-megasoft-gateway-universal' ), $deleted ),
            'deleted' => $deleted
        ) );
    }
    
    private function ajax_export_logs() {
        $start_date = sanitize_text_field( $_POST['start_date'] ?? date( 'Y-m-01' ) );
        $end_date = sanitize_text_field( $_POST['end_date'] ?? date( 'Y-m-d' ) );
        
        $export = $this->logger->export_logs( $start_date, $end_date );
        
        wp_send_json_success( array(
            'message' => sprintf( __( 'Logs exportados: %d registros', 'woocommerce-megasoft-gateway-universal' ), $export['count'] ),
            'download_url' => $export['url'],
            'filename' => $export['filename']
        ) );
    }
    
    private function ajax_view_transaction() {
        $transaction_id = intval( $_POST['transaction_id'] ?? 0 );
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'megasoft_transactions';
        
        $transaction = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE id = %d",
            $transaction_id
        ) );
        
        if ( ! $transaction ) {
            wp_send_json_error( __( 'Transacción no encontrada', 'woocommerce-megasoft-gateway-universal' ) );
        }
        
        $order = wc_get_order( $transaction->order_id );
        $response_data = json_decode( $transaction->response_data, true );
        
        wp_send_json_success( array(
            'transaction' => $transaction,
            'order' => $order ? array(
                'id' => $order->get_id(),
                'status' => $order->get_status(),
                'total' => $order->get_total(),
                'customer' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                'email' => $order->get_billing_email()
            ) : null,
            'response_data' => $response_data
        ) );
    }
    
    private function ajax_system_info() {
        $info = array(
            'wordpress_version' => get_bloginfo( 'version' ),
            'woocommerce_version' => WC()->version,
            'plugin_version' => MEGASOFT_PLUGIN_VERSION,
            'php_version' => phpversion(),
            'mysql_version' => $GLOBALS['wpdb']->db_version(),
            'server_info' => $_SERVER['SERVER_SOFTWARE'] ?? 'N/A',
            'memory_limit' => ini_get( 'memory_limit' ),
            'max_execution_time' => ini_get( 'max_execution_time' ),
            'ssl_enabled' => is_ssl(),
            'curl_version' => curl_version()['version'] ?? 'N/A',
            'openssl_version' => OPENSSL_VERSION_TEXT,
        );
        
        wp_send_json_success( $info );
    }
    
    /**
     * Métodos auxiliares para obtener datos
     */
    private function get_dashboard_stats() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'megasoft_transactions';
        
        $stats = array();
        
        // Estadísticas por período
        $periods = array(
            'today' => 'DATE(created_at) = CURDATE()',
            'week' => 'created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)',
            'month' => 'created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)',
            'total' => '1=1'
        );
        
        foreach ( $periods as $period => $where_clause ) {
            $result = $wpdb->get_row(
                "SELECT COUNT(*) as count, COALESCE(SUM(amount), 0) as amount 
                 FROM {$table_name} 
                 WHERE {$where_clause} AND status = 'approved'"
            );
            
            $stats[$period] = array(
                'count' => intval( $result->count ),
                'amount' => floatval( $result->amount )
            );
        }
        
        return $stats;
    }
    
    private function get_recent_transactions( $limit = 10 ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'megasoft_transactions';
        
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table_name} 
             ORDER BY created_at DESC 
             LIMIT %d",
            $limit
        ) );
    }
    
    private function check_gateway_status() {
        $gateway = new WC_Gateway_MegaSoft_Universal();
        $issues = array();
        $status = 'healthy';
        
        // Verificar configuración
        if ( empty( $gateway->get_option( 'cod_afiliacion' ) ) ) {
            $issues[] = __( 'Código de afiliación no configurado', 'woocommerce-megasoft-gateway-universal' );
            $status = 'error';
        }
        
        if ( empty( $gateway->get_option( 'api_user' ) ) || empty( $gateway->get_option( 'api_password' ) ) ) {
            $issues[] = __( 'Credenciales API no configuradas', 'woocommerce-megasoft-gateway-universal' );
            $status = 'error';
        }
        
        if ( ! is_ssl() && ! $gateway->testmode ) {
            $issues[] = __( 'SSL requerido para producción', 'woocommerce-megasoft-gateway-universal' );
            $status = 'warning';
        }
        
        if ( $gateway->testmode ) {
            $issues[] = __( 'Gateway en modo de prueba', 'woocommerce-megasoft-gateway-universal' );
            $status = 'warning';
        }
        
        $message = $status === 'healthy' ? 
            __( 'Gateway funcionando correctamente', 'woocommerce-megasoft-gateway-universal' ) :
            __( 'Se encontraron problemas de configuración', 'woocommerce-megasoft-gateway-universal' );
        
        return array(
            'status' => $status,
            'message' => $message,
            'issues' => $issues
        );
    }
    
    private function get_transactions( $per_page, $offset, $status_filter = '', $search = '' ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'megasoft_transactions';
        
        $where_conditions = array( '1=1' );
        $where_values = array();
        
        if ( $status_filter ) {
            $where_conditions[] = 'status = %s';
            $where_values[] = $status_filter;
        }
        
        if ( $search ) {
            $where_conditions[] = '(control_number LIKE %s OR client_name LIKE %s OR order_id = %d)';
            $where_values[] = '%' . $search . '%';
            $where_values[] = '%' . $search . '%';
            $where_values[] = intval( $search );
        }
        
        $where_clause = implode( ' AND ', $where_conditions );
        $where_values[] = $per_page;
        $where_values[] = $offset;
        
        $query = "SELECT * FROM {$table_name} 
                  WHERE {$where_clause} 
                  ORDER BY created_at DESC 
                  LIMIT %d OFFSET %d";
        
        return $wpdb->get_results( $wpdb->prepare( $query, $where_values ) );
    }
    
    private function get_transactions_count( $status_filter = '', $search = '' ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'megasoft_transactions';
        
        $where_conditions = array( '1=1' );
        $where_values = array();
        
        if ( $status_filter ) {
            $where_conditions[] = 'status = %s';
            $where_values[] = $status_filter;
        }
        
        if ( $search ) {
            $where_conditions[] = '(control_number LIKE %s OR client_name LIKE %s OR order_id = %d)';
            $where_values[] = '%' . $search . '%';
            $where_values[] = '%' . $search . '%';
            $where_values[] = intval( $search );
        }
        
        $where_clause = implode( ' AND ', $where_conditions );
        $query = "SELECT COUNT(*) FROM {$table_name} WHERE {$where_clause}";
        
        if ( ! empty( $where_values ) ) {
            return $wpdb->get_var( $wpdb->prepare( $query, $where_values ) );
        } else {
            return $wpdb->get_var( $query );
        }
    }
    
    private function get_report_data( $period ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'megasoft_transactions';
        
        // Definir fechas según período
        $where_clause = $this->get_period_where_clause( $period );
        
        // Estadísticas básicas
        $basic_stats = $wpdb->get_row(
            "SELECT 
                COUNT(*) as total_transactions,
                COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved_transactions,
                COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed_transactions,
                COALESCE(SUM(CASE WHEN status = 'approved' THEN amount END), 0) as total_amount
             FROM {$table_name} 
             WHERE {$where_clause}"
        );
        
        $approval_rate = $basic_stats->total_transactions > 0 ? 
            round( ( $basic_stats->approved_transactions / $basic_stats->total_transactions ) * 100, 2 ) : 0;
        
        $failure_rate = 100 - $approval_rate;
        
        // Transacciones por día
        $daily_transactions = $wpdb->get_results(
            "SELECT DATE(created_at) as date, COUNT(*) as count 
             FROM {$table_name} 
             WHERE {$where_clause} 
             GROUP BY DATE(created_at) 
             ORDER BY date"
        );
        
        $daily_data = array();
        foreach ( $daily_transactions as $day ) {
            $daily_data[ $day->date ] = intval( $day->count );
        }
        
        // Métodos de pago
        $payment_methods = $wpdb->get_results(
            "SELECT 
                COALESCE(payment_method, 'Desconocido') as method, 
                COUNT(*) as count 
             FROM {$table_name} 
             WHERE {$where_clause} AND status = 'approved' 
             GROUP BY payment_method 
             ORDER BY count DESC"
        );
        
        $payment_methods_data = array();
        foreach ( $payment_methods as $method ) {
            $payment_methods_data[ $method->method ] = intval( $method->count );
        }
        
        // Top transacciones
        $top_transactions = $wpdb->get_results(
            "SELECT * FROM {$table_name} 
             WHERE {$where_clause} AND status = 'approved' 
             ORDER BY amount DESC 
             LIMIT 10"
        );
        
        return array(
            'total_transactions' => intval( $basic_stats->total_transactions ),
            'approved_transactions' => intval( $basic_stats->approved_transactions ),
            'failed_transactions' => intval( $basic_stats->failed_transactions ),
            'total_amount' => floatval( $basic_stats->total_amount ),
            'approval_rate' => $approval_rate,
            'failure_rate' => $failure_rate,
            'daily_transactions' => $daily_data,
            'payment_methods' => $payment_methods_data,
            'top_transactions' => $top_transactions
        );
    }
    
    private function get_period_where_clause( $period ) {
        switch ( $period ) {
            case 'today':
                return 'DATE(created_at) = CURDATE()';
            case 'yesterday':
                return 'DATE(created_at) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)';
            case 'last_7_days':
                return 'created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)';
            case 'last_30_days':
                return 'created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
            case 'this_month':
                return 'YEAR(created_at) = YEAR(NOW()) AND MONTH(created_at) = MONTH(NOW())';
            case 'last_month':
                return 'YEAR(created_at) = YEAR(NOW() - INTERVAL 1 MONTH) AND MONTH(created_at) = MONTH(NOW() - INTERVAL 1 MONTH)';
            default:
                return 'created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)';
        }
    }
    
    /**
     * Agregar columnas personalizadas en lista de órdenes
     */
    public function add_order_columns( $columns ) {
        $new_columns = array();
        
        foreach ( $columns as $key => $column ) {
            $new_columns[ $key ] = $column;
            
            if ( $key === 'order_status' ) {
                $new_columns['megasoft_payment'] = __( 'Mega Soft', 'woocommerce-megasoft-gateway-universal' );
            }
        }
        
        return $new_columns;
    }
    
    public function render_order_columns( $column, $post_id ) {
        if ( $column === 'megasoft_payment' ) {
            $order = wc_get_order( $post_id );
            
            if ( $order && $order->get_payment_method() === 'megasoft_gateway_universal' ) {
                $control_number = $order->get_meta( '_megasoft_control_number' );
                $auth_id = $order->get_meta( '_megasoft_auth_id' );
                
                if ( $control_number ) {
                    echo '<div class="megasoft-order-info">';
                    echo '<small>Control: <code>' . esc_html( $control_number ) . '</code></small><br>';
                    if ( $auth_id ) {
                        echo '<small>Auth: <code>' . esc_html( $auth_id ) . '</code></small>';
                    }
                    echo '</div>';
                }
            } else {
                echo '-';
            }
        }
    }
    
    /**
     * Agregar meta box en edición de órdenes
     */
    public function add_order_meta_boxes() {
        add_meta_box(
            'megasoft_order_details',
            __( 'Detalles Mega Soft', 'woocommerce-megasoft-gateway-universal' ),
            array( $this, 'render_order_meta_box' ),
            'shop_order',
            'side',
            'default'
        );
    }
    
    public function render_order_meta_box( $post ) {
        $order = wc_get_order( $post->ID );
        
        if ( ! $order || $order->get_payment_method() !== 'megasoft_gateway_universal' ) {
            echo '<p>' . __( 'Esta orden no utilizó Mega Soft Gateway.', 'woocommerce-megasoft-gateway-universal' ) . '</p>';
            return;
        }
        
        $control_number = $order->get_meta( '_megasoft_control_number' );
        $auth_id = $order->get_meta( '_megasoft_auth_id' );
        $referencia = $order->get_meta( '_megasoft_referencia' );
        $medio = $order->get_meta( '_megasoft_medio' );
        
        ?>
        <div class="megasoft-order-meta">
            <p><strong><?php _e( 'Número de Control:', 'woocommerce-megasoft-gateway-universal' ); ?></strong></p>
            <p><code><?php echo esc_html( $control_number ?: 'N/A' ); ?></code></p>
            
            <?php if ( $auth_id ) : ?>
                <p><strong><?php _e( 'ID de Autorización:', 'woocommerce-megasoft-gateway-universal' ); ?></strong></p>
                <p><code><?php echo esc_html( $auth_id ); ?></code></p>
            <?php endif; ?>
            
            <?php if ( $referencia ) : ?>
                <p><strong><?php _e( 'Referencia:', 'woocommerce-megasoft-gateway-universal' ); ?></strong></p>
                <p><code><?php echo esc_html( $referencia ); ?></code></p>
            <?php endif; ?>
            
            <?php if ( $medio ) : ?>
                <p><strong><?php _e( 'Método de Pago:', 'woocommerce-megasoft-gateway-universal' ); ?></strong></p>
                <p><?php echo esc_html( $medio ); ?></p>
            <?php endif; ?>
            
            <div class="megasoft-actions">
                <?php if ( $control_number && $order->get_status() === 'pending' ) : ?>
                    <button type="button" class="button" onclick="megaSoftSyncTransaction('<?php echo esc_js( $control_number ); ?>')">
                        <?php _e( 'Sincronizar Estado', 'woocommerce-megasoft-gateway-universal' ); ?>
                    </button>
                <?php endif; ?>
                
                <button type="button" class="button" onclick="megaSoftViewOrderLogs(<?php echo $post->ID; ?>)">
                    <?php _e( 'Ver Logs', 'woocommerce-megasoft-gateway-universal' ); ?>
                </button>
            </div>
        </div>
        <?php
    }
    
    /**
     * Mostrar notificaciones admin
     */
    public function show_admin_notices() {
        // Solo mostrar en páginas relacionadas con MegaSoft
        $screen = get_current_screen();
        if ( ! $screen || ( strpos( $screen->id, 'megasoft' ) === false && $screen->id !== 'shop_order' ) ) {
            return;
        }
        
        // Verificar si hay transacciones pendientes hace más de 24 horas
        global $wpdb;
        $table_name = $wpdb->prefix . 'megasoft_transactions';
        
        $old_pending = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$table_name} 
             WHERE status = 'pending' 
             AND created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)"
        );
        
        if ( $old_pending > 0 ) {
            ?>
            <div class="notice notice-warning is-dismissible">
                <p>
                    <?php printf(
                        __( 'Hay %d transacciones pendientes hace más de 24 horas. <a href="%s">Revisar transacciones</a>', 'woocommerce-megasoft-gateway-universal' ),
                        $old_pending,
                        admin_url( 'admin.php?page=megasoft-transactions&status=pending' )
                    ); ?>
                </p>
            </div>
            <?php
        }
    }
}