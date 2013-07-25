#!/bin/bash

PWD=$(pwd);

for D in *; do
    if [ -d "${D}" ]; then
        mysql -usqluser -psqlpass ${D} -e "UPDATE core_config_data SET value=\"America/Los_Angeles\" WHERE path LIKE \"%timezone%\"";
    fi
done
