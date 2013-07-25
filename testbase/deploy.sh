#!/bin/bash

PWD=$(pwd);

for D in *; do
    if [ -d "${D}" ]; then
        echo "cp -a ../../www/magento/app ./${D};"
        cp -a ../www/magento/app ./${D}/;
    fi
done
