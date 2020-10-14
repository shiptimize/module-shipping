<?php 

if (interface_exists("Magento\Framework\App\CsrfAwareActionInterface"))
    include __DIR__ . "/Update23.php";
else
    include __DIR__ . "/Update22.php";