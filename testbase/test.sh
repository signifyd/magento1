#!/bin/bash

PWD=$(pwd);

for D in *; do
    if [ -d "${D}" ]; then
        echo "Testing ${D}"
        (cd "${D}/app/code/community/Grep/Signifyd/tests";
        phpunit Grep_Signifyd_ObserverTest observer.php;
        cd "${PWD}";)
    fi
done
