#! /bin/sh
# 
# $Id$
#
# Configure LDAP for Sourceforge
# Christian Bayle, Roland Mas, debian-sf (Sourceforge for Debian)

set -e

if [  $(id -u) != 0 -a  "x$1" != "xlist" ] ; then
	echo "You must be root to run this, please enter passwd"
	exec su -c "$0 $1"
fi

PATH=$PATH:/usr/sbin

# Should I do something for /etc/pam_ldap.conf ?
modify_pam_ldap(){
	echo "Nothing to do"
}

# Check/Modify /etc/libnss-ldap.conf
modify_libnss_ldap(){
	dn=$1
	# Check if DN is correct
	if ! grep -q "^base.[ 	]*$dn" /etc/libnss-ldap.conf ; then
		echo "WARNING: Probably incorrect base line in /etc/libnss-ldap.conf"
	fi
	# Check bindpw
	# Should contain the secret
	# All users can see ldap stored gid/uid
	chmod 644 /etc/libnss-ldap.conf
# It doesn't seem to be necessary, only rootbinddn is necessary
#	if ! grep -q "^bindpw" /etc/libnss-ldap.conf ; then
#		echo "# Next line added by Sourceforge install" >>/etc/libnss-ldap.conf
#		echo "bindpw secret" >>/etc/libnss-ldap.conf
#	fi
	# Check rootbinddn
	# This seems to be necessary to display uid/gid
	# Should be cn=admin,ou=People,dc=...
	if ! grep -q "^rootbinddn" /etc/libnss-ldap.conf ; then
		echo "# Next line added by Sourceforge install" >>/etc/libnss-ldap.conf
		echo "rootbinddn cn=admin,ou=People,$dn" >>/etc/libnss-ldap.conf
	fi
}

# Purge /etc/libnss-ldap.conf
purge_libnss_ldap(){
	perl -pi -e "s/^# Next line added by Sourceforge install\n/#SF#/g" /etc/libnss-ldap.conf
	perl -pi -e "s/^#SF#.*\n//g" /etc/libnss-ldap.conf
}

# Modify /etc/ldap/slapd.conf
modify_slapd(){
	dn=$1
	# Maybe should comment referral line too
	echo "WARNING: Please check referal line in /etc/ldap/slapd.conf"
	
	# Debian config by default only include core schema
	if ! grep -q "Sourceforge" /etc/ldap/slapd.conf ; then
		rm -f /etc/ldap/slapd.conf.sourceforge
		for schema in /etc/ldap/schema/core.schema \
			/etc/ldap/schema/cosine.schema \
			/etc/ldap/schema/inetorgperson.schema \
			/etc/ldap/schema/nis.schema \
			/etc/sourceforge/sourceforge.schema
		do
			if ! grep -q "^include.[ 	]*$schema" /etc/ldap/slapd.conf ; then
				echo "include	$schema	#Added by Sourceforge install" >>/etc/ldap/slapd.conf.sourceforge
				echo "Adding $schema"
			else
				echo "Commenting $schema"
				export schema
				perl -pi -e "s/^include.[        ]*\$schema/#Comment by Sourceforge install#include	\$schema/g" /etc/ldap/slapd.conf
				echo "include	$schema	#Added by Sourceforge install" >>/etc/ldap/slapd.conf.sourceforge
				echo "Adding $schema"
			fi
		done
		cat /etc/ldap/slapd.conf >>/etc/ldap/slapd.conf.sourceforge
		mv /etc/ldap/slapd.conf.sourceforge /etc/ldap/slapd.conf

		# Then write access for SF_robot
		perl -pi -e "s/access to attribute=userPassword/# Next second line added by Sourceforge install
access to attribute=userPassword
	by dn=\"cn=SF_robot,$dn\" write/" /etc/ldap/slapd.conf

		perl -pi -e "s/access to \*/# Next lines added by Sourceforge install
access to dn=\".*,ou=People,$dn\"		
	by dn=\"cn=admin,ou=People,$dn\" write	
	by dn=\"cn=SF_robot,$dn\" write		
	by * read				
access to dn=\"ou=People,$dn\"		
	by dn=\"cn=admin,ou=People,$dn\" write	
	by dn=\"cn=SF_robot,$dn\" write		
	by * read				
access to dn=\"ou=Group,$dn\"		
	by dn=\"cn=admin,ou=People,$dn\" write	
	by dn=\"cn=SF_robot,$dn\" write		
	by * read				
access to dn=\"ou=cvsGroup,$dn\"		
	by dn=\"cn=admin,ou=People,$dn\" write	
	by dn=\"cn=SF_robot,$dn\" write		
	by * read				
# End of sourceforge add
access to */" /etc/ldap/slapd.conf

		# invoke-rc.d slapd restart
	fi	
}

