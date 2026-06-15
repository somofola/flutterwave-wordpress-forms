/**
 * Frontend logic for Payment Forms for Flutterwave.
 *
 * Uses Flutterwave Inline Checkout v3 (FlutterwaveCheckout). All amounts in this
 * file are in major units (NGN/USD/etc.) — no kobo multiplication. The fee
 * calculator below internally multiplies by 100 only so that the existing
 * integer-arithmetic-style fee model maps cleanly to Flutterwave's
 * percentage/threshold/cap configuration in settings.
 */

function PffFlutterwaveFee()
{

    this.DEFAULT_PERCENTAGE = 0.014;
    this.DEFAULT_ADDITIONAL_CHARGE = 0;
    this.DEFAULT_THRESHOLD = 250000;
    this.DEFAULT_CAP = 200000;

    this.__initialize = function () {

        this.percentage = this.DEFAULT_PERCENTAGE;
        this.additional_charge = this.DEFAULT_ADDITIONAL_CHARGE;
        this.threshold = this.DEFAULT_THRESHOLD;
        this.cap = this.DEFAULT_CAP;

        if (window && window.KKD_FLUTTERWAVE_CHARGE_SETTINGS) {
            this.percentage = window.KKD_FLUTTERWAVE_CHARGE_SETTINGS.percentage;
            this.additional_charge = window.KKD_FLUTTERWAVE_CHARGE_SETTINGS.additional_charge;
            this.threshold = window.KKD_FLUTTERWAVE_CHARGE_SETTINGS.threshold;
            this.cap = window.KKD_FLUTTERWAVE_CHARGE_SETTINGS.cap;
        }

    };

    this.chargeDivider = 0;
    this.crossover = 0;
    this.flatlinePlusCharge = 0;
    this.flatline = 0;

    this.withPercentage = function (percentage) {
        this.percentage = percentage;
        this.__setup();
    };

    this.withAdditionalCharge = function (additional_charge) {
        this.additional_charge = additional_charge;
        this.__setup();
    };

    this.withThreshold = function (threshold) {
        this.threshold = threshold;
        this.__setup();
    };

    this.withCap = function (cap) {
        this.cap = cap;
        this.__setup();
    };

    this.__setup = function () {
        this.__initialize();
        this.chargeDivider = this.__chargeDivider();
        this.crossover = this.__crossover();
        this.flatlinePlusCharge = this.__flatlinePlusCharge();
        this.flatline = this.__flatline();
    };

    this.__chargeDivider = function () { return 1 - this.percentage; };
    this.__crossover = function () { return this.threshold * this.chargeDivider - this.additional_charge; };
    this.__flatlinePlusCharge = function () { return (this.cap - this.additional_charge) / this.percentage; };
    this.__flatline = function () { return this.flatlinePlusCharge - this.cap; };

    this.addFor = function (amount) {
        if (amount > this.flatline) {
            return parseInt(Math.round(amount + this.cap));
        } else if (amount > this.crossover) {
            return parseInt(Math.round((amount + this.additional_charge) / this.chargeDivider));
        } else {
            return parseInt(Math.round(amount / this.chargeDivider));
        }
    };

    this.__setup();
}

