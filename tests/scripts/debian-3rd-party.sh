#! /bin/sh -e

# Setup Env
relativepath=`dirname $0`
absolutesourcepath=`cd $relativepath/../..; pwd`
cd $absolutesourcepath

echo "Read config from tests/config/default"
. tests/config/default
if [ -f tests/config/`hostname` ]
then
        echo "Read config from tests/config/`hostname`"
        . tests/config/`hostname`
fi
BUILDERDIR=${BUILDERDIR:-$HOME/builder/}
DIST=wheezy
COWBUILDERCONFIG=$BUILDERDIR/config/$DIST.config

# Setup Repo
WORKDIR=$(cd $absolutesourcepath/..; pwd)
# Jenkins will set WORKSPACE
WORKSPACE=${WORKSPACE:-$WORKDIR}

REPOPATH=$WORKSPACE/build/debian
[ ! -d $REPOPATH ] || rm -r $REPOPATH
mkdir -p $REPOPATH/conf
DEFAULTKEY=buildbot@$(hostname -f)
SIGNKEY=${DEBEMAIL:-$DEFAULTKEY}
cat > $REPOPATH/conf/distributions <<EOF
Codename: $DIST
Suite: $DIST
Components: main
UDebComponents: main
Architectures: amd64 i386 source
Origin: buildbot.fusionforge.org
Description: FusionForge 3rd-party autobuilt repository
SignWith: $SIGNKEY
EOF

# Build mediawiki
make -C 3rd-party/mediawiki COWBUILDERCONFIG=$COWBUILDERCONFIG REPOPATH=$REPOPATH
gpg --export --armor > ${REPOPATH}/key
