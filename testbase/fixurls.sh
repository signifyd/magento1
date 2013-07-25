#!/bin/bash

PWD=$(pwd);

for D in *; do
    if [ -d "${D}" ]; then
        mysql -usqluser -psqlpass ${D} -e "UPDATE core_config_data SET value=\"http://127.0.0.1/magento\" WHERE path LIKE \"%base_url%\"";
    fi
done
