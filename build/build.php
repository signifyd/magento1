#!/usr/bin/php -q
<?php

$longopts  = array(
    "path:",
    "config:",
    "version::",
    "copy",
    "v1::",
    "v2::",
);

$options = getopt('', $longopts);

if (!isset($options['path'], $options['config'])) {
    echo "Usage: ./build.php --path='magento/instance/path' --config='config/file.php' [--version='1.0.0'] [--v1='path/to/test/instance'] [--v2='path/to/test/instance' [--copy]\n";
    
    return;
}

echo "Setting up build system...\n";

$current_dir = getcwd();

$path = $options['path'];

chdir($path);

include($path . '/app/Mage.php');

Mage::app();

$config = array();

if (isset($options['config'])) {
    $config = include($options['config']);
}

if (isset($options['version'])) {
    $config['version'] = $options['version'];
}

if (!isset($config['version_ids'])) {
    echo "Error: Version ids not set in config file\n";
    
    return;
}

try {
    $connect = Mage::getModel('connect/extension');
    $connect->addData($config);
    
    $session = Mage::getSingleton('connect/session');
    $session->setCustomExtensionPackageFormData($config);
    echo "Setup complete.\n";
    echo "Starting build...\n";
    
    $packageVersion = $config['version_ids'];
    if (is_array($packageVersion)) {
        if (in_array(Mage_Connect_Package::PACKAGE_VERSION_2X, $packageVersion)) {
            echo "Building v2 package\n";
            $connect->createPackage();
        }
        if (in_array(Mage_Connect_Package::PACKAGE_VERSION_1X, $packageVersion)) {
            echo "Building v1 package\n";
            $connect->createPackageV1x();
        }
    }
    
    echo "Build complete.\n";
    
    chdir($current_dir);
    
    $package_name = $connect->getData('name') . '-' . $connect->getData('version') . '.tgz';
    $package = $path.$config['package_path']. '/' .$package_name;
    
    if (isset($options['copy'])) {
        echo "Copying package to working directory\n";
        shell_exec("cp $package $current_dir;");
    }
    
    if (isset($options['v1'])) {
        echo "Testing v1 install...\n";
        
        $test = $options['v1'];
        $cmd = "cp $package $test;\n";
        echo $cmd;
        shell_exec($cmd);
        
        chdir($test);
        
        $cmd = "./pear install -f $package_name;\n";
        echo $cmd;
        shell_exec($cmd);
        
        $cmd = "rm $test/$package_name;\n";
        echo $cmd;
        shell_exec($cmd);
        
        echo "Test complete.\n";
    }
    
    if (isset($options['v2'])) {
        echo "Testing v2 install...\n";
        
        $test = $options['v2'];
        $cmd = "cp $package $test;\n";
        echo $cmd;
        shell_exec($cmd);
        
        chdir($test);
        
        $cmd = "./mage install $package_name;\n";
        echo $cmd;
        shell_exec($cmd);
        
        $cmd = "rm $test/$package_name;\n";
        echo $cmd;
        shell_exec($cmd);
        
        echo "Test complete.\n";
    }
    
    echo "Done.\n";
} catch (Exception $e) {
    echo $e->__toString();
}


