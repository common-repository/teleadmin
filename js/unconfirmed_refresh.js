jQuery(document).ready(function ($) {

    setInterval(function() {
        jQuery.post(ajaxurl, {
            'action': 'teleadmin-unconfirmed-refresh-ajax',
            '_wpnonce': $('#teleadmin-unconfirmed-refresh-ajax').data('nonce'),
            'last-known-chat-id': $('#last-known-chat-id').val()
        }, function (response) {
            if (response === '') return;
            $('#chats-filter').html(response);
        });
    }, 2000);

});