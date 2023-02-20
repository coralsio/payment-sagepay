<script type="text/javascript">
    function onlyNumbers(value) {
        return value.replace(/[^0-9]/g, '');
    }

    $(document).on('keyup', '#sagePay_cvv, #sagePay_number', function (event) {
        $(this).val(onlyNumbers($(this).val()));
    });
</script>