# Purge /etc/ldap/slapd.conf
purge_slapd(){
	perl -pi -e "s/^.*#Added by Sourceforge install\n//" /etc/ldap/slapd.conf
	perl -pi -e "s/#Comment by Sourceforge install#//" /etc/ldap/slapd.conf
if grep -q "# Next second line added by Sourceforge install" /etc/ldap/slapd.conf
then
	vi -e /etc/ldap/slapd.conf <<-FIN
/# Next second line added by Sourceforge install
:d
/SF_robot
:d
:w
:x
FIN
fi
if grep -q "Next lines added by Sourceforge install" /etc/ldap/slapd.conf
then
	vi -e /etc/ldap/slapd.conf <<-FIN
/# Next lines added by Sourceforge install
:ma a
/# End of sourceforge add
:ma b
:'a,'bd
:w
:x
FIN
fi

}

# Modify /etc/nsswitch.conf
modify_nsswitch()
{
	# This is sensitive file
	if ! grep -q "Sourceforge" /etc/nsswitch.conf ; then
		# By security i let priority to files
		# Should maybe enhance this to take in account nis
		# Maybe ask the order db/files/nis/ldap
		perl -pi -e "s/^passwd/passwd	files ldap #Added by Sourceforge install\n#Comment by Sourceforge install#passwd/g" /etc/nsswitch.conf
		perl -pi -e "s/^group/group	files ldap #Added by Sourceforge install\n#Comment by Sourceforge install#group/g" /etc/nsswitch.conf
		perl -pi -e "s/^shadow/shadow	files ldap #Added by Sourceforge install\n#Comment by Sourceforge install#shadow/g" /etc/nsswitch.conf
	fi
}

# Purge /etc/nsswitch.conf
purge_nsswitch()
{
	perl -pi -e "s/^.*#Added by Sourceforge install\n//" /etc/nsswitch.conf
	perl -pi -e "s/#Comment by Sourceforge install#//" /etc/nsswitch.conf
}

# Load ldap database from sourceforge database
load_ldap(){
	naming_context=$1
	secret=$2
	if [ "x$secret" != "x" ] 
	then
		# This load the ldap database
		echo "Distinguished Name is $naming_context"
		echo "Creating ldif file from database"
		tmpldif="/tmp/ldif$$"
		/usr/lib/sourceforge/bin/sql2ldif.pl >$tmpldif
		echo "Filling LDAP with database"
		# Only if the ldap server is local
		# Maybe ask for the password, but will simple athentication
		# Be allowed on remote server ?
		# VERBOSE=-v
		# -v Use  verbose mode, with many diagnostics written to
		# standard output.
		# -c Continuous  operation  mode. Errors are reported,
		# but ldapmodify will  continue  with  modifications.
		# The default is to exit after reporting an error.
		# -x Use simple authentication instead of SASL.
		# -w passwd Use passwd as the password for  simple
		# authentication.
		# -r Replace existing values by default.
		# add with -r don't modify and modify don't add so i do add and modify
 		ldapadd $VERBOSE -r -c -D "cn=admin,ou=People,$naming_context" -x -w"$secret" -f $tmpldif > /dev/null 2>&1 || true
 		ldapmodify $VERBOSE -r -c -D "cn=admin,ou=People,$naming_context" -x -w"$secret" -f $tmpldif > /dev/null 2>&1 || true
		# rm -f $tmpldif
	else
		echo "WARNING: Can't load ldap table without /etc/lapd.secret file"
		echo "AFAIK  : This file should be installed by libpam-ldap"
	fi
}

print_ldif_default(){
	dn=$1
	cryptedpasswd=$2
	cat <<-FIN
dn: $dn
objectClass: top
objectClass: domain
dc: rd

dn: ou=People, $dn
objectClass: top
objectClass: organizationalUnit
ou: People

dn: cn=admin, ou=People, $dn
objectClass: top
userPassword: $cryptedpasswd
cn: admin

dn: ou=Roaming, $dn
objectClass: top
objectCLass: organizationalUnit
FIN
}

