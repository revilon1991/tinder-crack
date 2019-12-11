jQuery(function ($) {
    $('.phone').mask('+7 (000) 000 0000');

    var account_kit_start_login_form = $('#account_kit_start_login'),
        account_kit_start_confirm_login = $('#account_kit_confirm_login'),
        login_request_code,
        phone_number
    ;

    $('.clear-auth').click(function (e) {
        Cookies.remove('api_token');
        Cookies.remove('refresh_token');
        window.location = '/';
    });

    $(document).ready(function() {
        $('.popup-gallery').magnificPopup({
            delegate: 'a',
            type: 'image',
            tLoading: 'Loading image #%curr%...',
            mainClass: 'mfp-img-mobile',
            gallery: {
                enabled: true,
                navigateByImgClick: true,
                preload: [0,1]
            },
            image: {
                tError: '<a href="%url%">The image #%curr%</a> could not be loaded.',
                titleSrc: function(item) {
                    return item.el.attr('title') + '<small>coming soon this link to profile</small>';
                }
            }
        });
    });

    account_kit_start_login_form.submit(function (e) {
        e.preventDefault();

        account_kit_start_login_form.find('.btn-send-phone-text').hide();
        account_kit_start_login_form.find('.btn-send-phone-text-load').show();

        $.ajax({
            type: 'GET',
            url: account_kit_start_login_form.attr('action'),
            data: {
                'phone_number': account_kit_start_login_form.find('input[name=phone_number]').val(),
            },
            success: function (data) {
                login_request_code = data['login_request_code'];
                phone_number = data['phone_number'];

                account_kit_start_login_form.find('.btn-send-phone-text').show();
                account_kit_start_login_form.find('.btn-send-phone-text-load').hide();
                account_kit_start_login_form.fadeOut('slow', function () {
                    account_kit_start_confirm_login.show().fadeIn('slow');
                });
            },
            error: function () {
                noty({
                    type: 'error',
                    text: 'Ошибка. Попробуйте позже'
                }).show();

                account_kit_start_login_form.find('.btn-send-phone-text').show();
                account_kit_start_login_form.find('.btn-send-phone-text-load').hide();
            }
        });
    });

    account_kit_start_confirm_login.submit(function (e) {
        e.preventDefault();

        account_kit_start_confirm_login.find('.btn-send-sms-text').hide();
        account_kit_start_confirm_login.find('.btn-send-phone-sms-load').show();

        $.ajax({
            type: 'GET',
            url: account_kit_start_confirm_login.attr('action'),
            data: {
                'confirmation_code': account_kit_start_confirm_login.find('input[name=sms_code]').val(),
                'login_request_code': login_request_code,
                'phone_number': phone_number,
            },
            success: function (data) {
                Cookies.set('api_token', data['api_token']);
                Cookies.set('refresh_token', data['refresh_token']);
                window.location = '/';
            },
            error: function () {
                noty({
                    type: 'error',
                    text: 'Ошибка. Попробуйте позже'
                }).show();

                account_kit_start_confirm_login.find('.btn-send-sms-text').show();
                account_kit_start_confirm_login.find('.btn-send-phone-sms-load').hide();
            }
        });
    });
});