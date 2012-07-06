#!/bin/sh -ex

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
else
	export HOST=centos5.$DNSDOMAIN
	export VEID=$VEIDCOS
fi


export LXCTEMPLATE=$LXCCOSTEMPLATE

export IPBASE=$IPCOSBASE
export IPDNS=$IPCOSDNS
export IPMASK=$IPCOSMASK
export IPGW=$IPCOSGW

export VZTEMPLATE
export VZPRIVATEDIR
export DEBMIRROR

export DIST
export VMENGINE
export SSHPUBKEY
export HOSTKEYDIR

if [ "x${HUDSON_URL}" = "x" ]
then
	export BASEDIR=${BASEDIR:-/~`id -un`/ws}
	export USEVZCTL=true
	export SELENIUM_RC_HOST=localhost
	export SELENIUM_RC_URL=http://`hostname -f`$BASEDIR/reports
else
	export SELENIUM_RC_URL=${HUDSON_URL}job/$JOB_NAME/ws/reports
fi
export DB_NAME=gforge
export CONFIGURED=true

[ ! -d $WORKSPACE/build ] || rm -fr $WORKSPACE/build
[ ! -d $WORKSPACE/reports ] || rm -fr $WORKSPACE/reports
mkdir -p $WORKSPACE/build/packages $WORKSPACE/build/config $WORKSPACE/reports/coverage

if $KEEPVM
then
	echo "Destroying vm $HOST"
	(cd tests/scripts ; sh ./stop_vm.sh $HOST || true)
fi

(cd 3rd-party/selenium ; make getselenium)

(cd tests/scripts ; sh ./start_vm.sh $HOST)

# BUILD FUSIONFORGE REPO
echo "Build FUSIONFORGE REPO"
make -f Makefile.rh BUILDRESULT=$WORKSPACE/build/packages all

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

# DAG REPO
if [ ! -z "$DAG_RPMFORGE_REPO" ] ; then
        echo "Installing specific DAG REPO $DAG_RPMFORGE_REPO"
        cp src/rpm-specific/dag-rpmforge.repo $WORKSPACE/build/packages/dag-rpmforge.repo
        sed -i "s#http://apt.sw.be/redhat#${DAG_RPMFORGE_REPO}#" $WORKSPACE/build/packages/dag-rpmforge.repo
        scp $WORKSPACE/build/packages/dag-rpmforge.repo root@$HOST:/etc/yum.repos.d/
else
        echo "Installing standart DAG REPO from src/rpm-specific/dag-rpmforge.repo"
        scp src/rpm-specific/dag-rpmforge.repo root@$HOST:/etc/yum.repos.d/
fi

# TODO: Make test dir a parameter
echo "Transfer phpunit test on $HOST"
cat > $WORKSPACE/build/config/phpunit <<-EOF
HUDSON_URL=$HUDSON_URL
JOB_NAME=$JOB_NAME
EOF

scp -r $WORKSPACE/build/config  root@$HOST:/root/
rsync -a 3rd-party/selenium/selenium-server.jar root@$HOST:/root/selenium-server.jar
rsync -a --delete tests/ root@$HOST:/root/tests/

ssh root@$HOST "ln -s gforge /usr/share/src"

sleep 5
[ ! -e "/tmp/timedhosts.txt" ] || scp -p /tmp/timedhosts.txt root@$HOST:/var/cache/yum/timedhosts.txt
ssh root@$HOST "FFORGE_DB=$DB_NAME FFORGE_USER=gforge FFORGE_ADMIN_USER=$FORGE_ADMIN_USERNAME FFORGE_ADMIN_PASSWORD=$FORGE_ADMIN_PASSWORD export FFORGE_DB FFORGE_USER FFORGE_ADMIN_USER FFORGE_ADMIN_PASSWORD; yum install -y --skip-broken fusionforge fusionforge-plugin-scmsvn fusionforge-plugin-online_help fusionforge-plugin-extratabs fusionforge-plugin-ldapextauth fusionforge-plugin-scmgit fusionforge-plugin-blocks"
scp -p root@$HOST:/var/cache/yum/timedhosts.txt /tmp/timedhosts.txt || true
ssh root@$HOST '(echo [core];echo use_ssl=no;echo use_fti=no) > /etc/gforge/config.ini.d/zzz-buildbot.ini'
#ssh root@$HOST "cd /root/tests/func; CONFIGURED=true CONFIG_PHP=config.php.buildbot DB_NAME=$DB_NAME php db_reload.php"
#ssh root@$HOST "su - postgres -c \"pg_dump -Fc $DB_NAME\" > /root/dump"
ssh root@$HOST "su - postgres -c \"pg_dumpall\" > /root/dump"
# Install a fake sendmail to catch all outgoing emails.
# ssh root@".HOST." 'perl -spi -e s#/usr/sbin/sendmail#/usr/share/tests/scripts/catch_mail.php# /etc/gforge/local.inc'
ssh root@$HOST "service crond stop" || true

retcode=0
if $REMOTESELENIUM
then
	echo "Run phpunit test on $HOST"
	ssh root@$HOST "yum install -y vnc-server ; mkdir -p /root/.vnc"
	ssh root@$HOST "cat > /root/.vnc/xstartup ; chmod +x /root/.vnc/xstartup" <<EOF
#! /bin/bash
: > /root/phpunit.exitcode
/root/tests/scripts/phpunit.sh RPMCentos52Tests.php &> /var/log/phpunit.log &
echo \$! > /root/phpunit.pid
wait %1
echo \$? > /root/phpunit.exitcode
EOF
	ssh root@$HOST vncpasswd <<EOF
password
password
EOF
	ssh root@$HOST "vncserver :1"
	sleep 5
	pid=$(ssh root@$HOST cat /root/phpunit.pid)
	ssh root@$HOST "tail -f /var/log/phpunit.log --pid=$pid"
	sleep 5
	retcode=$(ssh root@$HOST cat /root/phpunit.exitcode)
	ssh root@$HOST "vncserver -kill :1" || retcode=$?
else
	cd tests
	phpunit --log-junit $WORKSPACE/reports/phpunit-selenium.xml RPMCentos52Tests.php || retcode=$?
	cd ..
fi

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
	cd tests/scripts
	sh ./stop_vm.sh $HOST || true
fi
exit $retcode
