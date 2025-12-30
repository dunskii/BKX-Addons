/**
 * Developer SDK Admin JavaScript
 *
 * @package BookingX\DeveloperSDK
 */

(function($) {
    'use strict';

    const BKXDevSDK = {
        templates: {},
        currentTemplate: null,

        /**
         * Initialize.
         */
        init: function() {
            this.loadTemplates();
            this.bindEvents();
            this.initSyntaxHighlighting();
        },

        /**
         * Load template data.
         */
        loadTemplates: function() {
            const data = $('#bkx-template-data').html();
            if (data) {
                this.templates = JSON.parse(data);
            }
        },

        /**
         * Bind events.
         */
        bindEvents: function() {
            // Code Generator
            $(document).on('click', '.bkx-template-item', this.selectTemplate.bind(this));
            $(document).on('click', '#bkx-generate-code', this.generateCode.bind(this));
            $(document).on('click', '#bkx-copy-code', this.copyCode.bind(this));
            $(document).on('click', '#bkx-download-code', this.downloadCode.bind(this));

            // API Explorer
            $(document).on('click', '.bkx-endpoint-item', this.selectEndpoint.bind(this));
            $(document).on('click', '#bkx-send-request', this.sendRequest.bind(this));
            $(document).on('click', '.bkx-response-tab', this.switchResponseTab.bind(this));
            $(document).on('click', '.bkx-code-tab', this.switchCodeTab.bind(this));
            $(document).on('change', '#bkx-request-method', this.toggleRequestBody.bind(this));

            // Hook Inspector
            $(document).on('click', '.bkx-copy-hook', this.copyHookUsage.bind(this));
            $(document).on('click', '.bkx-generate-listener', this.generateListener.bind(this));

            // Sandbox
            $(document).on('click', '#bkx-create-sandbox', this.createSandbox.bind(this));
            $(document).on('click', '#bkx-generate-data', this.generateTestData.bind(this));
            $(document).on('click', '#bkx-delete-test-data', this.deleteTestData.bind(this));
            $(document).on('click', '.bkx-delete-sandbox', this.deleteSandbox.bind(this));
            $(document).on('click', '#bkx-run-code', this.runCode.bind(this));
            $(document).on('click', '#bkx-clear-code', this.clearCode.bind(this));

            // Settings
            $(document).on('submit', '#bkx-sdk-settings-form', this.saveSettings.bind(this));
        },

        /**
         * Initialize syntax highlighting.
         */
        initSyntaxHighlighting: function() {
            if (typeof hljs !== 'undefined') {
                hljs.highlightAll();
            }
        },

        /**
         * Select code template.
         */
        selectTemplate: function(e) {
            e.preventDefault();

            const templateKey = $(e.currentTarget).data('template');
            const template = this.templates[templateKey];

            if (!template) return;

            this.currentTemplate = templateKey;

            // Update UI.
            $('.bkx-template-item').removeClass('active');
            $(e.currentTarget).addClass('active');

            $('#bkx-template-title').text(template.label);
            $('#bkx-template-desc').text(template.description);
            $('#bkx-template-params').show();
            $('#bkx-generator-output').hide();

            // Build params form.
            const table = $('#bkx-params-table');
            table.empty();

            template.params.forEach(param => {
                const label = param.charAt(0).toUpperCase() + param.slice(1).replace(/_/g, ' ');
                const row = `
                    <tr>
                        <th><label for="param_${param}">${label}</label></th>
                        <td><input type="text" id="param_${param}" name="${param}" class="regular-text"></td>
                    </tr>
                `;
                table.append(row);
            });
        },

        /**
         * Generate code.
         */
        generateCode: function(e) {
            e.preventDefault();

            if (!this.currentTemplate) return;

            const params = {};
            $('#bkx-params-table input').each(function() {
                params[$(this).attr('name')] = $(this).val();
            });

            const button = $('#bkx-generate-code');

            $.ajax({
                url: bkxDevSDK.ajaxurl,
                type: 'POST',
                data: {
                    action: 'bkx_generate_code',
                    nonce: bkxDevSDK.nonce,
                    type: this.currentTemplate,
                    params: params
                },
                beforeSend: function() {
                    button.prop('disabled', true).text(bkxDevSDK.strings.generating);
                },
                success: function(response) {
                    button.prop('disabled', false).text(bkxDevSDK.strings.generate);

                    if (response.success) {
                        $('#bkx-generated-code').text(response.data.code);
                        $('#bkx-generator-output').show();

                        if (typeof hljs !== 'undefined') {
                            hljs.highlightElement($('#bkx-generated-code')[0]);
                        }
                    } else {
                        alert(response.data.message || bkxDevSDK.strings.error);
                    }
                },
                error: function() {
                    button.prop('disabled', false).text(bkxDevSDK.strings.generate);
                    alert(bkxDevSDK.strings.error);
                }
            });
        },

        /**
         * Copy generated code.
         */
        copyCode: function(e) {
            e.preventDefault();

            const code = $('#bkx-generated-code').text();
            navigator.clipboard.writeText(code).then(() => {
                alert(bkxDevSDK.strings.copied);
            });
        },

        /**
         * Download generated code.
         */
        downloadCode: function(e) {
            e.preventDefault();

            const code = $('#bkx-generated-code').text();
            const filename = (this.currentTemplate || 'generated') + '.php';

            $.ajax({
                url: bkxDevSDK.ajaxurl,
                type: 'POST',
                data: {
                    action: 'bkx_download_code',
                    nonce: bkxDevSDK.nonce,
                    code: code,
                    filename: filename
                },
                success: function(response) {
                    if (response.success) {
                        // Create download link.
                        const link = document.createElement('a');
                        link.href = response.data.url;
                        link.download = filename;
                        link.click();
                    } else {
                        alert(response.data.message || bkxDevSDK.strings.error);
                    }
                }
            });
        },

        /**
         * Select API endpoint.
         */
        selectEndpoint: function(e) {
            e.preventDefault();

            const item = $(e.currentTarget);
            const method = item.data('method');
            const endpoint = item.data('endpoint');
            const body = item.data('body');

            $('#bkx-request-method').val(method);
            $('#bkx-request-endpoint').val(endpoint);
            $('#bkx-request-body').val(body || '');

            this.toggleRequestBody();
        },

        /**
         * Toggle request body visibility.
         */
        toggleRequestBody: function() {
            const method = $('#bkx-request-method').val();
            const showBody = ['POST', 'PUT', 'PATCH'].includes(method);
            $('#bkx-body-container').toggle(showBody);
        },

        /**
         * Send API request.
         */
        sendRequest: function(e) {
            e.preventDefault();

            const method = $('#bkx-request-method').val();
            const endpoint = $('#bkx-request-endpoint').val();
            const body = $('#bkx-request-body').val();

            if (!endpoint) {
                alert('Please enter an endpoint.');
                return;
            }

            const button = $('#bkx-send-request');

            $.ajax({
                url: bkxDevSDK.ajaxurl,
                type: 'POST',
                data: {
                    action: 'bkx_explore_api',
                    nonce: bkxDevSDK.nonce,
                    method: method,
                    endpoint: endpoint,
                    body: body
                },
                beforeSend: function() {
                    button.prop('disabled', true);
                },
                success: function(response) {
                    button.prop('disabled', false);

                    if (response.success) {
                        const data = response.data;

                        // Update status.
                        const statusClass = data.success ? 'success' : 'error';
                        $('#bkx-response-status')
                            .removeClass('success error')
                            .addClass(statusClass)
                            .text(data.status);
                        $('#bkx-response-time').text(data.response_time + ' ms');
                        $('#bkx-response-meta').show();

                        // Update body.
                        const bodyText = typeof data.body === 'object'
                            ? JSON.stringify(data.body, null, 2)
                            : data.body;
                        $('#bkx-response-body').text(bodyText);

                        // Update headers.
                        $('#bkx-response-headers').text(JSON.stringify(data.headers, null, 2));

                        // Generate code samples.
                        BKXDevSDK.generateCodeSamples(method, endpoint, body);

                        // Highlight.
                        if (typeof hljs !== 'undefined') {
                            hljs.highlightElement($('#bkx-response-body')[0]);
                            hljs.highlightElement($('#bkx-response-headers')[0]);
                        }
                    }
                },
                error: function() {
                    button.prop('disabled', false);
                    alert(bkxDevSDK.strings.error);
                }
            });
        },

        /**
         * Generate code samples.
         */
        generateCodeSamples: function(method, endpoint, body) {
            // Store for tab switching.
            this.lastRequest = { method, endpoint, body };

            // Generate cURL.
            let curl = `curl -X ${method} \\\n`;
            curl += `  '${window.location.origin}/wp-json${endpoint}' \\\n`;
            curl += `  -H 'Content-Type: application/json' \\\n`;
            curl += `  -H 'X-WP-Nonce: YOUR_NONCE'`;

            if (body && ['POST', 'PUT', 'PATCH'].includes(method)) {
                curl += ` \\\n  -d '${body}'`;
            }

            $('#bkx-code-sample').text(curl);

            if (typeof hljs !== 'undefined') {
                hljs.highlightElement($('#bkx-code-sample')[0]);
            }
        },

        /**
         * Switch response tab.
         */
        switchResponseTab: function(e) {
            e.preventDefault();

            const tab = $(e.currentTarget).data('tab');

            $('.bkx-response-tab').removeClass('active');
            $(e.currentTarget).addClass('active');

            $('.bkx-response-content').removeClass('active');
            $(`.bkx-response-content[data-tab="${tab}"]`).addClass('active');
        },

        /**
         * Switch code sample tab.
         */
        switchCodeTab: function(e) {
            e.preventDefault();

            const lang = $(e.currentTarget).data('lang');

            $('.bkx-code-tab').removeClass('active');
            $(e.currentTarget).addClass('active');

            if (!this.lastRequest) return;

            const { method, endpoint, body } = this.lastRequest;
            let code = '';

            switch (lang) {
                case 'curl':
                    code = `curl -X ${method} \\\n`;
                    code += `  '${window.location.origin}/wp-json${endpoint}' \\\n`;
                    code += `  -H 'Content-Type: application/json' \\\n`;
                    code += `  -H 'X-WP-Nonce: YOUR_NONCE'`;
                    if (body) code += ` \\\n  -d '${body}'`;
                    break;

                case 'js':
                    code = `fetch('${window.location.origin}/wp-json${endpoint}', {\n`;
                    code += `    method: '${method}',\n`;
                    code += `    headers: {\n`;
                    code += `        'Content-Type': 'application/json',\n`;
                    code += `        'X-WP-Nonce': wpApiSettings.nonce\n`;
                    code += `    }`;
                    if (body) code += `,\n    body: JSON.stringify(${body})`;
                    code += `\n})\n.then(r => r.json())\n.then(data => console.log(data));`;
                    break;

                case 'php':
                    code = `<?php\n$response = wp_remote_request(\n`;
                    code += `    rest_url('${endpoint}'),\n`;
                    code += `    array(\n`;
                    code += `        'method'  => '${method}',\n`;
                    code += `        'headers' => array(\n`;
                    code += `            'Content-Type' => 'application/json',\n`;
                    code += `            'X-WP-Nonce'   => wp_create_nonce('wp_rest'),\n`;
                    code += `        ),\n`;
                    if (body) code += `        'body' => '${body}',\n`;
                    code += `    )\n);\n\n$body = json_decode(wp_remote_retrieve_body($response), true);`;
                    break;
            }

            $('#bkx-code-sample').text(code);

            if (typeof hljs !== 'undefined') {
                hljs.highlightElement($('#bkx-code-sample')[0]);
            }
        },

        /**
         * Copy hook usage.
         */
        copyHookUsage: function(e) {
            e.preventDefault();

            const hook = $(e.currentTarget).data('hook');
            const type = $(e.currentTarget).data('type');

            let code = '';
            if (type === 'action') {
                code = `add_action('${hook}', function($args) {\n    // Your code here\n});`;
            } else {
                code = `add_filter('${hook}', function($value) {\n    // Modify and return value\n    return $value;\n});`;
            }

            navigator.clipboard.writeText(code).then(() => {
                alert(bkxDevSDK.strings.copied);
            });
        },

        /**
         * Generate hook listener.
         */
        generateListener: function(e) {
            e.preventDefault();

            const hook = $(e.currentTarget).data('hook');

            // Redirect to generator with pre-filled params.
            window.location.href = `${window.location.pathname}?post_type=bkx_booking&page=bkx-developer-sdk&tab=generator&template=hook_listener&hook=${hook}`;
        },

        /**
         * Create sandbox.
         */
        createSandbox: function(e) {
            e.preventDefault();

            const name = $('#bkx-sandbox-name').val();
            if (!name) {
                alert('Please enter a sandbox name.');
                return;
            }

            if (!confirm(bkxDevSDK.strings.confirm_sandbox)) {
                return;
            }

            $.ajax({
                url: bkxDevSDK.ajaxurl,
                type: 'POST',
                data: {
                    action: 'bkx_create_sandbox',
                    nonce: bkxDevSDK.nonce,
                    name: name
                },
                success: function(response) {
                    if (response.success) {
                        alert(bkxDevSDK.strings.sandbox_created);
                        location.reload();
                    } else {
                        alert(response.data.message || bkxDevSDK.strings.error);
                    }
                },
                error: function() {
                    alert(bkxDevSDK.strings.error);
                }
            });
        },

        /**
         * Generate test data.
         */
        generateTestData: function(e) {
            e.preventDefault();

            const type = $('#bkx-data-type').val();
            const count = $('#bkx-data-count').val();

            $.ajax({
                url: bkxDevSDK.ajaxurl,
                type: 'POST',
                data: {
                    action: 'bkx_generate_test_data',
                    nonce: bkxDevSDK.nonce,
                    type: type,
                    count: count
                },
                success: function(response) {
                    if (response.success) {
                        alert(response.data.message);
                        location.reload();
                    } else {
                        alert(response.data.message || bkxDevSDK.strings.error);
                    }
                },
                error: function() {
                    alert(bkxDevSDK.strings.error);
                }
            });
        },

        /**
         * Delete test data.
         */
        deleteTestData: function(e) {
            e.preventDefault();

            if (!confirm('Delete all test data? This cannot be undone.')) {
                return;
            }

            $.ajax({
                url: bkxDevSDK.ajaxurl,
                type: 'POST',
                data: {
                    action: 'bkx_delete_test_data',
                    nonce: bkxDevSDK.nonce
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.data.message || bkxDevSDK.strings.error);
                    }
                }
            });
        },

        /**
         * Delete sandbox.
         */
        deleteSandbox: function(e) {
            e.preventDefault();

            if (!confirm('Delete this sandbox and all its data?')) {
                return;
            }

            const sandboxId = $(e.currentTarget).data('id');

            $.ajax({
                url: bkxDevSDK.ajaxurl,
                type: 'POST',
                data: {
                    action: 'bkx_delete_sandbox',
                    nonce: bkxDevSDK.nonce,
                    sandbox_id: sandboxId
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.data.message || bkxDevSDK.strings.error);
                    }
                }
            });
        },

        /**
         * Run code in playground.
         */
        runCode: function(e) {
            e.preventDefault();

            const code = $('#bkx-code-input').val();
            const button = $('#bkx-run-code');

            $.ajax({
                url: bkxDevSDK.ajaxurl,
                type: 'POST',
                data: {
                    action: 'bkx_test_code',
                    nonce: bkxDevSDK.nonce,
                    code: code
                },
                beforeSend: function() {
                    button.prop('disabled', true).text(bkxDevSDK.strings.test_running);
                },
                success: function(response) {
                    button.prop('disabled', false).html('<span class="dashicons dashicons-controls-play"></span> Run Code');

                    $('#bkx-code-output').show();

                    if (response.success) {
                        let output = response.data.output || '';
                        if (response.data.result) {
                            output += '\n\nReturn value: ' + response.data.result;
                        }
                        $('#bkx-output-content').text(output || '(no output)');
                    } else {
                        $('#bkx-output-content').text('Error: ' + (response.data.error || bkxDevSDK.strings.error));
                    }
                },
                error: function() {
                    button.prop('disabled', false).html('<span class="dashicons dashicons-controls-play"></span> Run Code');
                    alert(bkxDevSDK.strings.error);
                }
            });
        },

        /**
         * Clear code playground.
         */
        clearCode: function(e) {
            e.preventDefault();
            $('#bkx-code-input').val('<?php\n// Enter PHP code to test...\n');
            $('#bkx-code-output').hide();
        },

        /**
         * Save settings.
         */
        saveSettings: function(e) {
            e.preventDefault();

            const form = $(e.currentTarget);
            const button = form.find('input[type="submit"]');

            $.ajax({
                url: bkxDevSDK.ajaxurl,
                type: 'POST',
                data: form.serialize() + '&action=bkx_save_sdk_settings&nonce=' + bkxDevSDK.nonce,
                beforeSend: function() {
                    button.prop('disabled', true).val(bkxDevSDK.strings.saving);
                },
                success: function(response) {
                    button.prop('disabled', false).val('Save Settings');

                    if (response.success) {
                        const notice = $('<div class="notice notice-success is-dismissible"><p>' + bkxDevSDK.strings.saved + '</p></div>');
                        form.before(notice);
                        setTimeout(() => notice.fadeOut(), 3000);
                    } else {
                        alert(response.data.message || bkxDevSDK.strings.error);
                    }
                },
                error: function() {
                    button.prop('disabled', false).val('Save Settings');
                    alert(bkxDevSDK.strings.error);
                }
            });
        }
    };

    // Initialize on document ready.
    $(document).ready(function() {
        BKXDevSDK.init();
    });

})(jQuery);
