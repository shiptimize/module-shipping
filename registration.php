<?php 
$isdevmachine = 0; 

if(isset($file)){ 
    //Support for links breaks on mage 2.3 only supported for mage 2.4 
    $installdir = dirname($file);
    $installdir = substr($installdir, 0, stripos($installdir, 'app')); 
    $isdevmachine =  file_exists($installdir . 'isdevmachine'); 
}

\Magento\Framework\Component\ComponentRegistrar::register(
    \Magento\Framework\Component\ComponentRegistrar::MODULE,
    'Shiptimize_Shipping',
   $isdevmachine && isset($file) && realpath($file) == __FILE__ ? dirname($file) : __DIR__
);
