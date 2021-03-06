#! /bin/sh

# Author : Olivier BERGER <olivier.berger@it-sudparis.eu>

# This script will checkout the needed branch and will setup the
# scripts symlink to give the correct up-to-date scripts versions to
# the user

# This script, itself, may need to be brought up-to-date.
#
# You may try : 
#   wget "https://fusionforge.org/scm/viewvc.php/*checkout*/trunk/tools/VM-scripts/configure-scripts.sh?root=fusionforge"
# to get the latest version from the trunk (replace '/trunk' by
# '/branches/Branch_5_1' for Branch 5.1's version for instance.
#

if [ $# -ne 1 ]; then
    echo "Please provide branch name to work on (Branch_5_1|trunk)"
    exit 1
fi

cd $HOME

if [ -f ./fusionforge ]; then
    if [ ! -L ./fusionforge ]; then
	echo "You have an existing ./fusionforge file or directory. Stopping."
	exit 1
    fi
fi

BRANCH="$1"

if [ "$BRANCH" = "trunk" -o "$BRANCH" = "Branch_5_1" ]; then
    if [ -d "./fusionforge-$BRANCH" ]; then
	echo "Assuming './fusionforge-$BRANCH/' already contains a bzr checkout of the $BRANCH. Please check following output of 'bzr info' :"
	(cd "./fusionforge-$BRANCH/" && bzr info)
    else
	if [ "$BRANCH" = "trunk" ]; then
	    echo "no 'fusionforge-trunk/' dir found : checking out from SVN's trunk with 'bzr checkout svn://scm.fusionforge.org/svnroot/fusionforge/trunk' :"
	    bzr checkout svn://scm.fusionforge.org/svnroot/fusionforge/trunk fusionforge-trunk
	else
	    echo "no 'fusionforge-$BRANCH/' dir found : checking out from SVN's $BRANCH with 'bzr checkout svn://scm.fusionforge.org/svnroot/fusionforge/branches/$BRANCH' :"
	    bzr checkout "svn://scm.fusionforge.org/svnroot/fusionforge/branches/$BRANCH" "fusionforge-$BRANCH"
	fi
    fi
else
    echo "The supplied branch : $BRANCH wasn't recognized. Maybe the script is now outdated"
    exit 1
fi

if [ -L ./fusionforge ]; then
    oldlink=$(ls -ld ./fusionforge)
    echo "Removing old ./fusionforge link ($oldlink)"
    rm ./fusionforge
fi

echo "Creating a link from './fusionforge' to 'fusionforge-$BRANCH'"
ln -s "fusionforge-$BRANCH" fusionforge

if [ -d scripts ]; then
    echo "Saving old 'scripts/' dir in 'scripts.old/'."
    mv scripts scripts.old
fi

if [ -L ./scripts ]; then
    oldlink=$(ls -ld ./scripts)
    echo "Removing old ./scripts link ($oldlink)"
    rm ./scripts
fi

if [ -d "fusionforge-$BRANCH/tools/VM-scripts/" ]; then
    echo "Creating a link from 'fusionforge-$BRANCH/tools/VM-scripts/' to './scripts'."
    ln -s "fusionforge-$BRANCH/tools/VM-scripts/" scripts

    if [ -L scripts/reload-db.sh ]; then
	oldlink=$(ls -ld scripts/reload-db.sh)
	echo "Removing old scripts/reload-db.sh link ($oldlink)"
	rm scripts/reload-db.sh
    fi

    echo "Creating a link from 'fusionforge-$BRANCH/tests/func/db_reload.sh' to 'scripts/reload-db.sh'."
    ln -s "../../tests/func/db_reload.sh" scripts/reload-db.sh
else
    echo "Warning: there appears to be no 'fusionforge-$BRANCH/tools/VM-scripts/' directory."
    echo "You may need to update the contents of the checked-out copy in 'fusionforge-$BRANCH/'."
fi
