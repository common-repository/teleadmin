jQuery(document).ready(function ($) {

    $('#submit').prop('disabled', true);

    let timer;
    let check = function () {

        $('#token-validation').html('<span class="spinner is-active" style="float:none;"></span> Checking token...');
        $('#submit').prop('disabled', true);
        clearTimeout(timer);

        timer = setTimeout(function () {
            jQuery.post(ajaxurl, {
                'action': 'teleadmin-token-validation-ajax',
                '_wpnonce': $('#teleadmin-token-validation-ajax').data('nonce'),
                'token': $('#token').val()
            }, function (response) {
                let res = JSON.parse(response);
                if (res.token === $('#token').val()) {
                    if (res.hasOwnProperty('error')) {
                        $('#token-validation').html('<span style="color: red;">Error!</span> ' + res.error);
                        $('#submit').prop('disabled', true);
                    } else if (res.hasOwnProperty('username')) {
                        $('#token-validation').html('<span style="color: green;">Success!</span> Bot username is @' + res.username);
                        $('#submit').prop('disabled', false);
                    }
                }
            });
        }, 1000);
    };

    $('#token').on("input", check);
    if ($('#token').val() !== '') {
        check();
    }

});