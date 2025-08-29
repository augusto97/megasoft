/**
 * MegaSoft Gateway Admin JavaScript
 */

(function($) {
    'use strict';

    // Objeto principal
    const MegaSoftAdmin = {
        init: function() {
            this.bindEvents();
            this.initTooltips();
            this.checkNotifications();
        },

        bindEvents: function() {
            // Eventos generales
            $(document).on('click', '.megasoft-notification .close', this.closeNotification);
            
            // Auto-refresh para dashboard cada 5 minutos
            if ($('.megasoft-dashboard').length) {
                setInterval(this.refreshDashboard, 300000);
            }
            
            // Confirmaciones para acciones críticas
            $(document).on('click', '[data-confirm]', this.confirmAction);
        },

        initTooltips: function() {
            // Inicializar tooltips si están disponibles
            if (typeof $.fn.tooltip !== 'undefined') {
                $('[data-tooltip]').tooltip();
            }
        },

        checkNotifications: function() {
            // Verificar notificaciones pendientes cada minuto
            setInterval(function() {
                $.post(ajaxurl, {
                    action: 'megasoft_check_notifications',
                    nonce: megasoft_admin.nonce
                }, function(response) {
                    if (response.success && response.data.notifications) {
                        response.data.notifications.forEach(function(notification) {
                            MegaSoftAdmin.showNotification(notification.message, notification.type);
                        });
                    }
                });
            }, 60000);
        },

        showNotification: function(message, type = 'success') {
            const notification = $('<div class="megasoft-notification ' + type + '">')
                .html(message + '<button type="button" class="close">&times;</button>')
                .appendTo('body');

            // Mostrar con animación
            setTimeout(() => notification.addClass('show'), 100);

            // Auto-ocultar después de 5 segundos
            setTimeout(() => this.hideNotification(notification), 5000);
        },

        hideNotification: function(notification) {
            notification.removeClass('show');
            setTimeout(() => notification.remove(), 300);
        },

        closeNotification: function() {
            MegaSoftAdmin.hideNotification($(this).parent());
        },

        confirmAction: function(e) {
            const message = $(this).data('confirm');
            if (!confirm(message)) {
                e.preventDefault();
                return false;
            }
        },

        setLoading: function(element, loading = true) {
            if (loading) {
                element.addClass('megasoft-loading').prop('disabled', true);
            } else {
                element.removeClass('megasoft-loading').prop('disabled', false);
            }
        },

        refreshDashboard: function() {
            // Actualizar estadísticas del dashboard
            $.post(ajaxurl, {
                action: 'megasoft_admin_action',
                admin_action: 'refresh_dashboard',
                nonce: megasoft_admin.nonce
            }, function(response) {
                if (response.success) {
                    location.reload();
                }
            });
        },

        makeRequest: function(action, data = {}, callback = null) {
            const requestData = {
                action: 'megasoft_admin_action',
                admin_action: action,
                nonce: megasoft_admin.nonce,
                ...data
            };

            return $.post(ajaxurl, requestData, function(response) {
                if (callback) {
                    callback(response);
                }
                
                if (response.success) {
                    MegaSoftAdmin.showNotification(response.data.message || megasoft_admin.strings.success);
                } else {
                    MegaSoftAdmin.showNotification(response.data.message || megasoft_admin.strings.error, 'error');
                }
            }).fail(function() {
                MegaSoftAdmin.showNotification(megasoft_admin.strings.error, 'error');
                if (callback) {
                    callback({success: false, data: {message: megasoft_admin.strings.error}});
                }
            });
        }
    };

    // Funciones globales para compatibilidad
    window.megaSoftTestConnection = function() {
        const button = $('button:contains("Probar Conexión")');
        MegaSoftAdmin.setLoading(button, true);
        
        MegaSoftAdmin.makeRequest('test_connection', {}, function(response) {
            MegaSoftAdmin.setLoading(button, false);
            
            if (response.success) {
                $('#connection-result').html(`
                    <div style="margin-top: 10px; padding: 10px; background: #d1eddd; border-left: 4px solid #00a32a;">
                        <strong>✓ Conexión exitosa</strong><br>
                        <small>Control de prueba: ${response.data.details.control_number || 'N/A'}</small>
                    </div>
                `);
            } else {
                $('#connection-result').html(`
                    <div style="margin-top: 10px; padding: 10px; background: #fbeaea; border-left: 4px solid #d63638;">
                        <strong>✗ Error de conexión</strong><br>
                        <small>${response.data.message}</small>
                    </div>
                `);
            }
        });
    };

    window.megaSoftSyncTransactions = function() {
        if (!confirm(megasoft_admin.strings.confirm_sync)) {
            return;
        }

        const button = $('button:contains("Sincronizar")');
        MegaSoftAdmin.setLoading(button, true);
        
        MegaSoftAdmin.makeRequest('sync_transactions', {}, function(response) {
            MegaSoftAdmin.setLoading(button, false);
            
            if (response.success) {
                $('#sync-result').html(`
                    <div style="margin-top: 10px; padding: 10px; background: #d1eddd; border-left: 4px solid #00a32a;">
                        <strong>✓ Sincronización completada</strong><br>
                        <small>${response.data.synced} transacciones actualizadas</small>
                    </div>
                `);
                
                // Recargar página después de 2 segundos
                setTimeout(() => location.reload(), 2000);
            }
        });
    };

    window.megaSoftSyncTransaction = function(controlNumber) {
        const button = event.target;
        MegaSoftAdmin.setLoading($(button), true);
        
        MegaSoftAdmin.makeRequest('sync_single_transaction', { control_number: controlNumber }, function(response) {
            MegaSoftAdmin.setLoading($(button), false);
            
            if (response.success) {
                // Actualizar la fila de la transacción
                const row = $(button).closest('tr');
                row.find('.status-badge').removeClass().addClass('status-badge status-' + response.data.status);
                row.find('.status-badge').text(response.data.status.charAt(0).toUpperCase() + response.data.status.slice(1));
                
                MegaSoftAdmin.showNotification('Transacción sincronizada correctamente');
            }
        });
    };

    window.megaSoftCleanupLogs = function() {
        if (!confirm(megasoft_admin.strings.confirm_cleanup)) {
            return;
        }

        const button = $('button:contains("Limpiar")');
        MegaSoftAdmin.setLoading(button, true);
        
        MegaSoftAdmin.makeRequest('cleanup_logs', {}, function(response) {
            MegaSoftAdmin.setLoading(button, false);
            
            if (response.success) {
                $('#cleanup-result').html(`
                    <div style="margin-top: 10px; padding: 10px; background: #d1eddd; border-left: 4px solid #00a32a;">
                        <strong>✓ Limpieza completada</strong><br>
                        <small>${response.data.deleted} logs eliminados</small>
                    </div>
                `);
            }
        });
    };

    window.megaSoftCleanupData = function() {
        if (!confirm('¿Eliminar todos los datos antiguos? Esta acción no se puede deshacer.')) {
            return;
        }

        const button = event.target;
        MegaSoftAdmin.setLoading($(button), true);
        
        MegaSoftAdmin.makeRequest('cleanup_data', {}, function(response) {
            MegaSoftAdmin.setLoading($(button), false);
            
            if (response.success) {
                $('#cleanup-result').html(`
                    <div style="margin-top: 10px; padding: 10px; background: #d1eddd; border-left: 4px solid #00a32a;">
                        <strong>✓ Datos limpiados</strong><br>
                        <small>Logs: ${response.data.logs_deleted}, Webhooks: ${response.data.webhooks_deleted}</small>
                    </div>
                `);
            }
        });
    };

    window.megaSoftExportLogs = function() {
        const startDate = prompt('Fecha de inicio (YYYY-MM-DD):', new Date(Date.now() - 30*24*60*60*1000).toISOString().split('T')[0]);
        const endDate = prompt('Fecha de fin (YYYY-MM-DD):', new Date().toISOString().split('T')[0]);
        
        if (!startDate || !endDate) {
            return;
        }

        const button = event.target;
        MegaSoftAdmin.setLoading($(button), true);
        
        MegaSoftAdmin.makeRequest('export_logs', { start_date: startDate, end_date: endDate }, function(response) {
            MegaSoftAdmin.setLoading($(button), false);
            
            if (response.success) {
                // Crear enlace de descarga
                const link = document.createElement('a');
                link.href = response.data.download_url;
                link.download = response.data.filename;
                link.click();
                
                MegaSoftAdmin.showNotification(`${response.data.count} logs exportados`);
            }
        });
    };

    window.megaSoftExportConfig = function() {
        MegaSoftAdmin.makeRequest('export_config', {}, function(response) {
            if (response.success) {
                // Crear archivo de descarga
                const dataStr = JSON.stringify(response.data.config, null, 2);
                const dataBlob = new Blob([dataStr], {type: 'application/json'});
                
                const link = document.createElement('a');
                link.href = URL.createObjectURL(dataBlob);
                link.download = 'megasoft-config-' + new Date().toISOString().split('T')[0] + '.json';
                link.click();
                
                MegaSoftAdmin.showNotification('Configuración exportada');
            }
        });
    };

    window.megaSoftImportConfig = function() {
        const input = document.createElement('input');
        input.type = 'file';
        input.accept = '.json';
        
        input.onchange = function() {
            const file = input.files[0];
            if (!file) return;
            
            const reader = new FileReader();
            reader.onload = function(e) {
                try {
                    const config = JSON.parse(e.target.result);
                    
                    if (!confirm('¿Importar esta configuración? Se sobrescribirá la configuración actual.')) {
                        return;
                    }
                    
                    MegaSoftAdmin.makeRequest('import_config', { config: config }, function(response) {
                        if (response.success) {
                            MegaSoftAdmin.showNotification('Configuración importada. Recargando página...');
                            setTimeout(() => location.reload(), 2000);
                        }
                    });
                } catch (err) {
                    MegaSoftAdmin.showNotification('Archivo de configuración inválido', 'error');
                }
            };
            reader.readAsText(file);
        };
        
        input.click();
    };

    window.megaSoftShowSystemInfo = function() {
        const button = event.target;
        MegaSoftAdmin.setLoading($(button), true);
        
        MegaSoftAdmin.makeRequest('system_info', {}, function(response) {
            MegaSoftAdmin.setLoading($(button), false);
            
            if (response.success) {
                const info = response.data;
                let html = '<div style="margin-top: 10px; padding: 15px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">';
                html += '<h4>Información del Sistema</h4>';
                html += '<table style="width: 100%; font-size: 12px;">';
                
                Object.keys(info).forEach(key => {
                    const label = key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
                    html += `<tr><td><strong>${label}:</strong></td><td>${info[key]}</td></tr>`;
                });
                
                html += '</table>';
                html += '<br><button type="button" onclick="megaSoftCopySystemInfo()" class="button">Copiar al Portapapeles</button>';
                html += '</div>';
                
                $('#system-info').html(html);
                
                // Guardar info para copiar
                window.megaSoftSystemInfoData = JSON.stringify(info, null, 2);
            }
        });
    };

    window.megaSoftCopySystemInfo = function() {
        if (navigator.clipboard && window.megaSoftSystemInfoData) {
            navigator.clipboard.writeText(window.megaSoftSystemInfoData).then(() => {
                MegaSoftAdmin.showNotification('Información copiada al portapapeles');
            });
        } else {
            MegaSoftAdmin.showNotification('No se pudo copiar la información', 'error');
        }
    };

    window.megaSoftGenerateTestData = function() {
        const count = parseInt(prompt('¿Cuántas transacciones de prueba generar? (1-10):', '3'));
        
        if (!count || count < 1 || count > 10) {
            return;
        }

        const button = event.target;
        MegaSoftAdmin.setLoading($(button), true);
        
        MegaSoftAdmin.makeRequest('generate_test_data', { count: count }, function(response) {
            MegaSoftAdmin.setLoading($(button), false);
            
            if (response.success) {
                $('#test-data-result').html(`
                    <div style="margin-top: 10px; padding: 10px; background: #d1eddd; border-left: 4px solid #00a32a;">
                        <strong>✓ Datos de prueba generados</strong><br>
                        <small>${response.data.generated} transacciones creadas</small>
                    </div>
                `);
            }
        });
    };

    window.megaSoftViewTransaction = function(transactionId) {
        MegaSoftAdmin.makeRequest('view_transaction', { transaction_id: transactionId }, function(response) {
            if (response.success) {
                const data = response.data;
                
                // Crear modal con información
                const modal = $(`
                    <div class="megasoft-modal-backdrop" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 10000;">
                        <div class="megasoft-modal" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.3); max-width: 600px; width: 90%; max-height: 80%; overflow-y: auto;">
                            <div class="megasoft-modal-header" style="padding: 20px; border-bottom: 1px solid #ddd;">
                                <h3>Detalles de Transacción #${data.transaction.id}</h3>
                                <button type="button" class="close" style="float: right; background: none; border: none; font-size: 20px; cursor: pointer;">&times;</button>
                            </div>
                            <div class="megasoft-modal-content" style="padding: 20px;">
                                <table style="width: 100%; border-collapse: collapse;">
                                    <tr><td><strong>Orden:</strong></td><td>#${data.transaction.order_id}</td></tr>
                                    <tr><td><strong>Control:</strong></td><td><code>${data.transaction.control_number || 'N/A'}</code></td></tr>
                                    <tr><td><strong>Estado:</strong></td><td><span class="status-badge status-${data.transaction.status}">${data.transaction.status}</span></td></tr>
                                    <tr><td><strong>Monto:</strong></td><td>${parseFloat(data.transaction.amount).toLocaleString('es-VE', {style: 'currency', currency: 'VES'})}</td></tr>
                                    <tr><td><strong>Cliente:</strong></td><td>${data.transaction.client_name}</td></tr>
                                    <tr><td><strong>Documento:</strong></td><td>${data.transaction.document_type}-${data.transaction.document_number}</td></tr>
                                    <tr><td><strong>Método:</strong></td><td>${data.transaction.payment_method || 'N/A'}</td></tr>
                                    <tr><td><strong>Auth ID:</strong></td><td>${data.transaction.auth_id || 'N/A'}</td></tr>
                                    <tr><td><strong>Referencia:</strong></td><td>${data.transaction.reference || 'N/A'}</td></tr>
                                    <tr><td><strong>Creado:</strong></td><td>${new Date(data.transaction.created_at).toLocaleString()}</td></tr>
                                </table>
                                
                                ${data.response_data ? `
                                    <h4 style="margin-top: 20px;">Respuesta de la API</h4>
                                    <pre style="background: #f5f5f5; padding: 10px; border-radius: 4px; overflow-x: auto; font-size: 12px;">${JSON.stringify(data.response_data, null, 2)}</pre>
                                ` : ''}
                            </div>
                        </div>
                    </div>
                `);
                
                $('body').append(modal);
                
                // Cerrar modal
                modal.find('.close, .megasoft-modal-backdrop').click(function(e) {
                    if (e.target === this) {
                        modal.remove();
                    }
                });
            }
        });
    };

    window.megaSoftViewOrderLogs = function(orderId) {
        MegaSoftAdmin.makeRequest('view_order_logs', { order_id: orderId }, function(response) {
            if (response.success) {
                const logs = response.data.logs;
                
                let html = '<h4 style="margin-top: 20px;">Logs de la Orden #' + orderId + '</h4>';
                
                if (logs.length === 0) {
                    html += '<p>No hay logs para esta orden.</p>';
                } else {
                    html += '<div style="max-height: 300px; overflow-y: auto; border: 1px solid #ddd; border-radius: 4px;">';
                    html += '<table style="width: 100%; border-collapse: collapse;">';
                    html += '<thead style="background: #f5f5f5;"><tr><th style="padding: 8px; text-align: left; border-bottom: 1px solid #ddd;">Fecha</th><th style="padding: 8px; text-align: left; border-bottom: 1px solid #ddd;">Nivel</th><th style="padding: 8px; text-align: left; border-bottom: 1px solid #ddd;">Mensaje</th></tr></thead>';
                    html += '<tbody>';
                    
                    logs.forEach(log => {
                        html += `
                            <tr>
                                <td style="padding: 8px; border-bottom: 1px solid #eee; font-size: 12px;">${new Date(log.created_at).toLocaleString()}</td>
                                <td style="padding: 8px; border-bottom: 1px solid #eee;"><span class="log-level log-level-${log.level.toLowerCase()}">${log.level}</span></td>
                                <td style="padding: 8px; border-bottom: 1px solid #eee; font-size: 13px;">${log.message}</td>
                            </tr>
                        `;
                    });
                    
                    html += '</tbody></table></div>';
                }
                
                // Crear modal
                const modal = $(`
                    <div class="megasoft-modal-backdrop" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 10000;">
                        <div class="megasoft-modal" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.3); max-width: 800px; width: 90%; max-height: 80%; overflow-y: auto;">
                            <div class="megasoft-modal-header" style="padding: 20px; border-bottom: 1px solid #ddd;">
                                <h3>Logs de la Orden #${orderId}</h3>
                                <button type="button" class="close" style="float: right; background: none; border: none; font-size: 20px; cursor: pointer;">&times;</button>
                            </div>
                            <div class="megasoft-modal-content" style="padding: 20px;">
                                ${html}
                            </div>
                        </div>
                    </div>
                `);
                
                $('body').append(modal);
                
                modal.find('.close, .megasoft-modal-backdrop').click(function(e) {
                    if (e.target === this) {
                        modal.remove();
                    }
                });
            }
        });
    };

    window.megaSoftToggleContext = function(logId) {
        $('#context-' + logId).toggle();
    };

    window.megaSoftEmailReceipt = function(orderId) {
        if (!confirm('¿Enviar comprobante por email al cliente?')) {
            return;
        }

        MegaSoftAdmin.makeRequest('email_receipt', { order_id: orderId }, function(response) {
            // El resultado se maneja automáticamente por makeRequest
        });
    };

    // Funciones para el checkout (frontend)
    window.megaSoftCheckout = {
        init: function() {
            $(document).on('change', '#megasoft_document_type', this.handleDocumentTypeChange);
            $(document).on('input', '#megasoft_document_number', this.handleDocumentNumberInput);
            $(document).on('change', '#megasoft_installments', this.updateInstallmentInfo);
        },

        handleDocumentTypeChange: function() {
            const type = $(this).val();
            const numberField = $('#megasoft_document_number');
            
            // Ajustar placeholder y validación según tipo
            switch(type) {
                case 'V':
                case 'E':
                case 'C':
                    numberField.attr('placeholder', 'Ej: 12345678').attr('maxlength', '10');
                    break;
                case 'J':
                case 'G':
                    numberField.attr('placeholder', 'Ej: 123456789').attr('maxlength', '10');
                    break;
                case 'P':
                    numberField.attr('placeholder', 'Ej: AB1234567').attr('maxlength', '15');
                    break;
                default:
                    numberField.attr('placeholder', '').removeAttr('maxlength');
            }
        },

        handleDocumentNumberInput: function() {
            const type = $('#megasoft_document_type').val();
            let value = $(this).val().toUpperCase();
            
            // Limpiar caracteres no permitidos según tipo
            switch(type) {
                case 'V':
                case 'E':
                case 'C':
                case 'J':
                case 'G':
                    value = value.replace(/[^0-9]/g, '');
                    break;
                case 'P':
                    value = value.replace(/[^A-Z0-9]/g, '');
                    break;
            }
            
            if ($(this).val() !== value) {
                $(this).val(value);
            }
        },

        updateInstallmentInfo: function() {
            const installments = parseInt($(this).val());
            const cartTotal = parseFloat($(this).data('cart-total') || 0);
            
            if (installments > 1 && cartTotal > 0) {
                const installmentAmount = cartTotal / installments;
                const info = `${installments} cuotas de ${installmentAmount.toLocaleString('es-VE', {style: 'currency', currency: 'VES'})}`;
                
                let infoDiv = $('.megasoft-installment-info');
                if (infoDiv.length === 0) {
                    infoDiv = $('<div class="megasoft-installment-info" style="font-size: 12px; color: #666; margin-top: 5px;"></div>');
                    $(this).after(infoDiv);
                }
                
                infoDiv.text(info);
            } else {
                $('.megasoft-installment-info').remove();
            }
        }
    };

    // Inicializar cuando el documento esté listo
    $(document).ready(function() {
        MegaSoftAdmin.init();
        
        // Inicializar checkout si estamos en esa página
        if ($('body').hasClass('woocommerce-checkout')) {
            megaSoftCheckout.init();
        }
    });

})(jQuery);