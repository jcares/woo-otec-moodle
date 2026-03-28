/**
 * PCC WooOTEC Chile - Modern Admin JS
 * Handles Wizard, SSO Test, Tabs, Media Picker, and Email Tools
 */
(function($) {
    'use strict';

    let frame;

    const PCC_Admin = {
        currentStep: 1,
        categories: [],
        teachers: [],
        courses: [],
        selectedCategories: [],
        editedCourses: [],

        init: function() {
            this.bindEvents();
            this.initTabs();
            this.initMediaPicker();
            this.initEmailTools();
            this.initTemplateTools();
            this.initAppearanceTools();
            
            // Auto-load categories if sync tab is active on page load
            if ($('.pcc-tab[data-tab="sync"]').hasClass('is-active')) {
                this.loadCategories();
            }
        },

        initAppearanceTools: function() {
            const $selector = $('#woo_otec_moodle_appearance_profile');
            if (!$selector.length) {
                return;
            }

            function renderAppearanceGroup() {
                const selected = $selector.val() || 'product';
                $('.pcc-appearance-group').removeClass('is-active').hide();
                $('.pcc-appearance-group[data-appearance-group="' + selected + '"]').addClass('is-active').show();
            }

            $(document).on('change', '#woo_otec_moodle_appearance_profile', renderAppearanceGroup);
            renderAppearanceGroup();
        },

        bindEvents: function() {
            const self = this;

            // Wizard Step 1 -> 2 (Categories -> Teachers)
            $(document).on('click', '#pcc-btn-next-1', function() {
                self.selectedCategories = [];
                $('.pcc-category-checkbox:checked').each(function() {
                    self.selectedCategories.push($(this).val());
                });

                if (self.selectedCategories.length === 0) {
                    alert('Por favor, selecciona al menos una categorÃ­a.');
                    return;
                }

                self.goToStep(2);
                self.loadTeachers();
            });

            // Wizard Step 2 -> 3 (Teachers -> Courses)
            $(document).on('click', '#pcc-btn-next-2', function() {
                self.goToStep(3);
                self.loadCourses();
            });

            // Wizard Step 3 -> 4 (Courses -> Confirmation)
            $(document).on('click', '#pcc-btn-next-3', function() {
                self.collectEditedCourses();
                self.goToStep(4);
                self.renderSummary();
            });

            // Wizard Navigation
            $(document).on('click', '#pcc-btn-prev-2', function() { self.goToStep(1); });
            $(document).on('click', '#pcc-btn-prev-3', function() { self.goToStep(2); });
            $(document).on('click', '#pcc-btn-prev-4', function() { self.goToStep(3); });

            // Execute Sync
            $(document).on('click', '#pcc-btn-execute-sync', function() {
                self.executeSync();
            });

            // SSO Test
            $(document).on('click', '#pcc-test-sso-connection', function() {
                self.testSSO();
            });

            // ZIP Generation
            $(document).on('click', '#pcc-generate-zip', function() {
                self.generateZIP();
            });
        },

        // --- Tabs Logic ---
        initTabs: function() {
            const self = this;
            const tabs = $('.pcc-tab');

            function setActiveTab(tab) {
                if (!tab || !tab.length) return;
                const target = tab.data('tab');
                if (!target) return;

                tabs.removeClass('is-active').attr('aria-selected', 'false');
                $('.pcc-tab-panel').removeClass('is-active').attr('hidden', true);

                tab.addClass('is-active').attr('aria-selected', 'true');
                const panel = $('.pcc-tab-panel[data-panel="' + target + '"]');
                if (panel.length) {
                    panel.addClass('is-active').attr('hidden', false);
                }

                // History state & Referer for WP Save
                if (window.history && window.history.replaceState) {
                    const url = new URL(window.location.href);
                    url.searchParams.set('tab', target);
                    window.history.replaceState({}, '', url.toString());
                    $('input[name="_wp_http_referer"]').val(url.toString());
                }

                // Trigger category load if needed
                if (target === 'sync' && self.categories.length === 0) {
                    self.loadCategories();
                }
            }

            $(document).on('click', '.pcc-tab', function() {
                setActiveTab($(this));
            });

            // Initial tab
            if (tabs.length) {
                const defaultTab = wooOtecMoodleAdmin && wooOtecMoodleAdmin.defaultTab ? wooOtecMoodleAdmin.defaultTab : '';
                const requestedTab = defaultTab ? $('.pcc-tab[data-tab="' + defaultTab + '"]').first() : $();
                const currentTab = requestedTab.length ? requestedTab : tabs.filter('.is-active').first();
                setActiveTab(currentTab.length ? currentTab : tabs.first());
            }
        },

        // --- Media Picker Logic ---
        initMediaPicker: function() {
            $(document).on('click', '.pcc-media-picker', function(e) {
                e.preventDefault();
                const $btn = $(this);
                const target = $btn.data('target');
                const preview = $btn.data('preview');

                if (frame) frame.off('select');

                frame = wp.media({
                    title: 'Seleccionar imagen',
                    button: { text: 'Usar esta imagen' },
                    multiple: false
                });

                frame.on('select', function() {
                    const attachment = frame.state().get('selection').first().toJSON();
                    $(target).val(attachment.id);
                    $(preview).attr('src', attachment.url).removeClass('is-hidden');
                });

                frame.open();
            });
        },

        // --- Email Tools Logic ---
        initEmailTools: function() {
            const $feedback = $('[data-email-feedback]');

            function setFeedback(msg, type) {
                $feedback.removeClass('is-error is-success').addClass(type ? 'is-' + type : '').html(msg || '');
            }

            $(document).on('click', '[data-email-preview]', function() {
                setFeedback('Generando vista previa...', '');
                $.post(wooOtecMoodleAdmin.ajaxUrl, {
                    action: 'woo_otec_moodle_email_preview',
                    nonce: wooOtecMoodleAdmin.emailNonce
                }).done(function(res) {
                    if (res.success) {
                        $('[data-email-preview-box]').html(res.data.html || '');
                        setFeedback('Vista previa generada.', 'success');
                    } else {
                        setFeedback('Error al generar vista previa.', 'error');
                    }
                });
            });

            $(document).on('click', '[data-email-send-test]', function() {
                const recipient = $('#woo_otec_moodle_email_test_recipient').val();
                setFeedback('Enviando...', '');
                $.post(wooOtecMoodleAdmin.ajaxUrl, {
                    action: 'woo_otec_moodle_send_test_email',
                    nonce: wooOtecMoodleAdmin.emailNonce,
                    recipient: recipient
                }).done(function(res) {
                    if (res.success) {
                        setFeedback(res.data.message || 'Correo enviado.', 'success');
                    } else {
                        setFeedback('Error al enviar prueba.', 'error');
                    }
                });
            });
        },

        // --- Template Tools Logic ---
        initTemplateTools: function() {
            const $container = $('[data-template-fields]');
            const $preview = $('[data-template-live-preview]');
            const $feedback = $('[data-template-feedback]');

            function setTemplateFeedback(msg, isError) {
                if (!$feedback.length) return;
                $feedback.text(msg || '');
                $feedback.toggleClass('is-error', !!isError);
            }

            function getSelectedFields() {
                const selected = [];
                $('[data-template-fields] input[type="checkbox"]:checked').each(function() {
                    selected.push($(this).val());
                });
                return selected;
            }

            function getFieldLabel(metaKey) {
                let label = '';
                $('[data-template-fields] input[type="checkbox"]').each(function() {
                    const $chk = $(this);
                    if (($chk.data('meta-key') || '').toString() === metaKey) {
                        label = ($chk.data('meta-label') || '').toString();
                    }
                });
                if (label) return label;
                return metaKey.replace(/^_+/, '').replace(/[_-]+/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
            }

            function getLiveValue(metaKey) {
                const normalized = (metaKey || '').toString().toLowerCase().replace(/[^a-z0-9_]/g, '');
                let value = '';
                $('.pcc-mapping-manual-value').each(function() {
                    const key = ($(this).data('meta-key') || '').toString().toLowerCase().replace(/[^a-z0-9_]/g, '');
                    if (key === normalized && value === '') {
                        value = ($(this).val() || '').toString().trim();
                    }
                });
                return value || 'Sin valor en este curso';
            }

            function renderLivePreview() {
                if (!$preview.length) return;
                const selected = getSelectedFields();
                if (!selected.length) {
                    $preview.html('<p class="description">Marca al menos un campo para ver la vista previa.</p>');
                    return;
                }

                let html = '<div class="pcc-template-live-list">';
                selected.forEach(function(metaKey) {
                    const label = getFieldLabel(metaKey);
                    const value = getLiveValue(metaKey);
                    html += '<div class="pcc-template-live-item">';
                    html += '<span>' + $('<div>').text(label).html() + '</span>';
                    html += '<strong>' + $('<div>').text(value).html() + '</strong>';
                    html += '</div>';
                });
                html += '</div>';
                $preview.html(html);
            }

            function updateMappingValues(mappingValues) {
                if (!mappingValues) return;
                $('.pcc-mapping-manual-value').each(function() {
                    const $input = $(this);
                    const keyByMeta = (($input.data('meta-key') || '').toString()).toLowerCase().replace(/[^a-z0-9_]/g, '');
                    const keyByMoodle = (($input.data('moodle-key') || '').toString()).toLowerCase().replace(/[^a-z0-9_]/g, '');
                    const current = ($input.val() || '').toString().trim();
                    if (current !== '') return;
                    const val = mappingValues[keyByMeta] || mappingValues[keyByMoodle] || '';
                    if (val !== '') {
                        $input.val(val);
                    }
                });
            }

            function persistTemplateConfig(productId, selectedFields, silent) {
                if (!productId) return;
                if (!silent) {
                    setTemplateFeedback('Actualizando configuracion...', false);
                }

                $.post(wooOtecMoodleAdmin.ajaxUrl, {
                    action: 'woo_otec_moodle_save_template_config',
                    nonce: wooOtecMoodleAdmin.templateNonce,
                    product_id: productId,
                    selected_fields: selectedFields || []
                }).done(function(res) {
                    if (!res.success) {
                        setTemplateFeedback('No se pudo actualizar la configuracion.', true);
                        return;
                    }

                    if (res.data.fields_html) {
                        $container.html(res.data.fields_html);
                    }
                    updateMappingValues(res.data.mapping_values || {});
                    renderLivePreview();
                    setTemplateFeedback('Cambios guardados automaticamente.', false);
                }).fail(function() {
                    setTemplateFeedback('Error de conexion al guardar configuracion.', true);
                });
            }

            $(document).on('change', '#woo_otec_moodle_template_reference', function() {
                const productId = $(this).val();
                if (!$container.length) return;

                if (!productId) {
                    $container.html('<p class="description">Selecciona un curso para listar los metadatos disponibles.</p>');
                    if ($preview.length) {
                        $preview.html('<p class="description">Selecciona un curso y marca los campos para ver la vista previa.</p>');
                    }
                    return;
                }

                $container.html('<p class="description">Cargando metadatos...</p>');
                if ($preview.length) {
                    $preview.html('<p class="description">Generando vista previa...</p>');
                }
                persistTemplateConfig(productId, getSelectedFields(), true);
            });

            $(document).on('change', '[data-template-fields] input[type="checkbox"]', function() {
                const productId = $('#woo_otec_moodle_template_reference').val();
                if (!productId) {
                    return;
                }
                persistTemplateConfig(productId, getSelectedFields(), false);
            });

            $(document).on('input', '.pcc-mapping-manual-value', function() {
                renderLivePreview();
            });

            // Cargar vista en vivo inicial si ya hay curso seleccionado.
            const initialProductId = $('#woo_otec_moodle_template_reference').val();
            if (initialProductId) {
                persistTemplateConfig(initialProductId, getSelectedFields(), true);
            } else {
                renderLivePreview();
            }
        },

        // --- Wizard Helper Methods ---
        goToStep: function(step) {
            this.currentStep = step;
            $('.pcc-step').removeClass('is-active is-completed');
            $('.pcc-wizard-content').removeClass('is-active');

            for (let i = 1; i <= 4; i++) {
                const $step = $(`.pcc-step[data-step="${i}"]`);
                if (i < step) $step.addClass('is-completed');
                else if (i === step) $step.addClass('is-active');
            }

            $(`#pcc-step-${step}-content`).addClass('is-active').show().siblings('.pcc-wizard-content').hide();
        },

        loadCategories: function() {
            const self = this;
            const $container = $('#pcc-categories-list-container');
            $container.html('<div class="pcc-loading-spinner">Obteniendo categorÃ­as...</div>');

            $.ajax({
                url: wooOtecMoodleAdmin.ajaxUrl,
                type: 'POST',
                data: { action: 'woo_otec_moodle_get_categories', nonce: wooOtecMoodleAdmin.nonce },
                success: function(res) {
                    if (res.success) {
                        self.categories = res.data;
                        self.renderCategories();
                    } else {
                        const msg = typeof res.data === 'string' ? res.data : (res.data && res.data.message ? res.data.message : 'Error desconocido');
                        $container.html('<div class="notice notice-error"><p><strong>Error Moodle API:</strong> ' + msg + '</p></div>');
                    }
                },
                error: function(xhr) {
                    $container.html('<div class="notice notice-error"><p>Error de conexiÃ³n al servidor (AJAX). Revisa el log de PHP.</p></div>');
                }
            });
        },

        renderCategories: function() {
            const $container = $('#pcc-categories-list-container');
            let html = '<table class="pcc-table"><thead><tr><th><input type="checkbox" id="pcc-select-all-cats"></th><th>ID</th><th>Nombre</th><th>Estado</th></tr></thead><tbody>';
            
            this.categories.forEach(cat => {
                const statusLabel = cat.exists ? '<span style="color: #2ed8b6;">Existente</span>' : '<span style="color: #404e67; font-weight: bold;">Nuevo</span>';
                html += `<tr>
                    <td><input type="checkbox" class="pcc-category-checkbox" value="${cat.id}" ${cat.exists ? '' : 'checked'}></td>
                    <td>${cat.id}</td>
                    <td>${cat.name}</td>
                    <td>${statusLabel}</td>
                </tr>`;
            });

            html += '</tbody></table>';
            $container.html(html);

            $('#pcc-select-all-cats').on('change', function() {
                $('.pcc-category-checkbox').prop('checked', $(this).prop('checked'));
            });
        },

        loadTeachers: function() {
            const self = this;
            const $container = $('#pcc-teachers-list-container');
            $container.html('<div class="pcc-loading-spinner">Buscando profesores en las categorÃ­as seleccionadas...</div>');

            $.ajax({
                url: wooOtecMoodleAdmin.ajaxUrl,
                type: 'POST',
                data: { 
                    action: 'woo_otec_moodle_get_teachers', 
                    nonce: wooOtecMoodleAdmin.nonce,
                    categories: self.selectedCategories
                },
                success: function(res) {
                    if (res.success) {
                        self.teachers = res.data;
                        self.renderTeachers();
                    } else {
                        $container.html('<div class="notice notice-error"><p>' + res.data + '</p></div>');
                    }
                },
                error: function() {
                    $container.html('<div class="notice notice-error"><p>Error de conexiÃ³n al buscar profesores.</p></div>');
                }
            });
        },

        renderTeachers: function() {
            const $container = $('#pcc-teachers-list-container');
            if (this.teachers.length === 0) {
                $container.html('<div class="notice notice-warning"><p>No se encontraron profesores para estas categorÃ­as. Puedes continuar, pero los cursos se asignarÃ¡n al instructor por defecto.</p></div>');
                return;
            }

            let html = '<p>Se han detectado los siguientes profesores:</p><ul style="list-style: disc; margin-left: 20px;">';
            this.teachers.forEach(teacher => {
                html += `<li>${teacher}</li>`;
            });
            html += '</ul>';
            $container.html(html);
        },

        loadCourses: function() {
            const self = this;
            const $container = $('#pcc-courses-table-container');
            $container.html('<div class="pcc-loading-spinner">Obteniendo cursos...</div>');

            $.ajax({
                url: wooOtecMoodleAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'woo_otec_moodle_get_courses',
                    nonce: wooOtecMoodleAdmin.nonce,
                    categories: self.selectedCategories
                },
                success: function(res) {
                    if (res.success) {
                        self.courses = res.data;
                        self.renderCoursesTable();
                    } else {
                        const errorMsg = typeof res.data === 'string' ? res.data : (res.data && res.data.message ? res.data.message : 'Error desconocido');
                        $container.html('<div class="notice notice-error"><p><strong>Error al obtener cursos:</strong> ' + errorMsg + '</p></div>');
                    }
                },
                error: function(xhr, status, error) {
                    let msg = 'Error de conexiÃ³n al servidor.';
                    if (status === 'timeout') {
                        msg = 'La solicitud tardÃ³ demasiado. Es posible que tengas muchos cursos en Moodle. Intenta seleccionar menos categorÃ­as.';
                    }
                    $container.html('<div class="notice notice-error"><p>' + msg + '</p></div>');
                }
            });
        },

        renderCoursesTable: function() {
            const $container = $('#pcc-courses-table-container');
            let html = '<table class="pcc-table"><thead><tr><th>TÃ­tulo</th><th>Modalidad</th><th>DuraciÃ³n</th><th>Inicio/Fin</th><th>Profesor</th><th>Imagen</th></tr></thead><tbody>';
            
            this.courses.forEach((course, index) => {
                html += `<tr data-course-index="${index}">
                    <td><input type="text" class="pcc-course-title" value="${course.fullname}"><p class="pcc-field-help">Nombre comercial.</p></td>
                    <td>
                        <select class="pcc-course-modality">
                            <option value="online" ${course.modality === 'online' ? 'selected' : ''}>Online</option>
                            <option value="presencial" ${course.modality === 'presencial' ? 'selected' : ''}>Presencial</option>
                            <option value="semipresencial" ${course.modality === 'semipresencial' ? 'selected' : ''}>Semipresencial</option>
                        </select>
                    </td>
                    <td><input type="text" class="pcc-course-duration" value="${course.duration || ''}" placeholder="Ej: 40 horas"></td>
                    <td>
                        <input type="date" class="pcc-course-start" value="${course.startdate_iso || ''}"><br>
                        <input type="date" class="pcc-course-end" value="${course.enddate_iso || ''}">
                    </td>
                    <td><input type="text" class="pcc-course-teacher" value="${course.teacher || ''}"></td>
                    <td>
                        <button type="button" class="button pcc-course-media-picker" data-index="${index}">Cambiar</button>
                        <input type="hidden" class="pcc-course-image-id" value="${course.image_id || ''}">
                        <div class="pcc-course-image-preview" style="margin-top:5px;">
                            ${course.image_url ? `<img src="${course.image_url}" style="max-width:50px;">` : ''}
                        </div>
                    </td>
                </tr>
                <tr>
                    <td colspan="6">
                        <textarea class="pcc-course-syllabus" placeholder="Temario/DescripciÃ³n">${course.summary || ''}</textarea>
                    </td>
                </tr>`;
            });

            html += '</tbody></table>';
            $container.html(html);

            // Bind media picker for course images
            $('.pcc-course-media-picker').on('click', function() {
                const $btn = $(this);
                const frameCourse = wp.media({
                    title: 'Imagen del curso',
                    button: { text: 'Usar esta imagen' },
                    multiple: false
                });
                frameCourse.on('select', function() {
                    const attachment = frameCourse.state().get('selection').first().toJSON();
                    $btn.siblings('.pcc-course-image-id').val(attachment.id);
                    $btn.siblings('.pcc-course-image-preview').html(`<img src="${attachment.url}" style="max-width:50px;">`);
                });
                frameCourse.open();
            });
        },

        collectEditedCourses: function() {
            const self = this;
            self.editedCourses = [];
            $('.pcc-table tbody tr[data-course-index]').each(function() {
                const index = $(this).data('course-index');
                const $row = $(this);
                const $nextRow = $row.next('tr');
                
                // Extraer solo los campos necesarios para reducir el tamaÃ±o del payload
                const source = self.courses[index];
                const course = {
                    id: source.id,
                    categoryid: source.categoryid,
                    fullname: $row.find('.pcc-course-title').val(),
                    modality: $row.find('.pcc-course-modality').val(),
                    duration: $row.find('.pcc-course-duration').val(),
                    startdate: $row.find('.pcc-course-start').val(),
                    enddate: $row.find('.pcc-course-end').val(),
                    teacher: $row.find('.pcc-course-teacher').val(),
                    image_id: $row.find('.pcc-course-image-id').val(),
                    summary: $nextRow.find('.pcc-course-syllabus').val(),
                    certificate_enabled: source.certificate_enabled === 'yes' ? 'yes' : 'no'
                };
                
                self.editedCourses.push(course);
            });
        },

                renderSummary: function() {
            let html = `<ul>
                <li><strong>Categorias seleccionadas:</strong> ${this.selectedCategories.length}</li>
                <li><strong>Profesores detectados:</strong> ${this.teachers.length}</li>
                <li><strong>Cursos a sincronizar:</strong> ${this.editedCourses.length}</li>
            </ul>`;

            html += `<h4>Certificado de finalizacion por curso</h4>
            <p class="description">Activa solo en los cursos que entregan certificado.</p>
            <table class="pcc-table">
                <thead><tr><th>Curso</th><th style="width: 180px;">Certificado</th></tr></thead>
                <tbody>`;

            this.editedCourses.forEach((course, index) => {
                const checked = course.certificate_enabled === 'yes' ? 'checked' : '';
                html += `<tr>
                    <td>${course.fullname || ('Curso ' + (index + 1))}</td>
                    <td><label><input type="checkbox" class="pcc-certificate-toggle" data-course-index="${index}" ${checked}> Entrega certificado</label></td>
                </tr>`;
            });

            html += `</tbody></table>`;
            $('#pcc-sync-summary-container').html(html);
        },
        executeSync: function() {
            const self = this;
            const $btn = $('#pcc-btn-execute-sync');
            const $progressBar = $('.pcc-progress-bar');

            $('.pcc-certificate-toggle').each(function() {
                const idx = parseInt($(this).data('course-index'), 10);
                if (!Number.isNaN(idx) && self.editedCourses[idx]) {
                    self.editedCourses[idx].certificate_enabled = $(this).is(':checked') ? 'yes' : 'no';
                }
            });

            $btn.prop('disabled', true).text('Procesando datos...');
            $('.pcc-progress').show();
            $progressBar.css('width', '50%').text('Sincronizando con Moodle...');

            $.ajax({
                url: wooOtecMoodleAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'woo_otec_moodle_execute_wizard_sync',
                    nonce: wooOtecMoodleAdmin.nonce,
                    categories: self.selectedCategories,
                    courses: self.editedCourses
                },
                success: function(res) {
                    if (res.success) {
                        $progressBar.css('width', '100%').text('100% - Â¡Completado!');
                        alert(res.data || 'SincronizaciÃ³n completada.');
                        location.reload();
                    } else {
                        const errorMsg = res.data || 'Error desconocido durante la sincronizaciÃ³n.';
                        alert('Error: ' + errorMsg);
                        $btn.prop('disabled', false).text('Ejecutar sincronizaciÃ³n');
                        $progressBar.css('width', '0%').text('0%');
                    }
                },
                error: function(xhr, status, error) {
                    alert('Error de conexiÃ³n al servidor: ' + error);
                    $btn.prop('disabled', false).text('Ejecutar sincronizaciÃ³n');
                    $progressBar.css('width', '0%').text('0%');
                }
            });
        },

        testSSO: function() {
            const $btn = $('#pcc-test-sso-connection');
            const $result = $('#sso-test-result');
            const url = $('#woo_otec_moodle_sso_base_url').val();

            if (!url) { alert('Ingresa una URL base SSO.'); return; }

            $btn.prop('disabled', true).text('Probando...');
            $result.removeClass('success error').text('Verificando...');

            $.ajax({
                url: wooOtecMoodleAdmin.ajaxUrl,
                type: 'POST',
                data: { action: 'woo_otec_moodle_test_sso', nonce: wooOtecMoodleAdmin.nonce, url: url },
                success: function(res) {
                    $btn.prop('disabled', false).text('Probar conexiÃ³n');
                    if (res.success) {
                        $result.addClass('success').text('Â¡Ã‰xito!');
                    } else {
                        let errorMsg = res.data;
                        if (typeof errorMsg === 'string' && errorMsg.includes('Could not resolve host')) {
                            errorMsg = 'Error de DNS: El servidor no reconoce el dominio. Revisa la URL.';
                        }
                        $result.addClass('error').text('Error: ' + errorMsg);
                    }
                },
                error: function() {
                    $btn.prop('disabled', false).text('Probar conexiÃ³n');
                    $result.addClass('error').text('Error de comunicaciÃ³n con el servidor.');
                }
            });
        },

        generateZIP: function() {
            const $btn = $('#pcc-generate-zip');
            const $result = $('#pcc-zip-result');
            $btn.prop('disabled', true).text('Generando...');
            $result.html('<p>Comprimiendo...</p>');

            $.ajax({
                url: wooOtecMoodleAdmin.ajaxUrl,
                type: 'POST',
                data: { action: 'woo_otec_moodle_generate_zip', nonce: wooOtecMoodleAdmin.nonce },
                success: function(res) {
                    $btn.prop('disabled', false).text('Generar ZIP');
                    if (res.success) $result.html(`<p style="color:green;">${res.data.message} <a href="${res.data.url}" class="button button-small" target="_blank">Descargar</a></p>`);
                    else $result.html(`<p style="color:red;">Error: ${res.data}</p>`);
                }
            });
        }
    };

    $(document).ready(function() {
        PCC_Admin.init();
    });

})(jQuery);