setup_vars() {
	sys_ldap_base_dn=$(grep sys_ldap_base_dn /etc/sourceforge/local.inc | cut -d\" -f2)
	#echo "=====>sys_ldap_base_dn=$sys_ldap_base_dn"
	sys_ldap_admin_dn=$(grep sys_ldap_admin_dn /etc/sourceforge/local.inc | cut -d\" -f2)
	#echo "=====>sys_ldap_admin_dn=$sys_ldap_admin_dn"
	sys_ldap_bind_dn=$(grep sys_ldap_bind_dn /etc/sourceforge/local.inc | cut -d\" -f2)
	#echo "=====>sys_ldap_bind_dn=$sys_ldap_bind_dn"
	sys_ldap_passwd=$(grep sys_ldap_passwd /etc/sourceforge/database.inc | cut -d\" -f2)
	#echo "=====>sys_ldap_passwd=$sys_ldap_passwd"
	[ -f /etc/ldap.secret ] && secret=$(cat /etc/ldap.secret) || secret=$sys_ldap_passwd
	cryptedpasswd=`slappasswd -s "$secret" -h {CRYPT}`
	#echo "=====>$cryptedpasswd"
}

# Setup SF_robot Passwd
setup_robot() {
	setup_vars

	# The first account is only used in a multiserver SF
	echo "Adding robot accounts"

	{ ldapadd -r -c -D "$sys_ldap_admin_dn" -x -w"$secret" > /dev/null 2>&1 || true ; } <<-FIN
dn: cn=Replicator,$sys_ldap_base_dn
cn: Replicator
sn: Replicator the Robot
description: empty
objectClass: top
objectClass: person
userPassword: {crypt}x

dn: cn=SF_robot,$sys_ldap_base_dn
cn: SF_robot
sn: SF the Robot
description: empty
objectClass: top
objectClass: person
userPassword: {crypt}x
FIN

	echo "Changing SF_robot passwd using admin account"
	ldapmodify -v -c -D "$sys_ldap_admin_dn" -x -w"$secret" > /dev/null 2>&1 <<-FIN
dn: $sys_ldap_bind_dn
changetype: modify
replace: userPassword
userPassword: $cryptedpasswd
FIN

	echo "Testing LDAP"
	#naming_context=$(ldapsearch -x -b '' -s base '(objectclass=*)' namingContexts | grep "namingContexts:" | cut -d" " -f2)
	echo "Changing dummy cn using SF_robot account"
	ldapmodify -v -c -D "$sys_ldap_bind_dn" -x -w"$secret" > /dev/null 2>&1 <<-FIN
dn: uid=dummy,ou=People,$sys_ldap_base_dn
changetype: modify
replace: cn
cn: Dummy User Tested
FIN
}

# Main
case "$1" in
	configure)
		dn=$(grep sys_ldap_base_dn /etc/sourceforge/local.pl | cut -d\' -f2)
		setup_vars
		echo "Modifying /etc/ldap/slapd.conf"
		purge_slapd
		modify_slapd $dn
		echo "Modifying /etc/libnss-ldap.conf"
		modify_libnss_ldap $dn
		echo "Modifying /etc/nsswitch.conf"
		modify_nsswitch
		# Restarting ldap 
		invoke-rc.d slapd restart
		sleep 5		# Sometimes it takes a bit of time to get out of bed
		echo "Load ldap"
		load_ldap $dn "$secret"
		echo "Setup SF_robot account"
		setup_robot
		;;
	update)
		dn=$(grep sys_ldap_base_dn /etc/sourceforge/local.pl | cut -d\' -f2)
		setup_vars
		load_ldap $dn "$secret"
		# [ -f /etc/ldap.secret ] && secret=$(cat /etc/ldap.secret) && load_ldap $dn $secret &>/dev/null
		# [ -f /etc/ldap.secret ] || load_ldap $dn $secret
		;;
	purge)
		echo "Purging /etc/ldap/slapd.conf"
		purge_slapd
		echo "Purging /etc/nsswitch.conf"
		purge_nsswitch
		echo "Purging /etc/libnss-ldap.conf"
		purge_libnss_ldap
		$0 empty
		;;
	list)
		naming_context=$(ldapsearch -x -b '' -s base '(objectclass=*)' namingContexts | grep "namingContexts:" | cut -d" " -f2)
		# Display what is now in the database
		ldapsearch -x -b "$naming_context" '(objectclass=*)' 
		;;
	empty)
	        setup_vars
		naming_context=$(ldapsearch -x -b '' -s base '(objectclass=*)' namingContexts | grep "namingContexts:" | cut -d" " -f2)
		admin_regexp=$(echo $sys_ldap_base_dn | sed 's/, */, */g')
		admin_regexp="^cn=admin, *ou=People, *$regexp"
		get_our_entries () {
		    slapcat \
			| grep "^dn:" \
			| sed 's/^dn: *//' \
			| grep -v "^dc=" \
			| grep -v "^ou=" \
			| grep -v "$admin_regexp"
		    slapcat \
			| grep "^dn:" \
			| sed 's/^dn: *//' \
			| grep -v "^dc=" \
			| grep -v "^ou=People," \
			| grep -v "^ou=Roaming," \
			| grep -v "$admin_regexp"
		}
		get_our_entries || true
		get_our_entries | ldapdelete -D "cn=admin,ou=People,$sys_ldap_base_dn" -x -w"$secret" > /dev/null 2>&1 || true
		;;
	reset)
		setup_vars
		invoke-rc.d slapd stop
		rm -f /var/lib/ldap/*.dbb
		invoke-rc.d slapd start
		print_ldif_default $sys_ldap_base_dn $cryptedpasswd > /tmp/ldif$$ 
		slapadd -l /tmp/ldif$$
		rm -f /tmp/ldif$$
		;;
	test)	
		setup_robot
		;;
	*)
		echo "Usage: $0 {configure|update|purge|list|empty|reset}"
		exit 1
		;;
esac

# Ancient ldaptest follow

# All info found in /usr/share/doc/openldap-guide

# This is testing local ldap server
##echo "============ LDAP SEARCH ==================="
##ldapsearch -x -b '' -s base '(objectclass=*)' namingContexts
##echo "============ LDAP SEARCH ==================="

# Then you need LDIF file and run ldapadd
# To fill this you need to get your namingContexts
# This do this and should be used a the sourceforge base DN
##naming_context=$(ldapsearch -x -b '' -s base '(objectclass=*)' namingContexts | grep "namingContexts:" | cut -d" " -f2)
##echo "Naming Context is: ===>$naming_context<=="

# Un fichier ldif d'exemple
##echo "============ Example ldif file =============="
##tee /tmp/example.ldif <<-FIN
##dn: cn=Bob Smith,ou=People,$naming_context
##objectClass: person
##cn: Bob Smith
##sn: Smith
##FIN
##echo "============ Example ldif file =============="
##echo "============ Adding this to the database ===="
#/usr/sbin/slapadd -v -d2 -l /tmp/example.ldif
#ldapadd -U admin -D "cn=admin,ou=People,$naming_context" -W -f /tmp/example.ldif
#ldapadd -v -D "cn=admin,ou=People,$naming_context" -X u:admin  -f /tmp/example.ldif
##ldapadd -v -D "cn=admin,ou=People,$naming_context" -x -W -f /tmp/example.ldif
##echo "============ Checking the database =========="
##ldapsearch -x -b "$naming_context" '(objectclass=*)'

##Un ACL exemple pour la partie web
#access to dn=".*,ou=People,dc=dragoninc,dc=on,dc=ca" 
#attr=userpassword,ntpassword,lmpassword 
#        by dn="uid=root,ou=People,dc=dragoninc,dc=on,dc=ca" write 
#        by * none 
#
#access to dn=".*,ou=Group,dc=dragoninc,dc=on,dc=ca" attr=userpassword 
#        by dn="uid=root,ou=People,dc=dragoninc,dc=on,dc=ca" write 
#        by * none
#
# La mine d'or http://www.bayour.com/LDAPv3-HOWTO.html
# http://www.ameritech.net/users/mhwood/ldap-sec-setup.html
# A lire /usr/share/doc/openssl/README.Debian
# /usr/share/doc/libsasl7/sysadmin.html
# 
# To create the certificate that OpenLDAP will use, we issue the command openssl like this:
# openssl req -new -x509 -nodes -out server.pem -keyout server.pem -days 365
# openssl x509 -in server.pem -text
#
#
# Until this work:  ldapsearch -b "dc=g-tt,dc=rd,dc=francetelecom,dc=fr" '(objectclass=*)'
