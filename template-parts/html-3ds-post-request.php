<?php
$args = [
    'receiptId' => WC()->session->get('3Dsecure')['receiptId'],
    'user_id' => WC()->session->get('3Dsecure')['user_id']
];
$tokenize = !empty(WC()->session->get('3Dsecure')['tokenize']) ? WC()->session->get('3Dsecure')['tokenize'] : false;
if ($tokenize) {
    $args['tokenize'] = 'tokenize';
}
$data = base64_encode(implode(';', $args));
$session = WC()->session->get('3Dsecure');
?>
<html>
<head>
    <title>3Dsecure request</title>
</head>
<body onload="document.form.submit()">
<form name="form" action="<?php echo $session['acsUrl']; ?>" method="POST">
    <input type="hidden" name="PaReq" value="<?php echo $session['paReq']; ?>">
    <?php if (!empty($session['md'])): ?>
        <input type="hidden" name="md" value="<?php echo $session['md']; ?>">
    <?php endif; ?>
    <input type="hidden" name="TermUrl" value="<?php echo $this->webhook_root . '3ds-complete/?data=' . $data; ?>"/>
</form>
</body>
</html>
