#!/bin/sh -e

export CURDIR=`pwd`
export WORKSPACE=${WORKSPACE:-$CURDIR}

export CONFIG_PHP=func/config.php.buildbot
export SELENIUM_RC_HOST=${SELENIUM_RC_HOST:-`hostname -i`}
export SELENIUM_RC_DIR=$WORKSPACE/reports

# get config 
. tests/config/default
if [ -f tests/config/`hostname` ] ; then . tests/config/`hostname`; fi
if [ ! -z "$1" ]
then
	export HOST="$1"
fi

export DEBMIRROR
export DIST
export VMENGINE
export SSHPUBKEY
export HOSTKEYDIR

if [ "x${HUDSON_URL}" = "x" ]
then
	export BASEDIR=${BASEDIR:-/~`id -un`/ws}
	export SELENIUM_RC_HOST=localhost
	export SELENIUM_RC_URL=http://`hostname -f`$BASEDIR/reports
else
	export SELENIUM_RC_URL=${HUDSON_URL}job/$JOB_NAME/ws/reports
fi

export DB_NAME=fforge
export CONFIGURED=true

export BUILDRESULT=$WORKSPACE/build/packages

# Create place to build package if necessary
[ ! -d $WORKSPACE/build/packages ] || mkdir -p $WORKSPACE/build/packages

# Erase config
[ ! -d $WORKSPACE/build/config ] || rm -fr $WORKSPACE/build/config
mkdir -p $WORKSPACE/build/config

# Erase reports
[ ! -d $WORKSPACE/reports ] || rm -fr $WORKSPACE/reports
mkdir -p $WORKSPACE/reports/coverage

# Erase apidocs
[ ! -d $WORKSPACE/apidocs ] || rm -fr $WORKSPACE/apidocs
mkdir -p $WORKSPACE/apidocs

#[ ! -e $HOME/doxygen-1.6.3/bin/doxygen ] || make build-doc DOCSDIR=$WORKSPACE/apidocs DOXYGEN=$HOME/doxygen-1.6.3/bin/doxygen
#make BUILDRESULT=$WORKSPACE/build/packages buildtar
#make -f Makefile.rh BUILDRESULT=$WORKSPACE/build/packages src

if $KEEPVM
then
	# VM can already exist
	echo "Starting vm $HOST"
	tests/scripts/start_vm $HOST
else 
	# Destroy the VM if found
	echo "Destroying vm $HOST"
	tests/scripts/stop_vm $HOST || true
fi

# BUILD FUSIONFORGE REPO
echo "Build FUSIONFORGE REPO"
make -C 3rd-party -f Makefile.rh BUILDRESULT=$WORKSPACE/build/packages

# FUSIONFORGE REPO
if [ ! -z "$FFORGE_RPM_REPO" ]
then
	echo "Installing specific FUSIONFORGE REPO $FFORGE_RPM_REPO"
	cp src/rpm-specific/fusionforge.repo $WORKSPACE/build/packages/fusionforge.repo
	sed -i "s#http://fusionforge.org/#${HUDSON_URL}#" $WORKSPACE/build/packages/fusionforge.repo
	sed -i "s#baseurl = .*#baseurl = ${FFORGE_RPM_REPO}/#" $WORKSPACE/build/packages/fusionforge.repo
	scp $WORKSPACE/build/packages/fusionforge.repo root@$HOST:/etc/yum.repos.d/
else
	rsync -a --delete $WORKSPACE/build/packages/ root@$HOST:/root/fusionforge_repo/
	echo "Installing standart FUSIONFORGE REPO from src/rpm-specific/fusionforge.repo"
	scp src/rpm-specific/fusionforge.repo root@$HOST:/etc/yum.repos.d/
fi

# DAG
if [ ! -z "$DAG_RPMFORGE_REPO" ] ; then
	echo "Installing specific DAG REPO $DAG_RPMFORGE_REPO"
	cp src/rpm-specific/dag-rpmforge.repo $WORKSPACE/build/packages/dag-rpmforge.repo
        sed -i "s#http://apt.sw.be/redhat#${DAG_RPMFORGE_REPO}#" $WORKSPACE/build/packages/dag-rpmforge.repo
	scp $WORKSPACE/build/packages/dag-rpmforge.repo root@$HOST:/etc/yum.repos.d/
else
	echo "Installing standart DAG REPO from src/rpm-specific/dag-rpmforge.repo"
	scp src/rpm-specific/dag-rpmforge.repo root@$HOST:/etc/yum.repos.d/
fi

cat > $WORKSPACE/build/config/phpunit <<-EOF
HUDSON_URL=$HUDSON_URL
JOB_NAME=$JOB_NAME
EOF

rsync -a --delete src/ root@$HOST:/opt/gforge/
rsync -a --delete tests/ root@$HOST:/opt/gforge/tests/

ssh root@$HOST "/opt/gforge/install-ng"

ssh root@$HOST "su - postgres -c \"pg_dumpall\" > /root/dump"

ssh root@$HOST "(echo [core];echo use_ssl=no) > /etc/gforge/config.ini.d/zzz-zbuildbot.ini"
ssh root@$HOST "cd /opt/gforge/tests/func; CONFIGURED=true CONFIG_PHP=config.php.buildbot DB_NAME=$DB_NAME php db_reload.php"
#  Install a fake sendmail to catch all outgoing emails.
# ssh root@$HOST "perl -spi -e s#/usr/sbin/sendmail#/opt/tests/scripts/catch_mail.php# /etc/gforge/local.inc"
ssh root@$HOST "service crond stop" || true

retcode=0
echo "Run phpunit test on $HOST"
ssh -X root@$HOST "/opt/gforge/tests/scripts/phpunit.sh TarCentos52Tests.php" || retcode=$?

if [ "x$SELENIUM_RC_DIR" != "x" ]
then
	rsync -av root@$HOST:/var/log/ $SELENIUM_RC_DIR/
fi

cp $WORKSPACE/reports/phpunit-selenium.xml $WORKSPACE/reports/phpunit-selenium.xml.org
xalan -in $WORKSPACE/reports/phpunit-selenium.xml.org -xsl fix_phpunit.xslt -out $WORKSPACE/reports/phpunit-selenium.xml

if $KEEPVM 
then
	echo "Keeping vm $HOST alive"
else
	tests/scripts/stop_vm $HOST
fi
exit $retcode