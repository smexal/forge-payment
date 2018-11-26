var forgePayment = {
    running : false,

    init : function() {

        $(".payment-trigger").each(function() {
            $(this).on("click", function() {
                var data = $(this).data();
                forgePayment.openOverlay(data);
            });
        });

        // bind "escape" key to close the overlay
        $(document).keyup(function(e) {
            if($("body").hasClass("payment-panel-open") && e.keyCode == 27) {
                forgePayment.hideOverlay();
            }
        });

        forgePayment.deliverySubmitHandle();
        forgePayment.deliveryInputChange();
        forgePayment.deliveryDifferentAddressCheckbox();
    },

    deliveryDifferentAddressCheckbox : function() {
        // initialy hidden
        $(".collapsed-address-fields").hide();

        $("#delivery_custom_address").on('change', function() {
            if($(this).is(':checked')) {
                $(".collapsed-address-fields").show();
            } else {
                $(".collapsed-address-fields").hide();
            }
        });
    },


    deliverySubmitHandle : function() {
        $("#payment-overlay.delivery").find("button.btn-primary").each(function() {
            var field = $(this);
            $(this).on('click', function(e) {
                field.closest('.tab-content').addClass('loading');
                e.stopImmediatePropagation();
                e.preventDefault();
                var data = field.closest('form').serialize();
                $.ajax({
                    method: 'POST',
                    url: field.closest('form').data('api') + '/forge-payment/submit-delivery',
                    data : data
                }).done(function(data) {
                    var tabContent = field.closest('.tab-content').addClass('transition');
                    setTimeout(function() {
                        tabContent.html(data.new_data);
                        forgePayment.deliverySetActiveTab(data.active_step);
                        $(document).trigger("ajaxReload");
                    }, 300);
                    setTimeout(function() {
                        tabContent.removeClass('loading').removeClass('transition');
                    }, 700);
                });
            });
        });
    },

    deliverySetActiveTab : function(active) {
        $(".tab-bar h4").each(function() {
            $(this).removeClass('active');
        });
        $(".tab-bar h4#" + active).addClass('active');
        var position = $(".tab-bar h4#" + active).position();
        $(".tab-bar .sub-bar").css({
            'left' : position.left + 'px',
            'width' : $(".tab-bar h4#" + active).width()
        });
    },

    deliveryInputChange : function() {
        var update = false;
        $("#payment-overlay.delivery").find("input").each(function() {
            $(this).on('input', function() {
                clearTimeout(update);
                var field = $(this);
                update = setTimeout(function() {
                    var data = field.closest('form').serialize();
                    $.ajax({
                        method: 'POST',
                        url: field.closest('form').data('api') + '/forge-payment/delivery-check',
                        data : data
                    }).done(function(data) {
                        if(data.status == "data-incomplete") {
                            field.closest('form').find('button').each(function() {
                                $(this).attr('disabled', 'disabled');
                            });
                        } else {
                            field.closest('form').find('button').each(function() {
                                $(this).removeAttr('disabled');
                            });
                        }
                    });
                }, 1500);
            });
        });
    },

    hideOverlay : function() {
        if(! $("body").find("#payment-overlay .pay-panel").hasClass('show')) {
            return;
        }
        forgePayment.running = true;
        $("body").removeClass("payment-panel-open");
        $("body").find("#payment-overlay .pay-panel").removeClass("show");
        setTimeout(function() {
            $("body").find("#payment-overlay").remove();
            forgePayment.running = false;
        }, 800);
    },

    openOverlay : function(data) {
        if(forgePayment.running) {
            return;
        }
        $(document).trigger("hideOverlays");
        forgePayment.running = true;
        if(!$("body").hasClass("payment-panel-open")) {
            $("body").addClass("payment-panel-open");
        }
        $("body").find("#payment-overlay").remove();
        var deliveryClass = '';
        if(data.delivery == true) {
            deliveryClass = 'delivery';
        }
        var overlay = $(
            "<div class="+deliveryClass+" id='payment-overlay'><div class='pay-panel loading'>" +
                "<div class='content'></div>" +
                "<div class='close'></div>" +
            "</div></div>"
        );
        overlay.appendTo("body");
        overlay.find(".close").on("click", function() {
            forgePayment.hideOverlay();
        });
        setTimeout(function()  {
            overlay.find(".pay-panel").addClass("show");
            forgePayment.load(data, overlay.find(".pay-panel"))
        }, 100);
    }, 

    load : function(data, modal) {
        data.api = data.api.replace('http://', '//');
        $.ajax({
            method: 'POST',
            url: data.api + 'forge-payment/modal/',
            data : data
        }).done(function(data) {
            modal.addClass("stopload");
            setTimeout(function() {
                modal.find(".content").html(data.content);
                forgePayment.running = false;
                $(document).trigger("ajaxReload");
            }, 300);
        });
    }
};

$(document).ready(forgePayment.init);
$(document).on("ajaxReload", forgePayment.init);
$(document).on("hideOverlays", forgePayment.hideOverlay);