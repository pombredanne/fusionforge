#!/bin/sh
. tests/scripts/common-functions

echo "WORK IN PROGRESS: NOT WORKING RIGHT NOW"
exit 0

export FORGE_HOME=/opt/fusionforge
get_config $@
prepare_workspace
start_vm_if_not_keeped $@

cat > tests/build/config/phpunit <<-EOF
HUDSON_URL=$HUDSON_URL
JOB_NAME=$JOB_NAME
EOF

echo "Sync code on root@$HOST:$FORGE_HOME"
ssh root@$HOST mkdir -p $FORGE_HOME
rsync -a --delete . root@$HOST:$FORGE_HOME

echo "Run Install on $HOST"
ssh root@$HOST "$FORGE_HOME/src/install-ng --auto --reinit"

echo "Dump freshly installed database"
ssh root@$HOST "su - postgres -c \"pg_dumpall\" > /root/dump"

echo "Stop cron daemon"
ssh root@$HOST "service crond stop" || true

retcode=0
echo "Run phpunit test on $HOST"
if xterm -e "sh -c exit" 2>/dev/null
then
	ssh -X root@$HOST "$FORGE_HOME/tests/scripts/phpunit.sh DEBDebian60Tests.php" || retcode=$?
	rsync -av root@$HOST:/var/log/ $WORKSPACE/reports/log/
else
	echo "No display is available, NOT RUNNING TESTS"
	retcode=2
fi

stop_vm_if_not_keeped $@
return $retcode