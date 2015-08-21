#!/bin/bash

# Bash script to facilitate building of Magento Signifyd extension.
# Prerequisites:
# - A Magento installation > 1.6
# - A clone of the signifyd_connect git repo and the ability to update it

echo -n "Starting Magento Signifyd build at "
date

if [[ $# -lt 1 ]]; then
   echo "Must supply a version# for extension (e.g., 1.2.3)" >&2
   exit 1
fi

EXT_VERSION=$1

# Most likely this should be the directory in which this build script resides. 
SIG_CONN_DIR=$HOME/signifyd_connect/
WORK_DIR=$HOME/magento_work/

MAGE_DIR=/var/www/html/magento/
# Set this to the installed Enterprise edition of Magento if available. Otherwise, set it to the same installation as MAGE_DIR.
#VERSION2=/var/www/enterprise_magento/
VERSION2=/var/www/html/magento/
TARGET_DIR=/vagrant

# Assume git repo has already been cloned in this dir
echo -n "Updating repo from GitHub... "
cd ${SIG_CONN_DIR}
git pull
echo "done."

echo -n "Cloning Magento installation into work directory... "
rsync -a -q --delete ${MAGE_DIR}/ ${WORK_DIR}/
echo "done."

echo -n "Installing Signifyd extension... "
cp -r ${SIG_CONN_DIR}/www/magento/. ${WORK_DIR}/.
echo "done."

echo -n "Preparing config files for build... "
sed -i -E "s/(<version>)(.+)(<\/version>)/\1${EXT_VERSION}\3/" ${WORK_DIR}/app/code/community/Signifyd/Connect/etc/config.xml
echo "done."

echo -n "Building... "
cd ${TARGET_DIR}
chmod +x ${WORK_DIR}/mage  ${VERSION2}/mage
${SIG_CONN_DIR}/build/build.php --path=${WORK_DIR} --config="${SIG_CONN_DIR}/build/config.php" --copy  --v2=$VERSION2 --version=${EXT_VERSION}
echo "done."

echo -n "Cleaning up ${WORK_DIR}... "
rm -rf ${WORK_DIR}
echo "done."

echo
echo -n "Finished Magento Signifyd build at "
date
echo

