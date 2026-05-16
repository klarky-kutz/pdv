/**
 * Product Code Generator - EAN-13 Sequential
 * Separated file to avoid AngularJS conflicts
 */

(function($) {
    'use strict';
    
    if (typeof $ === 'undefined') {
        console.error('jQuery is required for product-code-generator.js');
        return;
    }

    // Initialize on document ready
    $(document).ready(function() {
        initProductCodeGenerator();
    });

    function initProductCodeGenerator() {
        if (!window.baseUrl) {
            return;
        }

        // Preenche sequencial automaticamente (se estiver vazio)
        var $pcode = $(document).find('#p_code');
        if ($pcode.length && !$pcode.val()) {
            fetchNextCode('seq', function(code) {
                if (code) {
                    $pcode.val(code);
                }
            });
        }

        // Remove existing handler if any
        $('.random_num').off('click.productCodeGenerator');

        // Clique no ícone: gerar ALEATÓRIO mantendo prefixo 7898
        $('.random_num').on('click.productCodeGenerator', function(e) {
            e.preventDefault();
            generateRandomCode($(this));
        });
    }

    function fetchNextCode(mode, cb) {
        var m = mode || 'seq';
        $.ajax({
            url: window.baseUrl + '_inc/product.php?action_type=NEXT_PCODE&mode=' + encodeURIComponent(m) + '&prefix=7898',
            method: 'GET',
            dataType: 'json',
            timeout: 10000,
            success: function(response) {
                if (response && (response.p_code || response.code)) {
                    cb(response.p_code || response.code);
                    return;
                }
                cb(null);
            },
            error: function() {
                cb(null);
            }
        });
    }

    function generateRandomCode($btn) {
        var $input = $btn.parent('.input-group').children('input');
        var $icon = $btn.find('i');

        // Save original state
        var originalIcon = $icon.attr('class');
        var originalDisabled = $btn.prop('disabled');

        // Show loading state
        $icon.removeClass('fa-random').addClass('fa-spinner fa-spin');
        $btn.prop('disabled', true);

        fetchNextCode('random', function(code) {
            if (code) {
                $input.val(code);
            } else {
                // fallback: gera random manual (sem alertas)
                fallbackToRandom($input);
            }

            // Restore original state
            $icon.attr('class', originalIcon);
            $btn.prop('disabled', originalDisabled);
        });
    }

    function fallbackToRandom($input) {
        // Fallback SEMPRE com prefixo 7898
        var prefix = '7898';
        var remain = 13 - prefix.length;
        var randomCode = prefix;
        for (var i = 0; i < remain; i++) {
            randomCode += Math.floor(Math.random() * 10);
        }
        $input.val(randomCode);
    }

    // Expose to global scope if needed
    window.ProductCodeGenerator = {
        init: initProductCodeGenerator
    };

})(jQuery);
