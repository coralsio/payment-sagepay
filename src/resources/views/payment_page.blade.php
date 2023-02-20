<div id="content">
    <progress value="0" max="200" id="progressBar">

    </progress>
    <div id="contentHeader">
        Your Authentication Required
    </div>
    <p>
        Please click the button below to continue.
    </p>

    <form action="{{ $url }}" method="post" id="sagepay-form">
        @foreach($redirect_data as $redirect_key => $redirect_value)
            <input type="hidden" name="{{$redirect_key}}" value="{{ $redirect_value }}"/>
        @endforeach
        <input type="submit" class="btn btn-primary" value="Click to continue"/>
    </form>
</div>

<script type="text/javascript">
    window.onload = function () {
        let timeLeft = 200;

        let redirectTimer = setInterval(function () {
            if (timeLeft <= 0) {
                clearInterval(redirectTimer);
            }

            document.getElementById("progressBar").value = 200 - timeLeft;

            timeLeft -= 1;
        }, 10);


        setTimeout(paymentInitiation, 2000);
    }

    function paymentInitiation() {
        document.getElementById('sagepay-form').submit();
    }
</script>
