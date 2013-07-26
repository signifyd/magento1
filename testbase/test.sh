#!/bin/bash

PWD=$(pwd);

for D in *; do
    if [ -d "${D}" ]; then
        echo -e "\n\n\nTesting ${D}\n\n"
        (cd "${D}/app/code/community/Signifyd/Connect/tests";
        phpunit Signifyd_Connect_SignifydTest signifyd.php;
        cd "${PWD}";)
    fi
done
