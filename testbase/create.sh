#!/bin/bash

PWD=$(pwd);

CONTENTS=$(ls);

for D in `ls`; do
    if [ -d "${D}" ]; then
        (cd ${D}; 
        echo "${D};"
        php -f createproducts.php;
        cd ${PWD};)
    fi
done
