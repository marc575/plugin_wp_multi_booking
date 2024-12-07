jQuery(document).ready(function($) {
    const priceCalculator = {
        init: function() {
            this.bindEvents();
            this.setupParticipantFields();
        },

        bindEvents: function() {
            $(document).on('change', '.participant-email', this.handleEmailChange.bind(this));
            $(document).on('keyup', '.participant-email', this.debounce(this.handleEmailChange.bind(this), 500));
        },

        setupParticipantFields: function() {
            // Initialiser les champs existants
            $('.participant-email').each(function() {
                if ($(this).val()) {
                    priceCalculator.handleEmailChange.call(this);
                }
            });
        },

        handleEmailChange: function(e) {
            const $input = $(this);
            const email = $input.val();
            
            if (!email || !this.validateEmail(email)) {
                return;
            }

            this.checkParticipantStatus(email, $input);
        },

        checkParticipantStatus: function(email, $input) {
            $.ajax({
                url: degbAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'calculate_participant_price',
                    email: email,
                    nonce: degbAjax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        priceCalculator.updatePrices(response.data, $input);
                    }
                }
            });
        },

        updatePrices: function(data, $input) {
            const $row = $input.closest('.participant-row');
            $row.find('.participant-base-price').text(data.base_price);
            $row.find('.participant-tps').text(data.tps);
            $row.find('.participant-tvq').text(data.tvq);
            $row.find('.participant-total').text(data.total);
            
            this.updateTotalPrice();
        },

        updateTotalPrice: function() {
            let totalBase = 0;
            let totalTPS = 0;
            let totalTVQ = 0;
            let grandTotal = 0;

            $('.participant-row').each(function() {
                totalBase += parseFloat($(this).find('.participant-base-price').text() || 0);
                totalTPS += parseFloat($(this).find('.participant-tps').text() || 0);
                totalTVQ += parseFloat($(this).find('.participant-tvq').text() || 0);
            });

            grandTotal = totalBase + totalTPS + totalTVQ;

            $('#total-base-price').text(totalBase.toFixed(2));
            $('#total-tps').text(totalTPS.toFixed(2));
            $('#total-tvq').text(totalTVQ.toFixed(2));
            $('#grand-total').text(grandTotal.toFixed(2));
        },

        validateEmail: function(email) {
            const re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
            return re.test(String(email).toLowerCase());
        },

        debounce: function(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func.apply(this, args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }
    };

    // Initialisation
    priceCalculator.init();
});
