// Returns Toggle

$(function() {
    var toggleReturnsInput = function() {
        var val;
        val = $('#form_resolution').val();

        if ('refund' == val) {
            $('#form_exchangeUnit').parents('.field').hide(0);
        }
        else {
            $('#form_exchangeUnit').parents('.field').show(0);
        }
    };

    $('#form_resolution').change(function() {
        toggleReturnsInput();
    });

    toggleReturnsInput();
});