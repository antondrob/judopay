<html>
<head>
    <title>3Dsecure response</title>
</head>
<body onload="document.form.submit()">
<form name="form" action="<?php echo wc_get_checkout_url(); ?>" method="POST">
    <?php if(!empty($error_code)): ?>
        <input type="hidden" name="judopay_error_code" value="<?php echo $error_code; ?>">
    <?php endif; ?>
    <?php if(!empty($error_message)): ?>
        <input type="hidden" name="judopay_error_message" value="<?php echo $error_message; ?>">
    <?php endif; ?>
</form>
</body>
</html>
