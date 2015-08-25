#!/bin/bash

# Bash script to facilitate building of Magento Signifyd extension.
# Prerequisites:
# - A Magento installation > 1.6
# - A clone of the signifyd_connect git repo and the ability to update it
# - Magento Enterprise (optional)
#

# Most likely this should be the directory in which this build script resides. 
SIG_CONN_DIR=$HOME/signifyd_connect/
BACKUP_DIR=$HOME/magento_backup

MAGE_DIR=/var/www/html/magento/
# Set this to the installed Enterprise edition of Magento if available.
# Otherwise, you will need a clone of MAGE_DIR
#VERSION2_DIR=/var/www/enterprise_magento/
VERSION2_DIR=/var/www/html/magento_alt
TARGET_DIR=/vagrant

echo -n "Starting Magento Signifyd build at "
date

if [[ $# -lt 1 ]]; then
   echo "Must supply a version# for extension (e.g., 1.2.3)" >&2
   exit 1
fi

EXT_VERSION=$1

# Ensure MAGE_DIR and VERSION2_DIR exist
if [[ ! -d "${MAGE_DIR}" ]]; then
  echo "MAGE_DIR directory '$MAGE_DIR' does not exist or is not a directory." >&2
  exit 1
fi

if [[ ! -e "${VERSION2_DIR}" ]]; then
  echo "VERSION2_DIR directory '$VERSION2_DIR' not found. Cloning '$MAGE_DIR'..."
  rsync -a -q --delete ${MAGE_DIR}/ ${VERSION2_DIR}/
  echo "done."
fi

if [[ ! -d "${VERSION2_DIR}" ]]; then
  echo "VERSION2_DIR directory '$VERSION2_DIR' does not exist or is not a directory." >&2
  exit 1
fi

# Ensure MAGE_DIR and VERSION2_DIR refer to different locations.
# Note that VERSION2_DIR can be an exact copy of MAGE_DIR, but not a symlink.
REAL_MAGE_DIR=`readlink -f ${MAGE_DIR}`
REAL_VERSION2_DIR=`readlink -f ${VERSION2_DIR}`
if [[ "${REAL_MAGE_DIR}" == "${REAL_VERSION2_DIR}" ]]; then
  echo "MAGE_DIR and VERSION2_DIR should not refer to the same directory: '$REAL_MAGE_DIR'." >&2
  exit 1
fi

# Assume git repo has already been cloned in this dir
echo -n "Updating repo from GitHub... "
cd ${SIG_CONN_DIR}
git pull
echo "done."

echo -n "Backup Magento installation... "
rsync -a -q --delete ${MAGE_DIR}/ ${BACKUP_DIR}/
echo "done."

echo -n "Installing Signifyd extension... "
cp -r ${SIG_CONN_DIR}/www/magento/. ${MAGE_DIR}/.
echo "done."

echo -n "Preparing config files for build... "
sed -i -E "s/(<version>)(.+)(<\/version>)/\1${EXT_VERSION}\3/" ${MAGE_DIR}/app/code/community/Signifyd/Connect/etc/config.xml
echo "done."

echo -n "Building... "
cd ${TARGET_DIR}
chmod +x ${MAGE_DIR}/mage  ${VERSION2_DIR}/mage
${SIG_CONN_DIR}/build/build.php --path=${MAGE_DIR} --config="${SIG_CONN_DIR}/build/config.php" --copy  --v2=${VERSION2_DIR} --version=${EXT_VERSION}
echo "done."

echo -n "Cleaning up... "
rm -rf ${MAGE_DIR}
mv ${BACKUP_DIR} ${MAGE_DIR}
echo "done."

echo
echo -n "Finished Magento Signifyd build at "
date
echo
