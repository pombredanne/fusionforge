#! /bin/sh
# 
# $Id$
#
# Configure apache for GForge
# Christian Bayle, Roland Mas, debian-sf (GForge for Debian)
#
# Reset fb color mode
RESET="]R"
# ANSI COLORS
# Erase to end of line
CRE="
[K"
# Clear and reset Screen
CLEAR="c"
# Normal color
NORMAL="[0;39m"
# RED: Failure or error message
RED="[1;31m"
# GREEN: Success message
GREEN="[1;32m"

set -e

ARG=$@
if [ $(id -u) != 0 ] ; then
    echo "You must be root to run this, please enter passwd"
    exec su -c "$0 $1"
fi

remove_gforge_insert(){
			cp -a $1 $1.gforge-new
			echo "Removing Gforge inserted lines"
			vi -e $1.gforge-new > /dev/null 2>&1 <<-FIN
/### Next line inserted by GForge install
:d
:d
:w
:x
FIN
}

search_conf_file(){
CONFFILE=$1
shift
echo -n "Searching $CONFFILE config file	"
RESULT=""
for i in $*
do
	if [ -f "$i" ]
	then
		RESULT="$i $RESULT"
	fi
done
if [ -z "$RESULT" ]
then
	echo "$RED[Failed]$NORMAL" 
	echo "${CONFFILE} conf file not found at $*"
	echo "Please set ${CONFFILE}_ETC_SEARCH" ; exit 1
else
	echo "$GREEN[OK]$NORMAL"
fi
}

get_conf(){
if [ "$HAVECONF" != "true" ]
then
if [ -z "$APACHE_ETC_SEARCH" ] 
then 
	APACHE_ETC_SEARCH="/etc/apache/httpd.conf /etc/apache-perl/httpd.conf /etc/apache-ssl/httpd.conf"
fi
if [ -z "$GFORGE_ETC_SEARCH" ] 
then 
	GFORGE_ETC_SEARCH="/etc/gforge/httpd.conf"
fi
if [ -z "$PHP_ETC_SEARCH" ] 
then 
	PHP_ETC_SEARCH="/etc/php4/apache/php.ini /etc/php4/cgi/php.ini"
fi
export APACHE_ETC_SEARCH GFORGE_ETC_SEARCH PHP_ETC_SEARCH

search_conf_file APACHE "$APACHE_ETC_SEARCH"
APACHE_ETC_LIST="$RESULT"
search_conf_file GFORGE "$GFORGE_ETC_SEARCH"
GFORGE_ETC_LIST="$RESULT"
search_conf_file PHP "$PHP_ETC_SEARCH"
PHP_ETC_LIST="$RESULT"
export APACHE_ETC_LIST GFORGE_ETC_LIST PHP_ETC_LIST

[ -z "$gforgebin" ] && gforgebin="/usr/lib/gforge/bin"
set $GFORGE_ETC_LIST
gforgeconffile=$1
echo Using $gforgeconffile
export gforgeconffile
HAVECONF=true
export HAVECONF
fi
}

get_conf
set $ARG
case "$1" in
    configure-files)
	# Make sure Apache sees us
	for apacheconffile in $APACHE_ETC_LIST
	do
		APACHE_ETC_DIR=`basename $apacheconffile`
		if [ -d "$APACHE_ETC_DIR/conf.d" ]
		then
			# New apache conf	
			# Remove old hack to have Apache see us
	    		if [ -e $apacheconffile ] && grep -q "Include $gforgeconffile" $apacheconffile ; then
				remove_gforge_insert $apacheconffile
	    		fi
		else	
			# Old fashion Apache
			if [ -e $apacheconffile ] ; then
	    			cp -a $apacheconffile $apacheconffile.gforge-new
	    			perl -pi -e "s/# *LoadModule php4_module/LoadModule php4_module/gi" $apacheconffile.gforge-new
	    			perl -pi -e "s/# *LoadModule ssl_module/LoadModule ssl_module/gi" $apacheconffile.gforge-new
	    			perl -pi -e "s/# *LoadModule env_module/LoadModule env_module/gi" $apacheconffile.gforge-new
	    			perl -pi -e "s/# *LoadModule vhost_alias_module/LoadModule vhost_alias_module/gi" $apacheconffile.gforge-new
	    
	    			if ! grep -q "^Include $gforgeconffile" $apacheconffile.gforge-new ; then
					echo "### Next line inserted by GForge install" >> $apacheconffile.gforge-new
					echo "Include $gforgeconffile" >> $apacheconffile.gforge-new
	    			fi
			fi
		fi
	done
	# Make sure pgsql, ldap and gd are enabled in the PHP config files
	for phpconffile in $PHP_ETC_LIST
	do
		cp -a $phpconffile $phpconffile.gforge-new
		if [ -f $etcphp4apache/php.ini.gforge-new ]; then
	    		if ! grep -q "^[[:space:]]*extension[[:space:]]*=[[:space:]]*pgsql.so" $phpconffile.gforge-new; then
				echo "Enabling pgsql in $phpconffile"
				echo "extension=pgsql.so" >> $phpconffile.gforge-new
	    		fi
	    		if ! grep -q "^[[:space:]]*extension[[:space:]]*=[[:space:]]*gd.so" $phpconffile.gforge-new; then
				echo "Enabling gd in $phpconffile"
				echo "extension=gd.so" >> $phpconffile.gforge-new
	    		fi
	    		if ! grep -q "^[[:space:]]*extension[[:space:]]*=[[:space:]]*ldap.so" $phpconffile.gforge-new; then
				echo "Enabling ldap in $phpconffile"
				echo "extension=ldap.so" >> $phpconffile.gforge-new
	    		fi
		fi
	done
	;;
	
    configure)
	[ -f $gforgebin/prepare-vhosts-file.pl ] && $gforgebin/prepare-vhosts-file.pl
	if [ -f /usr/sbin/modules-config ] ; then
		for flavour in apache apache-perl apache-ssl ; do
			if [ -e /etc/$flavour/httpd.conf ] ; then
	    			/usr/sbin/modules-config $flavour enable mod_php4
				if [ $flavour != apache-ssl ] ; then
	    				/usr/sbin/modules-config $flavour enable mod_ssl
				fi
	    			/usr/sbin/modules-config $flavour enable mod_env
	    			/usr/sbin/modules-config $flavour enable mod_vhost_alias

				if [ -d /etc/$flavour/conf.d ] ; then
					[ ! -e /etc/$flavour/conf.d/gforge.httpd.conf ] && ln -s /etc/gforge/httpd.conf /etc/$flavour/conf.d/gforge.httpd.conf
				fi
			fi
			if [ -x /usr/sbin/$flavour ]; then
				invoke-rc.d $flavour restart || true
			fi
		done
	fi
	;;

    purge-files)
	for apacheconffile in $APACHE_ETC_LIST
	do
	echo "Looking at $apacheconffile"
	    	#if [ -e $apacheconffile ] && grep -q "Include $gforgeconffile" $apacheconffile ; then
	    	if [ -e $apacheconffile ] && grep -q "### Next line inserted by GForge install" $apacheconffile ; then
			remove_gforge_insert $apacheconffile
	    	fi
	done
	;;

    purge)
    	for flavour in apache apache-perl apache-ssl ; do
		[ ! -e /etc/$flavour/conf.d/gforge.httpd.conf ] && rm -f /etc/$flavour/conf.d/gforge.httpd.conf
		if [ -x /usr/sbin/$flavour ]; then
			invoke-rc.d $flavour restart || true
		fi
	done
	;;

    setup)
    	$0 configure-files
	for apacheconffile in $APACHE_ETC_LIST
	do
		cp $apacheconffile $apacheconffile.gforge-old
		mv $apacheconffile.gforge-new $apacheconffile
	done
	$0 configure
	;;

    cleanup)
    	$0 purge-files
	for apacheconffile in $APACHE_ETC_LIST
	do
		cp $apacheconffile $apacheconffile.gforge-old
		mv $apacheconffile.gforge-new $apacheconffile
	done
	$0 purge
	;;

    *)
	echo "Usage: $0 {configure|configure-files|purge|purge-files|setup|cleanup}"
	exit 1
	;;
	
esac
