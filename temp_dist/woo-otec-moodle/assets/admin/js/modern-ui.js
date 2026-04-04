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
            this.relocateAdminNotices();
            
            // Auto-load categories if sync tab is active on page load
            if ($('.pcc-tab[data-tab="sync"]').hasClass('is-active')) {
                this.loadCategories();
            }
        },

        relocateAdminNotices: function() {
            const $wrap = $('.pcc-admin-wrap');
            const $target = $('#pcc-admin-notices-bottom');

            if (!$wrap.length || !$target.length) {
                return;
            }

            const moveVisibleNotices = function() {
                const notices = $wrap.prevAll('.notice, .error, .updated, .update-nag').get().reverse();
                notices.forEach(function(node) {
                    const $node = $(node);
                    if ($node.closest('.pcc-admin-wrap').length) {
                        return;
                    }
                    $target.append($node);
                });
            };

            const convertEscapedNoticeText = function() {
                const scope = document.querySelector('#wpbody-content');
                if (!scope) {
                    return;
                }

                const walker = document.createTreeWalker(scope, NodeFilter.SHOW_TEXT);
                const textNodes = [];
                let current = walker.nextNode();

                while (current) {
                    textNodes.push(current);
                    current = walker.nextNode();
                }

                textNodes.forEach(function(textNode) {
                    const raw = (textNode.textContent || '').trim();
                    if (!raw || !raw.includes('<div') || !raw.includes('notice')) {
                        return;
                    }

                    const container = document.createElement('div');
                    container.innerHTML = raw;
                    const parsedNotice = container.querySelector('.notice, .error, .updated, .update-nag');

                    if (!parsedNotice) {
                        return;
                    }

                    $target.append(parsedNotice);
                    textNode.textContent = textNode.textContent.replace(raw, '').trim();
                });
            };

            moveVisibleNotices();
            convertEscapedNoticeText();
            moveVisibleNotices();

            const observer = new MutationObserver(function() {
                moveVisibleNotices();
                convertEscapedNoticeText();
            });

            const scope = document.querySelector('#wpbody-content');
            if (scope) {
                observer.observe(scope, { childList: true, subtree: true });
            }
        },

        initAppearanceTools: function() {
            const $selector = $('#woo_otec_moodle_appearance_profile');
            const $tabs = $('.pcc-appearance-tab');
            if (!$selector.length || !$tabs.length) {
                return;
            }

            const $singlePreview = $('[data-appearance-single-preview]');
            const $shopPreview = $('[data-appearance-shop-preview]');
            const $cartPreview = $('[data-appearance-cart-preview]');
            const $checkoutPreview = $('[data-appearance-checkout-preview]');
            const $portalPreview = $('[data-appearance-portal-preview]');
            const $emailPreview = $('[data-appearance-email-preview]');

            function updateSingleProductPreview() {
                if (!$singlePreview.length) {
                    return;
                }

                const $selectedOption = $('#pcc-appearance-preview-product option:selected');
                const name = $selectedOption.data('name') || 'Curso sincronizado de ejemplo';
                const shortText = $selectedOption.data('short') || 'Así se verá el bloque principal del curso con tu paleta actual.';
                const price = $selectedOption.data('price') || 'Sin precio';
                const modality = $selectedOption.data('modality') || 'Modalidad no informada';
                const teacher = $selectedOption.data('teacher') || 'Relator no informado';
                const image = $selectedOption.data('image') || '';
                const style = $('#woo_otec_moodle_template_style').val() || 'classic';
                const primary = $('#woo_otec_moodle_pcc_color_primary').val() || '#023E25';
                const text = $('#woo_otec_moodle_pcc_color_text').val() || '#7A7A7A';
                const accent = $('#woo_otec_moodle_pcc_color_accent').val() || '#61CE70';
                const buttonColor = $('#woo_otec_moodle_single_button_color').val() || '#1f9d6f';
                const buttonText = $('#woo_otec_moodle_single_button_text').val() || 'Comprar curso';
                const descriptionHeading = $('#woo_otec_moodle_single_description_heading').val() || 'Descripción del curso';

                $singlePreview.removeClass('pcc-appearance-mini-card--classic pcc-appearance-mini-card--academy pcc-appearance-mini-card--pccurico')
                    .addClass('pcc-appearance-mini-card--' + style)
                    .css('--pcc-accent-color', accent);

                $singlePreview.find('[data-preview-name]').text(name).css('color', primary);
                $singlePreview.find('p').first().text(shortText).css('color', text);
                $singlePreview.find('[data-preview-modality]').text(modality);
                $singlePreview.find('[data-preview-teacher]').text(teacher);
                $singlePreview.find('[data-preview-price]').text(price).css('color', primary);
                $singlePreview.find('button').first().text(buttonText).css({
                    background: buttonColor,
                    borderColor: buttonColor
                });
                $singlePreview.find('[data-preview-description-heading]').text(descriptionHeading);

                const $media = $singlePreview.find('.pcc-appearance-mini-card__media');
                if (image) {
                    if ($media.find('img').length) {
                        $media.find('img').attr('src', image).attr('alt', name);
                    } else {
                        $media.html('<img src="' + image + '" alt="' + $('<div>').text(name).html() + '" data-preview-image>');
                    }
                    $media.removeClass('is-empty');
                } else {
                    $media.addClass('is-empty').html('<span data-preview-image-placeholder>Sin portada</span>');
                }
            }

            function updateMiniBanner($preview, config) {
                if (!$preview.length) {
                    return;
                }

                $preview.css('background', config.bg);
                $preview.find('[data-preview-title]').text(config.title).css('color', config.titleColor);
                $preview.find('[data-preview-text]').text(config.text).css('color', config.textColor);
                $preview.find('button').first().css({
                    background: config.buttonColor,
                    borderColor: config.buttonColor
                });
                $preview.find('[data-preview-button]').text(config.buttonText);
            }

            function updateShopPreview() {
                updateMiniBanner($shopPreview, {
                    title: $('#woo_otec_moodle_shop_intro_title').val() || 'Explora nuestra oferta de cursos',
                    text: $('#woo_otec_moodle_shop_intro_text').val() || 'Descubre nuestros cursos disponibles y selecciona el que mejor se ajusta a tu objetivo.',
                    bg: $('#woo_otec_moodle_shop_color_bg').val() || '#f8fbff',
                    titleColor: $('#woo_otec_moodle_shop_color_title').val() || '#21405a',
                    textColor: $('#woo_otec_moodle_shop_color_text').val() || '#2b4b63',
                    buttonColor: $('#woo_otec_moodle_shop_color_button').val() || '#0f3d5e',
                    buttonText: $('#woo_otec_moodle_shop_button_text').val() || 'Ver curso'
                });
            }

            function updateCartPreview() {
                updateMiniBanner($cartPreview, {
                    title: $('#woo_otec_moodle_cart_intro_title').val() || 'Tu carrito de capacitación',
                    text: $('#woo_otec_moodle_cart_intro_text').val() || 'Revisa tus cursos antes de finalizar el pago.',
                    bg: $('#woo_otec_moodle_cart_color_bg').val() || '#f5fbf8',
                    titleColor: $('#woo_otec_moodle_cart_color_title').val() || '#1d5a41',
                    textColor: $('#woo_otec_moodle_cart_color_text').val() || '#355846',
                    buttonColor: $('#woo_otec_moodle_cart_color_button').val() || '#1f9d6f',
                    buttonText: 'Continuar al pago'
                });
            }

            function updateCheckoutPreview() {
                updateMiniBanner($checkoutPreview, {
                    title: $('#woo_otec_moodle_checkout_intro_title').val() || 'Último paso para activar tus cursos',
                    text: $('#woo_otec_moodle_checkout_intro_text').val() || 'Completa tus datos para activar el acceso inmediato a tus cursos.',
                    bg: $('#woo_otec_moodle_checkout_color_bg').val() || '#fff8f1',
                    titleColor: $('#woo_otec_moodle_checkout_color_title').val() || '#7b4b12',
                    textColor: $('#woo_otec_moodle_checkout_color_text').val() || '#6f5a40',
                    buttonColor: $('#woo_otec_moodle_checkout_color_button').val() || '#d9822b',
                    buttonText: $('#woo_otec_moodle_checkout_button_text').val() || 'Finalizar compra'
                });
            }

            function updateEmailPreview() {
                if (!$emailPreview.length) {
                    return;
                }

                const bg = $('#woo_otec_moodle_email_color_bg_appearance').val() || '#f3f8fc';
                const primary = $('#woo_otec_moodle_email_color_primary_appearance').val() || '#0f3d5e';
                const accent = $('#woo_otec_moodle_email_color_accent_appearance').val() || '#1f9d6f';
                const title = $('#woo_otec_moodle_email_builder_heading_appearance').val() || 'Tus accesos ya están listos';
                const text = $('#woo_otec_moodle_email_builder_intro_appearance').val() || 'Tu compra fue confirmada correctamente. Aquí tienes los datos para ingresar a tu plataforma.';
                const buttonText = $('#woo_otec_moodle_email_builder_button_text_appearance').val() || 'Acceder a mis cursos';

                $emailPreview.css('background', bg);
                $emailPreview.find('.pcc-appearance-mini-email__head').css('background', primary);
                $emailPreview.find('[data-preview-title]').text(title);
                $emailPreview.find('[data-preview-text]').text(text);
                $emailPreview.find('button').first().css({
                    background: accent,
                    borderColor: accent
                });
                $emailPreview.find('[data-preview-button]').text(buttonText);
            }

            function updatePortalPreview() {
                updateMiniBanner($portalPreview, {
                    title: $('#woo_otec_moodle_portal_title').val() || 'Mis cursos',
                    text: $('#woo_otec_moodle_portal_intro_text').val() || 'Desde aquí puedes entrar directamente a cada curso comprado.',
                    bg: $('#woo_otec_moodle_portal_color_bg').val() || '#f7fbff',
                    titleColor: $('#woo_otec_moodle_portal_color_title').val() || '#173246',
                    textColor: $('#woo_otec_moodle_portal_color_text').val() || '#567187',
                    buttonColor: $('#woo_otec_moodle_portal_color_button').val() || '#0f3d5e',
                    buttonText: $('#woo_otec_moodle_portal_button_text').val() || 'Entrar al curso'
                });
            }

            function setAppearanceTab(selected) {
                if (!$('.pcc-appearance-group[data-appearance-group="' + selected + '"]').length) {
                    selected = 'product';
                }
                $('.pcc-appearance-group').removeClass('is-active').hide();
                $('.pcc-appearance-group[data-appearance-group="' + selected + '"]').addClass('is-active').show();
                $tabs.removeClass('is-active');
                $('.pcc-appearance-tab[data-appearance-tab="' + selected + '"]').addClass('is-active');
                $selector.val(selected);
            }

            $(document).on('click', '.pcc-appearance-tab', function() {
                const selected = $(this).data('appearance-tab') || 'product';
                setAppearanceTab(selected);
            });

            $(document).on('change input', [
                '#pcc-appearance-preview-product',
                '#woo_otec_moodle_template_style',
                '#woo_otec_moodle_single_button_text',
                '#woo_otec_moodle_single_button_color',
                '#woo_otec_moodle_single_description_heading',
                '#woo_otec_moodle_pcc_color_primary',
                '#woo_otec_moodle_pcc_color_text',
                '#woo_otec_moodle_pcc_color_accent'
            ].join(', '), updateSingleProductPreview);

            $(document).on('change input', [
                '#woo_otec_moodle_shop_intro_title',
                '#woo_otec_moodle_shop_intro_text',
                '#woo_otec_moodle_shop_color_bg',
                '#woo_otec_moodle_shop_color_title',
                '#woo_otec_moodle_shop_color_text',
                '#woo_otec_moodle_shop_color_button',
                '#woo_otec_moodle_shop_button_text'
            ].join(', '), updateShopPreview);

            $(document).on('change input', [
                '#woo_otec_moodle_cart_intro_title',
                '#woo_otec_moodle_cart_intro_text',
                '#woo_otec_moodle_cart_color_bg',
                '#woo_otec_moodle_cart_color_title',
                '#woo_otec_moodle_cart_color_text',
                '#woo_otec_moodle_cart_color_button'
            ].join(', '), updateCartPreview);

            $(document).on('change input', [
                '#woo_otec_moodle_checkout_intro_title',
                '#woo_otec_moodle_checkout_intro_text',
                '#woo_otec_moodle_checkout_color_bg',
                '#woo_otec_moodle_checkout_color_title',
                '#woo_otec_moodle_checkout_color_text',
                '#woo_otec_moodle_checkout_color_button',
                '#woo_otec_moodle_checkout_button_text'
            ].join(', '), updateCheckoutPreview);

            $(document).on('change input', [
                '#woo_otec_moodle_portal_title',
                '#woo_otec_moodle_portal_intro_text',
                '#woo_otec_moodle_portal_button_text',
                '#woo_otec_moodle_portal_color_bg',
                '#woo_otec_moodle_portal_color_title',
                '#woo_otec_moodle_portal_color_text',
                '#woo_otec_moodle_portal_color_button'
            ].join(', '), updatePortalPreview);

            $(document).on('change input', [
                '#woo_otec_moodle_email_builder_heading_appearance',
                '#woo_otec_moodle_email_builder_intro_appearance',
                '#woo_otec_moodle_email_builder_button_text_appearance',
                '#woo_otec_moodle_email_color_primary_appearance',
                '#woo_otec_moodle_email_color_accent_appearance',
                '#woo_otec_moodle_email_color_bg_appearance'
            ].join(', '), updateEmailPreview);

            setAppearanceTab($selector.val() || 'product');
            updateSingleProductPreview();
            updateShopPreview();
            updateCartPreview();
            updateCheckoutPreview();
            updatePortalPreview();
            updateEmailPreview();
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
                    alert('Por favor, selecciona al menos una categoría.');
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

            // Config export
            $(document).on('click', '#pcc-export-config', function() {
                self.exportConfig();
            });

            $(document).on('click', '#pcc-import-config', function() {
                self.importConfig();
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

            function getSelectedFields() {
                const selected = [];
                $('[data-template-fields] input[type="checkbox"]:checked').each(function() {
                    selected.push($(this).val());
                });
                return selected;
            }

            function renderEmptyPreview(message) {
                if (!$preview.length) return;
                $preview.html('<p class="description">' + message + '</p>');
            }

            function persistTemplateConfig(productId, selectedFields, silent) {
                if (!productId) return;

                $.post(wooOtecMoodleAdmin.ajaxUrl, {
                    action: 'woo_otec_moodle_save_template_config',
                    nonce: wooOtecMoodleAdmin.templateNonce,
                    product_id: productId,
                    selected_fields: selectedFields || []
                }).done(function(res) {
                    if (!res.success) {
                        renderEmptyPreview('No se pudo actualizar la vista previa.');
                        return;
                    }

                    if (res.data.fields_html) {
                        $container.html(res.data.fields_html);
                    }
                    if ($preview.length) {
                        $preview.html(res.data.preview_html || '<p class="description">Sin datos para mostrar.</p>');
                    }
                }).fail(function() {
                    renderEmptyPreview('Error de conexión al actualizar la vista previa.');
                });
            }

            $(document).on('change', '#woo_otec_moodle_template_reference', function() {
                const productId = $(this).val();
                if (!$container.length) return;

                if (!productId) {
                    $container.html('<p class="description">Selecciona un curso para listar los metadatos disponibles.</p>');
                    if ($preview.length) {
                        renderEmptyPreview('Selecciona un curso y marca los campos para ver la plantilla en tiempo real.');
                    }
                    return;
                }

                $container.html('<p class="description">Cargando metadatos...</p>');
                if ($preview.length) {
                    renderEmptyPreview('Generando vista previa...');
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

            // Cargar vista en vivo inicial si ya hay curso seleccionado.
            const initialProductId = $('#woo_otec_moodle_template_reference').val();
            if (initialProductId) {
                persistTemplateConfig(initialProductId, getSelectedFields(), true);
            } else {
                renderEmptyPreview('Selecciona un curso y marca los campos para ver la plantilla en tiempo real.');
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
            $container.html('<div class="pcc-loading-spinner">Obteniendo categorías...</div>');

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
                    $container.html('<div class="notice notice-error"><p>Error de conexión al servidor (AJAX). Revisa el log de PHP.</p></div>');
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
            $container.html('<div class="pcc-loading-spinner">Buscando profesores en las categorías seleccionadas...</div>');

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
                    $container.html('<div class="notice notice-error"><p>Error de conexión al buscar profesores.</p></div>');
                }
            });
        },

        renderTeachers: function() {
            const $container = $('#pcc-teachers-list-container');
            if (this.teachers.length === 0) {
                $container.html('<div class="notice notice-warning"><p>No se encontraron profesores para estas categorías. Puedes continuar, pero los cursos se asignarán al instructor por defecto.</p></div>');
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
                    let msg = 'Error de conexión al servidor.';
                    if (status === 'timeout') {
                        msg = 'La solicitud tardó demasiado. Es posible que tengas muchos cursos en Moodle. Intenta seleccionar menos categorías.';
                    }
                    $container.html('<div class="notice notice-error"><p>' + msg + '</p></div>');
                }
            });
        },

        renderCoursesTable: function() {
            const $container = $('#pcc-courses-table-container');
            let html = '<table class="pcc-table"><thead><tr><th>Título</th><th>Modalidad</th><th>Duración</th><th>Inicio/Fin</th><th>Profesor</th><th>Imagen</th></tr></thead><tbody>';
            
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
                        <textarea class="pcc-course-syllabus" placeholder="Temario/Descripción">${course.summary || ''}</textarea>
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
                
                // Extraer solo los campos necesarios para reducir el tamaño del payload
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
                <li><strong>Categorías seleccionadas:</strong> ${this.selectedCategories.length}</li>
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
                        $progressBar.css('width', '100%').text('100% - ¡Completado!');
                        alert(res.data || 'Sincronización completada.');
                        location.reload();
                    } else {
                        const errorMsg = res.data || 'Error desconocido durante la sincronización.';
                        alert('Error: ' + errorMsg);
                        $btn.prop('disabled', false).text('Ejecutar sincronización');
                        $progressBar.css('width', '0%').text('0%');
                    }
                },
                error: function(xhr, status, error) {
                    alert('Error de conexión al servidor: ' + error);
                    $btn.prop('disabled', false).text('Ejecutar sincronización');
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
                    $btn.prop('disabled', false).text('Probar conexión');
                    if (res.success) {
                        $result.addClass('success').text('¡Éxito!');
                    } else {
                        let errorMsg = res.data;
                        if (typeof errorMsg === 'string' && errorMsg.includes('Could not resolve host')) {
                            errorMsg = 'Error de DNS: El servidor no reconoce el dominio. Revisa la URL.';
                        }
                        $result.addClass('error').text('Error: ' + errorMsg);
                    }
                },
                error: function() {
                    $btn.prop('disabled', false).text('Probar conexión');
                    $result.addClass('error').text('Error de comunicación con el servidor.');
                }
            });
        },

        exportConfig: function() {
            const $btn = $('#pcc-export-config');
            const $result = $('#pcc-config-export-result');
            const params = new URLSearchParams({
                action: 'woo_otec_moodle_export_config',
                nonce: wooOtecMoodleAdmin.configNonce
            });

            $btn.prop('disabled', true).text('Preparando...');
            $result.html('<p>Generando archivo de configuración...</p>');

            window.location.href = `${wooOtecMoodleAdmin.ajaxUrl}?${params.toString()}`;

            window.setTimeout(function() {
                $btn.prop('disabled', false).text('Descargar configuración actual');
                $result.html('<p style="color:green;">La descarga fue solicitada. Revisa tu navegador si el archivo no baja de inmediato.</p>');
            }, 800);
        },

        importConfig: function() {
            const $btn = $('#pcc-import-config');
            const $result = $('#pcc-config-import-result');
            const fileInput = document.getElementById('pcc-import-config-file');

            if (!fileInput || !fileInput.files || !fileInput.files.length) {
                $result.html('<p style="color:red;">Selecciona un archivo JSON antes de importar.</p>');
                return;
            }

            const formData = new FormData();
            formData.append('action', 'woo_otec_moodle_import_config');
            formData.append('nonce', wooOtecMoodleAdmin.importConfigNonce);
            formData.append('config_file', fileInput.files[0]);

            $btn.prop('disabled', true).text('Importando...');
            $result.html('<p>Aplicando configuración...</p>');

            $.ajax({
                url: wooOtecMoodleAdmin.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(res) {
                    $btn.prop('disabled', false).text('Importar configuración');
                    if (res.success) {
                        $result.html('<p style="color:green;">' + (res.data.message || 'Configuración importada correctamente.') + ' Recargando...</p>');
                        window.setTimeout(function() {
                            window.location.reload();
                        }, 900);
                    } else {
                        const msg = (res.data && res.data.message) ? res.data.message : 'No se pudo importar la configuración.';
                        $result.html('<p style="color:red;">' + msg + '</p>');
                    }
                },
                error: function(xhr) {
                    $btn.prop('disabled', false).text('Importar configuración');
                    let msg = 'Error al importar la configuración.';
                    if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                        msg = xhr.responseJSON.data.message;
                    }
                    $result.html('<p style="color:red;">' + msg + '</p>');
                }
            });
        }
    };

    $(document).ready(function() {
        PCC_Admin.init();
    });

})(jQuery);

