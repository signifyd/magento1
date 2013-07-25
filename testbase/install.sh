#!/bin/bash

EXPECTED_ARGS=4
E_BADARGS=65

if [ $# -ne $EXPECTED_ARGS ]
then
    echo "Tool for automatically installing lots of Magento instances"
    echo "Requires pre-extracted Magento tarballs. Run once for each instance"
    echo "Usage: `basename $0` {magentodirectory} {db_user} {db_pass} {db_name}"
    exit $E_BADARGS
fi

DIRECTORY=$1
USER=$2
PASS=$3
VERSION=$4
CUR_DIR=$( pwd )
DIR="$( cd "$( dirname "$0" )" && pwd )"

COMMAND="install.php -- --license_agreement_accepted yes \
    --locale en_US --timezone America/Los_Angeles --default_currency USD \
    --db_host localhost --db_name $VERSION --db_user $USER --db_pass $PASS \
    --url http://127.0.0.1/magento/ --use_rewrites yes \
    --use_secure yes --secure_base_url http://127.0.0.1/magento/ --use_secure_admin no \
    --admin_lastname Owner --admin_firstname Store --admin_email admin@example.com \
    --admin_username admin --admin_password pass123456 \
    --encryption_key EncryptionKey --skip_url_validation"

cd $DIRECTORY;

echo "php -f $COMMAND"

php -f $COMMAND

cd $CUR_DIR;

