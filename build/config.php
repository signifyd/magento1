<?php

return array(
    "form_key" => "mcFhkWzshRMenR6h",
    "package_path" => "var/connect",
    "_create" => 1,
    "file_name" => '',
    "name" => "Signifyd_Connect",
    "channel" => "community",
    "version_ids" => array(
        "0" => 2,
        "1" => 1
    ),
    "summary" => "Signifyd protects e-commerce merchants from fraudulent buyers.",
    "description" => "Supports all versions of Magento",
    "license" => "OSL",
    "license_uri" => "http://opensource.org/licenses/osl-3.0.php",
    "version" => "3.1.3",
    "stability" => "stable",
    "notes" => "Supports all versions of Magento",
    "authors" => array(
        "name" => array(
            "0" => "signifyd"
        ),
        "user" => array(
            "0" => "signifyd"
        ),
        "email" => array(
            "0" => "manelis@signifyd.com"
        ),
    ),
    "depends_php_min" => "5.2.0",
    "depends_php_max" => "6.0.0",
    "depends" => array(
        "package" => array(
            "name" => array(
                "0" => null,
            ),
            "channel" => array(
                "0" => null,
            ),

            "min" => array(
                "0" => null,
            ),

            "max" => array(
                "0" => null,
            ),

            "files" => array(
                "0" =>  null,
            ),
        ),
        "extension" => array(
            "name" => array(
                "0" => 'Core'
            ),

            "min" => array(
                "0" => ''
            ),

            "max" => array(
                "0" => ''
            ),
        ),
    ),
    "contents" => array(
        "target" => array(
            "0" => 'magelocal',
            "1" => 'mageetc',
            "2" => 'magecommunity'
        ),

        "path" => array(
            "0" => '',
            "1" => 'modules/Signifyd_Connect.xml',
            "2" => 'Signifyd/Connect'
        ),

        "type" => array(
            "0" => 'file',
            "1" => 'file',
            "2" => 'dir'
        ),

        "include" => array(
            "0" => '',
            "1" => '',
            "2" => "#(etc|Model|Helper|sql)#"
        ),

        "ignore" => array(
            "0" => '',
            "1" => '',
            "2" => '',
        ),
    ),
    "page" => 1,
    "limit" => 200,
    "folder" => '',
    "package" => '',
);