(function ($) {
    "use strict";

    /**
     * Build the FlutterwaveCheckout config and launch the modal.
     *
     * @param {object} data       Response from the form/retry submit endpoint.
     * @param {object} confirmCtx Extra payload to send on verification (retry flag, quantity, nonces).
     * @param {jQuery} $form      The form element (used for resetting / scrolling after success).
     * @param {jQuery} self       The same form element, but as the message-insertion target.
     * @param {boolean} isRetry   Whether this is a retry-submit flow.
     */
    function launchFlutterwaveCheckout(data, confirmCtx, $form, self, isRetry) {
        var names     = (data.name || "").split(" ");
        var firstName = names[0] || "";
        var lastName  = names[1] || "";

        // Push the plugin identifier into custom fields so it shows up on the dashboard.
        if (Array.isArray(data.custom_fields)) {
            data.custom_fields.push({
                "display_name":  "Plugin",
                "variable_name": "plugin",
                "value":         "pff-flutterwave"
            });
        }

        // Flatten custom fields into a meta object — Flutterwave v3 wants meta as a
        // flat key/value bag, not Paystack's metadata.custom_fields array.
        // Flutterwave's events tracker rejects keys/values containing '.', so sanitize.
        function sanitizeKey(k) { return String(k).replace(/[^A-Za-z0-9_]/g, "_"); }
        function sanitizeVal(v) { return (v === undefined || v === null) ? "" : String(v).replace(/\./g, "_"); }

        var meta = {};
        if (Array.isArray(data.custom_fields)) {
            data.custom_fields.forEach(function (f) {
                if (f && f.variable_name) {
                    meta[sanitizeKey(f.variable_name)] = sanitizeVal(f.value);
                }
            });
        }

        // Build a dot-free consumer id from tx_ref so the inline events tracker doesn't
        // fall back to email (which contains dots and triggers "customer.id must not contain '.'").
        var safeConsumerId = String(data.code || "").replace(/\./g, "_");

        var hasSubaccount = data.subaccount && data.subaccount !== "null" && data.subaccount !== "";

        var checkoutConfig = {
            public_key:  pffSettings.key,
            tx_ref:      data.code,
            amount:      Number(data.total),
            currency:    data.currency || "NGN",
            redirect_url: window.location.href.split("?")[0],
            payment_options: "card, banktransfer, ussd, account, mobilemoneyghana, mobilemoneyrwanda, mobilemoneyuganda, mobilemoneyzambia",
            customer: {
                id: safeConsumerId,
                email: data.email,
                phone_number: String(meta.phone_number || meta.phone || "").replace(/\./g, ""),
                name: (firstName + " " + lastName).trim().replace(/\./g, "")
            },
            customizations: {
                title:       String(data.title || pffSettings.sitename || "Payment").replace(/\./g, " "),
                description: String(data.description || "").replace(/\./g, " "),
                logo:        data.logo || pffSettings.logo
            },
            meta: meta,
            callback: function (response) {
                // Flutterwave Inline does NOT auto-close after callback — must do it here.
                if (typeof window.closePaymentModal === "function") {
                    window.closePaymentModal();
                }

                // Flutterwave's inline callback fires with { transaction_id, tx_ref, status, ... }
                if (!response || response.status !== "successful") {
                    self.before('<div class="alert-danger">Payment was not successful.</div>');
                    $.unblockUI();
                    return;
                }

                $.blockUI({ message: "Please wait..." });

                var payload = $.extend({
                    action:         "pff_flutterwave_confirm_payment",
                    code:           response.tx_ref,
                    transaction_id: response.transaction_id,
                    nonce:          data.confirmNonce
                }, confirmCtx || {});

                $.post($form.attr("action"), payload, function (newdata) {
                    var resp = JSON.parse(newdata);
                    if (resp.result == "success2") {
                        window.location.href = resp.link;
                        return;
                    }
                    if (resp.result == "success") {
                        if (isRetry) {
                            // Strip query params on the current URL to clear retry context.
                            var currentUrl = window.location.href;
                            var url = new URL(currentUrl);
                            window.location.href = url.origin + url.pathname;
                        } else {
                            $(".flutterwave-form")[0].reset();
                            $("html,body").animate(
                                { scrollTop: $(".flutterwave-form").offset().top - 110 },
                                500
                            );
                            self.before('<div class="alert-success">' + resp.message + '</div>');
                            $form.find("input, select, textarea").each(function () {
                                $(this).css({ "border-color": "#d1d1d1", "background-color": "#fff" });
                            });
                            calculateFees();
                            $.unblockUI();
                        }
                    } else {
                        self.before('<div class="alert-' + (isRetry ? 'danger' : 'danger') + '">' + resp.message + '</div>');
                        $.unblockUI();
                    }
                });
            },
            onclose: function () {
                $.unblockUI();
            }
        };

        if (hasSubaccount) {
            // Flutterwave routes split payments via a subaccounts array. Charge type and
            // ratio are configured on the Flutterwave dashboard for the subaccount.
            checkoutConfig.subaccounts = [ { id: data.subaccount } ];
        }

        // For plan subscriptions, attach the plan id; FlutterwaveCheckout will then
        // create the subscription against the configured payment plan.
        if (data.plan && data.plan !== "none" && data.plan !== "" && data.plan !== "no") {
            checkoutConfig.payment_plan = data.plan;
        }

        FlutterwaveCheckout(checkoutConfig);
    }

    var amountField;

    $(document).ready(function () {

        // Handle Flutterwave redirect return — async methods (bank transfer/account/ussd)
        // dismiss the inline modal by redirecting back to redirect_url with these params.
        (function handleFlutterwaveReturn() {
            var params = new URLSearchParams(window.location.search);
            var status = params.get("status");
            var txRef  = params.get("tx_ref");
            var txnId  = params.get("transaction_id");
            if (!status || !txRef) { return; }

            var $form = $(".flutterwave-form").first();
            if (!$form.length) { return; }

            if (status !== "successful" && status !== "completed") {
                $form.before('<div class="alert-danger">Payment was not completed.</div>');
                history.replaceState(null, "", window.location.pathname);
                return;
            }

            $.blockUI({ message: "Verifying payment..." });
            $.post($form.attr("action"), {
                action:         "pff_flutterwave_confirm_payment",
                code:           txRef,
                transaction_id: txnId,
                nonce:          (typeof pffSettings !== "undefined" && pffSettings.confirmNonce) ? pffSettings.confirmNonce : ""
            }, function (newdata) {
                $.unblockUI();
                try {
                    var resp = JSON.parse(newdata);
                    if (resp.result == "success2") { window.location.href = resp.link; return; }
                    var msg = resp.message || resp.error_message || "Payment processed.";
                    if (resp.result == "success") {
                        var supportEmail = (typeof pffSettings !== "undefined" && pffSettings.supportEmail) ? pffSettings.supportEmail : "";
                        var homeUrl      = (typeof pffSettings !== "undefined" && pffSettings.homeUrl) ? pffSettings.homeUrl : "/";
                        var labels       = (typeof pffSettings !== "undefined" && pffSettings.i18n) ? pffSettings.i18n : { contactSupport: "Contact Support", goHome: "Return Home" };
                        var supportHref  = supportEmail ? ("mailto:" + supportEmail + "?subject=" + encodeURIComponent("Payment support request")) : "#";
                        var panel = '<div class="pff-success-panel" style="padding:32px 24px;text-align:center;border:2px solid #472A7A;border-radius:8px;background:#fff;max-width:560px;margin:24px auto">' +
                            '<div style="font-size:48px;line-height:1;color:#1a7e1a;margin-bottom:12px">&#10004;</div>' +
                            '<h3 style="color:#472A7A;margin:0 0 12px;font-size:22px">Payment Successful</h3>' +
                            '<p style="color:#333;margin:0 0 24px;font-size:15px;line-height:1.5">' + msg + '</p>' +
                            '<div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap">' +
                                '<a href="' + supportHref + '" style="display:inline-block;padding:10px 20px;background:#F36F21;color:#fff;text-decoration:none;border-radius:4px;font-weight:600">' + labels.contactSupport + '</a>' +
                                '<a href="' + homeUrl + '" style="display:inline-block;padding:10px 20px;background:#472A7A;color:#fff;text-decoration:none;border-radius:4px;font-weight:600">' + labels.goHome + '</a>' +
                            '</div>' +
                        '</div>';
                        $form.hide();
                        $form.before(panel);
                    } else {
                        $form.before('<div class="alert-danger">' + msg + '</div>');
                    }
                } catch (e) {
                    $form.before('<div class="alert-danger">Verification response invalid.</div>');
                }
                history.replaceState(null, "", window.location.pathname);
            });
        })();

        if ($("#pf-vamount").length) {
            amountField = $("#pf-vamount");
            calculateTotal();
        } else {
            amountField = $("#pf-amount");
        }

        var max = 10;
        amountField.keydown(function (e) { format_validate(max, e); });
        amountField.keyup(function () { checkMinimumVal(); });

        $.fn.digits = function () {
            return this.each(function () {
                $(this).text($(this).text().replace(/(\d)(?=(\d\d\d)+(?!\d))/g, "$1,"));
            });
        };

        calculateFees();

        $(".pf-number").keydown(function (event) {
            if (event.keyCode == 46 || event.keyCode == 8 || event.keyCode == 9
                || event.keyCode == 27 || event.keyCode == 13
                || (event.keyCode == 65 && event.ctrlKey === true)
                || (event.keyCode >= 35 && event.keyCode <= 39)) {
                return;
            } else if (event.shiftKey
                || ((event.keyCode < 48 || event.keyCode > 57)
                    && (event.keyCode < 96 || event.keyCode > 105))) {
                event.preventDefault();
            }
        });

        if ($("#pf-quantity").length) {
            $("#pf-quantity").on('change', function () { checkMinimumVal(); });
            calculateTotal();
        }

        $("#pf-quantity, #pf-vamount, #pf-amount").on("change", function () {
            calculateTotal();
            calculateFees();
        });

        $(".flutterwave-form").on("submit", function (e) {
            var requiredFieldIsInvalid = false;
            e.preventDefault();

            $("#pf-agreementicon").removeClass("rerror");
            $(this).find("input, select, textarea").each(function () { $(this).removeClass("rerror"); });

            var email = $(this).find("#pf-email").val();
            var amount;
            if ($("#pf-vamount").length) {
                amount = $("#pf-vamount").val();
                calculateTotal();
            } else {
                amount = $(this).find("#pf-amount").val();
            }

            if (!(Number(amount) > 0)) {
                $(this).find("#pf-amount,#pf-vamount").addClass("rerror");
                $("html,body").animate({ scrollTop: $(".rerror").offset().top - 110 }, 500);
                return false;
            }
            if (!validateEmail(email)) {
                $(this).find("#pf-email").addClass("rerror");
                $("html,body").animate({ scrollTop: $(".rerror").offset().top - 110 }, 500);
                return false;
            }
            if (checkMinimumVal() == false) {
                $(this).find("#pf-amount").addClass("rerror");
                $("html,body").animate({ scrollTop: $(".rerror").offset().top - 110 }, 500);
                return false;
            }

            $(this).find("input, select, text, textarea").filter("[required]").filter(function () {
                return this.value === "";
            }).each(function () {
                $(this).addClass("rerror");
                requiredFieldIsInvalid = true;
            });

            if ($("#pf-agreement").length && !$("#pf-agreement").is(":checked")) {
                $("#pf-agreementicon").addClass("rerror");
                requiredFieldIsInvalid = true;
            }

            if (requiredFieldIsInvalid) {
                $("html,body").animate({ scrollTop: $(".rerror").offset().top - 110 }, 500);
                return false;
            }

            var self  = $(this);
            var $form = $(this);

            $.blockUI({ message: "Please wait..." });

            var formdata = new FormData(this);

            $.ajax({
                url: $form.attr("action"),
                type: "POST",
                data: formdata,
                mimeTypes: "multipart/form-data",
                contentType: false,
                cache: false,
                processData: false,
                dataType: "JSON",
                success: function (data) {
                    $.unblockUI();
                    if (data.result == "success") {
                        $("#pf-nonce").val(data.invoiceNonce);
                        launchFlutterwaveCheckout(
                            data,
                            { quantity: data.quantity },
                            $form,
                            self,
                            false
                        );
                    } else {
                        alert(data.error_message || data.message);
                    }
                },
                error: function (xhr, status, error) {
                    console.log("An error occurred", xhr, status, error);
                }
            });
        });

        $(".retry-form").on("submit", function (e) {
            var self  = $(this);
            var $form = $(this);
            e.preventDefault();

            $.blockUI({ message: "Please wait..." });
            var formdata = new FormData(this);

            $.ajax({
                url: $form.attr("action"),
                type: "POST",
                data: formdata,
                mimeTypes: "multipart/form-data",
                contentType: false,
                cache: false,
                processData: false,
                dataType: "JSON",
                success: function (data) {
                    $.unblockUI();
                    if (data.result == "success") {
                        $("#pf-nonce").val(data.retryNonce);
                        launchFlutterwaveCheckout(
                            data,
                            { quantity: data.quantity, retry: true },
                            $form,
                            self,
                            true
                        );
                    } else {
                        alert(data.message);
                    }
                },
                error: function (xhr, status, error) {
                    console.log("An error occurred", xhr, status, error);
                }
            });
        });

        function checkMinimumVal() {
            if ($("#pf-amount").length) {
                var min_amount = Number($("#pf-amount").attr('min'));
                var amt = Number($("#pf-amount").val());
                var quantity = 1;

                if ($("#pf-quantity").length) {
                    quantity = $("#pf-quantity").val();
                }

                amt = amt * quantity;

                if (min_amount > 0 && amt < min_amount) {
                    $("#pf-min-val-warn").text("Amount cannot be less than the minimum amount");
                    return false;
                } else {
                    $("#pf-min-val-warn").text("");
                    $("#pf-amount").removeClass("rerror");
                }
            }
        }

        function format_validate(max, e) {
            var value = amountField.text();
            if (e.which != 8 && value.length > max) {
                e.preventDefault();
            }
            if ($.inArray(e.keyCode, [46, 8, 9, 27, 13, 110, 190]) !== -1
                || (e.keyCode == 65 && e.ctrlKey === true)
                || (e.keyCode == 67 && e.ctrlKey === true)
                || (e.keyCode == 88 && e.ctrlKey === true)
                || (e.keyCode >= 35 && e.keyCode <= 39)) {
                calculateFees();
                return;
            }
            if ((e.shiftKey || (e.keyCode < 48 || e.keyCode > 57))
                && (e.keyCode < 96 || e.keyCode > 105)) {
                e.preventDefault();
            } else {
                calculateFees();
            }
        }

        function calculateTotal() {
            var unit;
            if ($("#pf-vamount").length) { unit = $("#pf-vamount").val(); }
            else { unit = $("#pf-amount").val(); }
            var quant = $("#pf-quantity").val();
            var newvalue = unit * quant;
            if (quant == "" || quant == null) {
                quant = 1;
            } else {
                $("#pf-total").val(newvalue);
            }
        }

        function calculateFees(transaction_amount) {
            setTimeout(function () {
                transaction_amount = transaction_amount || parseInt(amountField.val());
                var currency = $("#pf-currency").val();
                var quant = $("#pf-quantity").val();
                if ($("#pf-vamount").length) {
                    var name = $("#pf-vamount option:selected").attr("data-name");
                    $("#pf-vname").val(name);
                }
                var total, fees;
                if (transaction_amount == "" || transaction_amount == 0
                    || transaction_amount.length == 0 || transaction_amount == null
                    || isNaN(transaction_amount)) {
                    total = 0;
                    fees = 0;
                } else {
                    var obj = new PffFlutterwaveFee();
                    obj.withAdditionalCharge(pffSettings.fee.adc);
                    obj.withThreshold(pffSettings.fee.ths);
                    obj.withCap(pffSettings.fee.cap);
                    obj.withPercentage(pffSettings.fee.prc);
                    if (quant) {
                        transaction_amount = transaction_amount * quant;
                    }
                    // The fee model is calibrated on minor-unit arithmetic, so we scale
                    // up only for the calculation and scale back down for display.
                    total = obj.addFor(transaction_amount * 100) / 100;
                    fees = total - transaction_amount;
                }
                $(".pf-txncharge").hide().html(currency + " " + fees.toFixed(2)).show().digits();
                $(".pf-txntotal").hide().html(currency + " " + total.toFixed(2)).show().digits();
            }, 100);
        }

        function validateEmail(email) {
            var re = /^(([^<>()[\]\\.,;:\s@\"]+(\.[^<>()[\]\\.,;:\s@\"]+)*)|(\".+\"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
            return re.test(email);
        }

    });
})(jQuery);
