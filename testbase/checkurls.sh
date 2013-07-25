#!/bin/bash

PWD=$(pwd);

for D in *; do
    if [ -d "${D}" ]; then
        mysql -usqluser -psqlpass ${D} -e "SELECT * FROM core_config_data WHERE path LIKE \"%base_url%\"";
    fi
done
