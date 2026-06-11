<!Doctype html>
<head>
    <title>Invoice &rarr; <?php echo $recipient_name; ?></title>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <style>
    <?php echo isset($styles) ? $styles : '';?>
    * {
        line-height: 1.444;
    }
    #invoice table {
        width: 100%!important;
    }
    .store-name {
        font-size: 26px!important;
    }
    .no-print, .logo-area {
        display: none!important;
    }
    .text-center {
        text-align: center;
    }
    </style>
</head>
<body>
    <h4>
        <strong>Dear <?php echo $recipient_name; ?>,</strong>
    </h4>
    <p><?php echo trans('text_thank_you_for_choosing'); ?> <?php echo $store_name; ?>.   <?php echo trans('text_summary_of_purchase'); ?> </p>

    <div id="invoice">
        <?php echo html_entity_decode($body); ?>
    </div>

    <br/><br/><b><?php echo trans('text_thanks_regards'); ?></b>
    <br/>Admin, <?php echo $store_name; ?>, <?php echo $store_address; ?>
</body>
</html>