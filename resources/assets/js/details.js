$(function()
{
	var toggleMethodInput = function()
	{
		var val;
		val = $('#form_payee input[name="form[payee]"]:checked').val();

		if ('retailer' == val || 'none' == val) {
			$('#form_refund_method').parents('.field-wrap').hide(0);
		}
		else {
			$('#form_refund_method').parents('.field-wrap').show(0);
		}
	};

	$('#form_payee input[name="form[payee]"]').change(function()
	{
		toggleMethodInput();
	});

	toggleMethodInput();
});